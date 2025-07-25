<?php
/**
 * JobQueue.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job queue management for background processing.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class JobQueue {
	/**
	 * Job queue storage.
	 *
	 * @var array<string, array{id: string, type: string, data: array, priority: int, attempts: int, scheduled: int, status: string}>
	 */
	private static array $job_queue = array();

	/**
	 * Maximum concurrent jobs.
	 * Increased from 3 to better utilize modern server capabilities.
	 */
	private const MAX_CONCURRENT_JOBS = 10;

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
		// Check for duplicate job
		$duplicate_id = self::find_duplicate_job( $type, $data );
		if ( $duplicate_id ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Duplicate job detected. Returning existing job ID: %s', $duplicate_id )
			);
			return $duplicate_id;
		}
		
		$job_id = wp_generate_uuid4();

		$job = array(
			'id'        => $job_id,
			'type'      => $type,
			'data'      => $data,
			'priority'  => $priority,
			'attempts'  => 0,
			'scheduled' => time() + $delay,
			'status'    => 'queued',
			'created'   => time(),
		);

		// Store job in database for persistence.
		self::store_job( $job );

		// Also keep in memory for current request.
		self::$job_queue[ $job_id ] = $job;

		return $job_id;
	}

	/**
	 * Cancel a job.
	 *
	 * @param string $job_id Job ID.
	 * @return bool Whether job was cancelled.
	 */
	public static function cancel_job( string $job_id ): bool {
		// Update status in database.
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'nuclen_background_jobs',
			array( 'status' => 'cancelled' ),
			array( 'job_id' => $job_id ),
			array( '%s' ),
			array( '%s' )
		);

		// Remove from memory queue.
		unset( self::$job_queue[ $job_id ] );

		return $result !== false;
	}

	/**
	 * Get jobs ready for processing.
	 *
	 * @return array Jobs ready to process.
	 */
	public static function get_ready_jobs(): array {
		global $wpdb;

		// First, check how many jobs are currently processing
		$processing_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}nuclen_background_jobs WHERE status = 'processing'"
		);
		
		$available_slots = self::MAX_CONCURRENT_JOBS - intval( $processing_count );
		
		if ( $available_slots <= 0 ) {
			return array();
		}

		return // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT job_id, type, data, priority, attempts, scheduled, status
			FROM {$wpdb->prefix}nuclen_background_jobs
			WHERE status IN ('queued', 'retrying')
			AND scheduled <= %d
			ORDER BY priority ASC, scheduled ASC
			LIMIT %d
		",
				time(),
				$available_slots
			),
			ARRAY_A
		);
	}

	/**
	 * Get job statistics.
	 *
	 * @return array Job statistics.
	 */
	public static function get_statistics(): array {
		global $wpdb;

		$stats = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_row(
			"
			SELECT 
				COUNT(*) as total,
				SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
				SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
				SUM(CASE WHEN status = 'retrying' THEN 1 ELSE 0 END) as retrying
			FROM {$wpdb->prefix}nuclen_background_jobs 
			WHERE created > DATE_SUB(NOW(), INTERVAL 24 HOUR)
		",
			ARRAY_A
		);

		return $stats ?: array();
	}

	/**
	 * Find duplicate job in the queue.
	 *
	 * @param string $type Job type.
	 * @param array  $data Job data.
	 * @return string|null Existing job ID if duplicate found, null otherwise.
	 */
	private static function find_duplicate_job( string $type, array $data ): ?string {
		global $wpdb;
		
		// Create a hash of the job data for comparison
		$data_hash = md5( wp_json_encode( $data ) );
		
		// Check for existing job with same type and data within last hour
		$existing_job = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT job_id FROM {$wpdb->prefix}nuclen_background_jobs 
				WHERE type = %s 
				AND MD5(data) = %s 
				AND status IN ('queued', 'processing', 'retrying')
				AND created > %d
				LIMIT 1",
				$type,
				$data_hash,
				time() - 3600 // Within last hour
			)
		);
		
		return $existing_job ?: null;
	}
	
	/**
	 * Store job in database.
	 *
	 * @param array $job Job data.
	 */
	private static function store_job( array $job ): void {
		global $wpdb;

		// Create table if it doesn't exist.
		self::maybe_create_jobs_table();

		$result = $wpdb->insert(
			$wpdb->prefix . 'nuclen_background_jobs',
			array(
				'job_id'    => $job['id'],
				'type'      => $job['type'],
				'data'      => wp_json_encode( $job['data'] ),
				'priority'  => $job['priority'],
				'attempts'  => $job['attempts'],
				'scheduled' => $job['scheduled'],
				'status'    => $job['status'],
				'created'   => $job['created'],
				'progress'  => 0,
				'message'   => '',
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s' )
		);

		if ( $result === false ) {
			$error_msg = $wpdb->last_error ?: 'Unknown database error';
			\NuclearEngagement\Services\LoggingService::log(
				"Failed to store background job {$job['id']}: {$error_msg}"
			);
			throw new \RuntimeException( "Failed to store background job: {$error_msg}" );
		}
	}

	/**
	 * Create jobs table if it doesn't exist.
	 */
	private static function maybe_create_jobs_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nuclen_background_jobs';

		if ( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
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
	 * Clean up completed jobs.
	 */
	public static function cleanup_completed_jobs(): void {
		global $wpdb;

		// Delete completed jobs older than 7 days.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query(
			$wpdb->prepare(
				"
			DELETE FROM {$wpdb->prefix}nuclen_background_jobs 
			WHERE status IN ('completed', 'failed', 'cancelled') 
			AND created < %d
		",
				time() - ( 7 * DAY_IN_SECONDS )
			)
		);
	}
}
