<?php
/**
 * BackgroundProcessor.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

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
		// Register default job handlers.
		JobHandler::register_default_handlers();

		// Set up cron for job processing.
		if ( ! wp_next_scheduled( 'nuclen_process_background_jobs' ) ) {
			wp_schedule_event( time(), 'nuclen_every_minute', 'nuclen_process_background_jobs' );
		}

		// Add custom cron interval.
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['nuclen_every_minute'] = array(
					'interval' => 60,
					'display'  => __( 'Every Minute', 'nuclear-engagement' ),
				);
				return $schedules;
			}
		);

		add_action( 'nuclen_process_background_jobs', array( self::class, 'process_jobs' ) );

		// Clean up completed jobs.
		add_action( 'nuclen_cleanup_completed_jobs', array( JobQueue::class, 'cleanup_completed_jobs' ) );
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
	public static function queue_job( string $type, array $data = array(), int $priority = 10, int $delay = 0 ): string {
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
		// Check memory before starting
		if ( PerformanceMonitor::isMemoryUsageHigh( 70.0 ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Skipping job processing due to high memory usage: %s / %s (%.1f%%)',
					size_format( memory_get_usage( true ) ),
					size_format( PerformanceMonitor::getMemoryUsage()['limit'] ),
					PerformanceMonitor::getMemoryUsage()['percentage']
				),
				'warning'
			);
			return;
		}

		// Prevent overlapping job processing with distributed lock.
		$lock_name = 'job_processing';
		$lock_value = wp_generate_uuid4();

		// Use distributed lock for multi-server support
		if ( ! DistributedLock::acquire( $lock_name, $lock_value, 300 ) ) {
			// Check if lock is stale
			$lock_info = DistributedLock::get_info( $lock_name );
			if ( $lock_info && $lock_info['is_expired'] ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'Stale job processing lock detected from server %s, attempting takeover',
						$lock_info['server'] ?? 'unknown'
					)
				);
			}
			return;
		}

		try {
			$jobs      = JobQueue::get_ready_jobs();
			$processed = 0;
			
			// Dynamically adjust concurrent jobs based on memory
			$max_jobs = self::calculate_max_concurrent_jobs();

			foreach ( $jobs as $job ) {
				if ( $processed >= $max_jobs ) {
					break;
				}

				// Check memory before each job
				if ( PerformanceMonitor::isMemoryUsageHigh( 80.0 ) ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Stopping job processing due to memory limit: %s / %s (%.1f%%)',
							size_format( memory_get_usage( true ) ),
							size_format( PerformanceMonitor::getMemoryUsage()['limit'] ),
							PerformanceMonitor::getMemoryUsage()['percentage']
						),
						'warning'
					);
					break;
				}

				// Monitor job memory usage
				PerformanceMonitor::start( 'job_' . $job['id'] );
				
				try {
					JobHandler::process_job( $job );
				} finally {
					PerformanceMonitor::stop( 'job_' . $job['id'] );
					
					// Log memory usage for this job
					$metrics = PerformanceMonitor::getMetrics( 'job_' . $job['id'] );
					if ( $metrics && $metrics['memory_usage'] > 10 * 1024 * 1024 ) { // More than 10MB
						\NuclearEngagement\Services\LoggingService::log(
							sprintf(
								'Job %s used significant memory: %s',
								$job['id'],
								size_format( $metrics['memory_usage'] )
							)
						);
					}
				}
				
				++$processed;
				
				// Force garbage collection after memory-intensive jobs
				if ( $metrics && $metrics['memory_usage'] > 50 * 1024 * 1024 ) { // More than 50MB
					if ( function_exists( 'gc_collect_cycles' ) ) {
						gc_collect_cycles();
					}
				}
			}
		} finally {
			DistributedLock::release( $lock_name, $lock_value );
		}
	}

	/**
	 * Calculate maximum concurrent jobs based on available memory.
	 *
	 * @return int Maximum number of concurrent jobs.
	 */
	private static function calculate_max_concurrent_jobs(): int {
		$memory_usage = PerformanceMonitor::getMemoryUsage();
		
		// If unlimited memory, use default
		if ( $memory_usage['limit'] < 0 ) {
			return self::MAX_CONCURRENT_JOBS;
		}
		
		// Reduce concurrent jobs if memory usage is high
		if ( $memory_usage['percentage'] > 60 ) {
			return 1;
		} elseif ( $memory_usage['percentage'] > 40 ) {
			return 2;
		}
		
		return self::MAX_CONCURRENT_JOBS;
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

}

/**
 * Background job context for job handlers.
 */
class BackgroundJobContext {
	private string $job_id;
	private array $data;

	public function __construct( string $job_id, array $data ) {
		$this->job_id = $job_id;
		$this->data   = $data;
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
