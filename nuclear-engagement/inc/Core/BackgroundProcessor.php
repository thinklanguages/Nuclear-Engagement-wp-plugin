<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background job processing coordinator.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class BackgroundProcessor {
	/**
	 * Maximum concurrent jobs.
	 */
	private const MAX_CONCURRENT_JOBS = 3;

	/**
	 * Initialize background processor.
	 */
	public static function init(): void {
		// Register default job handlers
		JobHandler::register_default_handlers();

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
		add_action( 'nuclen_cleanup_completed_jobs', [ JobQueue::class, 'cleanup_completed_jobs' ] );
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
		return JobQueue::queue_job( $type, $data, $priority, $delay );
	}

	/**
	 * Register a job handler.
	 *
	 * @param string   $type    Job type.
	 * @param callable $handler Job handler function.
	 */
	public static function register_handler( string $type, callable $handler ): void {
		JobHandler::register_handler( $type, $handler );
	}

	/**
	 * Get job status.
	 *
	 * @param string $job_id Job ID.
	 * @return array|null Job status or null if not found.
	 */
	public static function get_job_status( string $job_id ): ?array {
		return JobStatus::get_job_status( $job_id );
	}

	/**
	 * Cancel a job.
	 *
	 * @param string $job_id Job ID.
	 * @return bool Whether job was cancelled.
	 */
	public static function cancel_job( string $job_id ): bool {
		return JobQueue::cancel_job( $job_id );
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
			$jobs = JobQueue::get_ready_jobs();
			$processed = 0;

			foreach ( $jobs as $job ) {
				if ( $processed >= self::MAX_CONCURRENT_JOBS ) {
					break;
				}

				JobHandler::process_job( $job );
				$processed++;
			}
		} finally {
			self::release_lock( $lock_key, $lock_value );
		}
	}

	/**
	 * Update job progress.
	 *
	 * @param string $job_id  Job ID.
	 * @param int    $progress Progress percentage.
	 * @param string $message Progress message.
	 */
	public static function update_progress( string $job_id, int $progress, string $message = '' ): void {
		JobStatus::update_progress( $job_id, $progress, $message );
	}

	/**
	 * Get job statistics.
	 *
	 * @return array Job statistics.
	 */
	public static function get_statistics(): array {
		return JobQueue::get_statistics();
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