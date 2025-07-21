<?php
/**
 * TransientCleanupCommand.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin
 */

declare(strict_types=1);

namespace NuclearEngagement\Admin;

use NuclearEngagement\Utils\TransientCleanup;

/**
 * Admin command for cleaning up transients.
 */
class TransientCleanupCommand {

	/**
	 * Register admin actions.
	 */
	public static function register(): void {
		add_action( 'admin_action_nuclen_cleanup_transients', array( __CLASS__, 'handle_cleanup' ) );
		add_action( 'admin_notices', array( __CLASS__, 'display_cleanup_notice' ) );
	}

	/**
	 * Handle the cleanup action.
	 */
	public static function handle_cleanup(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		// Verify nonce
		check_admin_referer( 'nuclen_cleanup_transients' );

		// Perform cleanup
		$stats = TransientCleanup::cleanup_all_transients();

		// Store results for display
		set_transient( 'nuclen_cleanup_results', $stats, 60 );

		// Redirect back to referring page
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=nuclear-engagement' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Display cleanup results notice.
	 */
	public static function display_cleanup_notice(): void {
		$stats = get_transient( 'nuclen_cleanup_results' );
		if ( ! $stats ) {
			return;
		}

		delete_transient( 'nuclen_cleanup_results' );

		$message = sprintf(
			__( 'Nuclear Engagement cleanup completed: %d transients deleted, %d cron jobs cleared.', 'nuclear-engagement' ),
			$stats['transients_deleted'],
			$stats['cron_jobs_cleared']
		);

		if ( ! empty( $stats['posts_checked'] ) ) {
			$message .= ' ' . sprintf(
				__( 'Posts referenced in cleaned jobs: %s', 'nuclear-engagement' ),
				implode( ', ', $stats['posts_checked'] )
			);
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Get cleanup URL.
	 *
	 * @return string Cleanup action URL.
	 */
	public static function get_cleanup_url(): string {
		return wp_nonce_url(
			admin_url( 'admin.php?action=nuclen_cleanup_transients' ),
			'nuclen_cleanup_transients'
		);
	}
}