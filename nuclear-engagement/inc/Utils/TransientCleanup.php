<?php
/**
 * TransientCleanup.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Utils
 */

declare(strict_types=1);

namespace NuclearEngagement\Utils;

/**
 * Utility class for cleaning up stale transients and background jobs.
 */
class TransientCleanup {

	/**
	 * Clean up all Nuclear Engagement transients.
	 *
	 * @return array Statistics about cleaned items.
	 */
	public static function cleanup_all_transients(): array {
		global $wpdb;

		$stats = array(
			'transients_deleted' => 0,
			'cron_jobs_cleared'  => 0,
			'posts_checked'      => array(),
		);

		// Clean up transients
		$transient_pattern = $wpdb->esc_like( '_transient_nuclen_' ) . '%';
		$timeout_pattern   = $wpdb->esc_like( '_transient_timeout_nuclen_' ) . '%';

		// Delete transients
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$transient_pattern,
				$timeout_pattern
			)
		);

		$stats['transients_deleted'] = $deleted;

		// Clean up scheduled cron events
		$crons = _get_cron_array();
		if ( ! empty( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				foreach ( $cron as $hook => $tasks ) {
					if ( strpos( $hook, 'nuclen_' ) === 0 ) {
						foreach ( $tasks as $task ) {
							wp_unschedule_event( $timestamp, $hook, $task['args'] );
							++$stats['cron_jobs_cleared'];

							// Track any post IDs in the arguments
							if ( ! empty( $task['args'] ) ) {
								foreach ( $task['args'] as $arg ) {
									if ( is_numeric( $arg ) && $arg > 0 ) {
										$stats['posts_checked'][] = (int) $arg;
									}
								}
							}
						}
					}
				}
			}
		}

		// Remove duplicates from posts checked
		$stats['posts_checked'] = array_unique( $stats['posts_checked'] );

		// Log the cleanup
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[TransientCleanup] Cleanup completed: %d transients deleted, %d cron jobs cleared, posts referenced: %s',
				$stats['transients_deleted'],
				$stats['cron_jobs_cleared'],
				implode( ', ', $stats['posts_checked'] )
			)
		);

		return $stats;
	}

	/**
	 * Clean up transients and jobs for a specific post.
	 *
	 * @param int $post_id Post ID to clean up.
	 * @return array Statistics about cleaned items.
	 */
	public static function cleanup_post_transients( int $post_id ): array {
		global $wpdb;

		$stats = array(
			'transients_deleted' => 0,
			'cron_jobs_cleared'  => 0,
		);

		// Clean up transients containing the post ID
		$patterns = array(
			$wpdb->esc_like( "_transient_nuclen_generation_{$post_id}_" ) . '%',
			$wpdb->esc_like( "_transient_nuclen_batch_{$post_id}_" ) . '%',
			$wpdb->esc_like( "_transient_nuclen_poll_{$post_id}_" ) . '%',
		);

		foreach ( $patterns as $pattern ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$pattern
				)
			);
			$stats['transients_deleted'] += $deleted;
		}

		// Clean up scheduled cron events for this post
		$crons = _get_cron_array();
		if ( ! empty( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				foreach ( $cron as $hook => $tasks ) {
					if ( strpos( $hook, 'nuclen_' ) === 0 ) {
						foreach ( $tasks as $task ) {
							// Check if this task references our post ID
							if ( in_array( $post_id, $task['args'], true ) ) {
								wp_unschedule_event( $timestamp, $hook, $task['args'] );
								++$stats['cron_jobs_cleared'];
							}
						}
					}
				}
			}
		}

		// Log the cleanup
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[TransientCleanup] Post %d cleanup: %d transients deleted, %d cron jobs cleared',
				$post_id,
				$stats['transients_deleted'],
				$stats['cron_jobs_cleared']
			)
		);

		return $stats;
	}

	/**
	 * Schedule regular cleanup of stale transients.
	 */
	public static function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( 'nuclen_cleanup_stale_transients' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_stale_transients' );
		}

		add_action( 'nuclen_cleanup_stale_transients', array( __CLASS__, 'cleanup_stale_transients' ) );
	}

	/**
	 * Clean up stale transients (called by cron).
	 */
	public static function cleanup_stale_transients(): void {
		global $wpdb;

		// Delete expired transients
		$expired = $wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_timeout_nuclen_%' 
			AND option_value < UNIX_TIMESTAMP()"
		);

		if ( $expired > 0 ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TransientCleanup] Cleaned up %d expired transients', $expired )
			);
		}
	}
}