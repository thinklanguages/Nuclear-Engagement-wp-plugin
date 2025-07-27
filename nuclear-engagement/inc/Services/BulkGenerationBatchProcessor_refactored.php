<?php
/**
 * BulkGenerationBatchProcessor.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\BaseService;
use NuclearEngagement\Exceptions\ValidationException;
use NuclearEngagement\Exceptions\ResourceException;
use NuclearEngagement\Exceptions\ApiException;
use NuclearEngagement\Utils\ProcessIdentifier;
use NuclearEngagement\Utils\ContentExtractor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles batch processing for large bulk generation requests
 */
class BulkGenerationBatchProcessor extends BaseService {

	/**
	 * Maximum posts per batch to prevent timeouts.
	 * Increased to 50 for better efficiency on modern servers.
	 */
	private const MAX_POSTS_PER_BATCH = 50;

	/**
	 * Maximum concurrent batches
	 * Increased to 12 for better parallelization.
	 */
	private const MAX_CONCURRENT_BATCHES = 12;

	/**
	 * Option name for processing lock.
	 */
	private const LOCK_OPTION = 'nuclen_batch_processing_lock';

	/**
	 * Lock timeout in seconds.
	 */
	private const LOCK_TIMEOUT = 300; // 5 minutes

	/**
	 * Option name for retry tracking.
	 */
	private const RETRY_OPTION = 'nuclen_generation_retries';

	/**
	 * Maximum retry attempts default.
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Retry delay in seconds.
	 */
	private const RETRY_DELAY = 300; // 5 minutes

	/**
	 * Batch size for auto-generation.
	 * Increased to 20 for better throughput.
	 */
	private const AUTO_BATCH_SIZE = 20;

	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * @var string|null Lock value for verification
	 */
	private ?string $lock_value = null;

	/**
	 * @var BatchLockManager Lock manager for batch operations
	 */
	private BatchLockManager $lock_manager;

	/**
	 * @var BatchDataManager Manager for batch data operations
	 */
	private BatchDataManager $data_manager;

	/**
	 * @var BatchCompletionHandler Handler for batch completion logic
	 */
	private BatchCompletionHandler $completion_handler;

	/**
	 * @var ParentTaskUpdater Updater for parent task operations
	 */
	private ParentTaskUpdater $parent_updater;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository $settings
	 */
	public function __construct( SettingsRepository $settings ) {
		parent::__construct();
		$this->settings = $settings;

		// Set service-specific cache TTL
		$this->cache_ttl = 3600; // 1 hour for batch data

		// Initialize sub-components
		$this->lock_manager = new BatchLockManager();
		$this->data_manager = new BatchDataManager();
		$this->completion_handler = new BatchCompletionHandler();
		$this->parent_updater = new ParentTaskUpdater();
	}

	/**
	 * Check if request should be processed in batches
	 *
	 * @param array $posts Array of posts to process
	 * @return bool
	 */
	public function should_batch_process( array $posts ): bool {
		return count( $posts ) > self::MAX_POSTS_PER_BATCH;
	}

