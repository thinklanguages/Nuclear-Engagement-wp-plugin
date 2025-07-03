<?php
// nuclear-engagement/bootstrap.php
declare(strict_types=1);

use NuclearEngagement\Core\Bootloader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


require_once __DIR__ . '/inc/Core/Bootloader.php';
require_once __DIR__ . '/inc/Core/PluginBootstrap.php';
require_once __DIR__ . '/inc/Core/CompatibilityAutoloader.php';

// Register compatibility autoloader to handle src/ vs nuclear-engagement/inc/ duplication
\NuclearEngagement\Core\CompatibilityAutoloader::register();

try {
	// IMPORTANT: Using the old Bootloader system instead of PluginBootstrap
	// The new PluginBootstrap system has a critical timing issue where admin services
	// are loaded on 'admin_init' hook, which fires AFTER 'admin_menu' hook.
	// This causes the admin menu items to not appear in WordPress admin.
	// DO NOT switch to PluginBootstrap until this timing issue is resolved.
	Bootloader::init();
	
	// The new optimized bootstrap system is commented out due to timing issues
	// $bootstrap = \NuclearEngagement\Core\PluginBootstrap::getInstance();
	// $bootstrap->init();
} catch ( \Throwable $e ) {
	// Log the error for debugging
	error_log( 'Nuclear Engagement Bootstrap Error: ' . $e->getMessage() );
	
	// Show admin notice for debugging
	if ( is_admin() ) {
		add_action( 'admin_notices', function() use ( $e ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>Nuclear Engagement Error:</strong> ' . esc_html( $e->getMessage() );
			echo '</p></div>';
		});
	}
}
