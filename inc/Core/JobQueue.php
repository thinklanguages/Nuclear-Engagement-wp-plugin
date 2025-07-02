<?php
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
	private static array $job_queue = [];

	/**
	 * Maximum concurrent jobs.
	 */
	private const MAX_CONCURRENT_JOBS = 3;

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

		return $result !== false;
	}

	/**
	 * Get jobs ready for processing.
	 *
	 * @return array Jobs ready to process.
	 */
	public static function get_ready_jobs(): array {
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