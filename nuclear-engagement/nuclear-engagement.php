<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name:       Nuclear Engagement
 * Plugin URI:        https://www.nuclearengagement.com
 * Description:       Manually create quizzes and summaries for your blog posts. (Pro add-on provides AI.)
 * Version:           0.6.1
 * Author:            Stefano Lodola
 * Requires at least: 5.6
 * Tested up to:      6.8
 * Requires PHP:      7.3
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       nuclear-engagement
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'NUCLEN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NUCLEN_PLUGIN_VERSION', '0.6.1' );
define( 'NUCLEN_ASSET_VERSION', '250414-1' );

/**
 * Simple autoloader for our plugin classes.
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
		$subpath        = '';

		if ( strpos( $relative_class, 'Admin\\' ) === 0 ) {
			$subpath        = '/admin/';
			$relative_class = substr( $relative_class, strlen( 'Admin\\' ) );
		} elseif ( strpos( $relative_class, 'Front\\' ) === 0 ) {
			$subpath        = '/front/';
			$relative_class = substr( $relative_class, strlen( 'Front\\' ) );
		} else {
			$subpath = '/includes/';
		}

		$file = $base_dir . $subpath . str_replace( '\\', '/', $relative_class ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Activation/Deactivation
function nuclear_engagement_activate_plugin() {
	NuclearEngagement\Activator::nuclen_activate();
}
register_activation_hook( __FILE__, 'nuclear_engagement_activate_plugin' );

function nuclear_engagement_deactivate_plugin() {
	NuclearEngagement\Deactivator::nuclen_deactivate();
}
register_deactivation_hook( __FILE__, 'nuclear_engagement_deactivate_plugin' );

/**
 * On plugin activation, optionally redirect to the plugin dashboard
 */
function nuclear_engagement_redirect_on_activation() {
	if ( get_transient( 'nuclen_plugin_activation_redirect' ) ) {
		delete_transient( 'nuclen_plugin_activation_redirect' );
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			wp_redirect( admin_url( 'admin.php?page=nuclear-engagement' ) );
			exit;
		}
	}
}
add_action( 'admin_init', 'nuclear_engagement_redirect_on_activation' );

/**
 * Migrate old meta keys (ne-quiz-data, ne-summary-data) to new keys if needed.
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
add_action( 'admin_init', 'nuclen_update_migrate_post_meta' );

// Finally run the plugin
function nuclear_engagement_run_plugin() {
	$plugin = new NuclearEngagement\Plugin();
	$plugin->nuclen_run();
}
nuclear_engagement_run_plugin();
