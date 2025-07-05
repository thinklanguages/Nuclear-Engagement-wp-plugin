<?php
/**
 * SettingsPageTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin
 */

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

use NuclearEngagement\Admin\Traits\SettingsPageLoadTrait;
use NuclearEngagement\Admin\Traits\SettingsPageSaveTrait;
use NuclearEngagement\Admin\Traits\SettingsCollectTrait;
use NuclearEngagement\Admin\Traits\SettingsPersistTrait;
use NuclearEngagement\Admin\Traits\SettingsPageCustomCSSTrait;

/* helper traits are autoloaded */

/**
 * Main trait mixed into the Admin class to drive the Settings screen.
 */
trait SettingsPageTrait {

	use SettingsPageLoadTrait;
	use SettingsPageSaveTrait;
	use SettingsCollectTrait;
	use SettingsPersistTrait;
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

		/*
		3) WRITE CUSTOM CSS (when needed) ------------------------ */
		// Generate custom CSS whenever custom theme is involved.
		if ( $saved && isset( $new_settings['theme'] ) && $new_settings['theme'] === 'custom' ) {
			// Always regenerate when saving with custom theme.
			\NuclearEngagement\Services\LoggingService::log( 'Generating CSS with new_settings: theme=' . $new_settings['theme'] . ', font_color=' . ( $new_settings['font_color'] ?? 'not set' ) );
			$this->nuclen_write_custom_css( $new_settings );
		} elseif ( $saved && isset( $settings['theme'] ) && $settings['theme'] === 'custom' ) {
			// Also regenerate if theme was custom before saving (to catch customization changes).
			\NuclearEngagement\Services\LoggingService::log( 'Regenerating CSS for existing custom theme settings' );
			$this->nuclen_write_custom_css( $settings );
		} elseif ( ! $saved && isset( $settings['theme'] ) && $settings['theme'] === 'custom' ) {
			// Check if custom CSS file exists when custom theme is active.
			$css_info = \NuclearEngagement\Utils\Utils::nuclen_get_custom_css_info();
			if ( empty( $css_info ) || ! file_exists( $css_info['path'] ) ) {
				$this->nuclen_write_custom_css( $settings );
			}
		}

				/* 4) RENDER THE ADMIN FORM -------------------------------- */
				include NUCLEN_PLUGIN_DIR . 'templates/admin/nuclen-admin-settings.php';
	}
}
