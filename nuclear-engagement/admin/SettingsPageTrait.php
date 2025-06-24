<?php
declare(strict_types=1);
/**
 * File: admin/SettingsPageTrait.php
 *
 * Orchestrates the settings page: loads data, processes saves,
 * writes custom CSS when needed, and renders the tabbed UI.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* helper traits are autoloaded */

/**
 * Main trait mixed into the Admin class to drive the Settings screen.
 */
trait SettingsPageTrait {

	use SettingsPageLoadTrait;
	use SettingsPageSaveTrait;
	use SettingsPageCustomCSSTrait;

	/**
	 * Render the plugin Settings page and handle form submission.
	 */
	public function nuclen_display_settings_page(): void {

		/* 1) LOAD CURRENT SETTINGS --------------------------------- */
		list( $settings, $defaults ) = $this->nuclen_get_current_settings();

		/* 2) HANDLE SAVE (if submitted) ---------------------------- */
		$new_settings = array();
		$saved        = $this->nuclen_handle_save_settings( $settings, $defaults, $new_settings );

		/* 3) WRITE CUSTOM CSS (only right after a save) ------------ */
		if ( $saved && isset( $new_settings['theme'] ) && $new_settings['theme'] === 'custom' ) {
			$this->nuclen_write_custom_css( $new_settings );
		}

		/* 4) RENDER THE ADMIN FORM -------------------------------- */
		include plugin_dir_path( __FILE__ ) . 'partials/nuclen-admin-settings.php';
	}
}
