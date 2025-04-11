<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Plugin Name:       Nuclear Engagement
 * Plugin URI:        https://www.nuclearengagement.com
 * Description:       Bulk generate engaging content for your blog posts with AI in one click.
 * Version:           0.6.1
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
define( 'NUCLEN_PLUGIN_VERSION', '0.6.1' );
define( 'NUCLEN_ASSET_VERSION', '250410-1' );

/**
 * Simple autoloader for our plugin classes.
 * PSR-4 style: Namespace `NuclearEngagement\` maps to `includes/`
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'NuclearEngagement\\';
		$base_dir = __DIR__;

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				return;
		}

		// Get the relative class name
		$relative_class = substr( $class, $len );

		// Determine subnamespace directory
		$subpath = '';
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

/**
 * Activate the plugin.
 */
function nuclear_engagement_activate_plugin() {
	NuclearEngagement\Activator::nuclen_activate();
}
register_activation_hook( __FILE__, 'nuclear_engagement_activate_plugin' );

/**
 * Deactivate the plugin.
 */
function nuclear_engagement_deactivate_plugin() {
	NuclearEngagement\Deactivator::nuclen_deactivate();
}
register_deactivation_hook( __FILE__, 'nuclear_engagement_deactivate_plugin' );

function nuclear_engagement_redirect_on_activation() {
	if ( get_transient( 'nuclen_plugin_activation_redirect' ) ) {
		delete_transient( 'nuclen_plugin_activation_redirect' );
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			wp_redirect( admin_url( 'admin.php?page=nuclear-engagement-setup' ) );
			exit;
		}
	}
}

add_action( 'admin_init', 'nuclear_engagement_redirect_on_activation' );




/**
 * Migrate post meta keys on plugin update.
 *
 * Updates all post meta entries:
 * - Changes 'ne-summary-data' to 'nuclen-summary-data'
 * - Changes 'ne-quiz-data' to 'nuclen-quiz-data'
 *
 * It runs only once by checking an option flag.
 */
function nuclen_update_migrate_post_meta() {
	// Check if migration has already been performed.
	if ( get_option( 'nuclen_meta_migration_done' ) ) {
		return;
	}

	global $wpdb;

	// Update 'ne-summary-data' meta key to 'nuclen-summary-data'
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
			'nuclen-summary-data',
			'ne-summary-data'
		)
	);

	// Update 'ne-quiz-data' meta key to 'nuclen-quiz-data'
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
			'nuclen-quiz-data',
			'ne-quiz-data'
		)
	);

	// Set flag so this migration won't run again.
	update_option( 'nuclen_meta_migration_done', true );
}
// Hook into an admin action to run the migration.
add_action( 'admin_init', 'nuclen_update_migrate_post_meta' );



/**
 * Run the plugin.
 */
function nuclear_engagement_run_plugin() {
	$plugin = new NuclearEngagement\Plugin();
	$plugin->nuclen_run();
}

nuclear_engagement_run_plugin();
