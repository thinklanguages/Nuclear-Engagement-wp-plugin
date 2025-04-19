<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Plugin Name:       Nuclear Engagement
 * Plugin URI:        https://www.nuclearengagement.com
 * Description:       Bulk generate engaging content for your blog posts with AI in one click.
 * Version:           0.7
 * Author:            Stefano Lodola
 * Requires at least: 5.6
 * Tested up to:      6.8
 * Requires PHP:      7.3
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       nuclear-engagement
 * Domain Path:       /
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'NUCLEN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NUCLEN_PLUGIN_VERSION', '0.7' );
define( 'NUCLEN_ASSET_VERSION', '250417-2' );

/**
 * Simple autoloader for our plugin classes (PSR‑4‑ish).
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'NuclearEngagement\\';
		$base_dir = __DIR__;

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );

		$subpath = '/includes/';
		if ( strpos( $relative_class, 'Admin\\' ) === 0 ) {
			$subpath        = '/admin/';
			$relative_class = substr( $relative_class, strlen( 'Admin\\' ) );
		} elseif ( strpos( $relative_class, 'Front\\' ) === 0 ) {
			$subpath        = '/front/';
			$relative_class = substr( $relative_class, strlen( 'Front\\' ) );
		}

		$file = $base_dir . $subpath . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/* ──────────────────────────────────────────────────────────
 * Activation / deactivation hooks
 * ────────────────────────────────────────────────────────── */
function nuclear_engagement_activate_plugin() {
	NuclearEngagement\Activator::nuclen_activate();
}
register_activation_hook( __FILE__, 'nuclear_engagement_activate_plugin' );

function nuclear_engagement_deactivate_plugin() {
	NuclearEngagement\Deactivator::nuclen_deactivate();
}
register_deactivation_hook( __FILE__, 'nuclear_engagement_deactivate_plugin' );

/**
 * Redirect to Setup screen right after activation.
 */
function nuclear_engagement_redirect_on_activation() {
	if ( get_transient( 'nuclen_plugin_activation_redirect' ) ) {
		delete_transient( 'nuclen_plugin_activation_redirect' );
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=nuclear-engagement-setup' ) );
			exit;
		}
	}
}
add_action( 'admin_init', 'nuclear_engagement_redirect_on_activation' );

/* ──────────────────────────────────────────────────────────
 * ❶ MIGRATION: Convert legacy WP Application Password → new plugin password
 * ────────────────────────────────────────────────────────── */
function nuclen_migrate_app_password() {

	// Already migrated?
	if ( get_option( 'nuclen_app_pass_migration_done' ) ) {
		return;
	}

	// Only run when an admin‑area page loads and the current user can manage options.
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$app_setup = get_option(
		'nuclear_engagement_setup',
		array(
			'api_key'             => '',
			'connected'           => false,
			'wp_app_pass_created' => false,
			'plugin_password'     => '',
		)
	);

	// We need to migrate if the old flag is set, but no plugin_password yet.
	if ( empty( $app_setup['wp_app_pass_created'] ) || ! empty( $app_setup['plugin_password'] ) ) {
		update_option( 'nuclen_app_pass_migration_done', true ); // nothing to do
		return;
	}

	/* — Generate fresh plugin‑side password & UUID — */
	$plugin_password = wp_generate_password( 32, false, false );
	$uuid            = wp_generate_uuid4();

	/* — Pick a user_login to send (current admin, else first admin) — */
	$current_user = wp_get_current_user();
	if ( ! $current_user || 0 === $current_user->ID ) {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'user_login', 'ID' ),
			)
		);
		$current_user = $admins ? $admins[0] : (object) array( 'user_login' => 'admin' );
	}

	/* — Send the new creds to the SaaS, preserving the expected keys — */
	$payload = array(
		'appApiKey'     => $app_setup['api_key'],
		'siteUrl'       => get_site_url(),
		'wpUserLogin'   => $current_user->user_login,
		'wpAppPassword' => $plugin_password,
		'wpAppPassUuid' => $uuid,
	);

	$response = wp_remote_post(
		'https://app.nuclearengagement.com/api/store-wp-creds',
		array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		// Log but still store locally – user can re‑try from Setup page if needed.
		error_log( '[Nuclear Engagement] App‑password migration failed to contact SaaS: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ) );
	}

	/* — Persist new password & UUID — */
	$app_setup['plugin_password']  = $plugin_password;
	$app_setup['wp_app_pass_uuid'] = $uuid;
	update_option( 'nuclear_engagement_setup', $app_setup );

	/* — Optionally remove the old WP Application Password entry — */
	if ( class_exists( 'WP_Application_Passwords' ) && ! empty( $app_setup['wp_app_pass_uuid'] ) ) {
		$users = get_users(
			array(
				'fields' => array( 'ID' ),
				'number' => 50, // small – we just need to find and delete once
			)
		);
		foreach ( $users as $u ) {
			$apps = \WP_Application_Passwords::get_user_application_passwords( $u->ID );
			foreach ( $apps as $ap ) {
				if ( isset( $ap['uuid'] ) && $ap['uuid'] === $app_setup['wp_app_pass_uuid'] ) {
					\WP_Application_Passwords::delete_application_password( $u->ID, $ap['item_id'] );
					break 2; // done
				}
			}
		}
	}

	update_option( 'nuclen_app_pass_migration_done', true );
}
add_action( 'admin_init', 'nuclen_migrate_app_password', 9 ); // run early in admin_init

/* ──────────────────────────────────────────────────────────
 * ❷ Old meta‑key migration (unchanged)
 * ────────────────────────────────────────────────────────── */
/**
 * Updates all post‑meta keys from the legacy “ne‑*” names to “nuclen‑*”.
 */
function nuclen_update_migrate_post_meta() {

	if ( get_option( 'nuclen_meta_migration_done' ) ) {
		return;
	}

	global $wpdb;

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
			'nuclen-summary-data',
			'ne-summary-data'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
			'nuclen-quiz-data',
			'ne-quiz-data'
		)
	);

	update_option( 'nuclen_meta_migration_done', true );
}
add_action( 'admin_init', 'nuclen_update_migrate_post_meta', 20 );

/* ──────────────────────────────────────────────────────────
 * ❸ Run the plugin
 * ────────────────────────────────────────────────────────── */
function nuclear_engagement_run_plugin() {
	$plugin = new NuclearEngagement\Plugin();
	$plugin->nuclen_run();
}
nuclear_engagement_run_plugin();
