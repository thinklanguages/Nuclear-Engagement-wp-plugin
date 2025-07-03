<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://www.nuclearengagement.com
 * @since      0.3.1
 *
 * @package    Nuclear_Engagement
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
				exit;
}

// Load the plugin autoloader when available.
$autoload = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
}
if ( file_exists( $autoload ) ) {
	require_once $autoload;
} else {
	$utils   = __DIR__ . '/inc/Utils/Utils.php';
	$logging = __DIR__ . '/inc/Services/LoggingService.php';
	if ( file_exists( $utils ) ) {
		require_once $utils;
	}
	if ( file_exists( $logging ) ) {
		require_once $logging;
	}
}

// Get plugin settings.
$settings = get_option( 'nuclear_engagement_settings', array() );

$delete_settings  = ! empty( $settings['delete_settings_on_uninstall'] );
$delete_generated = ! empty( $settings['delete_generated_content_on_uninstall'] );
$delete_optins    = ! empty( $settings['delete_optin_data_on_uninstall'] );
$delete_log       = ! empty( $settings['delete_log_file_on_uninstall'] );
$delete_css       = ! empty( $settings['delete_custom_css_on_uninstall'] );

// Delete generated content from post meta if requested.
if ( $delete_generated ) {
								$meta_keys = array(
										'nuclen-quiz-data',
										\NuclearEngagement\Modules\Summary\Summary_Service::META_KEY,
										'nuclen_quiz_protected',
										\NuclearEngagement\Modules\Summary\Summary_Service::PROTECTED_KEY,
								);
								foreach ( $meta_keys as $mk ) {
												delete_post_meta_by_key( $mk );
								}
}

// Delete plugin settings if requested.
if ( $delete_settings ) {
	// Core plugin options
	delete_option( 'nuclear_engagement_settings' );
	delete_option( 'nuclear_engagement_setup' );
	delete_option( 'nuclen_custom_css_version' );
	
	// Additional plugin options that need cleanup
	delete_option( 'nuclen_error_tracking' );
	delete_option( 'nuclen_rate_limits' );
	delete_option( 'nuclen_error_analytics_report' );
	delete_option( 'nuclen_error_analytics_reports' );
	delete_option( 'nuclen_security_events' );
	delete_option( 'nuclen_meta_migration_error' );
	delete_option( 'nuclen_meta_migration_done' );
	delete_option( 'nuclen_active_generations' );
	delete_option( 'nuclen_posts_query_version' );
	
	// Clean up any transients
	delete_transient( 'nuclen_plugin_activation_redirect' );
	
	// Clean up any remaining options with nuclen prefix
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'nuclen_%',
		'_transient_nuclen_%'
	));
}

// Remove log file if requested.
if ( $delete_log ) {
		$info = \NuclearEngagement\Services\LoggingService::get_log_file_info();
	if ( file_exists( $info['path'] ) ) {
			wp_delete_file( $info['path'] );
	}
}

// Remove custom theme file if requested.
if ( $delete_css ) {
								$info = \NuclearEngagement\Utils\Utils::nuclen_get_custom_css_info();
	if ( ! empty( $info ) && file_exists( $info['path'] ) ) {
					wp_delete_file( $info['path'] );
	}
				delete_option( 'nuclen_custom_css_version' );
}

// Drop opt-in table only when the user opts to delete settings or generated
// content. This avoids data loss unless a full cleanup was requested.
if ( $delete_settings || $delete_generated ) {
	global $wpdb;
	
	// Security: Validate and safely drop optin table
	$table_suffix = 'nuclen_optins';
	$table_name = $wpdb->prefix . $table_suffix;
	
	// Validate table name format to prevent injection
	if ( preg_match( '/^[a-zA-Z0-9_]+$/', $table_suffix ) ) {
		// Safely escape table name using WordPress standards
		$escaped_table = esc_sql( $table_name );
		$wpdb->query( "DROP TABLE IF EXISTS `{$escaped_table}`" );
		
		// Also clean up any related options
		delete_option( 'nuclen_optins_version' );
		delete_option( 'nuclen_optins_db_version' );
	} else {
		// Log security event for invalid table name attempt
		error_log( 'Nuclear Engagement: Invalid table name attempted in uninstall: ' . $table_name );
	}
}