	/**
	 * Split posts into batches
	 *
	 * @param array  $posts Array of posts to process
	 * @param string $priority Priority level (high/low)
	 * @return array Array of batches
	 */
	public function create_batches( array $posts, string $priority = 'high' ): array {
		// Use smaller batch size for auto-generation to avoid overwhelming the system
		$default_batch_size = $priority === 'low' ? self::AUTO_BATCH_SIZE : self::MAX_POSTS_PER_BATCH;
		
		// Adjust batch size based on available memory
		$batch_size = $this->calculate_optimal_batch_size( $default_batch_size, $posts );

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BulkGenerationBatchProcessor::create_batches] Creating batches: Posts: %d, Priority: %s, Default size: %d, Optimal size: %d',
				count($posts),
				$priority,
				$default_batch_size,
				$batch_size
			),
			'info'
		);

		$batches = array_chunk( $posts, $batch_size, true );

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BulkGenerationBatchProcessor::create_batches] Created %d batches with sizes: %s',
				count($batches),
				implode(', ', array_map('count', $batches))
			),
			'info'
		);

		return $batches;
	}

	/**
	 * Calculate optimal batch size based on available memory.
	 *
	 * @param int   $default_size Default batch size.
	 * @param array $posts Sample posts to estimate memory usage.
	 * @return int Optimal batch size.
	 */
	private function calculate_optimal_batch_size( int $default_size, array $posts ): int {
		// Check current memory usage
		$memory_usage = \NuclearEngagement\Core\PerformanceMonitor::getMemoryUsage();
		
		// If unlimited memory, use default
		if ( $memory_usage['limit'] < 0 ) {
			return $default_size;
		}
		
		// Estimate memory per post (rough estimate based on content size)
		$sample_size = min( 5, count( $posts ) );
		$total_content_size = 0;
		$count = 0;
		
		foreach ( array_slice( $posts, 0, $sample_size ) as $post ) {
			if ( isset( $post['content'] ) ) {
				$total_content_size += strlen( $post['content'] );
				$count++;
			}
		}
		
		// Estimate memory usage per post (content + overhead)
		$avg_content_size = $count > 0 ? $total_content_size / $count : 1000;
		$estimated_memory_per_post = $avg_content_size * 10; // 10x content size for processing overhead
		
		// Ensure we never have zero to avoid division by zero
		if ( $estimated_memory_per_post <= 0 ) {
			$estimated_memory_per_post = 10000; // Default 10KB per post minimum
		}
		
		// Calculate safe batch size based on available memory
		$available_memory = \NuclearEngagement\Core\PerformanceMonitor::getAvailableMemory();
		if ( $available_memory > 0 ) {
			// Use 50% of available memory for safety
			$safe_memory = $available_memory * 0.5;
			$memory_based_batch_size = (int) ( $safe_memory / $estimated_memory_per_post );
			
			// Return the smaller of default and memory-based size
			$optimal_size = min( $default_size, max( 1, $memory_based_batch_size ) );
			
			if ( $optimal_size < $default_size ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BulkGenerationBatchProcessor] Reduced batch size from %d to %d due to memory constraints',
						$default_size,
						$optimal_size
					),
					'warning'
				);
			}
			
			return $optimal_size;
		}
		
		// If memory is already high, use smaller batches
		if ( $memory_usage['percentage'] > 70 ) {
			$reduced_size = max( 1, (int) ( $default_size * 0.25 ) );
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor] Memory usage high (%.1f%%) - reducing batch size from %d to %d',
					$memory_usage['percentage'],
					$default_size,
					$reduced_size
				),
				'warning'
			);
			return $reduced_size;
		} elseif ( $memory_usage['percentage'] > 50 ) {
			$reduced_size = max( 1, (int) ( $default_size * 0.5 ) );
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor] Memory usage moderate (%.1f%%) - reducing batch size from %d to %d',
					$memory_usage['percentage'],
					$default_size,
					$reduced_size
				),
				'warning'
			);
			return $reduced_size;
		}
		
		return $default_size;
	}

	/**
	 * Update batch status
	 *
	 * @param string $batch_id Batch ID
	 * @param string $status New status
	 * @param array  $results Optional results data
	 * @return bool Success status
	 */
	public function update_batch_status( string $batch_id, string $status, array $results = array() ): bool {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BulkGenerationBatchProcessor::update_batch_status] Updating batch %s to status: %s',
				$batch_id,
				$status
			)
		);
		
		// Acquire lock for this specific batch
		if ( ! $this->lock_manager->acquire_batch_lock( $batch_id ) ) {
			\NuclearEngagement\Services\LoggingService::log( "update_batch_status: Failed to acquire lock for batch {$batch_id}" );
			return false;
		}

		try {
			// Use transaction for atomic updates
			$transaction_manager = new \NuclearEngagement\Database\TransactionManager();
			
			$result = $transaction_manager->execute(
				function () use ( $batch_id, $status, $results ) {
					// Update batch data
					$batch_data = $this->data_manager->update_batch_data( $batch_id, $status, $results );
					
					if ( ! $batch_data ) {
						throw new \RuntimeException( "Failed to update batch data for {$batch_id}" );
					}
					
					// Update parent task
					$parent_id = $batch_data['parent_id'];
					$current_status = $batch_data['previous_status'] ?? 'unknown';
					
					$this->parent_updater->update_parent_task(
						$parent_id,
						$batch_id,
						$status,
						$current_status,
						$results,
						$batch_data
					);
					
					return true;
				},
				3 // Allow up to 3 retries for deadlocks
			);

			return true;

		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'update_batch_status: Transaction failed for batch %s: %s', $batch_id, $e->getMessage() ),
				'error'
			);
			return false;
		} finally {
			// Always release the lock
			$this->lock_manager->release_batch_lock( $batch_id );
		}
	}

	// ... rest of the methods remain the same ...
}

