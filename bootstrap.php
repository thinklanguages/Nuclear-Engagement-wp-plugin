<?php
// nuclear-engagement/bootstrap.php
declare(strict_types=1);

use NuclearEngagement\Core\Bootloader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


require_once __DIR__ . '/inc/Core/Bootloader.php';

try {
	Bootloader::init();
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
