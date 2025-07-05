<?php
/**
 * AdminHeaderTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Traits
 */

declare(strict_types=1);

namespace NuclearEngagement\Admin\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AdminHeaderTrait
 *
 * Provides a consistent header for all admin pages.
 *
 * @package NuclearEngagement\Admin\Traits
 */
trait AdminHeaderTrait {
	/**
	 * Display the admin page header.
	 *
	 * @return void
	 */
	protected function display_admin_header(): void {
		$image_url = NUCLEN_PLUGIN_URL . 'assets/images/nuclear-engagement-logo.webp';

		// Always load the template, even if URL validation fails.
		load_template(
			NUCLEN_PLUGIN_DIR . 'templates/admin/page-header.php',
			true,
			array( 'image_url' => $image_url )
		);
	}
}
