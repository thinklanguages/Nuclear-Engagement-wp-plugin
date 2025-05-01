<?php
/**
 * File: admin/trait-settings-page-load.php
 *
 * Loads and merges saved settings with defaults.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SettingsPageLoadTrait {

	/**
	 * Retrieve and sanitize the current settings, merging with defaults.
	 *
	 * @return array [ $settings , $defaults ]
	 */
	protected function nuclen_get_current_settings(): array {
		$raw_settings = get_option( 'nuclear_engagement_settings', array() );
		$defaults     = \NuclearEngagement\Defaults::nuclen_get_default_settings();

		// rely on the existing sanitizer
		$settings = wp_parse_args(
			$this->nuclen_sanitize_settings( $raw_settings ),
			$defaults
		);

		return array( $settings, $defaults );
	}
}
