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
	 * @var BatchLockHandler
	 */
	private BatchLockHandler $lockHandler;

	/**
	 * @var BatchDataHandler
	 */
	private BatchDataHandler $dataHandler;

	/**
	 * @var BatchApiHandler
	 */
	private BatchApiHandler $apiHandler;

	/**
	 * @var BatchErrorHandler
	 */
	private BatchErrorHandler $errorHandler;

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
		
		// Initialize handlers
		$this->lockHandler  = new BatchLockHandler();
		$this->dataHandler  = new BatchDataHandler( $batchProcessor );
		$this->apiHandler   = new BatchApiHandler( $api );
		$this->errorHandler = new BatchErrorHandler( $batchProcessor );
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
		// Try to acquire lock
		if ( ! $this->lockHandler->acquireLock( $batch_id ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchProcessingHandler::process_batch] WARNING: Batch %s is already being processed - skipping', $batch_id ),
				'warning'
			);
			return;
		}

		try {
			// Validate and prepare batch
			$batch_data = $this->dataHandler->validateBatch( $batch_id );
			if ( ! $batch_data ) {
				return;
			}

			// Check if parent is cancelled
			if ( $this->dataHandler->isParentCancelled( $batch_data ) ) {
				return;
			}

			// Process the batch
			$this->processBatchWithTimeout( $batch_id, $batch_data );

		} finally {
			// Always release the lock
			$this->lockHandler->releaseLock( $batch_id );
		}
	}

	/**
	 * Process batch with timeout handling
	 *
	 * @param string $batch_id
	 * @param array $batch_data
	 */
	private function processBatchWithTimeout( string $batch_id, array $batch_data ): void {
		// Set extended timeout
		$original_timeout = BulkGenerationTimeoutHandler::set_extended_timeout();

		try {
			// Update status and parent if needed
			$this->dataHandler->updateBatchStatus( $batch_id, 'processing' );
			$this->dataHandler->updateParentStatusIfNeeded( $batch_data );

			// Record task start
			do_action( 'nuclen_task_started', $batch_id, 3600 ); // 1 hour timeout

			// Send to API
			$result = $this->apiHandler->sendBatchToApi( $batch_id, $batch_data );

			// Process API response
			$this->processApiResponse( $batch_id, $result );

		} catch ( \Throwable $e ) {
			$this->errorHandler->handleError( $batch_id, $batch_data, $e );
		} finally {
			BulkGenerationTimeoutHandler::restore_timeout( $original_timeout );
		}
	}

	/**
	 * Process API response
	 *
	 * @param string $batch_id
	 * @param array $result
	 */
	private function processApiResponse( string $batch_id, array $result ): void {
		// Store generation ID for polling
		if ( isset( $result['generation_id'] ) ) {
			$this->apiHandler->storeGenerationId( $batch_id, $result['generation_id'] );
			$this->apiHandler->schedulePolling( $batch_id );
		} else {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchProcessingHandler::process_batch] WARNING: No generation_id in API response for batch %s', $batch_id ),
				'warning'
			);
		}

		// Store immediate results if any
		if ( ! empty( $result['results'] ) && is_array( $result['results'] ) ) {
			$this->apiHandler->storeImmediateResults( $batch_id, $result['results'] );
		}
	}

	// ... rest of the methods remain the same ...
}

/**
 * Handles batch locking operations
 */
class BatchLockHandler {
	/**
	 * Acquire lock for batch processing
	 *
	 * @param string $batch_id
	 * @return bool
	 */
	public function acquireLock( string $batch_id ): bool {
		$lock_option = 'nuclen_option_lock_batch_' . $batch_id;
		$lock_value = wp_generate_password( 12, false );

		// Try to acquire lock atomically
		if ( add_option(
			$lock_option,
			array(
				'value' => $lock_value,
				'time'  => time(),
			),
			'',
			'no'
		) ) {
			return true;
		}

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
			return true;
		}

		return false;
	}

	/**
	 * Release lock
	 *
	 * @param string $batch_id
	 */
	public function releaseLock( string $batch_id ): void {
		$lock_option = 'nuclen_option_lock_batch_' . $batch_id;
		delete_option( $lock_option );
	}
}

/**
 * Handles batch data operations
 */
class BatchDataHandler {
	/**
	 * @var BulkGenerationBatchProcessor
	 */
	private BulkGenerationBatchProcessor $batchProcessor;

	/**
	 * Constructor
	 *
	 * @param BulkGenerationBatchProcessor $batchProcessor
	 */
	public function __construct( BulkGenerationBatchProcessor $batchProcessor ) {
		$this->batchProcessor = $batchProcessor;
	}

