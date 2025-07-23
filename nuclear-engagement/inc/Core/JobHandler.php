<?php
/**
 * JobHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job handler registry and execution for background processing.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class JobHandler {
	/**
	 * Job types and their handlers.
	 *
	 * @var array<string, callable>
	 */
	private static array $job_handlers = array();

	/**
	 * Maximum retry attempts.
	 */
	private const MAX_RETRY_ATTEMPTS = 3;

	/**
	 * Job timeout in seconds.
	 */
	private const JOB_TIMEOUT = 300; // 5 minutes.

	/**
	 * Register a job handler.
	 *
	 * @param string   $type    Job type.
	 * @param callable $handler Job handler function.
	 */
	public static function register_handler( string $type, callable $handler ): void {
		self::$job_handlers[ $type ] = $handler;
	}

	/**
	 * Process a single job.
	 *
	 * @param array $job Job data.
	 */
	public static function process_job( array $job ): void {
		$job_id   = $job['id'];
		$job_type = $job['type'];

		// Update job status.
		JobStatus::update_job_status( $job_id, 'processing', 0, 'Starting job processing' );

		PerformanceMonitor::start( "background_job_{$job_type}" );

		try {
			if ( ! isset( self::$job_handlers[ $job_type ] ) ) {
				throw new \RuntimeException( "No handler registered for job type: {$job_type}" );
			}

			$handler = self::$job_handlers[ $job_type ];
			$context = new BackgroundJobContext( $job_id, $job['data'] );

			// Execute job with timeout.
			$result = self::execute_with_timeout( $handler, array( $context ), self::JOB_TIMEOUT );

			if ( $result === false ) {
				throw new \RuntimeException( 'Job execution timed out' );
			}

			// Mark as completed.
			JobStatus::update_job_status( $job_id, 'completed', 100, 'Job completed successfully' );

		} catch ( \Throwable $e ) {
			$attempts = $job['attempts'] + 1;

			if ( $attempts < self::MAX_RETRY_ATTEMPTS ) {
				// Schedule retry with exponential backoff.
				$delay = pow( 2, $attempts ) * 60; // 2^attempts minutes.
				JobStatus::retry_job( $job_id, $attempts, $delay );

				JobStatus::update_job_status(
					$job_id,
					'retrying',
					0,
					"Job failed, retrying in {$delay} seconds. Error: " . $e->getMessage()
				);
			} else {
				// Mark as failed.
				JobStatus::update_job_status(
					$job_id,
					'failed',
					0,
					'Job failed after maximum retry attempts. Error: ' . $e->getMessage()
				);
			}

			LoggingService::log_exception( $e );
			LoggingService::log(
				"Background job failed: {$job_type} (job_id: {$job_id}, attempts: {$attempts})"
			);
		}

		PerformanceMonitor::stop( "background_job_{$job_type}" );
	}

	/**
	 * Register default job handlers.
	 */
	public static function register_default_handlers(): void {
		// API Generation job.
		self::register_handler(
			'api_generation',
			function ( BackgroundJobContext $context ) {
				$data = $context->get_data();

				if ( ! isset( $data['post_ids'] ) ) {
					throw new \InvalidArgumentException( 'post_ids required for api_generation job' );
				}

				$context->update_progress( 10, 'Preparing data for generation' );

				// Simulate API generation process.
				$post_ids  = $data['post_ids'];
				$total     = count( $post_ids );
				$processed = 0;

				foreach ( $post_ids as $post_id ) {
					// Process individual post.
					self::process_post_generation( $post_id );

					$processed++;
					$progress = intval( ( $processed / $total ) * 90 ) + 10; // 10-100%.
					$context->update_progress( $progress, "Processed {$processed}/{$total} posts" );
				}
			}
		);

		// Cache warming job.
		self::register_handler(
			'cache_warmup',
			function ( BackgroundJobContext $context ) {
				$context->update_progress( 20, 'Starting cache warmup' );

				QueryOptimizer::warmup_queries();
				$context->update_progress( 50, 'Query cache warmed up' );

				CacheManager::warmup();
				$context->update_progress( 80, 'General cache warmed up' );

				$context->update_progress( 100, 'Cache warmup completed' );
			}
		);

		// Data export job.
		self::register_handler(
			'data_export',
			function ( BackgroundJobContext $context ) {
				$data        = $context->get_data();
				$export_type = $data['type'] ?? 'all';

				$context->update_progress( 10, 'Preparing export' );

				// Export logic would go here.
				$context->update_progress( 50, 'Exporting data' );

				// Save export file.
				$context->update_progress( 90, 'Saving export file' );

				$context->update_progress( 100, 'Export completed' );
			}
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

		// Simple timeout check (WordPress doesn't support true async).
		register_shutdown_function(
			function () use ( $start_time, $timeout ) {
				if ( time() - $start_time > $timeout ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Nuclear Engagement: Background job timed out after ' . $timeout . ' seconds' );
				}
			}
		);

		return call_user_func_array( $function, $args );
	}

	/**
	 * Process post generation (placeholder).
	 *
	 * @param int $post_id Post ID.
	 */
	private static function process_post_generation( int $post_id ): void {
		// Placeholder for actual generation logic.
		usleep( 100000 ); // Simulate processing time.
	}
}
