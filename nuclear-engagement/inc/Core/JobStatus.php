<?php
/**
 * JobStatus.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job status management for background processing.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class JobStatus {
	/**
	 * Job processing status.
	 *
	 * @var array<string, array{status: string, started: int, progress: int, message: string}>
	 */
	private static array $job_status = array();

	/**
	 * Get job status.
	 *
	 * @param string $job_id Job ID.
	 * @return array|null Job status or null if not found.
	 */
	public static function get_job_status( string $job_id ): ?array {
		// First check memory.
		if ( isset( self::$job_status[ $job_id ] ) ) {
			return self::$job_status[ $job_id ];
		}

		// Then check database.
		$job = self::get_stored_job( $job_id );
		if ( $job ) {
			return array(
				'status'   => $job['status'],
				'progress' => $job['progress'] ?? 0,
				'message'  => $job['message'] ?? '',
				'started'  => $job['started'] ?? 0,
			);
		}

		return null;
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
	 * Update job status in database.
	 *
	 * @param string $job_id  Job ID.
	 * @param string $status  Job status.
	 * @param int    $progress Progress percentage.
	 * @param string $message Status message.
	 */
	public static function update_job_status( string $job_id, string $status, int $progress = 0, string $message = '' ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'nuclen_background_jobs',
			array(
				'status'   => $status,
				'progress' => $progress,
				'message'  => $message,
				'updated'  => time(),
			),
			array( 'job_id' => $job_id ),
			array( '%s', '%d', '%s', '%d' ),
			array( '%s' )
		);

		// Update memory cache.
		self::$job_status[ $job_id ] = array(
			'status'   => $status,
			'progress' => $progress,
			'message'  => $message,
			'started'  => time(),
		);
	}

	/**
	 * Retry a failed job.
	 *
	 * @param string $job_id  Job ID.
	 * @param int    $attempts Current attempt count.
	 * @param int    $delay   Delay before retry.
	 */
	public static function retry_job( string $job_id, int $attempts, int $delay ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'nuclen_background_jobs',
			array(
				'status'    => 'retrying',
				'attempts'  => $attempts,
				'scheduled' => time() + $delay,
			),
			array( 'job_id' => $job_id ),
			array( '%s', '%d', '%d' ),
			array( '%s' )
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

		$job = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}nuclen_background_jobs WHERE job_id = %s",
				$job_id
			),
			ARRAY_A
		);

		if ( $job ) {
			$job['data'] = wp_json_decode( $job['data'], true );
		}

		return $job;
	}
}
