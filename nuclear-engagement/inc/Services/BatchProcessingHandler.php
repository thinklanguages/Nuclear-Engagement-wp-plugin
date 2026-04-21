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

		// Add custom cron schedules
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

		// Schedule cleanup if not already scheduled
		if ( ! wp_next_scheduled( 'nuclen_cleanup_old_batches' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_old_batches' );
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
		// Ensure hooks are initialized if this is called directly
		self::init();
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BatchProcessingHandler::process_batch_hook] Called with batch ID: %s',
				$batch_id
			)
		);

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
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BatchProcessingHandler::process_batch] Acquiring expired lock for batch %s (locked %d seconds ago)',
						$batch_id,
						time() - $existing['time']
					),
					'warning'
				);
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
				
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BatchProcessingHandler::process_batch] BATCH STARTED - ID: %s | Parent: %s | Posts: %d | Index: %d/%d',
						$batch_id,
						$batch_data['parent_id'] ?? 'unknown',
						count( $batch_data['posts'] ?? array() ),
						$batch_data['batch_index'] ?? 0,
						$batch_data['total_batches'] ?? 0
					)
				);

				// Update parent task status from scheduled to processing if it hasn't been updated yet
				// (This is a fallback in case the status wasn't updated during scheduling)
				if ( $parent_data && isset( $parent_data['status'] ) && $parent_data['status'] === 'scheduled' ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'[BatchProcessingHandler::process_batch] Parent task %s still in scheduled state - updating to processing (fallback)',
							$batch_data['parent_id']
						)
					);

					$parent_data['status']     = 'processing';
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
				} else {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'[BatchProcessingHandler::process_batch] Parent task %s already in %s state - no status update needed',
							$batch_data['parent_id'],
							$parent_data['status'] ?? 'unknown'
						)
					);
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

				// Set maximum execution time for this request
				$original_max_time = ini_get( 'max_execution_time' );
				if ( $original_max_time !== false && intval( $original_max_time ) < 120 ) {
					@set_time_limit( 120 ); // 2 minutes should be enough for API call + processing
				}

				// Log start of API call
				$api_start_time = microtime( true );
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BatchProcessingHandler::process_batch] Starting API call for batch %s with %d posts',
						$batch_id,
						count( $batch_data['posts'] )
					)
				);

				// Send batch to API with timeout monitoring
				try {
					$result = $this->api->send_posts_to_generate(
						array(
							'posts'         => $batch_data['posts'],
							'workflow'      => $batch_data['workflow'],
							'generation_id' => $batch_id,
						)
					);

					// Log successful API call
					$api_duration = microtime( true ) - $api_start_time;
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'[BatchProcessingHandler::process_batch] API call completed for batch %s in %.2f seconds',
							$batch_id,
							$api_duration
						)
					);
				} catch ( \Throwable $api_error ) {
					// Log API error with duration
					$api_duration = microtime( true ) - $api_start_time;
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'[BatchProcessingHandler::process_batch] API call failed for batch %s after %.2f seconds: %s',
							$batch_id,
							$api_duration,
							$api_error->getMessage()
						),
						'error'
					);

					// Re-throw to be handled by outer catch block
					throw $api_error;
				} finally {
					// Restore original max execution time
					if ( $original_max_time !== false ) {
						@set_time_limit( intval( $original_max_time ) );
					}
				}

				// Validate API response
				$has_generation_id = isset( $result['generation_id'] );
				$has_results = ! empty( $result['results'] ) && is_array( $result['results'] );
				
				// Store generation ID for polling if needed
				if ( $has_generation_id ) {
					// Re-fetch the batch data to ensure we have the latest
					$batch_data = TaskTransientManager::get_batch_transient( $batch_id );
					if ( ! is_array( $batch_data ) ) {
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( '[BatchProcessingHandler::process_batch] ERROR: Batch data lost for %s during processing', $batch_id ),
							'error'
						);
						
						// Mark batch as failed since we can't track it
						$this->batchProcessor->update_batch_status(
							$batch_id,
							'failed',
							array(
								'error' => 'Batch data lost during processing',
								'fail_count' => count( $batch_data['posts'] ?? array() ),
								'success_count' => 0
							)
						);
						return;
					}

					$batch_data['api_generation_id'] = $result['generation_id'];
					TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );

					// Try to schedule polling for this batch
					$polling_scheduled = false;
					try {
						if ( ! wp_next_scheduled( 'nuclen_poll_batch', array( $batch_id ) ) ) {
							$polling_scheduled = wp_schedule_single_event(
								time() + 30,
								'nuclen_poll_batch',
								array( $batch_id )
							);

							if ( $polling_scheduled ) {
								\NuclearEngagement\Services\LoggingService::log(
									sprintf( '[BatchProcessingHandler::process_batch] Polling scheduled for batch %s in 30 seconds', $batch_id )
								);
								
								// Force immediate cron spawn to process the scheduled event
								if ( ! defined( 'DOING_CRON' ) ) {
									spawn_cron();
								}
							} else {
								\NuclearEngagement\Services\LoggingService::log(
									sprintf( '[BatchProcessingHandler::process_batch] WARNING: Failed to schedule polling for batch %s', $batch_id ),
									'warning'
								);
							}
						}
					} catch ( \Throwable $poll_error ) {
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( '[BatchProcessingHandler::process_batch] ERROR: Exception scheduling polling for batch %s: %s', $batch_id, $poll_error->getMessage() ),
							'error'
						);
					}
					
					// If polling failed to schedule, mark batch for immediate retry
					if ( ! $polling_scheduled && ! $has_results ) {
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( '[BatchProcessingHandler::process_batch] ERROR: No polling scheduled and no immediate results for batch %s', $batch_id ),
							'error'
						);
						
						// Schedule immediate retry through the failed batch handler
						$this->batchProcessor->handle_failed_batch( $batch_id, 'Failed to schedule polling for async generation', $batch_data );
						return;
					}
				} else if ( ! $has_results ) {
					// No generation ID and no results - this is an error
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[BatchProcessingHandler::process_batch] ERROR: No generation_id and no results in API response for batch %s', $batch_id ),
						'error'
					);
					
					// Mark batch as failed
					$this->batchProcessor->update_batch_status(
						$batch_id,
						'failed',
						array(
							'error' => 'Invalid API response: no generation_id or results',
							'fail_count' => count( $batch_data['posts'] ?? array() ),
							'success_count' => 0
						)
					);
					return;
				}

				// Store immediate results if any
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
					
					// If we have all results and no generation_id (synchronous response), process immediately
					if ( ! $has_generation_id && count( $existing_results ) >= count( $batch_data['posts'] ) ) {
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( '[BatchProcessingHandler::process_batch] Processing immediate results for batch %s (%d results)', $batch_id, count( $existing_results ) )
						);
						
						// Ensure workflow type exists
						$workflow_type = isset( $batch_data['workflow']['type'] ) ? $batch_data['workflow']['type'] : 'default';
						$this->process_batch_results( $batch_id, $existing_results, $workflow_type );

						// Mark task as completed for timeout tracking
						do_action( 'nuclen_task_completed', $batch_id );
						
						return; // Exit early since we're done
					}
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
							'fail_count'    => count( $batch_data['posts'] ?? array() ),
							'success_count' => 0,
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
			// Keep the raw post results available for the UI even after backend storage completes.
			set_transient( 'nuclen_batch_results_' . $batch_id, $results, DAY_IN_SECONDS );

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
			
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BatchProcessingHandler::process_batch_results] BATCH COMPLETED - ID: %s | Total: %d | Success: %d | Failed: %d',
					$batch_id,
					count( $results ),
					$total_success,
					$total_fail
				)
			);

			// Next batch scheduling is handled by BulkGenerationBatchProcessor::update_batch_status
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			$this->batchProcessor->update_batch_status(
				$batch_id,
				'failed',
				array(
					'error'         => 'Failed to store results: ' . $e->getMessage(),
					'fail_count'    => count( $results ),
					'success_count' => 0,
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
	 * Hard cap on consecutive not_found responses before failing the batch.
	 */
	private const MAX_NOT_FOUND_STREAK = 20;

	/**
	 * Hard cap on total poll attempts before failing the batch.
	 */
	private const MAX_POLL_ATTEMPTS = 240;

	/**
	 * Optional test-only collaborators for scheduling / hooks / transients.
	 *
	 * Production code leaves this null and the normal WordPress functions are
	 * called directly. Tests may assign an object with method names matching
	 * schedule_single_event, clear_scheduled_hook, get_transient, set_transient,
	 * delete_transient, do_action, and spawn_cron so the poll loop can be
	 * asserted without relying on (potentially polluted) global function stubs.
	 *
	 * @var object|null
	 */
	public static $test_hooks = null;

	/**
	 * Poll for batch results (static entry point that wires dependencies).
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

		// Create circuit breaker instance.
		$circuit_breaker = new CircuitBreaker( 'remote_api', 5, 300, 2 );

		$api = new RemoteApiService(
			$settings,
			new Remote\RemoteRequest( $settings ),
			new Remote\ApiResponseHandler(),
			$circuit_breaker
		);

		$storage        = new ContentStorageService( $settings );
		$batchProcessor = new BulkGenerationBatchProcessor( $settings );

		$handler = new self( $api, $storage, $batchProcessor );
		$handler->handle_poll( $batch_id, $batch_data );
	}

	/**
	 * Core polling routine.
	 *
	 * Branches on the server-provided status/completed/not_found contract. All
	 * terminal branches (complete/cancelled/failed) clear any queued poll
	 * events and the results transient. Non-terminal branches write poll state
	 * back to the batch transient and schedule the next poll with exponential
	 * backoff, subject to bounded caps on attempts and consecutive not_found
	 * responses.
	 *
	 * @param string $batch_id   Batch ID.
	 * @param array  $batch_data Pre-loaded batch data.
	 */
	public function handle_poll( string $batch_id, array $batch_data ): void {
		try {
			$updates = $this->api->fetch_updates( $batch_data['api_generation_id'] );
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BatchProcessingHandler::handle_poll] ERROR: Exception during poll for batch %s - %s',
					$batch_id,
					$e->getMessage()
				),
				'error'
			);
			if ( method_exists( \NuclearEngagement\Services\LoggingService::class, 'log_exception' ) ) {
				\NuclearEngagement\Services\LoggingService::log_exception( $e );
			}

			// Treat exceptions as soft failures - do not bump not_found_streak,
			// just reschedule subject to poll_attempts cap.
			$this->handle_soft_failure( $batch_id, $batch_data, 'exception: ' . $e->getMessage() );
			return;
		}

		// Soft-fail shape: null, false, WP_Error, empty, or non-array. Do not
		// touch not_found_streak; treat as transient network error.
		if ( ! is_array( $updates ) || empty( $updates ) ) {
			$this->handle_soft_failure( $batch_id, $batch_data, 'empty or invalid fetch_updates response' );
			return;
		}

		// Accumulate partial results into the separate results transient so the
		// UI keeps seeing progress even when the batch is not yet complete.
		if ( ! empty( $updates['results'] ) && is_array( $updates['results'] ) ) {
			$results_key      = 'nuclen_batch_results_' . $batch_id;
			$existing_results = get_transient( $results_key );
			if ( ! is_array( $existing_results ) ) {
				$existing_results = array();
			}

			foreach ( $updates['results'] as $post_id => $result ) {
				$existing_results[ $post_id ] = $result;
			}

			// Guard against runaway size.
			$serialized_size = strlen( serialize( $existing_results ) );
			if ( $serialized_size > 5 * MB_IN_BYTES ) {
				$existing_results = array_slice( $existing_results, -50, null, true );
			}

			set_transient( $results_key, $existing_results, DAY_IN_SECONDS );

			$batch_data['result_count'] = count( $existing_results );
		}

		if ( isset( $updates['processed'] ) ) {
			$batch_data['processed'] = (int) $updates['processed'];
		}
		if ( isset( $updates['total'] ) ) {
			$batch_data['total'] = (int) $updates['total'];
		}

		$status       = isset( $updates['status'] ) ? (string) $updates['status'] : 'unknown';
		$is_complete  = ! empty( $updates['completed'] );
		$is_cancelled = $status === 'cancelled';
		$is_not_found = $status === 'not_found';

		// Update poll counters.
		$poll_attempts    = isset( $batch_data['poll_attempts'] ) ? (int) $batch_data['poll_attempts'] : 0;
		$not_found_streak = isset( $batch_data['not_found_streak'] ) ? (int) $batch_data['not_found_streak'] : 0;

		$poll_attempts++;
		if ( $is_not_found ) {
			$not_found_streak++;
		} else {
			$not_found_streak = 0;
		}

		$batch_data['poll_attempts']    = $poll_attempts;
		$batch_data['not_found_streak'] = $not_found_streak;
		$batch_data['last_poll_at']     = time();
		$batch_data['last_status']      = $status;

		// Persist counters before branching so state is never lost.
		TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );

		$results_count = isset( $batch_data['result_count'] ) ? (int) $batch_data['result_count'] : 0;
		$processed     = isset( $batch_data['processed'] ) ? (int) $batch_data['processed'] : 0;
		$total         = isset( $batch_data['total'] ) ? (int) $batch_data['total'] : 0;

		$this->log_poll_branch(
			$batch_id,
			$status,
			$is_complete,
			$poll_attempts,
			$not_found_streak,
			$processed,
			$total,
			$results_count
		);

		// Terminal branches.
		if ( $is_cancelled ) {
			$this->handle_cancelled( $batch_id );
			return;
		}

		if ( $is_complete ) {
			$this->handle_completed( $batch_id, $batch_data );
			return;
		}

		// Bounded-polling caps.
		if ( $not_found_streak >= self::MAX_NOT_FOUND_STREAK ) {
			$this->handle_cap_exceeded(
				$batch_id,
				$batch_data,
				sprintf( 'Server lost track of generation (%d consecutive not_found)', self::MAX_NOT_FOUND_STREAK )
			);
			return;
		}

		if ( $poll_attempts >= self::MAX_POLL_ATTEMPTS ) {
			$this->handle_cap_exceeded(
				$batch_id,
				$batch_data,
				sprintf( 'Polling exceeded %d attempts', self::MAX_POLL_ATTEMPTS )
			);
			return;
		}

		// Still polling - schedule next poll with exponential backoff.
		$this->schedule_next_poll( $batch_id, $poll_attempts );
	}

	/**
	 * Handle a cancelled batch: mark cancelled, clean up, fire completion hook.
	 *
	 * @param string $batch_id Batch ID.
	 */
	private function handle_cancelled( string $batch_id ): void {
		$this->batchProcessor->update_batch_status( $batch_id, 'cancelled' );
		$this->cleanup_terminal( $batch_id );
		$this->fire_action( 'nuclen_task_completed', $batch_id );
	}

	/**
	 * Handle a completed batch. Existing results sanity check is preserved:
	 * if completed is true but results are empty, the batch is marked failed.
	 *
	 * @param string $batch_id   Batch ID.
	 * @param array  $batch_data Current batch data.
	 */
	private function handle_completed( string $batch_id, array $batch_data ): void {
		$results_key    = 'nuclen_batch_results_' . $batch_id;
		$final_results  = $this->fetch_transient( $results_key );
		$expected_posts = count( $batch_data['posts'] ?? array() );
		$actual_results = is_array( $final_results ) ? count( $final_results ) : 0;

		if ( ! empty( $final_results ) && $actual_results > 0 ) {
			if ( $actual_results < $expected_posts ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BatchProcessingHandler::handle_poll] WARNING: Batch %s has fewer results than expected (%d/%d posts)',
						$batch_id,
						$actual_results,
						$expected_posts
					),
					'warning'
				);
			}

			$workflow_type = isset( $batch_data['workflow']['type'] ) ? $batch_data['workflow']['type'] : 'default';
			$this->process_batch_results( $batch_id, $final_results, $workflow_type );

			$this->cleanup_terminal( $batch_id );
			$this->fire_action( 'nuclen_task_completed', $batch_id );
			return;
		}

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BatchProcessingHandler::handle_poll] ERROR: Batch %s marked as complete but no results found (expected %d posts)',
				$batch_id,
				$expected_posts
			),
			'error'
		);

		$this->batchProcessor->update_batch_status(
			$batch_id,
			'failed',
			array(
				'error'         => 'Generation completed but no results received',
				'fail_count'    => $expected_posts,
				'success_count' => 0,
			)
		);
		$this->cleanup_terminal( $batch_id );
		$this->fire_action( 'nuclen_task_completed', $batch_id );
	}

	/**
	 * Handle hitting a bounded-polling cap (not_found streak or poll_attempts).
	 *
	 * @param string $batch_id   Batch ID.
	 * @param array  $batch_data Current batch data.
	 * @param string $error      Error message to record on the batch.
	 */
	private function handle_cap_exceeded( string $batch_id, array $batch_data, string $error ): void {
		$expected_posts = count( $batch_data['posts'] ?? array() );

		$this->batchProcessor->update_batch_status(
			$batch_id,
			'failed',
			array(
				'error'         => $error,
				'fail_count'    => $expected_posts,
				'success_count' => 0,
			)
		);
		$this->cleanup_terminal( $batch_id );
		$this->fire_action( 'nuclen_task_completed', $batch_id );
	}

	/**
	 * Schedule the next poll with exponential backoff based on poll_attempts.
	 *
	 * Polls 1-10: +30s, 11-30: +60s, 31+: +120s.
	 *
	 * @param string $batch_id      Batch ID.
	 * @param int    $poll_attempts Current attempt count (already incremented).
	 */
	private function schedule_next_poll( string $batch_id, int $poll_attempts ): void {
		if ( $poll_attempts <= 10 ) {
			$delay = 30;
		} elseif ( $poll_attempts <= 30 ) {
			$delay = 60;
		} else {
			$delay = 120;
		}

		$this->schedule_event( time() + $delay, 'nuclen_poll_batch', array( $batch_id ) );

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BatchProcessingHandler::handle_poll] Batch %s still processing, scheduled next poll in %d seconds (attempt %d)',
				$batch_id,
				$delay,
				$poll_attempts
			)
		);

		$this->spawn_cron_if_possible();
	}

	/**
	 * Handle a soft failure - fetch_updates returned null/false/WP_Error or
	 * threw. Reschedule subject to the poll_attempts cap without bumping
	 * not_found_streak.
	 *
	 * @param string $batch_id   Batch ID.
	 * @param array  $batch_data Current batch data.
	 * @param string $reason     Reason string for logging.
	 */
	private function handle_soft_failure( string $batch_id, array $batch_data, string $reason ): void {
		$poll_attempts    = isset( $batch_data['poll_attempts'] ) ? (int) $batch_data['poll_attempts'] : 0;
		$not_found_streak = isset( $batch_data['not_found_streak'] ) ? (int) $batch_data['not_found_streak'] : 0;

		$poll_attempts++;

		$batch_data['poll_attempts']    = $poll_attempts;
		$batch_data['not_found_streak'] = $not_found_streak;
		$batch_data['last_poll_at']     = time();
		$batch_data['last_status']      = 'soft_fail';

		TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );

		$processed     = isset( $batch_data['processed'] ) ? (int) $batch_data['processed'] : 0;
		$total         = isset( $batch_data['total'] ) ? (int) $batch_data['total'] : 0;
		$results_count = isset( $batch_data['result_count'] ) ? (int) $batch_data['result_count'] : 0;

		$this->log_poll_branch(
			$batch_id,
			'soft_fail',
			false,
			$poll_attempts,
			$not_found_streak,
			$processed,
			$total,
			$results_count,
			$reason
		);

		if ( $poll_attempts >= self::MAX_POLL_ATTEMPTS ) {
			$this->handle_cap_exceeded(
				$batch_id,
				$batch_data,
				sprintf( 'Polling exceeded %d attempts', self::MAX_POLL_ATTEMPTS )
			);
			return;
		}

		$this->schedule_next_poll( $batch_id, $poll_attempts );
	}

	/**
	 * Clear any queued poll events and delete the results transient on a
	 * terminal outcome. Audited to cover every terminal branch: complete,
	 * completed-but-empty, cancelled, cap-exceeded.
	 *
	 * @param string $batch_id Batch ID.
	 */
	private function cleanup_terminal( string $batch_id ): void {
		$this->clear_scheduled( 'nuclen_poll_batch', array( $batch_id ) );
		$this->remove_transient( 'nuclen_batch_results_' . $batch_id );
	}

	/**
	 * Indirect wrappers around the WordPress globals so tests can redirect
	 * them without needing namespace-scoped function overrides (which are
	 * prone to collisions when multiple test files define the same function
	 * in the same namespace).
	 */
	private function schedule_event( int $timestamp, string $hook, array $args ): void {
		if ( is_object( self::$test_hooks ) && method_exists( self::$test_hooks, 'schedule_single_event' ) ) {
			self::$test_hooks->schedule_single_event( $timestamp, $hook, $args );
			return;
		}
		wp_schedule_single_event( $timestamp, $hook, $args );
	}

	private function clear_scheduled( string $hook, array $args ): void {
		if ( is_object( self::$test_hooks ) && method_exists( self::$test_hooks, 'clear_scheduled_hook' ) ) {
			self::$test_hooks->clear_scheduled_hook( $hook, $args );
			return;
		}
		wp_clear_scheduled_hook( $hook, $args );
	}

	private function fetch_transient( string $key ) {
		if ( is_object( self::$test_hooks ) && method_exists( self::$test_hooks, 'get_transient' ) ) {
			return self::$test_hooks->get_transient( $key );
		}
		return get_transient( $key );
	}

	private function remove_transient( string $key ): void {
		if ( is_object( self::$test_hooks ) && method_exists( self::$test_hooks, 'delete_transient' ) ) {
			self::$test_hooks->delete_transient( $key );
			return;
		}
		delete_transient( $key );
	}

	private function fire_action( string $hook, ...$args ): void {
		if ( is_object( self::$test_hooks ) && method_exists( self::$test_hooks, 'do_action' ) ) {
			self::$test_hooks->do_action( $hook, ...$args );
			return;
		}
		do_action( $hook, ...$args );
	}

	private function spawn_cron_if_possible(): void {
		if ( is_object( self::$test_hooks ) && method_exists( self::$test_hooks, 'spawn_cron' ) ) {
			self::$test_hooks->spawn_cron();
			return;
		}
		if ( ! defined( 'DOING_CRON' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Single pipe-separated debug line for each poll iteration / branch.
	 */
	private function log_poll_branch(
		string $batch_id,
		string $status,
		bool $is_complete,
		int $poll_attempts,
		int $not_found_streak,
		int $processed,
		int $total,
		int $results_count,
		string $extra = ''
	): void {
		$message = sprintf(
			'[BatchProcessingHandler::handle_poll] batch_id=%s | status=%s | completed=%s | poll_attempts=%d | not_found_streak=%d | processed=%d | total=%d | results_count=%d',
			$batch_id,
			$status,
			$is_complete ? 'true' : 'false',
			$poll_attempts,
			$not_found_streak,
			$processed,
			$total,
			$results_count
		);
		if ( $extra !== '' ) {
			$message .= ' | note=' . $extra;
		}
		\NuclearEngagement\Services\LoggingService::log( $message );
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
		$settings  = SettingsRepository::get_instance();
		$processor = new BulkGenerationBatchProcessor( $settings );
		$processor->reconcile_parent_task( $task_id );
	}

}