/**
 * Manages locks for batch operations
 */
class BatchLockManager {
	/**
	 * Acquire lock for a specific batch
	 *
	 * @param string $batch_id Batch ID
	 * @return bool Success status
	 */
	public function acquire_batch_lock( string $batch_id ): bool {
		$lock_value = wp_generate_password( 12, false );
		$lock_option = 'nuclen_option_lock_' . $batch_id;
		$max_attempts = 10;
		$attempts = 0;

		while ( $attempts < $max_attempts ) {
			$lock_data = array(
				'value' => $lock_value,
				'time'  => time(),
				'process_id' => ProcessIdentifier::get(),
			);

			if ( add_option( $lock_option, $lock_data, '', 'no' ) ) {
				return true;
			}

			// Check if existing lock is expired
			$existing = get_option( $lock_option );
			if ( is_array( $existing ) && isset( $existing['time'] ) ) {
				if ( time() - $existing['time'] > 30 ) {
					// Try to take over expired lock
					if ( update_option( $lock_option, $lock_data ) ) {
						return true;
					}
				}
			}

			++$attempts;
			// Exponential backoff
			$sleep_time = min( 100000 * pow( 2, $attempts - 1 ), 1000000 );
			usleep( $sleep_time );
		}

		return false;
	}

	/**
	 * Release lock for a specific batch
	 *
	 * @param string $batch_id Batch ID
	 */
	public function release_batch_lock( string $batch_id ): void {
		$lock_option = 'nuclen_option_lock_' . $batch_id;
		delete_option( $lock_option );
	}

	/**
	 * Acquire lock for parent task update
	 *
	 * @param string $parent_id Parent task ID
	 * @return bool Success status
	 */
	public function acquire_parent_lock( string $parent_id ): bool {
		$parent_lock_key = 'nuclen_parent_lock_' . $parent_id;
		$parent_lock_value = wp_generate_uuid4();
		
		// Try to acquire parent lock with retries
		for ( $i = 0; $i < 20; $i++ ) {
			// Use transient as fallback if object cache not available
			if ( function_exists( 'wp_cache_add' ) && wp_using_ext_object_cache() ) {
				if ( wp_cache_add( $parent_lock_key, $parent_lock_value, '', 10 ) ) {
					return true;
				}
			} else {
				// Fallback to transient-based locking
				if ( false === get_transient( $parent_lock_key ) ) {
					set_transient( $parent_lock_key, $parent_lock_value, 10 );
					// Double-check we got the lock
					if ( get_transient( $parent_lock_key ) === $parent_lock_value ) {
						return true;
					}
				}
			}
			usleep( 50000 ); // 50ms wait between retries
		}
		
		return false;
	}

	/**
	 * Release parent lock
	 *
	 * @param string $parent_id Parent task ID
	 */
	public function release_parent_lock( string $parent_id ): void {
		$parent_lock_key = 'nuclen_parent_lock_' . $parent_id;
		
		if ( function_exists( 'wp_cache_delete' ) && wp_using_ext_object_cache() ) {
			wp_cache_delete( $parent_lock_key );
		} else {
			delete_transient( $parent_lock_key );
		}
	}
}

