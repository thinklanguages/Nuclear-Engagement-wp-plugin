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
	 * Constructor
	 *
	 * @param SettingsRepository $settings
	 */
	public function __construct( SettingsRepository $settings ) {
		parent::__construct();
		$this->settings = $settings;

		// Set service-specific cache TTL
		$this->cache_ttl = 3600; // 1 hour for batch data
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

		// Creating batches based on memory and priority

		// array_chunk preserves keys when preserve_keys is true
		$batches = array_chunk( $posts, $batch_size, true );

		// Batches created successfully

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
			return max( 1, (int) ( $default_size * 0.25 ) );
		} elseif ( $memory_usage['percentage'] > 50 ) {
			return max( 1, (int) ( $default_size * 0.5 ) );
		}
		
		return $default_size;
	}

	/**
	 * Create batch generation jobs
	 *
	 * @param string $parent_generation_id Parent generation ID
	 * @param array  $batches Array of post batches
	 * @param array  $workflow Workflow configuration
	 * @return array Batch job information
	 */
	public function create_batch_jobs( string $parent_generation_id, array $batches, array $workflow ): array {
		$batch_jobs = array();

		foreach ( $batches as $index => $batch ) {
			// Skip if batch is not valid
			if ( ! is_array( $batch ) || empty( $batch ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BulkGenerationBatchProcessor::create_batch_jobs] WARNING: Invalid batch at index %d - skipping', $index ),
					'warning'
				);
				continue;
			}

			// Validate batch contains valid post data
			$valid_posts = array_filter(
				$batch,
				function ( $post ) {
					return is_array( $post ) &&
						( isset( $post['post_id'] ) || isset( $post['id'] ) ) &&
						! empty( $post['title'] ) &&
						! empty( $post['content'] );
				}
			);

			if ( empty( $valid_posts ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BulkGenerationBatchProcessor::create_batch_jobs] WARNING: Batch %d contains no valid posts - skipping', $index ),
					'warning'
				);
				continue;
			}

			$batch_id = $parent_generation_id . '_batch_' . ( $index + 1 );

			// Log batch creation
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
				$batch
			);
			// Creating batch with valid posts

			// Store batch information in transient for background processing
			TaskTransientManager::set_batch_transient(
				$batch_id,
				array(
					'parent_id'     => $parent_generation_id,
					'batch_index'   => $index + 1,
					'total_batches' => count( $batches ),
					'posts'         => $batch,
					'workflow'      => $workflow,
					'status'        => 'pending',
					'created_at'    => time(),
				),
				DAY_IN_SECONDS // Expire after 24 hours
			);

			$batch_jobs[] = array(
				'batch_id'    => $batch_id,
				'batch_index' => $index + 1,
				'post_count'  => count( $batch ),
				'status'      => 'pending',
			);
		}

		// Calculate total posts from batches
		$total_posts = array_reduce(
			$batches,
			function ( $sum, $batch ) {
				return $sum + ( is_array( $batch ) ? count( $batch ) : 0 );
			},
			0
		);

		// Store parent job information
		$job_data = array(
			'total_posts'       => $total_posts,
			'total_batches'     => count( $batches ),
			'batch_jobs'        => $batch_jobs,
			'workflow'          => $workflow,
			'workflow_type'     => $workflow['type'] ?? 'unknown',
			'status'            => 'scheduled',
			'created_at'        => time(),
			'completed_batches' => 0,
			'failed_batches'    => 0,
			'action'            => $workflow['source'] ?? 'bulk', // Add action field from source
		);

		$result = TaskTransientManager::set_task_transient( $parent_generation_id, $job_data, DAY_IN_SECONDS );

		// Bulk job created successfully

		// Add to task index for efficient querying
		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		if ( $container->has( 'task_index_service' ) ) {
			$index_service = $container->get( 'task_index_service' );
			$index_service->add_task( $parent_generation_id, $job_data );

			// Don't add individual batch tasks to the index - they're internal implementation details
			// The parent task tracks all batch progress
		}

		// Clear tasks cache so new task shows immediately
		if ( class_exists( '\NuclearEngagement\Admin\Tasks' ) ) {
			\NuclearEngagement\Admin\Tasks::clear_tasks_cache();
		}

		// Immediately verify the transient was saved
		if ( ! $result ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BulkGenerationBatchProcessor::create_batch_jobs] ERROR: Failed to save bulk job transient for generation %s', $parent_generation_id ),
				'error'
			);

			// Try to diagnose why it failed
			global $wpdb;
			$last_error = $wpdb->last_error;
			if ( $last_error ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BulkGenerationBatchProcessor::create_batch_jobs] Database error: %s', $last_error ),
					'error'
				);
			}
		} else {
			// Verify it can be retrieved
			$verify = TaskTransientManager::get_task_transient( $parent_generation_id );
			if ( ! $verify ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BulkGenerationBatchProcessor::create_batch_jobs] WARNING: Transient for %s was saved but cannot be retrieved immediately', $parent_generation_id ),
					'warning'
				);
			} else {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[BulkGenerationBatchProcessor::create_batch_jobs] SUCCESS: Verified transient for generation %s is accessible', $parent_generation_id )
				);
			}
		}

		return $batch_jobs;
	}

	/**
	 * Schedule batch processing
	 *
	 * @param array $batch_jobs Array of batch jobs
	 * @return bool Success status
	 */
	public function schedule_batch_processing( array $batch_jobs ): bool {
		$scheduled = 0;

		// Schedule batch processing

		// Check current processing count
		$processing_count = $this->get_current_processing_count();

		// Check current processing capacity

		// Process the first 3 batches immediately with 20-second gaps
		$immediate_batch_count = 3;
		$immediate_batches = array_slice( $batch_jobs, 0, $immediate_batch_count );
		$seconds_between_batches = 20; // 20 seconds between batches
		
		foreach ( $immediate_batches as $index => $job ) {
			if ( ! wp_next_scheduled( 'nuclen_process_batch', array( $job['batch_id'] ) ) ) {
				// Schedule with 20-second intervals (1s, 21s, 41s)
				$delay = 1 + ( $index * $seconds_between_batches );
				$scheduled_time = time() + $delay;
				wp_schedule_single_event( $scheduled_time, 'nuclen_process_batch', array( $job['batch_id'] ) );
				
				// Update batch job with scheduled_at time
				$batch_data = TaskTransientManager::get_batch_transient( $job['batch_id'] );
				if ( $batch_data ) {
					$batch_data['scheduled_at'] = $scheduled_time;
					TaskTransientManager::set_batch_transient( $job['batch_id'], $batch_data, DAY_IN_SECONDS );
				}
				
				// Immediate batch scheduled
				++$scheduled;
			}
		}

		// Schedule remaining batches: 3 batches per minute
		$remaining_batches = array_slice( $batch_jobs, $immediate_batch_count );
		$batches_per_minute = 3;
		$seconds_between_batches = 60 / $batches_per_minute; // 20 seconds
		$current_minute = 0;
		$batch_in_minute = 0;

		foreach ( $remaining_batches as $index => $job ) {
			if ( ! wp_next_scheduled( 'nuclen_process_batch', array( $job['batch_id'] ) ) ) {
				// Calculate delay based on 3 batches per minute
				$minute_offset = floor( $index / $batches_per_minute );
				$position_in_minute = $index % $batches_per_minute;
				
				// Base delay starts at 60 seconds (1 minute) for the first batch after immediate ones
				$delay = 60 + ( $minute_offset * 60 ) + ( $position_in_minute * $seconds_between_batches );
				$scheduled_time = time() + $delay;
				
				wp_schedule_single_event( $scheduled_time, 'nuclen_process_batch', array( $job['batch_id'] ) );
				
				// Update batch job with scheduled_at time
				$batch_data = TaskTransientManager::get_batch_transient( $job['batch_id'] );
				if ( $batch_data ) {
					$batch_data['scheduled_at'] = $scheduled_time;
					TaskTransientManager::set_batch_transient( $job['batch_id'], $batch_data, DAY_IN_SECONDS );
				}
				
				// Batch scheduled
				++$scheduled;
			}
		}

		// Only log if there were issues
		if ( $scheduled < count( $batch_jobs ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor] WARNING: Only scheduled %d/%d batch jobs',
					$scheduled,
					count( $batch_jobs )
				),
				'warning'
			);
		}

		// Update parent job with scheduled_at time (earliest batch schedule time)
		if ( $scheduled > 0 && ! empty( $batch_jobs ) ) {
			$parent_id = null;
			$earliest_scheduled_at = PHP_INT_MAX;
			
			// Find the parent ID and earliest scheduled time
			foreach ( $batch_jobs as $job ) {
				$batch_data = TaskTransientManager::get_batch_transient( $job['batch_id'] );
				if ( $batch_data ) {
					if ( ! $parent_id && isset( $batch_data['parent_id'] ) ) {
						$parent_id = $batch_data['parent_id'];
					}
					if ( isset( $batch_data['scheduled_at'] ) && $batch_data['scheduled_at'] < $earliest_scheduled_at ) {
						$earliest_scheduled_at = $batch_data['scheduled_at'];
					}
				}
			}
			
			// Update parent job with scheduled_at
			if ( $parent_id && $earliest_scheduled_at < PHP_INT_MAX ) {
				$parent_data = TaskTransientManager::get_task_transient( $parent_id );
				if ( $parent_data ) {
					$parent_data['scheduled_at'] = $earliest_scheduled_at;
					TaskTransientManager::set_task_transient( $parent_id, $parent_data, DAY_IN_SECONDS );
					
					// Update task index if available
					$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
					if ( $container->has( 'task_index_service' ) ) {
						$index_service = $container->get( 'task_index_service' );
						$index_service->update_task( $parent_id, $parent_data );
					}
				}
			}
		}

		// Trigger WordPress cron to process the scheduled events
		if ( $scheduled > 0 && ! defined( 'DOING_CRON' ) ) {
			spawn_cron();

			// Also try to process the first batch immediately if possible
			if ( ! empty( $batch_jobs ) ) {
				$first_batch_id = $batch_jobs[0]['batch_id'];
				// Attempt immediate processing of first batch
				// Ensure BatchProcessingHandler is loaded
				if ( ! class_exists( '\NuclearEngagement\Services\BatchProcessingHandler' ) ) {
					require_once __DIR__ . '/BatchProcessingHandler.php';
				}

				// Trigger the action directly
				do_action( 'nuclen_process_batch', $first_batch_id );

				// Check if the action was registered
				if ( ! has_action( 'nuclen_process_batch' ) ) {
					\NuclearEngagement\Services\LoggingService::log(
						'[BulkGenerationBatchProcessor::schedule_batch_processing] WARNING: nuclen_process_batch action has no handlers registered! Attempting to register handler.',
						'warning'
					);

					// Try to register the handler if it's not registered
					if ( class_exists( '\NuclearEngagement\Services\BatchProcessingHandler' ) ) {
						\NuclearEngagement\Services\BatchProcessingHandler::init();

						// Check again after registration
						if ( has_action( 'nuclen_process_batch' ) ) {
							// Handler registered successfully
							// Trigger the action again now that it's registered
							do_action( 'nuclen_process_batch', $first_batch_id );
						} else {
							// Still no handler, try direct processing as last resort
							// Attempting fallback processing
							\NuclearEngagement\Services\BatchProcessingHandler::process_batch_hook( $first_batch_id );
						}
					} else {
						\NuclearEngagement\Services\LoggingService::log(
							'[BulkGenerationBatchProcessor] ERROR: Handler class missing',
							'error'
						);
					}
				}
			}
		}

		return $scheduled > 0;
	}

	/**
	 * Queue posts for generation (used by auto-generation)
	 *
	 * @param array  $post_ids Post IDs to queue
	 * @param string $workflow_type Workflow type
	 * @param string $priority Priority level
	 * @param string $source Source of generation (auto, manual, bulk, single)
	 * @return string Generation ID
	 */
	public function queue_generation( array $post_ids, string $workflow_type, string $priority = 'low', string $source = '' ): string {
		// Queue posts for generation

		// Acquire lock for low priority to prevent race conditions
		if ( $priority === 'low' && ! $this->acquire_lock() ) {
			\NuclearEngagement\Services\LoggingService::log(
				'[BulkGenerationBatchProcessor::queue_generation] ERROR: Failed to acquire queue lock',
				'error'
			);
			throw new ResourceException(
				'Failed to acquire batch processing lock - another process may be running',
				503,
				null,
				array(
					'lock_key'    => self::LOCK_OPTION,
					'timeout'     => self::LOCK_TIMEOUT,
					'retry_after' => 60,
				)
			);
		}

		try {
			// Create generation ID
			$generation_id = 'gen_' . uniqid( $priority . '_', true );

			// Generation ID created

			// Get posts data
			$posts         = array();
			$skipped_posts = array();
			foreach ( $post_ids as $post_id ) {
				// Validate post ID
				if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[BulkGenerationBatchProcessor::queue_generation] WARNING: Invalid post ID: %s', var_export( $post_id, true ) ),
						'warning'
					);
					$skipped_posts[] = array(
						'id'     => $post_id,
						'reason' => 'invalid_id',
					);
					continue;
				}

				$post = get_post( $post_id );
				if ( $post ) {
					// Get post data without validation
					$title   = trim( $post->post_title );
					$content = trim( wp_strip_all_tags( $post->post_content ) );

					$posts[] = array(
						'post_id' => $post->ID,  // Changed from 'id' to 'post_id' for consistency
						'id'      => $post->ID,  // Keep 'id' for backward compatibility
						'title'   => $title,
						'content' => $content,
					);
				} else {
					$skipped_posts[] = array(
						'id'     => $post_id,
						'reason' => 'post_not_found',
					);
				}
			}

			if ( ! empty( $skipped_posts ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BulkGenerationBatchProcessor::queue_generation] Skipped %d posts out of %d',
						count( $skipped_posts ),
						count( $post_ids )
					)
				);
			}

			// Build workflow first
			$workflow = array(
				'type'     => $workflow_type,
				'priority' => $priority,
				'source'   => ! empty( $source ) ? $source : ( $priority === 'low' ? 'auto' : 'manual' ),
			);

			if ( empty( $posts ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					'[BulkGenerationBatchProcessor::queue_generation] ERROR: No valid posts to process',
					'error'
				);
				throw new ValidationException(
					array( 'posts' => 'No valid posts to process in batch' ),
					'No valid posts to process in batch'
				);
			}

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor::queue_generation] %d valid posts ready for processing',
					count( $posts )
				)
			);

			// Create batches based on priority
			$batches = $this->create_batches( $posts, $priority );

			// Create batch jobs
			$batch_jobs = $this->create_batch_jobs( $generation_id, $batches, $workflow );

			// Schedule processing
			$scheduled = $this->schedule_batch_processing( $batch_jobs );

			if ( ! $scheduled ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BulkGenerationBatchProcessor::queue_generation] ERROR: Failed to schedule batch processing for generation %s',
						$generation_id
					),
					'error'
				);
				throw new ApiException(
					'Failed to schedule batch processing',
					500,
					array(
						'batch_count'     => count( $batch_jobs ),
						'scheduled_count' => $scheduled,
					),
					false
				);
			}

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor::queue_generation] SUCCESS: Queued generation %s - Posts: %d, Batches: %d, Priority: %s',
					$generation_id,
					count( $posts ),
					count( $batch_jobs ),
					$priority
				)
			);

			return $generation_id;

		} finally {
			if ( $priority === 'low' ) {
				$this->release_lock();
			}
		}
	}

	/**
	 * Get batch status
	 *
	 * @param string $parent_generation_id Parent generation ID
	 * @return array|null Batch status or null if not found
	 */
	public function get_batch_status( string $parent_generation_id ): ?array {
		$job_data = TaskTransientManager::get_task_transient( $parent_generation_id );

		if ( ! is_array( $job_data ) ) {
			return null;
		}

		// Calculate progress
		$total_processed = 0;
		$total_failed    = 0;

		foreach ( $job_data['batch_jobs'] as $batch ) {
			$batch_data = TaskTransientManager::get_batch_transient( $batch['batch_id'] );
			if ( is_array( $batch_data ) ) {
				if ( $batch_data['status'] === 'completed' || $batch_data['status'] === 'failed' ) {
					// Check if we have actual count data
					$has_success_count = isset( $batch_data['success_count'] ) || isset( $batch_data['results']['success_count'] );
					$has_fail_count = isset( $batch_data['fail_count'] ) || isset( $batch_data['results']['fail_count'] );
					
					if ( $has_success_count || $has_fail_count ) {
						// We have actual counts, use them (even if they're 0)
						$success_count = 0;
						$fail_count = 0;
						
						if ( isset( $batch_data['success_count'] ) ) {
							$success_count = $batch_data['success_count'];
						} elseif ( isset( $batch_data['results']['success_count'] ) ) {
							$success_count = $batch_data['results']['success_count'];
						}
						
						if ( isset( $batch_data['fail_count'] ) ) {
							$fail_count = $batch_data['fail_count'];
						} elseif ( isset( $batch_data['results']['fail_count'] ) ) {
							$fail_count = $batch_data['results']['fail_count'];
						}
						
						$total_processed += $success_count + $fail_count;
						$total_failed += $fail_count;
					} else {
						// Fall back to scheduled count if no actual counts available
						if ( $batch_data['status'] === 'completed' ) {
							$total_processed += $batch['post_count'];
						} else {
							$total_failed += $batch['post_count'];
						}
					}
				}
			}
		}

		return array(
			'total_posts'         => $job_data['total_posts'],
			'total_batches'       => $job_data['total_batches'],
			'completed_batches'   => $job_data['completed_batches'] ?? 0,
			'failed_batches'      => $job_data['failed_batches'] ?? 0,
			'processed_posts'     => $total_processed,
			'failed_posts'        => $total_failed,
			'status'              => $job_data['status'],
			'progress_percentage' => $job_data['total_posts'] > 0 ? round( ( $total_processed / $job_data['total_posts'] ) * 100 ) : 0,
		);
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
		// Use TransactionManager for atomic operations
		$transaction_manager = new \NuclearEngagement\Database\TransactionManager();

		// Acquire lock for this specific batch to prevent race conditions
		$lock_key      = 'nuclen_batch_lock_' . $batch_id;
		$lock_acquired = false;
		$attempts      = 0;
		$max_attempts  = 10;

		// Try to acquire lock with atomic operation
		$lock_value  = wp_generate_password( 12, false );
		$lock_option = 'nuclen_option_lock_' . $batch_id;

		while ( ! $lock_acquired && $attempts < $max_attempts ) {
			// Use add_option for atomic lock acquisition
			$lock_data = array(
				'value' => $lock_value,
				'time'  => time(),
				'pid'   => \getmypid(),
			);

			if ( add_option( $lock_option, $lock_data, '', 'no' ) ) {
				$lock_acquired = true;
			} else {
				// Check if existing lock is expired
				$existing = get_option( $lock_option );
				if ( is_array( $existing ) && isset( $existing['time'] ) ) {
					if ( time() - $existing['time'] > 30 ) {
						// Try to take over expired lock
						if ( update_option( $lock_option, $lock_data ) ) {
							$lock_acquired = true;
							continue;
						}
					}
				}

				++$attempts;
				// Exponential backoff: 100ms, 200ms, 400ms, 800ms, etc.
				$sleep_time = min( 100000 * pow( 2, $attempts - 1 ), 1000000 ); // Cap at 1 second
				usleep( $sleep_time );

				if ( $attempts % 3 === 0 ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Waiting for lock on batch %s (attempt %d/%d)',
							$batch_id,
							$attempts,
							$max_attempts
						)
					);
				}
			}
		}

		if ( ! $lock_acquired ) {
			\NuclearEngagement\Services\LoggingService::log( "update_batch_status: Failed to acquire lock for batch {$batch_id}" );
			return false;
		}

		try {
			// Use transaction for atomic updates
			$result = $transaction_manager->execute(
				function () use ( $batch_id, $status, $results ) {
					$batch_data = TaskTransientManager::get_batch_transient( $batch_id );

					if ( ! is_array( $batch_data ) ) {
							\NuclearEngagement\Services\LoggingService::log( "update_batch_status: No batch data found for {$batch_id}" );
							throw new \RuntimeException( "No batch data found for {$batch_id}" );
					}

					// Validate state transition
					$current_status = $batch_data['status'] ?? 'unknown';
					$container      = \NuclearEngagement\Core\ServiceContainer::getInstance();
					if ( $container->has( 'task_timeout_handler' ) ) {
						$timeout_handler = $container->get( 'task_timeout_handler' );
						if ( ! $timeout_handler->validate_state_transition( $current_status, $status ) ) {
							\NuclearEngagement\Services\LoggingService::log(
								sprintf(
									'update_batch_status: Invalid state transition for batch %s: %s -> %s',
									$batch_id,
									$current_status,
									$status
								),
								'warning'
							);
							// Don't throw exception, just log warning and allow transition
						}
					}

					\NuclearEngagement\Services\LoggingService::log(
						"update_batch_status: Updating batch {$batch_id} from status '{$current_status}' to '{$status}'"
					);

					$batch_data['status']     = $status;
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

						\NuclearEngagement\Services\LoggingService::log(
							sprintf(
								'Batch %s status update: success_count=%d, fail_count=%d',
								$batch_id,
								$results['success_count'] ?? 0,
								$results['fail_count'] ?? 0
							)
						);
					}

					TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );

					// Don't update task index for individual batches anymore
					// Only parent tasks should be in the index

					// Update parent job status with locking to prevent race conditions
					$parent_id   = $batch_data['parent_id'];
					
					// Acquire lock for parent update
					$parent_lock_key = 'nuclen_parent_lock_' . $parent_id;
					$parent_lock_acquired = false;
					$parent_lock_value = wp_generate_uuid4();
					
					// Try to acquire parent lock with retries
					for ( $i = 0; $i < 20; $i++ ) {
						// Use transient as fallback if object cache not available
						if ( function_exists( 'wp_cache_add' ) && wp_using_ext_object_cache() ) {
							if ( wp_cache_add( $parent_lock_key, $parent_lock_value, '', 10 ) ) {
								$parent_lock_acquired = true;
								break;
							}
						} else {
							// Fallback to transient-based locking
							if ( false === get_transient( $parent_lock_key ) ) {
								set_transient( $parent_lock_key, $parent_lock_value, 10 );
								// Double-check we got the lock
								if ( get_transient( $parent_lock_key ) === $parent_lock_value ) {
									$parent_lock_acquired = true;
									break;
								}
							}
						}
						usleep( 50000 ); // 50ms wait between retries
					}
					
					if ( ! $parent_lock_acquired ) {
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( 'Failed to acquire parent lock for %s after 20 attempts', $parent_id ),
							'error'
						);
						throw new \RuntimeException( sprintf( 'Failed to acquire parent lock for %s', $parent_id ) );
					}
					
					try {
						// Re-fetch parent data inside the lock to ensure we have the latest version
						$parent_data = TaskTransientManager::get_task_transient( $parent_id );

						if ( is_array( $parent_data ) ) {
							// Only increment counters if the batch wasn't already in this state
							// This prevents double-counting when a batch is updated multiple times with the same status
							if ( $status === 'completed' && $current_status !== 'completed' ) {
								$parent_data['completed_batches'] = ( $parent_data['completed_batches'] ?? 0 ) + 1;
							} elseif ( $status === 'failed' && $current_status !== 'failed' ) {
								$parent_data['failed_batches'] = ( $parent_data['failed_batches'] ?? 0 ) + 1;
							}

							// Ensure we don't exceed total batches (defensive programming)
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

							// Check if all batches are processed
							$total_processed = $parent_data['completed_batches'] + $parent_data['failed_batches'];
							\NuclearEngagement\Services\LoggingService::log(
								sprintf(
									'Batch status update for parent %s: completed=%d, failed=%d, total=%d',
									$parent_id,
									$parent_data['completed_batches'],
									$parent_data['failed_batches'],
									$parent_data['total_batches']
								)
							);

							if ( $total_processed >= $parent_data['total_batches'] && ! isset( $parent_data['completed_at'] ) ) {
								// Only process completion once (check if not already completed)
								
								// First, verify all batches have result counts available
								$all_counts_available = true;
								$success_count = 0;
								$fail_count    = 0;
								
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
											// Batch marked complete/failed but no counts yet - data may still be processing
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
							
								if ( $all_counts_available ) {
									// Verify that all posts have been processed
									$total_processed = $success_count + $fail_count;
									$expected_total = $parent_data['total_posts'] ?? 0;
									
									// Only mark as completed if all posts are accounted for
									if ( $total_processed >= $expected_total ) {
										// All data is available and all posts processed, proceed with completion
										$parent_data['status']       = $parent_data['failed_batches'] > 0 ? 'completed_with_errors' : 'completed';
										$parent_data['completed_at'] = time();

								\NuclearEngagement\Services\LoggingService::log(
									sprintf(
										'Generation %s completion stats: success_count=%d, fail_count=%d, total_posts=%d',
										$parent_id,
										$success_count,
										$fail_count,
										$parent_data['total_posts'] ?? 0
									)
								);

								// Add admin notice for completion only if not cancelled
								if ( $parent_data['status'] !== 'cancelled' ) {
									$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
									if ( $container->has( 'admin_notice_service' ) ) {
										$notice_service = $container->get( 'admin_notice_service' );
										// Use actual processed count (success + fail) instead of scheduled total
										$actual_processed = $success_count + $fail_count;
										$notice_service->add_generation_complete_notice(
											$parent_id,
											$actual_processed,
											$success_count,
											$fail_count,
											$parent_data['workflow_type'] ?? 'unknown'
										);
									}
								}

									// Store recent completion for tasks page notification
									$recent_completions   = get_transient( 'nuclen_recent_completions' ) ?: array();
									$recent_completions[] = array(
										'task_id'       => $parent_id,
										'status'        => $parent_data['status'],
										'fail_count'    => $fail_count,
									'success_count' => $success_count,
									'completed_at'  => time(),
								);

								// Keep only last 10 completions
							if ( count( $recent_completions ) > 10 ) {
									$recent_completions = array_slice( $recent_completions, -10 );
							}

								set_transient( 'nuclen_recent_completions', $recent_completions, HOUR_IN_SECONDS );

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
								} else {
								// Not all counts available yet - defer completion
								\NuclearEngagement\Services\LoggingService::log(
									sprintf(
										'Deferring completion for parent %s - waiting for all batch result counts',
										$parent_id
									)
								);
								// Schedule a delayed check to handle the case where counts might be available soon
								wp_schedule_single_event( time() + 5, 'nuclen_check_task_completion', array( $parent_id ) );
							}
						}

							TaskTransientManager::set_task_transient( $parent_id, $parent_data, DAY_IN_SECONDS );

							// Update parent task in index
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

							// Clear tasks cache to reflect updates immediately
							if ( class_exists( '\NuclearEngagement\Admin\Tasks' ) ) {
								\NuclearEngagement\Admin\Tasks::clear_tasks_cache();
							}

							// Schedule next batch if available
							$this->schedule_next_batch( $parent_id );
						}
					} finally {
						// Always release the parent lock
						if ( $parent_lock_acquired ) {
							if ( function_exists( 'wp_cache_delete' ) && wp_using_ext_object_cache() ) {
								wp_cache_delete( $parent_lock_key );
							} else {
								// Delete transient-based lock
								delete_transient( $parent_lock_key );
							}
						}
					}

					return true;
				},
				3
			); // Allow up to 3 retries for deadlocks

			return true;

		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'update_batch_status: Transaction failed for batch %s: %s', $batch_id, $e->getMessage() ),
				'error'
			);
			return false;
		} finally {
			// Always release the lock if we acquired it
			if ( $lock_acquired ) {
				$lock_option = 'nuclen_option_lock_' . $batch_id;
				$current     = get_option( $lock_option );
				if ( is_array( $current ) && isset( $current['value'] ) && $current['value'] === $lock_value ) {
					delete_option( $lock_option );
				}
			}
		}
	}


	/**
	 * Clean up orphaned batches
	 *
	 * @return int Number of orphaned batches cleaned
	 */
	public function cleanup_orphaned_batches(): int {
		global $wpdb;

		$cleaned = 0;

		// Find all batch transients
		$batch_transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s
				LIMIT 100",
				'_transient_nuclen_batch_%',
				'_transient_timeout_nuclen_batch_%'
			)
		);

		foreach ( $batch_transients as $transient ) {
			$batch_data = maybe_unserialize( $transient->option_value );
			if ( ! is_array( $batch_data ) || ! isset( $batch_data['parent_id'] ) ) {
				continue;
			}

			// Check if parent exists
			$parent_id   = $batch_data['parent_id'];
			$parent_data = TaskTransientManager::get_task_transient( $parent_id );

			if ( false === $parent_data ) {
				// Parent doesn't exist, this is an orphaned batch
				$batch_id = str_replace( '_transient_nuclen_batch_', '', $transient->option_name );

				\NuclearEngagement\Services\LoggingService::log(
					sprintf( 'Cleaning orphaned batch %s (parent %s not found)', $batch_id, $parent_id )
				);

				// Clean up batch and its results
				delete_transient( 'nuclen_batch_' . $batch_id );
				delete_transient( 'nuclen_batch_results_' . $batch_id );
				++$cleaned;
			}
		}

		return $cleaned;
	}

	/**
	 * Clean up old batch data with optimized bulk operations
	 *
	 * @param int $older_than_hours Clean batches older than this many hours
	 * @return int Number of cleaned batches
	 */
	public function cleanup_old_batches( int $older_than_hours = 24 ): int {
		global $wpdb;

		$cutoff_time = time() - ( $older_than_hours * HOUR_IN_SECONDS );
		$cleaned     = 0;
		$batch_size  = 50; // Process in batches to avoid memory issues

		// Apply filter to allow customization of bulk job retention
		$bulk_job_retention_hours = apply_filters( 'nuclen_bulk_job_retention_hours', 7 * 24 ); // Default 7 days
		$bulk_job_cutoff_time     = time() - ( $bulk_job_retention_hours * HOUR_IN_SECONDS );

		// Find old transients in batches
		$offset = 0;
		do {
			// Only clean up batch and batch_results transients, not bulk_job transients
			$transients = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM $wpdb->options 
					WHERE (option_name LIKE %s 
					OR option_name LIKE %s)
					AND option_name NOT LIKE %s
					LIMIT %d OFFSET %d",
					'_transient_nuclen_batch_%',
					'_transient_nuclen_batch_results_%',
					'_transient_nuclen_bulk_job_%',
					$batch_size,
					$offset
				)
			);

			if ( empty( $transients ) ) {
				break;
			}

			$to_delete = array();

			foreach ( $transients as $transient ) {
				try {
					$data = maybe_unserialize( $transient->option_value );
					if ( ! is_array( $data ) ) {
						// Corrupted transient, mark for deletion
						$to_delete[] = $transient->option_name;
						$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $transient->option_name );
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( 'Deleting corrupted transient: %s', $transient->option_name )
						);
					} elseif ( isset( $data['created_at'] ) && $data['created_at'] < $cutoff_time ) {
						$to_delete[] = $transient->option_name;
						// Also add timeout option
						$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $transient->option_name );
					}
				} catch ( \Exception $e ) {
					// Failed to unserialize, mark for deletion
					$to_delete[] = $transient->option_name;
					$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $transient->option_name );
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Failed to unserialize transient %s: %s',
							$transient->option_name,
							$e->getMessage()
						)
					);
				}
			}

			// Bulk delete old transients
			if ( ! empty( $to_delete ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%s' ) );
				$deleted      = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $wpdb->options WHERE option_name IN ($placeholders)",
						$to_delete
					)
				);
				$cleaned     += intval( $deleted / 2 ); // Divide by 2 because we delete both value and timeout

				// Clear object cache for deleted transients
				foreach ( $to_delete as $option_name ) {
					if ( strpos( $option_name, '_transient_' ) === 0 && strpos( $option_name, '_timeout_' ) === false ) {
						$transient_name = str_replace( '_transient_', '', $option_name );
						wp_cache_delete( $transient_name, 'transient' );
					}
				}
			}

			$offset += $batch_size;

			// Prevent runaway queries
			if ( $offset > 1000 ) {
				break;
			}
		} while ( count( $transients ) === $batch_size );

		// Now clean up old bulk job transients separately with longer retention
		$cleaned += $this->cleanup_old_bulk_jobs( $bulk_job_cutoff_time );

		return $cleaned;
	}

	/**
	 * Clean up old bulk job transients
	 *
	 * @param int $cutoff_time Timestamp before which jobs should be cleaned
	 * @return int Number of cleaned bulk jobs
	 */
	private function cleanup_old_bulk_jobs( int $cutoff_time ): int {
		global $wpdb;

		$cleaned    = 0;
		$batch_size = 20; // Smaller batch size for bulk jobs

		// Find old bulk job transients
		$bulk_jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s
				LIMIT %d",
				'_transient_nuclen_bulk_job_%',
				'_transient_timeout_nuclen_bulk_job_%',
				$batch_size
			)
		);

		$to_delete = array();

		foreach ( $bulk_jobs as $job ) {
			try {
				$data = maybe_unserialize( $job->option_value );
				if ( ! is_array( $data ) ) {
					// Corrupted transient
					$to_delete[] = $job->option_name;
					$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $job->option_name );
					continue;
				}

				// Only delete if older than cutoff AND completed
				if ( isset( $data['created_at'] ) &&
					$data['created_at'] < $cutoff_time &&
					isset( $data['status'] ) &&
					in_array( $data['status'], array( 'completed', 'failed', 'cancelled' ), true ) ) {

					$to_delete[] = $job->option_name;
					$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $job->option_name );

					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Cleaning up old bulk job: %s (status: %s, age: %d hours)',
							str_replace( '_transient_nuclen_bulk_job_', '', $job->option_name ),
							$data['status'],
							round( ( time() - $data['created_at'] ) / HOUR_IN_SECONDS )
						)
					);
				}
			} catch ( \Exception $e ) {
				// Failed to unserialize
				$to_delete[] = $job->option_name;
				$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $job->option_name );
			}
		}

		// Bulk delete old bulk jobs
		if ( ! empty( $to_delete ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%s' ) );
			$deleted      = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $wpdb->options WHERE option_name IN ($placeholders)",
					$to_delete
				)
			);
			$cleaned      = intval( $deleted / 2 ); // Divide by 2 because we delete both value and timeout

			// Clear object cache
			foreach ( $to_delete as $option_name ) {
				if ( strpos( $option_name, '_transient_' ) === 0 && strpos( $option_name, '_timeout_' ) === false ) {
					$transient_name = str_replace( '_transient_', '', $option_name );
					wp_cache_delete( $transient_name, 'transient' );
				}
			}
		}

		return $cleaned;
	}

	/**
	 * Get count of currently processing batches
	 *
	 * @return int Number of batches currently processing
	 */
	private function get_current_processing_count(): int {
		global $wpdb;

		// Count batches in 'running' or 'processing' status
		$count = 0;

		// Query for batch transients that are in running/processing state
		$transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s
				LIMIT 100",
				'_transient_nuclen_batch_%',
				'_transient_timeout_nuclen_batch_%'
			)
		);

		foreach ( $transients as $transient ) {
			$data = maybe_unserialize( $transient->option_value );
			if ( is_array( $data ) && isset( $data['status'] ) && ( $data['status'] === 'running' || $data['status'] === 'processing' ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Schedule next batch in queue
	 *
	 * @param string $parent_id Parent generation ID
	 */
	public function schedule_next_batch( string $parent_id ): void {
		$parent_data = TaskTransientManager::get_task_transient( $parent_id );
		if ( ! is_array( $parent_data ) ) {
			return;
		}

		// Check current processing count before scheduling
		$processing_count = $this->get_current_processing_count();

		if ( $processing_count >= self::MAX_CONCURRENT_BATCHES ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Delaying next batch scheduling - %d batches already processing (max: %d)',
					$processing_count,
					self::MAX_CONCURRENT_BATCHES
				)
			);
			// Schedule a check later
			wp_schedule_single_event( time() + 30, 'nuclen_check_batch_queue', array( $parent_id ) );
			return;
		}

		// Find next pending batch
		foreach ( $parent_data['batch_jobs'] as $job ) {
			if ( $job['status'] === 'pending' ) {
				// Schedule with delay based on priority and current load
				$base_delay  = $parent_data['workflow']['priority'] === 'low' ? 30 : 5;
				$load_factor = max( 0, $processing_count - 1 ) * 10; // Add 10s per active batch
				$delay       = $base_delay + $load_factor;

				if ( ! wp_next_scheduled( 'nuclen_process_batch', array( $job['batch_id'] ) ) ) {
					wp_schedule_single_event( time() + $delay, 'nuclen_process_batch', array( $job['batch_id'] ) );
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Scheduled next batch %s with %d second delay (processing: %d/%d)',
							$job['batch_id'],
							$delay,
							$processing_count,
							self::MAX_CONCURRENT_BATCHES
						)
					);
				}
				break;
			}
		}
	}

	/**
	 * Acquire processing lock
	 *
	 * @return bool Success status
	 */
	private function acquire_lock(): bool {
		$lock_key   = self::LOCK_OPTION;
		$lock_value = wp_generate_password( 20, false );

		// Handle multisite
		if ( is_multisite() ) {
			$blog_id  = get_current_blog_id();
			$lock_key = self::LOCK_OPTION . '_blog_' . $blog_id;
		}

		// Use option with add_option for true atomic operation
		$lock_data = array(
			'value' => $lock_value,
			'time'  => time(),
			'pid'   => \getmypid(),
		);

		// Try atomic lock acquisition
		if ( add_option( $lock_key, $lock_data, '', 'no' ) ) {
			$this->lock_value = $lock_value;
			return true;
		}

		// Check if existing lock is expired
		$existing = get_option( $lock_key );
		if ( is_array( $existing ) && isset( $existing['time'] ) ) {
			// Check if lock is older than timeout
			if ( time() - $existing['time'] > self::LOCK_TIMEOUT ) {
				// Try to take over expired lock atomically
				// Use update_option with specific old value check
				global $wpdb;

				// Direct DB update with WHERE clause for atomic operation
				$updated = $wpdb->update(
					$wpdb->options,
					array(
						'option_value' => maybe_serialize( $lock_data ),
					),
					array(
						'option_name'  => $lock_key,
						'option_value' => maybe_serialize( $existing ),
					)
				);

				if ( $updated ) {
					$this->lock_value = $lock_value;
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Took over expired lock for %s (expired %d seconds ago)',
							$lock_key,
							time() - $existing['time']
						)
					);
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Release processing lock
	 */
	private function release_lock(): void {
		if ( null === $this->lock_value ) {
			return;
		}

		$lock_key = self::LOCK_OPTION;
		if ( is_multisite() ) {
			$blog_id  = get_current_blog_id();
			$lock_key = self::LOCK_OPTION . '_blog_' . $blog_id;
		}

		// Only delete if we own the lock
		$current = get_option( $lock_key );
		if ( is_array( $current ) && isset( $current['value'] ) &&
			$current['value'] === $this->lock_value ) {
			delete_option( $lock_key );
		}

		$this->lock_value = null;
	}

	/**
	 * Get option with multisite compatibility
	 *
	 * @param string $option Option name
	 * @param mixed  $default Default value
	 * @return mixed Option value
	 */
	private function get_site_option( string $option, $default = false ) {
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
			return get_option( $option . '_blog_' . $blog_id, $default );
		}
		return get_option( $option, $default );
	}

	/**
	 * Update option with multisite compatibility
	 *
	 * @param string $option Option name
	 * @param mixed  $value Option value
	 * @param string $autoload Autoload setting
	 * @return bool Success status
	 */
	private function update_site_option( string $option, $value, $autoload = null ): bool {
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
			return update_option( $option . '_blog_' . $blog_id, $value, $autoload );
		}
		return update_option( $option, $value, $autoload );
	}

	/**
	 * Delete option with multisite compatibility
	 *
	 * @param string $option Option name
	 * @return bool Success status
	 */
	private function delete_site_option( string $option ): bool {
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
			return delete_option( $option . '_blog_' . $blog_id );
		}
		return delete_option( $option );
	}

	/**
	 * Force completion check for a specific task
	 * 
	 * @param string $task_id The task ID to check
	 * @return bool True if task was completed, false otherwise
	 */
	public function force_task_completion_check( string $task_id ): bool {
		$task_data = TaskTransientManager::get_task_transient( $task_id );
		
		if ( ! is_array( $task_data ) || ( $task_data['status'] !== 'running' && $task_data['status'] !== 'processing' ) ) {
			return false;
		}
		
		// Check if all batches are complete and have counts
		$all_batches_complete = true;
		$all_counts_available = true;
		$total_success = 0;
		$total_fail = 0;
		
		foreach ( $task_data['batch_jobs'] ?? array() as $batch_job ) {
			$batch_data = TaskTransientManager::get_batch_transient( $batch_job['batch_id'] );
			
			if ( ! is_array( $batch_data ) ) {
				return false; // Batch data missing
			}
			
			// Check batch status
			if ( ! in_array( $batch_data['status'] ?? '', array( 'completed', 'failed', 'cancelled' ), true ) ) {
				$all_batches_complete = false;
				break;
			}
			
			// Check for counts
			$has_counts = isset( $batch_data['success_count'] ) || 
						  isset( $batch_data['fail_count'] ) ||
						  isset( $batch_data['results']['success_count'] ) ||
						  isset( $batch_data['results']['fail_count'] );
			
			if ( ! $has_counts ) {
				$all_counts_available = false;
			}
			
			// Accumulate counts
			if ( isset( $batch_data['success_count'] ) ) {
				$total_success += $batch_data['success_count'];
			} elseif ( isset( $batch_data['results']['success_count'] ) ) {
				$total_success += $batch_data['results']['success_count'];
			}
			
			if ( isset( $batch_data['fail_count'] ) ) {
				$total_fail += $batch_data['fail_count'];
			} elseif ( isset( $batch_data['results']['fail_count'] ) ) {
				$total_fail += $batch_data['results']['fail_count'];
			}
		}
		
		// If all batches are complete, force completion
		if ( $all_batches_complete ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor::force_task_completion_check] Forcing completion for task %s - Success: %d, Failed: %d',
					$task_id,
					$total_success,
					$total_fail
				)
			);
			
			// Update the parent task status
			$task_data['status'] = $task_data['failed_batches'] > 0 ? 'completed_with_errors' : 'completed';
			$task_data['completed_at'] = time();
			$task_data['success_count'] = $total_success;
			$task_data['fail_count'] = $total_fail;
			
			// Save the updated task data
			TaskTransientManager::set_task_transient( $task_id, $task_data, DAY_IN_SECONDS );
			
			// Update task index
			$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
			if ( $container->has( 'task_index_service' ) ) {
				$index_service = $container->get( 'task_index_service' );
				$index_service->update_task_status(
					$task_id,
					$task_data['status'],
					array(
						'completed_at' => $task_data['completed_at'],
						'success_count' => $total_success,
						'fail_count' => $total_fail,
					)
				);
			}
			
			// Clear tasks cache
			if ( class_exists( '\NuclearEngagement\Admin\Tasks' ) ) {
				\NuclearEngagement\Admin\Tasks::clear_tasks_cache();
			}
			
			// Add completion notice
			$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
			if ( $container->has( 'admin_notice_service' ) ) {
				$notice_service = $container->get( 'admin_notice_service' );
				$notice_service->add_generation_complete_notice(
					$task_id,
					$total_success + $total_fail,
					$total_success,
					$total_fail,
					$task_data['workflow_type'] ?? 'unknown'
				);
			}
			
			return true;
		}
		
		return false;
	}

	/**
	 * Check and recover stuck parent tasks
	 * This method checks if all batches are complete but the parent is still in processing status
	 */
	public function check_and_recover_stuck_tasks(): void {
		global $wpdb;
		
		\NuclearEngagement\Services\LoggingService::log( '[BulkGenerationBatchProcessor::check_and_recover_stuck_tasks] Starting stuck task recovery check' );
		
		// Find all parent tasks that are still in 'running' or 'processing' status
		$transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s 
				AND option_name NOT LIKE %s",
				'_transient_nuclen_task_gen_%',
				'%_batch_%',
				'_transient_timeout_%'
			)
		);
		
		$recovered_count = 0;
		
		foreach ( $transients as $transient ) {
			$task_data = maybe_unserialize( $transient->option_value );
			
			if ( ! is_array( $task_data ) || ! isset( $task_data['status'] ) ) {
				continue;
			}
			
			// Only check tasks that are in 'running' or 'processing' status
			if ( $task_data['status'] !== 'running' && $task_data['status'] !== 'processing' ) {
				continue;
			}
			
			// Extract task ID from option name
			$task_id = str_replace( '_transient_nuclen_task_', '', $transient->option_name );
			
			// Check if all batches are complete
			$all_batches_complete = true;
			$total_success = 0;
			$total_fail = 0;
			
			foreach ( $task_data['batch_jobs'] ?? array() as $batch_job ) {
				$batch_data = TaskTransientManager::get_batch_transient( $batch_job['batch_id'] );
				
				if ( ! is_array( $batch_data ) ) {
					// Batch data missing - can't determine status
					$all_batches_complete = false;
					break;
				}
				
				// Check batch status
				if ( ! in_array( $batch_data['status'] ?? '', array( 'completed', 'failed', 'cancelled' ), true ) ) {
					$all_batches_complete = false;
					break;
				}
				
				// Accumulate counts
				if ( isset( $batch_data['success_count'] ) ) {
					$total_success += $batch_data['success_count'];
				} elseif ( isset( $batch_data['results']['success_count'] ) ) {
					$total_success += $batch_data['results']['success_count'];
				}
				
				if ( isset( $batch_data['fail_count'] ) ) {
					$total_fail += $batch_data['fail_count'];
				} elseif ( isset( $batch_data['results']['fail_count'] ) ) {
					$total_fail += $batch_data['results']['fail_count'];
				}
			}
			
			// If all batches are complete but parent is still processing, recover it
			if ( $all_batches_complete ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BulkGenerationBatchProcessor::check_and_recover_stuck_tasks] Recovering stuck task %s - all batches complete but status is still running/processing',
						$task_id
					)
				);
				
				// Update the parent task status
				$task_data['status'] = $task_data['failed_batches'] > 0 ? 'completed_with_errors' : 'completed';
				$task_data['completed_at'] = time();
				
				// Save the updated task data
				TaskTransientManager::set_task_transient( $task_id, $task_data, DAY_IN_SECONDS );
				
				// Update task index
				$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
				if ( $container->has( 'task_index_service' ) ) {
					$index_service = $container->get( 'task_index_service' );
					$index_service->update_task_status(
						$task_id,
						$task_data['status'],
						array(
							'completed_at' => $task_data['completed_at'],
						)
					);
				}
				
				// Clear tasks cache
				if ( class_exists( '\NuclearEngagement\Admin\Tasks' ) ) {
					\NuclearEngagement\Admin\Tasks::clear_tasks_cache();
				}
				
				$recovered_count++;
			}
		}
		
		if ( $recovered_count > 0 ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor::check_and_recover_stuck_tasks] Recovered %d stuck tasks',
					$recovered_count
				)
			);
		}
	}

	/**
	 * Handle failed batch with retry logic
	 *
	 * @param string $batch_id Batch ID that failed
	 * @param string $error_message Error message
	 * @param array  $batch_data Batch data
	 */
	public function handle_failed_batch( string $batch_id, string $error_message, array $batch_data ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BulkGenerationBatchProcessor::handle_failed_batch] Handling failed batch %s - Error: %s',
				$batch_id,
				$error_message
			),
			'error'
		);

		$retries = $this->get_site_option( self::RETRY_OPTION, array() );

		// Get retry count for this batch
		$retry_count = $retries[ $batch_id ]['count'] ?? 0;
		$max_retries = ( isset( $batch_data['workflow']['max_retries'] ) ) ? $batch_data['workflow']['max_retries'] : self::MAX_RETRIES;

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BulkGenerationBatchProcessor::handle_failed_batch] Batch %s - Current retry count: %d, Max retries: %d',
				$batch_id,
				$retry_count,
				$max_retries
			)
		);

		++$retry_count;

		if ( $retry_count < $max_retries ) {
			// Update retry info
			$retries[ $batch_id ] = array(
				'count'        => $retry_count,
				'last_error'   => $error_message,
				'last_attempt' => time(),
			);
			$this->update_site_option( self::RETRY_OPTION, $retries, 'no' );

			// Schedule retry with exponential backoff
			$base_delay = self::RETRY_DELAY; // 300 seconds (5 minutes)
			$max_delay  = 3600; // 1 hour max
			$delay      = min( $base_delay * pow( 2, $retry_count - 1 ), $max_delay );

			// Add jitter to prevent thundering herd
			$jitter = $delay * 0.1; // 10% jitter
			$delay  = $delay + mt_rand( (int) ( -$jitter ), (int) $jitter );

			$scheduled_time = time() + $delay;
			wp_schedule_single_event(
				$scheduled_time,
				'nuclen_process_batch',
				array( $batch_id )
			);

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor::handle_failed_batch] Scheduled retry %d/%d for batch %s at %s (in %d seconds)',
					$retry_count,
					$max_retries,
					$batch_id,
					date( 'Y-m-d H:i:s', $scheduled_time ),
					$delay
				)
			);
		} else {
			// Max retries reached, mark as permanently failed
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[BulkGenerationBatchProcessor::handle_failed_batch] Max retries (%d) reached for batch %s - Marking as permanently failed',
					$max_retries,
					$batch_id
				),
				'error'
			);

			$this->update_batch_status(
				$batch_id,
				'failed',
				array(
					'error'       => $error_message,
					'retry_count' => $retry_count,
				)
			);

			// Clean up retry data
			unset( $retries[ $batch_id ] );
			$this->update_site_option( self::RETRY_OPTION, $retries, 'no' );

			// Log parent generation failure if applicable
			if ( isset( $batch_data['parent_id'] ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[BulkGenerationBatchProcessor::handle_failed_batch] Parent generation %s affected by batch %s failure',
						$batch_data['parent_id'],
						$batch_id
					),
					'error'
				);
			}
		}
	}

	/**
	 * Get retry status for all batches
	 *
	 * @return array Retry status information
	 */
	public function get_retry_status(): array {
		$retry_info = array();
		$retries    = $this->get_site_option( self::RETRY_OPTION, array() );

		foreach ( $retries as $batch_id => $retry_data ) {
			// Get batch data from transient
			$batch_data = TaskTransientManager::get_batch_transient( $batch_id );
			if ( ! is_array( $batch_data ) ) {
				continue;
			}

			// Get post information from batch
			foreach ( $batch_data['posts'] ?? array() as $post_data ) {
				// Check both 'post_id' and 'id' for compatibility
				$post_id = 0;
				if ( isset( $post_data['post_id'] ) ) {
					$post_id = $post_data['post_id'];
				} elseif ( isset( $post_data['id'] ) ) {
					$post_id = $post_data['id'];
				}

				if ( ! $post_id ) {
					continue;
				}

				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}

				$retry_info[] = array(
					'post_id'       => $post->ID,
					'post_title'    => $post->post_title,
					'workflow_type' => $batch_data['workflow']['type'] ?? 'unknown',
					'retry_count'   => $retry_data['count'] ?? 0,
					'max_retries'   => $batch_data['workflow']['max_retries'] ?? self::MAX_RETRIES,
					'last_error'    => $retry_data['last_error'] ?? '',
					'last_attempt'  => $retry_data['last_attempt'] ?? 0,
					'started_at'    => $batch_data['created_at'] ?? 0,
					'batch_id'      => $batch_id,
				);
			}
		}

		return $retry_info;
	}

	/**
	 * Get service name for logging and caching.
	 *
	 * @return string Service name.
	 */
	protected function get_service_name(): string {
		return 'bulk_generation_batch_processor';
	}
}