	/**
	 * Validate batch and return data
	 *
	 * @param string $batch_id
	 * @return array|null
	 */
	public function validateBatch( string $batch_id ): ?array {
		$batch_data = TaskTransientManager::get_batch_transient( $batch_id );
		
		if ( ! is_array( $batch_data ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchProcessingHandler::validateBatch] ERROR: Batch %s not found', $batch_id ),
				'error'
			);
			return null;
		}

		if ( $batch_data['status'] !== 'pending' ) {
			return null;
		}

		return $batch_data;
	}

	/**
	 * Check if parent task is cancelled
	 *
	 * @param array $batch_data
	 * @return bool
	 */
	public function isParentCancelled( array $batch_data ): bool {
		if ( ! isset( $batch_data['parent_id'] ) || empty( $batch_data['parent_id'] ) ) {
			return false;
		}

		$parent_data = TaskTransientManager::get_task_transient( $batch_data['parent_id'] );
		
		if ( $parent_data && isset( $parent_data['status'] ) && $parent_data['status'] === 'cancelled' ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchProcessingHandler] Skipping batch - parent task %s is cancelled', $batch_data['parent_id'] ),
				'info'
			);
			
			// Update batch status to cancelled
			$this->batchProcessor->update_batch_status( $batch_data['batch_id'], 'cancelled' );
			
			return true;
		}

		return false;
	}

	/**
	 * Update batch status
	 *
	 * @param string $batch_id
	 * @param string $status
	 */
	public function updateBatchStatus( string $batch_id, string $status ): void {
		$this->batchProcessor->update_batch_status( $batch_id, $status );
	}

	/**
	 * Update parent status if needed
	 *
	 * @param array $batch_data
	 */
	public function updateParentStatusIfNeeded( array $batch_data ): void {
		if ( ! isset( $batch_data['parent_id'] ) ) {
			return;
		}

		$parent_data = TaskTransientManager::get_task_transient( $batch_data['parent_id'] );
		
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
			
			// Clear tasks cache
			if ( class_exists( '\NuclearEngagement\Admin\Tasks' ) ) {
				\NuclearEngagement\Admin\Tasks::clear_tasks_cache();
			}
		}
	}
}

/**
 * Handles batch API operations
 */
class BatchApiHandler {
	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $api;

	/**
	 * Constructor
	 *
	 * @param RemoteApiService $api
	 */
	public function __construct( RemoteApiService $api ) {
		$this->api = $api;
	}

	/**
	 * Send batch to API
	 *
	 * @param string $batch_id
	 * @param array $batch_data
	 * @return array
	 */
	public function sendBatchToApi( string $batch_id, array $batch_data ): array {
		// Log the posts being sent
		$post_ids = $this->extractPostIds( $batch_data['posts'] );

		// Send batch to API
		return $this->api->send_posts_to_generate(
			array(
				'posts'         => $batch_data['posts'],
				'workflow'      => $batch_data['workflow'],
				'generation_id' => $batch_id,
			)
		);
	}

	/**
	 * Extract post IDs from posts array
	 *
	 * @param array $posts
	 * @return array
	 */
	private function extractPostIds( array $posts ): array {
		return array_map(
			function ( $post ) {
				// Check both 'post_id' and 'id' for compatibility
				if ( isset( $post['post_id'] ) ) {
					return $post['post_id'];
				} elseif ( isset( $post['id'] ) ) {
					return $post['id'];
				}
				return 'unknown';
			},
			$posts
		);
	}

	/**
	 * Store generation ID for polling
	 *
	 * @param string $batch_id
	 * @param string $generation_id
	 */
	public function storeGenerationId( string $batch_id, string $generation_id ): void {
		// Re-fetch the batch data to ensure we have the latest
		$batch_data = TaskTransientManager::get_batch_transient( $batch_id );
		if ( ! is_array( $batch_data ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[BatchApiHandler] ERROR: Batch data lost for %s during processing', $batch_id ),
				'error'
			);
			return;
		}

		$batch_data['api_generation_id'] = $generation_id;
		TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );
	}

	/**
	 * Schedule polling for batch
	 *
	 * @param string $batch_id
	 */
	public function schedulePolling( string $batch_id ): void {
		if ( ! wp_next_scheduled( 'nuclen_poll_batch', array( $batch_id ) ) ) {
			wp_schedule_single_event(
				time() + 30,
				'nuclen_poll_batch',
				array( $batch_id )
			);
			
			// Force immediate cron spawn
			if ( ! defined( 'DOING_CRON' ) ) {
				spawn_cron();
			}
		}
	}

	/**
	 * Store immediate results
	 *
	 * @param string $batch_id
	 * @param array $results
	 */
	public function storeImmediateResults( string $batch_id, array $results ): void {
		// Store results in separate transients to avoid memory issues
		$results_key = 'nuclen_batch_results_' . $batch_id;
		$existing_results = get_transient( $results_key ) ?: array();

		// Merge new results with existing ones
		foreach ( $results as $post_id => $data ) {
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

		// Update batch data with count only
		$batch_data = TaskTransientManager::get_batch_transient( $batch_id );
		if ( is_array( $batch_data ) ) {
			$batch_data['result_count'] = count( $existing_results );
			TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );
		}
	}
}