/**
 * Manages batch data operations
 */
class BatchDataManager {
	/**
	 * Update batch data with new status and results
	 *
	 * @param string $batch_id Batch ID
	 * @param string $status New status
	 * @param array $results Results data
	 * @return array|null Updated batch data or null on failure
	 */
	public function update_batch_data( string $batch_id, string $status, array $results ): ?array {
		$batch_data = TaskTransientManager::get_batch_transient( $batch_id );

		if ( ! is_array( $batch_data ) ) {
			\NuclearEngagement\Services\LoggingService::log( "update_batch_data: No batch data found for {$batch_id}" );
			return null;
		}

		// Validate state transition
		$current_status = $batch_data['status'] ?? 'unknown';
		
		// Skip update if already in the target status
		if ( $current_status === $status ) {
			return $batch_data; // Already in the desired state
		}
		
		// Validate state transition
		$this->validate_state_transition( $batch_id, $current_status, $status );

		// Store previous status for reference
		$batch_data['previous_status'] = $current_status;
		$batch_data['status'] = $status;
		$batch_data['updated_at'] = time();

		if ( ! empty( $results ) ) {
			// Store success/fail counts at top level for easy access
			if ( isset( $results['success_count'] ) ) {
				$batch_data['success_count'] = $results['success_count'];
			}
			if ( isset( $results['fail_count'] ) ) {
				$batch_data['fail_count'] = $results['fail_count'];
			}
			$batch_data['results'] = $results;
		}

		TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );
		
		return $batch_data;
	}

	/**
	 * Validate state transition
	 *
	 * @param string $batch_id Batch ID
	 * @param string $current_status Current status
	 * @param string $new_status New status
	 */
	private function validate_state_transition( string $batch_id, string $current_status, string $new_status ): void {
		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		if ( $container->has( 'task_timeout_handler' ) ) {
			$timeout_handler = $container->get( 'task_timeout_handler' );
			if ( ! $timeout_handler->validate_state_transition( $current_status, $new_status ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'update_batch_status: Invalid state transition for batch %s: %s -> %s',
						$batch_id,
						$current_status,
						$new_status
					),
					'warning'
				);
				// Don't throw exception, just log warning and allow transition
			}
		}
	}
}

/**
 * Updates parent task based on batch changes
 */
class ParentTaskUpdater {
	/**
	 * @var BatchLockManager
	 */
	private BatchLockManager $lock_manager;

