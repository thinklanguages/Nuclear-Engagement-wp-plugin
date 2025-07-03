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
	// CRITICAL: DO NOT CHANGE THIS SECTION WITHOUT EXTENSIVE TESTING!
	// The PluginBootstrap system is now working correctly after fixing these issues:
	// 
	// 1. Constants Definition Order:
	//    - NUCLEN_PLUGIN_FILE must be defined in nuclear-engagement.php
	//    - defineEssentialConstants() MUST be called first in init()
	//    - This defines NUCLEN_PLUGIN_DIR which is used by the Autoloader
	// 
	// 2. Autoloader Path:
	//    - The Autoloader.php is in inc/Core/ (same directory as PluginBootstrap.php)
	//    - MUST use __DIR__ . '/Autoloader.php' (NOT dirname(__DIR__))
	// 
	// 3. Admin Menu Registration:
	//    - Admin services MUST be loaded immediately when is_admin() is true
	//    - This happens BEFORE WordPress fires the 'admin_menu' hook
	//    - Otherwise, admin menu items will NOT appear
	// 
	// If you need to modify the bootstrap process, ensure ALL three conditions above are met!
	
	$bootstrap = \NuclearEngagement\Core\PluginBootstrap::getInstance();
	$bootstrap->init();
	
	// The old Bootloader system is kept as a fallback option
	// Uncomment ONLY if PluginBootstrap fails and you need a quick fix:
	// Bootloader::init();
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
