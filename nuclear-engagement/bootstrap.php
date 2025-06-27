<?php
// nuclear-engagement/bootstrap.php
declare(strict_types=1);

use NuclearEngagement\Core\Bootloader;
use NuclearEngagement\Core\MetaRegistration;
use NuclearEngagement\Core\Plugin;
use NuclearEngagement\Core\InventoryCache;
use NuclearEngagement\Services\PostsQueryService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/Core/Bootloader.php';

Bootloader::init();

/**
 * Load the plugin text domain for translations.
 *
 * This enables localization by loading translation files from the
 * languages directory.
 *
 * @return void
 */
function nuclear_engagement_load_textdomain() {
	load_plugin_textdomain(
		'nuclear-engagement',
		false,
		dirname( plugin_basename( NUCLEN_PLUGIN_FILE ) ) . '/languages/'
	);
}

/**
 * Redirect to the setup screen on plugin activation.
 *
 * Checks a transient set during activation and, if present, redirects the
 * administrator to the plugin setup page.
 *
 * @return void
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

/**
 * Initialize and execute the core plugin logic.
 *
 * Sets up meta registration and runs the main plugin class.
 *
 * @return void
 */
function nuclear_engagement_run_plugin() {
	MetaRegistration::init();
	$plugin = new Plugin();
	$plugin->nuclen_run();
}

/**
 * Register services and bootstrap the plugin.
 *
 * Sets up caching, query services and other runtime hooks, then
 * runs the plugin.
 *
 * @return void
 */
function nuclear_engagement_init() {
	try {
		InventoryCache::register_hooks();
		PostsQueryService::register_hooks();
		\NuclearEngagement\Services\PostDataFetcher::register_hooks();
	} catch ( \Throwable $e ) {
		\NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: Cache system initialization failed - ' . $e->getMessage() );
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="error"><p>Nuclear Engagement: Cache system initialization failed.</p></div>';
			}
		);
	}

	nuclear_engagement_run_plugin();
}
