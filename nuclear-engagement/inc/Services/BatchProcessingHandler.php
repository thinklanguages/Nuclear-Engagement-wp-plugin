<?php
/**
 * BatchProcessingHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\Remote\RemoteRequest;
use NuclearEngagement\Services\Remote\ApiResponseHandler;
use NuclearEngagement\Exceptions\ApiException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles individual batch processing for bulk generation
 */
class BatchProcessingHandler {

	/**
	 * Track if hooks are already registered
	 */
	private static $hooks_registered = false;

	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $api;

	/**
	 * @var ContentStorageService
	 */
	private ContentStorageService $storage;

	/**
	 * @var BulkGenerationBatchProcessor
	 */
	private BulkGenerationBatchProcessor $batchProcessor;

	/**
	 * Constructor
	 *
	 * @param RemoteApiService             $api
	 * @param ContentStorageService        $storage
	 * @param BulkGenerationBatchProcessor $batchProcessor
	 */
	public function __construct(
		RemoteApiService $api,
		ContentStorageService $storage,
		BulkGenerationBatchProcessor $batchProcessor
	) {
		$this->api            = $api;
		$this->storage        = $storage;
		$this->batchProcessor = $batchProcessor;
	}

	/**
	 * Initialize batch processing hooks
	 */
	public static function init(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		add_action( 'nuclen_process_batch', array( __CLASS__, 'process_batch_hook' ) );
		add_action( 'nuclen_poll_batch', array( __CLASS__, 'poll_batch_hook' ) );
		add_action( 'nuclen_cleanup_old_batches', array( __CLASS__, 'cleanup_old_batches_hook' ) );
		add_action( 'nuclen_check_batch_queue', array( __CLASS__, 'check_batch_queue_hook' ) );
		add_action( 'nuclen_check_task_completion', array( __CLASS__, 'check_task_completion_hook' ) );
		add_action( 'nuclen_recheck_batch_counts', array( __CLASS__, 'recheck_batch_counts_hook' ), 10, 2 );
		add_action( 'nuclen_check_stuck_tasks', array( __CLASS__, 'check_stuck_tasks_hook' ) );
		
		// Add custom cron schedules
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

		// Schedule cleanup if not already scheduled
		if ( ! wp_next_scheduled( 'nuclen_cleanup_old_batches' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_old_batches' );
		}
		
		// Schedule stuck task check every 5 minutes
		if ( ! wp_next_scheduled( 'nuclen_check_stuck_tasks' ) ) {
			wp_schedule_event( time() + 300, 'nuclen_five_minutes', 'nuclen_check_stuck_tasks' );
		}

		self::$hooks_registered = true;
	}
	
	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules
	 * @return array Modified schedules
	 */
	public static function add_cron_schedules( array $schedules ): array {
		$schedules['nuclen_five_minutes'] = array(
			'interval' => 300, // 5 minutes
			'display'  => __( 'Every 5 Minutes', 'nuclear-engagement' ),
		);
		return $schedules;
	}

	/**
	 * Hook handler for processing a batch
	 *
	 * @param string $batch_id Batch ID to process
	 */
	public static function process_batch_hook( string $batch_id ): void {

		$settings = SettingsRepository::get_instance();

		// Create circuit breaker instance
		$circuit_breaker = new CircuitBreaker( 'remote_api', 5, 300, 2 );

		$api            = new RemoteApiService(
			$settings,
			new RemoteRequest( $settings ),
			new ApiResponseHandler(),
			$circuit_breaker
		);
		$storage        = new ContentStorageService( $settings );
		$batchProcessor = new BulkGenerationBatchProcessor( $settings );

		$handler = new self( $api, $storage, $batchProcessor );
		$handler->process_batch( $batch_id );
	}

	/**
	 * Process a single batch
	 *
	 * @param string $batch_id Batch ID to process
	 */
	public function process_batch( string $batch_id ): void {

		// Acquire lock to prevent concurrent processing using atomic operation
		$lock_key      = 'nuclen_batch_lock_' . $batch_id;
		$lock_option   = 'nuclen_option_lock_batch_' . $batch_id;
		$lock_value    = wp_generate_password( 12, false );
		$lock_acquired = false;

		// Try to acquire lock atomically using add_option
		if ( add_option(
			$lock_option,
			array(
				'value' => $lock_value,
				'time'  => time(),
			),
			'',
			'no'
		) ) {
			$lock_acquired = true;
		} else {
			// Check if existing lock is expired
			$existing = get_option( $lock_option );
			if ( is_array( $existing ) && isset( $existing['time'] ) &&
				( time() - $existing['time'] ) > 300 ) { // 5 minute timeout
				// Force update expired lock
				update_option(
					$lock_option,
					array(
						'value' => $lock_value,
						'time'  => time(),
					)
				);
				$lock_acquired = true;
			} else {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BatchProcessingHandler::process_batch] WARNING: Batch %s is already being processed - skipping', $batch_id ),
					'warning'
				);
				return;
			}
		}