/**
 * Handles batch error processing
 */
class BatchErrorHandler {
	/**
	 * @var BulkGenerationBatchProcessor
	 */
	private BulkGenerationBatchProcessor $batchProcessor;

	/**
	 * Constructor
	 *
	 * @param BulkGenerationBatchProcessor $batchProcessor
	 */
	public function __construct( BulkGenerationBatchProcessor $batchProcessor ) {
		$this->batchProcessor = $batchProcessor;
	}

	/**
	 * Handle batch processing error
	 *
	 * @param string $batch_id
	 * @param array $batch_data
	 * @param \Throwable $e
	 */
	public function handleError( string $batch_id, array $batch_data, \Throwable $e ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BatchProcessingHandler] ERROR: Exception processing batch %s - %s',
				$batch_id,
				$e->getMessage()
			),
			'error'
		);
		\NuclearEngagement\Services\LoggingService::log_exception( $e );

		// Determine if error is retryable
		$is_retryable = $this->isRetryableError( $e );

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BatchProcessingHandler] Error for batch %s is %sretryable',
				$batch_id,
				$is_retryable ? '' : 'NOT '
			)
		);

		if ( $is_retryable ) {
			$this->handleRetryableError( $batch_id, $batch_data, $e );
		} else {
			$this->handlePermanentError( $batch_id, $e );
		}

		// Add admin notice if needed
		$this->addAdminNoticeIfNeeded( $batch_data, $e );
	}

	/**
	 * Check if error is retryable
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	private function isRetryableError( \Throwable $e ): bool {
		// Network errors are retryable
		if ( $e instanceof ApiException ) {
			$code = $e->getCode();
			// 5xx errors and specific 4xx errors are retryable
			return $code >= 500 || in_array( $code, [408, 429], true );
		}

		// Check error message for known retryable patterns
		$message = strtolower( $e->getMessage() );
		$retryable_patterns = [
			'timeout',
			'timed out',
			'connection',
			'network',
			'temporary',
			'try again',
			'rate limit',
			'too many requests'
		];

		foreach ( $retryable_patterns as $pattern ) {
			if ( strpos( $message, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle retryable error
	 *
	 * @param string $batch_id
	 * @param array $batch_data
	 * @param \Throwable $e
	 */
	private function handleRetryableError( string $batch_id, array $batch_data, \Throwable $e ): void {
		$retry_count = isset( $batch_data['retry_count'] ) ? $batch_data['retry_count'] : 0;
		$this->batchProcessor->handle_failed_batch( $batch_id, $e->getMessage(), $batch_data );

		// Calculate next retry delay with exponential backoff
		$base_delay = 300; // 5 minutes base
		$max_delay = 3600; // 1 hour max
		$next_delay = min( $base_delay * pow( 2, $retry_count ), $max_delay );

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[BatchProcessingHandler] Batch %s queued for retry #%d in %d seconds',
				$batch_id,
				$retry_count + 1,
				$next_delay
			)
		);
	}

	/**
	 * Handle permanent error
	 *
	 * @param string $batch_id
	 * @param \Throwable $e
	 */
	private function handlePermanentError( string $batch_id, \Throwable $e ): void {
		$this->batchProcessor->update_batch_status(
			$batch_id,
			'failed',
			array(
				'error'         => $e->getMessage(),
				'non_retryable' => true,
			)
		);
		
		\NuclearEngagement\Services\LoggingService::log(
			sprintf( '[BatchProcessingHandler] Batch %s marked as permanently failed', $batch_id ),
			'error'
		);
	}

	/**
	 * Add admin notice if needed
	 *
	 * @param array $batch_data
	 * @param \Throwable $e
	 */
	private function addAdminNoticeIfNeeded( array $batch_data, \Throwable $e ): void {
		if ( ! isset( $batch_data['parent_id'] ) ) {
			return;
		}

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
}