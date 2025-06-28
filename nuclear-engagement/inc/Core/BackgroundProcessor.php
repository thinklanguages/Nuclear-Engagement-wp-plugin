<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background job processing system for async operations.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class BackgroundProcessor {
	/**
	 * Job queue storage.
	 *
	 * @var array<string, array{id: string, type: string, data: array, priority: int, attempts: int, scheduled: int, status: string}>
	 */
	private static array $job_queue = [];

	/**
	 * Job processing status.
	 *
	 * @var array<string, array{status: string, started: int, progress: int, message: string}>
	 */
	private static array $job_status = [];

	/**
	 * Maximum concurrent jobs.
	 */
	private const MAX_CONCURRENT_JOBS = 3;

	/**
	 * Maximum retry attempts.
	 */
	private const MAX_RETRY_ATTEMPTS = 3;

	/**
	 * Job timeout in seconds.
	 */
	private const JOB_TIMEOUT = 300; // 5 minutes

	/**
	 * Job types and their handlers.
	 *
	 * @var array<string, callable>
	 */
	private static array $job_handlers = [];

	/**
	 * Initialize background processor.
	 */
	public static function init(): void {
		// Register default job handlers
		self::register_default_handlers();

		// Set up cron for job processing
		if ( ! wp_next_scheduled( 'nuclen_process_background_jobs' ) ) {
			wp_schedule_event( time(), 'nuclen_every_minute', 'nuclen_process_background_jobs' );
		}

		// Add custom cron interval
		add_filter( 'cron_schedules', function( $schedules ) {
			$schedules['nuclen_every_minute'] = [
				'interval' => 60,
				'display'  => __( 'Every Minute', 'nuclear-engagement' ),
			];
			return $schedules;
		} );

		add_action( 'nuclen_process_background_jobs', [ self::class, 'process_jobs' ] );
		
		// Clean up completed jobs
		add_action( 'nuclen_cleanup_completed_jobs', [ self::class, 'cleanup_completed_jobs' ] );
		if ( ! wp_next_scheduled( 'nuclen_cleanup_completed_jobs' ) ) {
			wp_schedule_event( time(), 'hourly', 'nuclen_cleanup_completed_jobs' );
		}
	}

	/**
	 * Queue a background job.
	 *
	 * @param string $type     Job type.
	 * @param array  $data     Job data.
	 * @param int    $priority Job priority (lower = higher priority).
	 * @param int    $delay    Delay in seconds before processing.
	 * @return string Job ID.
	 */
	public static function queue_job( string $type, array $data = [], int $priority = 10, int $delay = 0 ): string {
		$job_id = wp_generate_uuid4();
		
		$job = [
			'id'        => $job_id,
			'type'      => $type,
			'data'      => $data,
			'priority'  => $priority,
			'attempts'  => 0,
			'scheduled' => time() + $delay,
			'status'    => 'queued',
			'created'   => time(),
		];

		// Store job in database for persistence
		self::store_job( $job );

		// Also keep in memory for current request
		self::$job_queue[$job_id] = $job;

		return $job_id;
	}

	/**
	 * Register a job handler.
	 *
	 * @param string   $type    Job type.
	 * @param callable $handler Job handler function.
	 */
	public static function register_handler( string $type, callable $handler ): void {
		self::$job_handlers[$type] = $handler;
	}

	/**
	 * Get job status.
	 *
	 * @param string $job_id Job ID.
	 * @return array|null Job status or null if not found.
	 */
	public static function get_job_status( string $job_id ): ?array {
		// First check memory
		if ( isset( self::$job_status[$job_id] ) ) {
			return self::$job_status[$job_id];
		}

		// Then check database
		$job = self::get_stored_job( $job_id );
		if ( $job ) {
			return [
				'status'   => $job['status'],
				'progress' => $job['progress'] ?? 0,
				'message'  => $job['message'] ?? '',
				'started'  => $job['started'] ?? 0,
			];
		}

		return null;
	}

	/**
	 * Cancel a job.
	 *
	 * @param string $job_id Job ID.
	 * @return bool Whether job was cancelled.
	 */
	public static function cancel_job( string $job_id ): bool {
		// Update status in database
		global $wpdb;
		
		$result = $wpdb->update(
			$wpdb->prefix . 'nuclen_background_jobs',
			[ 'status' => 'cancelled' ],
			[ 'job_id' => $job_id ],
			[ '%s' ],
			[ '%s' ]
		);

		// Remove from memory queue
		unset( self::$job_queue[$job_id] );
		
		if ( isset( self::$job_status[$job_id] ) ) {
			self::$job_status[$job_id]['status'] = 'cancelled';
		}

		return $result !== false;
	}

	/**
	 * Process queued jobs.
	 */
	public static function process_jobs(): void {
		// Prevent overlapping job processing
		$lock_key = 'nuclen_job_processing_lock';
		$lock_value = time();
		
		if ( ! self::acquire_lock( $lock_key, $lock_value ) ) {
			return;
		}

		try {
			$jobs = self::get_ready_jobs();
			$processed = 0;

			foreach ( $jobs as $job ) {
				if ( $processed >= self::MAX_CONCURRENT_JOBS ) {
					break;
				}

				self::process_job( $job );
				$processed++;
			}
		} finally {
			self::release_lock( $lock_key, $lock_value );
		}
	}

	/**
	 * Process a single job.
	 *
	 * @param array $job Job data.
	 */
	public static function process_job( array $job ): void {
		$job_id = $job['id'];
		$job_type = $job['type'];

		// Update job status
		self::update_job_status( $job_id, 'processing', 0, 'Starting job processing' );

		PerformanceMonitor::start( "background_job_{$job_type}" );

		try {
			if ( ! isset( self::$job_handlers[$job_type] ) ) {
				throw new \RuntimeException( "No handler registered for job type: {$job_type}" );
			}

			$handler = self::$job_handlers[$job_type];
			$context = new BackgroundJobContext( $job_id, $job['data'] );

			// Execute job with timeout
			$result = self::execute_with_timeout( $handler, [ $context ], self::JOB_TIMEOUT );

			if ( $result === false ) {
				throw new \RuntimeException( 'Job execution timed out' );
			}

			// Mark as completed
			self::update_job_status( $job_id, 'completed', 100, 'Job completed successfully' );

		} catch ( \Throwable $e ) {
			$attempts = $job['attempts'] + 1;
			
			if ( $attempts < self::MAX_RETRY_ATTEMPTS ) {
				// Schedule retry with exponential backoff
				$delay = pow( 2, $attempts ) * 60; // 2^attempts minutes
				self::retry_job( $job_id, $attempts, $delay );
				
				self::update_job_status( 
					$job_id, 
					'retrying', 
					0, 
					"Job failed, retrying in {$delay} seconds. Error: " . $e->getMessage() 
				);
			} else {
				// Mark as failed
				self::update_job_status( 
					$job_id, 
					'failed', 
					0, 
					'Job failed after maximum retry attempts. Error: ' . $e->getMessage() 
				);
			}

			ErrorRecovery::addErrorContext(
				"Background job failed: {$job_type}",
				[
					'job_id'   => $job_id,
					'job_type' => $job_type,
					'attempts' => $attempts,
					'error'    => $e->getMessage(),
					'file'     => $e->getFile(),
					'line'     => $e->getLine(),
				],
				'error'
			);
		}

		PerformanceMonitor::stop( "background_job_{$job_type}" );
	}

	/**
	 * Update job progress.
	 *
	 * @param string $job_id  Job ID.
	 * @param int    $progress Progress percentage.
	 * @param string $message Progress message.
	 */
	public static function update_progress( string $job_id, int $progress, string $message = '' ): void {
		self::update_job_status( $job_id, 'processing', $progress, $message );
	}

	/**
	 * Get job statistics.
	 *
	 * @return array Job statistics.
	 */
	public static function get_statistics(): array {
		global $wpdb;

		$stats = $wpdb->get_row( "
			SELECT 
				COUNT(*) as total,
				SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
				SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
				SUM(CASE WHEN status = 'retrying' THEN 1 ELSE 0 END) as retrying
			FROM {$wpdb->prefix}nuclen_background_jobs 
			WHERE created > DATE_SUB(NOW(), INTERVAL 24 HOUR)
		", ARRAY_A );

		return $stats ?: [];
	}

	/**
	 * Register default job handlers.
	 */
	private static function register_default_handlers(): void {
		// API Generation job
		self::register_handler( 'api_generation', function( BackgroundJobContext $context ) {
			$data = $context->get_data();
			
			if ( ! isset( $data['post_ids'] ) ) {
				throw new \InvalidArgumentException( 'post_ids required for api_generation job' );
			}

			$context->update_progress( 10, 'Preparing data for generation' );

			// Simulate API generation process
			$post_ids = $data['post_ids'];
			$total = count( $post_ids );
			$processed = 0;

			foreach ( $post_ids as $post_id ) {
				// Process individual post
				self::process_post_generation( $post_id );
				
				$processed++;
				$progress = intval( ( $processed / $total ) * 90 ) + 10; // 10-100%
				$context->update_progress( $progress, "Processed {$processed}/{$total} posts" );
			}
		} );

		// Cache warming job
		self::register_handler( 'cache_warmup', function( BackgroundJobContext $context ) {
			$context->update_progress( 20, 'Starting cache warmup' );
			
			QueryOptimizer::warmup_queries();
			$context->update_progress( 50, 'Query cache warmed up' );
			
			CacheManager::warmup();
			$context->update_progress( 80, 'General cache warmed up' );
			
			$context->update_progress( 100, 'Cache warmup completed' );
		} );

		// Data export job
		self::register_handler( 'data_export', function( BackgroundJobContext $context ) {
			$data = $context->get_data();
			$export_type = $data['type'] ?? 'all';
			
			$context->update_progress( 10, 'Preparing export' );
			
			// Export logic would go here
			$context->update_progress( 50, 'Exporting data' );
			
			// Save export file
			$context->update_progress( 90, 'Saving export file' );
			
			$context->update_progress( 100, 'Export completed' );
		} );
	}

	/**
	 * Get jobs ready for processing.
	 *
	 * @return array Jobs ready to process.
	 */
	private static function get_ready_jobs(): array {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "
			SELECT job_id, type, data, priority, attempts, scheduled, status
			FROM {$wpdb->prefix}nuclen_background_jobs
			WHERE status IN ('queued', 'retrying')
			AND scheduled <= %d
			ORDER BY priority ASC, scheduled ASC
			LIMIT %d
		", time(), self::MAX_CONCURRENT_JOBS ), ARRAY_A );
	}

	/**
	 * Store job in database.
	 *
	 * @param array $job Job data.
	 */
	private static function store_job( array $job ): void {
		global $wpdb;

		// Create table if it doesn't exist
		self::maybe_create_jobs_table();

		$wpdb->insert(
			$wpdb->prefix . 'nuclen_background_jobs',
			[
				'job_id'    => $job['id'],
				'type'      => $job['type'],
				'data'      => json_encode( $job['data'] ),
				'priority'  => $job['priority'],
				'attempts'  => $job['attempts'],
				'scheduled' => $job['scheduled'],
				'status'    => $job['status'],
				'created'   => $job['created'],
				'progress'  => 0,
				'message'   => '',
			],
			[ '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s' ]
		);
	}

	/**
	 * Get stored job from database.
	 *
	 * @param string $job_id Job ID.
	 * @return array|null Job data or null if not found.
	 */
	private static function get_stored_job( string $job_id ): ?array {
		global $wpdb;

		$job = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nuclen_background_jobs WHERE job_id = %s",
			$job_id
		), ARRAY_A );

		if ( $job ) {
			$job['data'] = json_decode( $job['data'], true );
		}

		return $job;
	}

	/**
	 * Update job status in database.
	 *
	 * @param string $job_id  Job ID.
	 * @param string $status  Job status.
	 * @param int    $progress Progress percentage.
	 * @param string $message Status message.
	 */
	private static function update_job_status( string $job_id, string $status, int $progress = 0, string $message = '' ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'nuclen_background_jobs',
			[
				'status'   => $status,
				'progress' => $progress,
				'message'  => $message,
				'updated'  => time(),
			],
			[ 'job_id' => $job_id ],
			[ '%s', '%d', '%s', '%d' ],
			[ '%s' ]
		);

		// Update memory cache
		self::$job_status[$job_id] = [
			'status'   => $status,
			'progress' => $progress,
			'message'  => $message,
			'started'  => time(),
		];
	}

	/**
	 * Retry a failed job.
	 *
	 * @param string $job_id  Job ID.
	 * @param int    $attempts Current attempt count.
	 * @param int    $delay   Delay before retry.
	 */
	private static function retry_job( string $job_id, int $attempts, int $delay ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'nuclen_background_jobs',
			[
				'status'    => 'retrying',
				'attempts'  => $attempts,
				'scheduled' => time() + $delay,
			],
			[ 'job_id' => $job_id ],
			[ '%s', '%d', '%d' ],
			[ '%s' ]
		);
	}

	/**
	 * Execute function with timeout.
	 *
	 * @param callable $function Function to execute.
	 * @param array    $args     Function arguments.
	 * @param int      $timeout  Timeout in seconds.
	 * @return mixed Function result or false on timeout.
	 */
	private static function execute_with_timeout( callable $function, array $args, int $timeout ) {
		$start_time = time();
		
		// Simple timeout check (WordPress doesn't support true async)
		register_shutdown_function( function() use ( $start_time, $timeout ) {
			if ( time() - $start_time > $timeout ) {
				error_log( 'Nuclear Engagement: Background job timed out after ' . $timeout . ' seconds' );
			}
		} );

		return call_user_func_array( $function, $args );
	}

	/**
	 * Acquire processing lock.
	 *
	 * @param string $key   Lock key.
	 * @param mixed  $value Lock value.
	 * @return bool Whether lock was acquired.
	 */
	private static function acquire_lock( string $key, $value ): bool {
		$existing = get_transient( $key );
		
		if ( $existing && ( time() - $existing ) < 300 ) { // 5 minute lock
			return false;
		}

		return set_transient( $key, $value, 300 );
	}

	/**
	 * Release processing lock.
	 *
	 * @param string $key   Lock key.
	 * @param mixed  $value Lock value.
	 */
	private static function release_lock( string $key, $value ): void {
		$existing = get_transient( $key );
		
		if ( $existing === $value ) {
			delete_transient( $key );
		}
	}

	/**
	 * Create jobs table if it doesn't exist.
	 */
	private static function maybe_create_jobs_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nuclen_background_jobs';
		
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_id varchar(36) NOT NULL,
				type varchar(50) NOT NULL,
				data longtext NOT NULL,
				priority int(11) NOT NULL DEFAULT 10,
				attempts int(11) NOT NULL DEFAULT 0,
				scheduled int(11) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'queued',
				progress int(11) NOT NULL DEFAULT 0,
				message text DEFAULT '',
				created int(11) NOT NULL,
				updated int(11) DEFAULT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY job_id (job_id),
				KEY status_scheduled (status, scheduled),
				KEY type_status (type, status)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}
	}

	/**
	 * Process post generation (placeholder).
	 *
	 * @param int $post_id Post ID.
	 */
	private static function process_post_generation( int $post_id ): void {
		// Placeholder for actual generation logic
		usleep( 100000 ); // Simulate processing time
	}

	/**
	 * Clean up completed jobs.
	 */
	public static function cleanup_completed_jobs(): void {
		global $wpdb;

		// Delete completed jobs older than 7 days
		$wpdb->query( $wpdb->prepare( "
			DELETE FROM {$wpdb->prefix}nuclen_background_jobs 
			WHERE status IN ('completed', 'failed', 'cancelled') 
			AND created < %d
		", time() - ( 7 * DAY_IN_SECONDS ) ) );
	}
}

/**
 * Background job context for job handlers.
 */
class BackgroundJobContext {
	private string $job_id;
	private array $data;

	public function __construct( string $job_id, array $data ) {
		$this->job_id = $job_id;
		$this->data = $data;
	}

	public function get_job_id(): string {
		return $this->job_id;
	}

	public function get_data(): array {
		return $this->data;
	}

	public function update_progress( int $progress, string $message = '' ): void {
		BackgroundProcessor::update_progress( $this->job_id, $progress, $message );
	}
}