<?php
/**
 * BatchCleanupService.php - Handles batch transient cleanup operations
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\BaseService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for cleaning up batch-related transients and data.
 *
 * Extracted from BulkGenerationBatchProcessor to improve maintainability.
 * Handles cleanup of orphaned batches, old batches, and bulk job transients.
 */
final class BatchCleanupService extends BaseService {

	/**
	 * Default retention period for batch data (in hours).
	 */
	private const DEFAULT_BATCH_RETENTION_HOURS = 24;

	/**
	 * Default retention period for bulk job data (in hours).
	 */
	private const DEFAULT_BULK_JOB_RETENTION_HOURS = 168; // 7 days

	/**
	 * Maximum transients to process per cleanup run.
	 */
	private const MAX_CLEANUP_ITERATIONS = 1000;

	/**
	 * Batch size for cleanup operations.
	 */
	private const CLEANUP_BATCH_SIZE = 50;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->cache_ttl = 600; // 10 minutes.
	}

	/**
	 * Get service name for logging.
	 *
	 * @return string Service name.
	 */
	protected function get_service_name(): string {
		return 'batch_cleanup';
	}

	/**
	 * Clean up orphaned batch transients.
	 *
	 * Orphaned batches are those whose parent generation no longer exists.
	 *
	 * @return int Number of orphaned batches cleaned.
	 */
	public function cleanup_orphaned_batches(): int {
		global $wpdb;

		$cleaned = 0;

		// Find all batch transients.
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

			// Check if parent exists.
			$parent_id   = $batch_data['parent_id'];
			$parent_data = TaskTransientManager::get_task_transient( $parent_id );

			if ( false === $parent_data ) {
				// Parent doesn't exist, this is an orphaned batch.
				$batch_id = str_replace( '_transient_nuclen_batch_', '', $transient->option_name );

				// Clean up batch and its results.
				delete_transient( 'nuclen_batch_' . $batch_id );
				delete_transient( 'nuclen_batch_results_' . $batch_id );
				++$cleaned;
			}
		}

		if ( $cleaned > 0 ) {
			$this->log_info( sprintf( 'Cleaned %d orphaned batches', $cleaned ) );
		}

		return $cleaned;
	}

	/**
	 * Clean up old batch data with optimized bulk operations.
	 *
	 * @param int $older_than_hours Clean batches older than this many hours.
	 * @return int Number of cleaned batches.
	 */
	public function cleanup_old_batches( int $older_than_hours = self::DEFAULT_BATCH_RETENTION_HOURS ): int {
		global $wpdb;

		$cutoff_time = time() - ( $older_than_hours * HOUR_IN_SECONDS );
		$cleaned     = 0;

		// Apply filter to allow customization of bulk job retention.
		$bulk_job_retention_hours = apply_filters(
			'nuclen_bulk_job_retention_hours',
			self::DEFAULT_BULK_JOB_RETENTION_HOURS
		);
		$bulk_job_cutoff_time     = time() - ( $bulk_job_retention_hours * HOUR_IN_SECONDS );

		// Clean batch transients.
		$cleaned += $this->cleanup_batch_transients( $cutoff_time );

		// Clean bulk job transients separately with longer retention.
		$cleaned += $this->cleanup_bulk_job_transients( $bulk_job_cutoff_time );

		if ( $cleaned > 0 ) {
			$this->log_info( sprintf( 'Cleaned %d old batch transients', $cleaned ) );
		}

		return $cleaned;
	}

	/**
	 * Clean up batch transients older than cutoff time.
	 *
	 * @param int $cutoff_time Unix timestamp cutoff.
	 * @return int Number of cleaned transients.
	 */
	private function cleanup_batch_transients( int $cutoff_time ): int {
		global $wpdb;

		$cleaned = 0;
		$offset  = 0;

		do {
			// Only clean up batch and batch_results transients, not bulk_job transients.
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
					self::CLEANUP_BATCH_SIZE,
					$offset
				)
			);

			$transient_count = count( $transients );

			if ( 0 === $transient_count ) {
				break;
			}

			$to_delete = $this->identify_expired_transients( $transients, $cutoff_time );
			$cleaned  += $this->bulk_delete_transients( $to_delete );

			$offset += self::CLEANUP_BATCH_SIZE;

			// Prevent runaway queries.
			if ( $offset > self::MAX_CLEANUP_ITERATIONS ) {
				break;
			}
		} while ( self::CLEANUP_BATCH_SIZE === $transient_count );

		return $cleaned;
	}

	/**
	 * Clean up old bulk job transients.
	 *
	 * @param int $cutoff_time Timestamp before which jobs should be cleaned.
	 * @return int Number of cleaned bulk jobs.
	 */
	private function cleanup_bulk_job_transients( int $cutoff_time ): int {
		global $wpdb;

		$cleaned    = 0;
		$batch_size = 20; // Smaller batch size for bulk jobs.

		// Find old bulk job transients.
		$bulk_jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options
				WHERE option_name LIKE %s
				AND option_name NOT LIKE %s
				LIMIT 100",
				'_transient_nuclen_bulk_job_%',
				'_transient_timeout_nuclen_bulk_job_%'
			)
		);

		$to_delete = array();

		foreach ( $bulk_jobs as $job ) {
			try {
				$data = maybe_unserialize( $job->option_value );

				if ( ! is_array( $data ) ) {
					// Corrupted transient, mark for deletion.
					$to_delete[] = $job->option_name;
					$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $job->option_name );
					continue;
				}

				// Check if job is old enough to clean.
				$job_time = $data['created_at'] ?? $data['started_at'] ?? 0;
				if ( $job_time > 0 && $job_time < $cutoff_time ) {
					// Only clean completed or failed jobs.
					$status = $data['status'] ?? '';
					if ( in_array( $status, array( 'completed', 'failed', 'completed_with_errors' ), true ) ) {
						$to_delete[] = $job->option_name;
						$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $job->option_name );
					}
				}
			} catch ( \Exception $e ) {
				// Failed to unserialize, mark for deletion.
				$to_delete[] = $job->option_name;
				$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $job->option_name );
				$this->log_exception( $e, 'cleanup_bulk_job_transients' );
			}
		}

		return $this->bulk_delete_transients( $to_delete );
	}

	/**
	 * Identify expired transients from a list.
	 *
	 * @param array $transients List of transient objects.
	 * @param int   $cutoff_time Cutoff timestamp.
	 * @return array List of option names to delete.
	 */
	private function identify_expired_transients( array $transients, int $cutoff_time ): array {
		$to_delete = array();

		foreach ( $transients as $transient ) {
			try {
				$data = maybe_unserialize( $transient->option_value );

				if ( ! is_array( $data ) ) {
					// Corrupted transient, mark for deletion.
					$to_delete[] = $transient->option_name;
					$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $transient->option_name );
				} elseif ( isset( $data['created_at'] ) && $data['created_at'] < $cutoff_time ) {
					$to_delete[] = $transient->option_name;
					$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $transient->option_name );
				}
			} catch ( \Exception $e ) {
				// Failed to unserialize, mark for deletion.
				$to_delete[] = $transient->option_name;
				$to_delete[] = str_replace( '_transient_', '_transient_timeout_', $transient->option_name );
				$this->log_exception( $e, 'identify_expired_transients' );
			}
		}

		return $to_delete;
	}

	/**
	 * Bulk delete transients from the database.
	 *
	 * @param array $to_delete List of option names to delete.
	 * @return int Number of transients deleted (pairs count as 1).
	 */
	private function bulk_delete_transients( array $to_delete ): int {
		if ( empty( $to_delete ) ) {
			return 0;
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name IN ($placeholders)",
				$to_delete
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Clear object cache for deleted transients.
		foreach ( $to_delete as $option_name ) {
			if ( strpos( $option_name, '_transient_' ) === 0 && strpos( $option_name, '_timeout_' ) === false ) {
				$transient_name = str_replace( '_transient_', '', $option_name );
				wp_cache_delete( $transient_name, 'transient' );
			}
		}

		// Divide by 2 because we delete both value and timeout.
		return intval( $deleted / 2 );
	}

	/**
	 * Run full cleanup routine.
	 *
	 * @param int $older_than_hours Hours threshold for old batches.
	 * @return array Cleanup statistics.
	 */
	public function run_full_cleanup( int $older_than_hours = self::DEFAULT_BATCH_RETENTION_HOURS ): array {
		$stats = array(
			'orphaned_cleaned' => $this->cleanup_orphaned_batches(),
			'old_cleaned'      => $this->cleanup_old_batches( $older_than_hours ),
			'timestamp'        => time(),
		);

		$this->log_info(
			sprintf(
				'Full cleanup completed: %d orphaned, %d old batches cleaned',
				$stats['orphaned_cleaned'],
				$stats['old_cleaned']
			)
		);

		return $stats;
	}
}
