<?php
/**
 * SettingsPageSaveTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Traits
 */

declare(strict_types=1);
/**
 * File: admin/Traits/SettingsPageSaveTrait.php
 *
 * Handles saving of settings from the admin form.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SettingsPageSaveTrait {

		use SettingsCollectTrait;
		use SettingsPersistTrait;

	/**
	 * Process and save submitted settings.
	 *
	 * @param array $settings      Current settings (passed by reference).
	 * @param array $defaults      Default settings.
	 * @param array &$new_settings Output: new sanitized settings.
	 * @return bool                True if a save occurred.
	 */
	protected function nuclen_handle_save_settings( array &$settings, array $defaults, array &$new_settings ): bool {

			/* ───────── Bail if not a form submission ───────── */
		if (
					! isset( $_POST['nuclen_save_settings'] ) ||
					! check_admin_referer( 'nuclen_settings_nonce', 'nuclen_settings_nonce_field', false )
			) {
				return false;
		}

			$raw          = $this->nuclen_collect_input();
			$new_settings = $this->nuclen_sanitize_and_defaults( $raw, $defaults );
			$settings     = $this->nuclen_persist_settings( $new_settings );

			// Custom CSS generation is handled in SettingsPageTrait after save.

			$this->nuclen_output_save_notice();

			return true;
	}
}