	/**
	 * @var BatchCompletionHandler
	 */
	private BatchCompletionHandler $completion_handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->lock_manager = new BatchLockManager();
		$this->completion_handler = new BatchCompletionHandler();
	}

	/**
	 * Update parent task based on batch status change
	 *
	 * @param string $parent_id Parent task ID
	 * @param string $batch_id Batch ID
	 * @param string $status New batch status
	 * @param string $current_status Previous batch status
	 * @param array $results Batch results
	 * @param array $batch_data Full batch data
	 */
	public function update_parent_task( 
		string $parent_id, 
		string $batch_id, 
		string $status, 
		string $current_status,
		array $results,
		array $batch_data 
	): void {
		// Acquire lock for parent update
		if ( ! $this->lock_manager->acquire_parent_lock( $parent_id ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Failed to acquire parent lock for %s after 20 attempts', $parent_id ),
				'error'
			);
			throw new \RuntimeException( sprintf( 'Failed to acquire parent lock for %s', $parent_id ) );
		}
		
		try {
			// Re-fetch parent data inside the lock
			$parent_data = TaskTransientManager::get_task_transient( $parent_id );

			if ( ! is_array( $parent_data ) ) {
				return;
			}

			// Update batch status in parent
			$this->update_batch_status_in_parent( $parent_data, $batch_id, $status );
			
			// Update counters
			$this->update_parent_counters( $parent_data, $status, $current_status, $results, $batch_id, $parent_id );
			
			// Check if all batches are processed
			$this->check_and_handle_completion( $parent_data, $parent_id );
			
			// Save updated parent data
			TaskTransientManager::set_task_transient( $parent_id, $parent_data, DAY_IN_SECONDS );
			
			// Update task index
			$this->update_task_index( $parent_id, $parent_data );
			
			// Clear cache and schedule next batch
			$this->post_update_actions( $parent_id );
			
		} finally {
			// Always release the parent lock
			$this->lock_manager->release_parent_lock( $parent_id );
		}
	}

	/**
	 * Update batch status in parent's batch_jobs array
	 *
	 * @param array $parent_data Parent task data
	 * @param string $batch_id Batch ID
	 * @param string $status New status
	 */
	private function update_batch_status_in_parent( array &$parent_data, string $batch_id, string $status ): void {
		foreach ( $parent_data['batch_jobs'] as &$batch_job ) {
			if ( $batch_job['batch_id'] === $batch_id ) {
				$batch_job['status'] = $status;
				break;
			}
		}
		unset( $batch_job ); // Clean up reference
	}

	/**
	 * Update parent counters based on batch status change
	 *
	 * @param array $parent_data Parent task data
	 * @param string $status New status
	 * @param string $current_status Previous status
	 * @param array $results Batch results
	 * @param string $batch_id Batch ID
	 * @param string $parent_id Parent ID
	 */
	private function update_parent_counters( 
		array &$parent_data, 
		string $status, 
		string $current_status, 
		array $results,
		string $batch_id,
		string $parent_id 
	): void {
		// Only increment counters if the batch wasn't already in this state
		$has_counts = isset( $results['success_count'] ) || isset( $results['fail_count'] );
		
		if ( $status === 'completed' && $current_status !== 'completed' ) {
			if ( $has_counts ) {
				$parent_data['completed_batches'] = ( $parent_data['completed_batches'] ?? 0 ) + 1;
			} else {
				// Batch marked complete but no counts yet
				$parent_data['completed_batches'] = ( $parent_data['completed_batches'] ?? 0 ) + 1;
				$parent_data['pending_count_batches'] = ( $parent_data['pending_count_batches'] ?? 0 ) + 1;
				
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'Batch %s marked completed but has no counts - incrementing counter but flagging for recheck',
						$batch_id
					)
				);
				// Schedule a check to update this batch later
				wp_schedule_single_event( time() + 2, 'nuclen_recheck_batch_counts', array( $batch_id, $parent_id ) );
			}
		} elseif ( $status === 'failed' && $current_status !== 'failed' ) {
			$parent_data['failed_batches'] = ( $parent_data['failed_batches'] ?? 0 ) + 1;
		}

		// Ensure we don't exceed total batches
		if ( $parent_data['completed_batches'] > $parent_data['total_batches'] ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'WARNING: Batch count exceeded for %s: completed=%d, total=%d. Capping at total.',
					$parent_id,
					$parent_data['completed_batches'],
					$parent_data['total_batches']
				),
				'warning'
			);
			$parent_data['completed_batches'] = $parent_data['total_batches'];
		}
	}

	/**
	 * Check if all batches are processed and handle completion
	 *
	 * @param array $parent_data Parent task data
	 * @param string $parent_id Parent ID
	 */
	private function check_and_handle_completion( array &$parent_data, string $parent_id ): void {
		$total_processed = $parent_data['completed_batches'] + $parent_data['failed_batches'];

		if ( $total_processed >= $parent_data['total_batches'] && ! isset( $parent_data['completed_at'] ) ) {
			$this->completion_handler->handle_task_completion( $parent_data, $parent_id );
		}
	}

	/**
	 * Update task index with new status
	 *
	 * @param string $parent_id Parent ID
	 * @param array $parent_data Parent task data
	 */
	private function update_task_index( string $parent_id, array $parent_data ): void {
		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		if ( $container->has( 'task_index_service' ) ) {
			$index_service = $container->get( 'task_index_service' );
			$index_service->update_task_status(
				$parent_id,
				$parent_data['status'],
				array(
					'completed_at'      => $parent_data['completed_at'] ?? null,
					'completed_batches' => $parent_data['completed_batches'],
					'failed_batches'    => $parent_data['failed_batches'],
				)
			);
		}
	}

	/**
	 * Perform post-update actions
	 *
	 * @param string $parent_id Parent ID
	 */
	private function post_update_actions( string $parent_id ): void {
		// Clear tasks cache to reflect updates immediately
		if ( class_exists( '\NuclearEngagement\Admin\Tasks' ) ) {
			\NuclearEngagement\Admin\Tasks::clear_tasks_cache();
		}

		// Schedule next batch if available
		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		if ( $container->has( 'bulk_generation_batch_processor' ) ) {
			$processor = $container->get( 'bulk_generation_batch_processor' );
			$processor->schedule_next_batch( $parent_id );
		}
	}
}

