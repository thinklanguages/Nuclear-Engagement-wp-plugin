<?php
/**
 * Plugin Name:       Nuclear Engagement
 * Plugin URI:        https://www.nuclearengagement.com
 * Description:       Bulk generate engaging content for your blog posts with AI in one click.
 * Version:           2.1.11
 * Author:            Stefano Lodola
 * Requires at least: 6.1
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       nuclear-engagement
 * Domain Path:       /
 *
 * @package    Nuclear_Engagement
 * @since      1.0.0
 */
declare(strict_types=1);

// Bail if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Minimum PHP version requirement.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	/**
	 * Display admin notice when PHP version is too low.
	 *
	 * This wrapper uses only PHP 5.6 compatible syntax so it can run on
	 * older installations without causing a fatal error.
	 */
	function nuclear_engagement_php_notice() {
		echo '<div class="error"><p>' .
			esc_html__(
				'Nuclear Engagement requires PHP 7.4 or higher.',
				'nuclear-engagement'
			) .
			'</p></div>';
	}
	add_action( 'admin_notices', 'nuclear_engagement_php_notice' );
	return;
}

// PHP version is sufficient; continue loading typed code.

define( 'NUCLEN_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/bootstrap.php';
