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
        $settings_repo = $this->nuclen_get_settings_repository();
        $defaults = \NuclearEngagement\Defaults::nuclen_get_default_settings();

        // Get all settings from the repository
        $settings = array();
        foreach ($defaults as $key => $default_value) {
            $settings[$key] = $settings_repo->get($key, $default_value);
        }

        // Sanitize the settings
        $settings = $this->nuclen_sanitize_settings($settings);

        return array( $settings, $defaults );
    }
}