/**
 * Handles batch completion logic
 */
class BatchCompletionHandler {
	/**
	 * Handle task completion when all batches are processed
	 *
	 * @param array $parent_data Parent task data
	 * @param string $parent_id Parent ID
	 */
	public function handle_task_completion( array &$parent_data, string $parent_id ): void {
		// Check if we have pending count batches
		$has_pending_counts = isset( $parent_data['pending_count_batches'] ) && $parent_data['pending_count_batches'] > 0;
		
		// First, verify all batches have result counts available
		$completion_data = $this->gather_completion_data( $parent_data );
		
		// If all batches are processed but we're waiting for counts, mark as completed anyway
		if ( ! $completion_data['all_counts_available'] && $has_pending_counts ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Parent %s has all batches complete but %d batches pending counts - marking as completed anyway',
					$parent_id,
					$parent_data['pending_count_batches']
				)
			);
			$completion_data['all_counts_available'] = true; // Force completion
		}
		
		if ( $completion_data['all_counts_available'] ) {
			$this->complete_task( $parent_data, $parent_id, $completion_data );
		} else {
			$this->defer_completion( $parent_id );
		}
	}

	/**
	 * Gather completion data from all batches
	 *
	 * @param array $parent_data Parent task data
	 * @return array Completion data
	 */
	private function gather_completion_data( array $parent_data ): array {
		$all_counts_available = true;
		$success_count = 0;
		$fail_count = 0;
		
		foreach ( $parent_data['batch_jobs'] ?? array() as $batch_job ) {
			$batch_data = TaskTransientManager::get_batch_transient( $batch_job['batch_id'] );
			if ( is_array( $batch_data ) ) {
				// Check if batch has been marked as completed or failed
				if ( in_array( $batch_data['status'] ?? '', array( 'completed', 'failed' ), true ) ) {
					// Check if we have actual count data
					$has_counts = isset( $batch_data['success_count'] ) || 
								 isset( $batch_data['fail_count'] ) ||
								 isset( $batch_data['results']['success_count'] ) ||
								 isset( $batch_data['results']['fail_count'] );
					
					if ( ! $has_counts ) {
						$all_counts_available = false;
						\NuclearEngagement\Services\LoggingService::log(
							sprintf(
								'Batch %s is %s but has no result counts yet - deferring parent completion',
								$batch_job['batch_id'],
								$batch_data['status']
							)
						);
						break;
					}
					
					// Accumulate counts
					if ( isset( $batch_data['success_count'] ) ) {
						$success_count += $batch_data['success_count'];
					} elseif ( isset( $batch_data['results']['success_count'] ) ) {
						$success_count += $batch_data['results']['success_count'];
					}

					if ( isset( $batch_data['fail_count'] ) ) {
						$fail_count += $batch_data['fail_count'];
					} elseif ( isset( $batch_data['results']['fail_count'] ) ) {
						$fail_count += $batch_data['results']['fail_count'];
					}
				}
			}
		}
		
		return array(
			'all_counts_available' => $all_counts_available,
			'success_count' => $success_count,
			'fail_count' => $fail_count,
		);
	}

	/**
	 * Complete the task
	 *
	 * @param array $parent_data Parent task data
	 * @param string $parent_id Parent ID
	 * @param array $completion_data Completion data
	 */
	private function complete_task( array &$parent_data, string $parent_id, array $completion_data ): void {
		// Verify that all posts have been processed
		$total_processed = $completion_data['success_count'] + $completion_data['fail_count'];
		$expected_total = $parent_data['total_posts'] ?? 0;
		
		// Only mark as completed if all posts are accounted for
		if ( $total_processed >= $expected_total ) {
			// Update status
			if ( $parent_data['status'] !== 'cancelled' ) {
				$parent_data['status'] = $parent_data['failed_batches'] > 0 ? 'completed_with_errors' : 'completed';
				$parent_data['completed_at'] = time();
			}

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Generation %s completion stats: success_count=%d, fail_count=%d, total_posts=%d',
					$parent_id,
					$completion_data['success_count'],
					$completion_data['fail_count'],
					$parent_data['total_posts'] ?? 0
				)
			);

			// Add notifications
			$this->add_completion_notifications( $parent_id, $parent_data, $completion_data );
			
			// Store recent completion
			$this->store_recent_completion( $parent_id, $parent_data, $completion_data );
			
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Bulk generation %s completed with status: %s (completed_at: %d)',
					$parent_id,
					$parent_data['status'],
					$parent_data['completed_at']
				)
			);
		} else {
			// Not all posts processed yet - defer completion
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Deferring completion for parent %s - only %d/%d posts processed',
					$parent_id,
					$total_processed,
					$expected_total
				)
			);
			// Schedule a delayed check to handle edge cases
			wp_schedule_single_event( time() + 5, 'nuclen_check_task_completion', array( $parent_id ) );
		}
	}

	/**
	 * Defer completion to wait for counts
	 *
	 * @param string $parent_id Parent ID
	 */
	private function defer_completion( string $parent_id ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'Deferring completion for parent %s - waiting for all batch result counts',
				$parent_id
			)
		);
		// Schedule a delayed check
		wp_schedule_single_event( time() + 5, 'nuclen_check_task_completion', array( $parent_id ) );
	}

	/**
	 * Add completion notifications
	 *
	 * @param string $parent_id Parent ID
	 * @param array $parent_data Parent task data
	 * @param array $completion_data Completion data
	 */
	private function add_completion_notifications( string $parent_id, array $parent_data, array $completion_data ): void {
		if ( $parent_data['status'] !== 'cancelled' ) {
			$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
			if ( $container->has( 'admin_notice_service' ) ) {
				$notice_service = $container->get( 'admin_notice_service' );
				$actual_processed = $completion_data['success_count'] + $completion_data['fail_count'];
				$notice_service->add_generation_complete_notice(
					$parent_id,
					$actual_processed,
					$completion_data['success_count'],
					$completion_data['fail_count'],
					$parent_data['workflow_type'] ?? 'unknown'
				);
			}
		}
	}

	/**
	 * Store recent completion for dashboard
	 *
	 * @param string $parent_id Parent ID
	 * @param array $parent_data Parent task data
	 * @param array $completion_data Completion data
	 */
	private function store_recent_completion( string $parent_id, array $parent_data, array $completion_data ): void {
		$recent_completions = get_transient( 'nuclen_recent_completions' ) ?: array();
		$recent_completions[] = array(
			'task_id'       => $parent_id,
			'status'        => $parent_data['status'],
			'fail_count'    => $completion_data['fail_count'],
			'success_count' => $completion_data['success_count'],
			'completed_at'  => time(),
		);

		// Keep only last 10 completions
		if ( count( $recent_completions ) > 10 ) {
			$recent_completions = array_slice( $recent_completions, -10 );
		}

		set_transient( 'nuclen_recent_completions', $recent_completions, HOUR_IN_SECONDS );
	}
}