		try {
			// Get batch data AFTER acquiring lock to prevent race conditions
			$batch_data = TaskTransientManager::get_batch_transient( $batch_id );
			if ( ! is_array( $batch_data ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BatchProcessingHandler::process_batch] ERROR: Batch %s not found - transient missing', $batch_id ),
					'error'
				);
				return;
			}


			if ( $batch_data['status'] !== 'pending' ) {
				return;
			}

			// Check parent task status before processing batch
			$parent_data = null;
			if ( isset( $batch_data['parent_id'] ) && ! empty( $batch_data['parent_id'] ) ) {
				$parent_data = TaskTransientManager::get_task_transient( $batch_data['parent_id'] );
				
				// Skip if parent task has been cancelled
				if ( $parent_data && isset( $parent_data['status'] ) && $parent_data['status'] === 'cancelled' ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[BatchProcessingHandler::process_batch] Skipping batch %s - parent task %s is cancelled', $batch_id, $batch_data['parent_id'] ),
						'info'
					);
					
					// Update batch status to cancelled to prevent rescheduling
					$this->batchProcessor->update_batch_status( $batch_id, 'cancelled' );
					
					return;
				}
			}

			// Set extended timeout for batch processing
			$original_timeout = BulkGenerationTimeoutHandler::set_extended_timeout();

			try {
				// Update status to processing
				$this->batchProcessor->update_batch_status( $batch_id, 'processing' );
				
				// Update parent task status from scheduled to running if this is the first batch
				if ( $parent_data && isset( $parent_data['status'] ) && $parent_data['status'] === 'scheduled' ) {
						$parent_data['status'] = 'processing';
						$parent_data['started_at'] = time();
						TaskTransientManager::set_task_transient( $batch_data['parent_id'], $parent_data, DAY_IN_SECONDS );
						
						// Update task index
						$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
						if ( $container->has( 'task_index_service' ) ) {
							$index_service = $container->get( 'task_index_service' );
							$index_service->update_task_status( $batch_data['parent_id'], 'processing', array( 'started_at' => time() ) );
						}
						
						// Clear tasks cache to reflect updates immediately
						if ( class_exists( '\NuclearEngagement\Admin\Tasks' ) ) {
							\NuclearEngagement\Admin\Tasks::clear_tasks_cache();
						}
						
					}

				// Record task start for timeout tracking
				do_action( 'nuclen_task_started', $batch_id, 3600 ); // 1 hour timeout

				// Log the posts being sent
				$post_ids = array_map(
					function ( $post ) {
						// Check both 'post_id' and 'id' for compatibility
						if ( isset( $post['post_id'] ) ) {
							return $post['post_id'];
						} elseif ( isset( $post['id'] ) ) {
							return $post['id'];
						}
						return 'unknown';
					},
					$batch_data['posts']
				);

				// Send batch to API
				$result = $this->api->send_posts_to_generate(
					array(
						'posts'         => $batch_data['posts'],
						'workflow'      => $batch_data['workflow'],
						'generation_id' => $batch_id,
					)
				);


				// Store generation ID for polling if needed
				if ( isset( $result['generation_id'] ) ) {
					// Re-fetch the batch data to ensure we have the latest
					$batch_data = TaskTransientManager::get_batch_transient( $batch_id );
					if ( ! is_array( $batch_data ) ) {
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( '[BatchProcessingHandler::process_batch] ERROR: Batch data lost for %s during processing', $batch_id ),
							'error'
						);
						return;
					}

					$batch_data['api_generation_id'] = $result['generation_id'];
					TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );


					// Schedule polling for this batch
					if ( ! wp_next_scheduled( 'nuclen_poll_batch', array( $batch_id ) ) ) {
							wp_schedule_single_event(
								time() + 30,
								'nuclen_poll_batch',
								array( $batch_id )
							);
							
							// Force immediate cron spawn to process the scheduled event
							if ( ! defined( 'DOING_CRON' ) ) {
								spawn_cron();
							}
					}
				} else {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[BatchProcessingHandler::process_batch] WARNING: No generation_id in API response for batch %s - immediate results expected', $batch_id ),
						'warning'
					);
				}

				// Store immediate results if any (don't process yet, just accumulate)
				if ( ! empty( $result['results'] ) && is_array( $result['results'] ) ) {
					// Store results in separate transients to avoid memory issues
					$results_key      = 'nuclen_batch_results_' . $batch_id;
					$existing_results = get_transient( $results_key ) ?: array();

					// Merge new results with existing ones
					foreach ( $result['results'] as $post_id => $data ) {
						$existing_results[ $post_id ] = $data;
					}

					// Check result size before storing
					$serialized_size = strlen( serialize( $existing_results ) );
					if ( $serialized_size > 5 * MB_IN_BYTES ) {
						// Keep only the latest results
						$existing_results = array_slice( $existing_results, -50, null, true );
					}

					// Store results separately from batch data
					set_transient( $results_key, $existing_results, DAY_IN_SECONDS );

					// Just track the count in batch data to avoid memory issues
					$batch_data['result_count'] = count( $existing_results );
					TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );

				}
			} catch ( \Throwable $e ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BatchProcessingHandler::process_batch] ERROR: Exception processing batch %s - %s',
						$batch_id,
						$e->getMessage()
					),
					'error'
				);
				\NuclearEngagement\Services\LoggingService::log_exception( $e );

				// Determine if error is retryable
				$is_retryable = $this->is_retryable_error( $e );

				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BatchProcessingHandler::process_batch] Error for batch %s is %sretryable',
						$batch_id,
						$is_retryable ? '' : 'NOT '
					)
				);

				if ( $is_retryable ) {
					// Use the batch processor's retry logic with exponential backoff
					$retry_count = isset( $batch_data['retry_count'] ) ? $batch_data['retry_count'] : 0;
					$this->batchProcessor->handle_failed_batch( $batch_id, $e->getMessage(), $batch_data );

					// Calculate next retry delay with exponential backoff
					$base_delay = 300; // 5 minutes base
					$max_delay  = 3600; // 1 hour max
					$next_delay = min( $base_delay * pow( 2, $retry_count ), $max_delay );

					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'[BatchProcessingHandler::process_batch] Batch %s queued for retry #%d in %d seconds',
							$batch_id,
							$retry_count + 1,
							$next_delay
						)
					);
				} else {
					// Mark as permanently failed
					$this->batchProcessor->update_batch_status(
						$batch_id,
						'failed',
						array(
							'error'         => $e->getMessage(),
							'non_retryable' => true,
						)
					);
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[BatchProcessingHandler::process_batch] Batch %s marked as permanently failed', $batch_id ),
						'error'
					);
				}

				// Add admin notice for batch failure only if parent is not cancelled
				if ( isset( $batch_data['parent_id'] ) ) {
					$parent_data = TaskTransientManager::get_task_transient( $batch_data['parent_id'] );
					if ( $parent_data && isset( $parent_data['status'] ) && $parent_data['status'] !== 'cancelled' ) {
						$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
						if ( $container->has( 'admin_notice_service' ) ) {
							$notice_service = $container->get( 'admin_notice_service' );
							$notice_service->add_generation_failure_notice(
								$batch_data['parent_id'],
								$e->getMessage()
							);
						}
					}
				}
			} finally {
				BulkGenerationTimeoutHandler::restore_timeout( $original_timeout );
			}
		} finally {
			// Always release the lock if we acquired it
			if ( $lock_acquired ) {
				delete_option( $lock_option );
			}
		}
	}

	/**
	 * Process and store batch results
	 *
	 * @param string $batch_id Batch ID
	 * @param array  $results Results from API
	 * @param string $workflow_type Workflow type
	 */
	private function process_batch_results( string $batch_id, array $results, string $workflow_type ): void {
		try {
			// Process results in chunks to avoid memory issues
			$chunk_size    = 10;
			$total_success = 0;
			$total_fail    = 0;
			$chunks        = array_chunk( $results, $chunk_size, true );

			foreach ( $chunks as $chunk_index => $chunk ) {
				try {
					// Store results chunk
					$statuses = $this->storage->storeResults( $chunk, $workflow_type );

					$success_count = count( array_filter( $statuses, fn( $s ) => $s === true ) );
					$fail_count    = count( $statuses ) - $success_count;

					$total_success += $success_count;
					$total_fail    += $fail_count;


					// Free memory after each chunk
					unset( $chunk );

					// Small delay between chunks to avoid overwhelming the system
					if ( $chunk_index < count( $chunks ) - 1 ) {
						usleep( 100000 ); // 100ms
					}
				} catch ( \Throwable $e ) {
					\NuclearEngagement\Services\LoggingService::log_exception( $e );
					$total_fail += count( $chunk );
				}
			}

			// Update batch status with counts in a single atomic operation
			// This ensures counts are ALWAYS available when status is 'completed'
			$this->batchProcessor->update_batch_status(
				$batch_id,
				'completed',
				array(
					'success_count'   => $total_success,
					'fail_count'      => $total_fail,
					// Don't store full results to avoid memory issues
					'processed_count' => count( $results ),
				)
			);


			// Next batch scheduling is handled by BulkGenerationBatchProcessor::update_batch_status
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			$this->batchProcessor->update_batch_status(
				$batch_id,
				'failed',
				array(
					'error' => 'Failed to store results: ' . $e->getMessage(),
				)
			);
		}
	}


	/**
	 * Hook handler for polling a batch
	 *
	 * @param string $batch_id Batch ID to poll
	 */
	public static function poll_batch_hook( string $batch_id ): void {
		self::poll_batch( $batch_id );
	}

	/**
	 * Poll for batch results
	 *
	 * @param string $batch_id Batch ID
	 */
	public static function poll_batch( string $batch_id ): void {

		$batch_data = TaskTransientManager::get_batch_transient( $batch_id );
		if ( ! is_array( $batch_data ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchProcessingHandler::poll_batch] ERROR: No batch data found for %s', $batch_id ),
				'error'
			);
			return;
		}


		if ( ! isset( $batch_data['api_generation_id'] ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchProcessingHandler::poll_batch] ERROR: No api_generation_id in batch data for %s', $batch_id ),
				'error'
			);
			return;
		}

		$settings = SettingsRepository::get_instance();

		// Create circuit breaker instance
		$circuit_breaker = new CircuitBreaker( 'remote_api', 5, 300, 2 );

		$api = new RemoteApiService(
			$settings,
			new Remote\RemoteRequest( $settings ),
			new Remote\ApiResponseHandler(),
			$circuit_breaker
		);

		try {

			$updates = $api->fetch_updates( $batch_data['api_generation_id'] );


			// Store partial results without marking batch as complete
			if ( ! empty( $updates['results'] ) && is_array( $updates['results'] ) ) {
				// Store results in separate transient to avoid memory issues
				$results_key      = 'nuclen_batch_results_' . $batch_id;
				$existing_results = get_transient( $results_key ) ?: array();

				// Merge new results with existing ones
				foreach ( $updates['results'] as $post_id => $result ) {
					$existing_results[ $post_id ] = $result;
				}

				// Check result size before storing
				$serialized_size = strlen( serialize( $existing_results ) );
				if ( $serialized_size > 5 * MB_IN_BYTES ) {
					// Keep only the latest results
					$existing_results = array_slice( $existing_results, -50, null, true );
				}

				// Store results separately from batch data
				set_transient( $results_key, $existing_results, DAY_IN_SECONDS );

				// Update batch data with result count only
				$batch_data['result_count'] = count( $existing_results );
				TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );

			}

			// Check if generation is complete
			$is_complete = false;
			if ( isset( $updates['processed'] ) && isset( $updates['total'] ) ) {
				$is_complete = $updates['processed'] >= $updates['total'];
			}

			if ( $is_complete ) {
				// Load results from separate transient
				$results_key   = 'nuclen_batch_results_' . $batch_id;
				$final_results = get_transient( $results_key );

				// Create services once for use in both branches
				$storage        = new ContentStorageService( $settings );
				$batchProcessor = new BulkGenerationBatchProcessor( $settings );

				if ( ! empty( $final_results ) ) {
					// Process all accumulated results
					$handler = new self( $api, $storage, $batchProcessor );

					// Ensure workflow type exists
					$workflow_type = isset( $batch_data['workflow']['type'] ) ? $batch_data['workflow']['type'] : 'default';
					$handler->process_batch_results( $batch_id, $final_results, $workflow_type );

					// Clean up the results transient after processing
					delete_transient( $results_key );

					// Mark task as completed for timeout tracking
					do_action( 'nuclen_task_completed', $batch_id );
					
					// Force parent task completion check to prevent stuck tasks
					if ( isset( $batch_data['parent_id'] ) && ! empty( $batch_data['parent_id'] ) ) {
						$batchProcessor->force_task_completion_check( $batch_data['parent_id'] );
					}
				} else {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[BatchProcessingHandler::poll_batch] ERROR: Batch %s marked as complete but no results found', $batch_id ),
						'error'
					);

					// Mark batch as failed if no results
					$batchProcessor->update_batch_status(
						$batch_id,
						'failed',
						array( 'error' => 'Generation completed but no results received' )
					);
				}
			} else {
				// Still processing, schedule another poll
				wp_schedule_single_event( time() + 30, 'nuclen_poll_batch', array( $batch_id ) );
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BatchProcessingHandler::poll_batch] Batch %s still processing, scheduled next poll in 30 seconds', $batch_id )
				);
				
				// Force immediate cron spawn to process the scheduled event
				if ( ! defined( 'DOING_CRON' ) ) {
					spawn_cron();
				}
			}
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BatchProcessingHandler::poll_batch] ERROR: Exception during poll for batch %s - %s',
					$batch_id,
					$e->getMessage()
				),
				'error'
			);
			\NuclearEngagement\Services\LoggingService::log_exception( $e );

			// Check if this is a retryable error during polling
			if ( strpos( $e->getMessage(), 'timeout' ) !== false ||
				strpos( $e->getMessage(), 'network' ) !== false ) {
				// Reschedule the poll
				wp_schedule_single_event( time() + 60, 'nuclen_poll_batch', array( $batch_id ) );
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BatchProcessingHandler::poll_batch] Poll failed with retryable error for batch %s, rescheduling in 60 seconds', $batch_id )
				);
				
				// Force immediate cron spawn to process the scheduled event
				if ( ! defined( 'DOING_CRON' ) ) {
					spawn_cron();
				}
			}
		}
	}

	/**
	 * Cleanup old batch data
	 */
	public static function cleanup_old_batches_hook(): void {
		$settings  = SettingsRepository::get_instance();
		$processor = new BulkGenerationBatchProcessor( $settings );

		// Clean old batches
		$cleaned_old = $processor->cleanup_old_batches( 48 ); // Clean batches older than 48 hours

		// Clean orphaned batches
		$cleaned_orphaned = $processor->cleanup_orphaned_batches();

		$total_cleaned = $cleaned_old + $cleaned_orphaned;

		if ( $total_cleaned > 0 ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Cleaned up %d batch records (%d old, %d orphaned)',
					$total_cleaned,
					$cleaned_old,
					$cleaned_orphaned
				)
			);
		}
	}

	/**
	 * Check batch queue and schedule next batches if capacity available
	 *
	 * @param string $parent_id Parent generation ID
	 */
	public static function check_batch_queue_hook( string $parent_id ): void {
		$settings  = SettingsRepository::get_instance();
		$processor = new BulkGenerationBatchProcessor( $settings );
		$processor->schedule_next_batch( $parent_id );
	}

	/**
	 * Recheck batch counts hook handler
	 *
	 * @param string $batch_id Batch ID
	 * @param string $parent_id Parent task ID
	 */
	public static function recheck_batch_counts_hook( string $batch_id, string $parent_id ): void {
		$settings  = SettingsRepository::get_instance();
		$processor = new BulkGenerationBatchProcessor( $settings );
		$processor->recheck_batch_counts( $batch_id, $parent_id );
	}

	/**
	 * Check if error is retryable
	 *
	 * @param \Throwable $e The exception
	 * @return bool Whether the error is retryable
	 */
	private function is_retryable_error( \Throwable $e ): bool {
		$message     = strtolower( $e->getMessage() );
		$error_class = get_class( $e );


		// Network/timeout errors are retryable
		if ( strpos( $message, 'timeout' ) !== false ||
			strpos( $message, 'timed out' ) !== false ||
			strpos( $message, 'connection' ) !== false ||
			strpos( $message, 'network' ) !== false ) {
			return true;
		}

		// API errors
		if ( $e instanceof ApiException ) {
			$is_retryable = $e->is_retryable();
			return $is_retryable;
		}

		// Database deadlocks are retryable
		if ( strpos( $message, 'deadlock' ) !== false ||
			strpos( $message, 'lock wait timeout' ) !== false ) {
			return true;
		}

		// Server errors are retryable
		if ( strpos( $message, '500' ) !== false ||
			strpos( $message, '502' ) !== false ||
			strpos( $message, '503' ) !== false ||
			strpos( $message, '504' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Hook handler for checking task completion
	 *
	 * @param string $task_id Task ID to check
	 */
	public static function check_task_completion_hook( string $task_id ): void {

		$settings = SettingsRepository::get_instance();
		$batchProcessor = new BulkGenerationBatchProcessor( $settings );
		
		// Force check the task completion
		$completed = $batchProcessor->force_task_completion_check( $task_id );
		
		if ( $completed ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchProcessingHandler::check_task_completion_hook] Task %s was successfully marked as completed', $task_id )
			);
		} else {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchProcessingHandler::check_task_completion_hook] Task %s is not yet ready for completion', $task_id )
			);
		}
	}
	
	/**
	 * Hook handler for checking stuck tasks
	 */
	public static function check_stuck_tasks_hook(): void {
		$settings = SettingsRepository::get_instance();
		$batchProcessor = new BulkGenerationBatchProcessor( $settings );
		$batchProcessor->check_and_recover_stuck_tasks();
	}
}

// Register initialization to happen after plugins are loaded
add_action( 'plugins_loaded', array( BatchProcessingHandler::class, 'init' ), 5 );
add_action( 'nuclen_poll_batch', array( BatchProcessingHandler::class, 'poll_batch' ) );
