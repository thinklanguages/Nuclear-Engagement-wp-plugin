<?php
// Activator.php

namespace NuclearEngagement;

use NuclearEngagement\SettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

class Activator {
    /**
     * Plugin activation hook
     * 
     * @param SettingsRepository|null $settings Optional settings repository instance
     */
    public static function nuclen_activate(?SettingsRepository $settings = null) {
        // Set transient for activation redirect
        set_transient('nuclen_plugin_activation_redirect', true, 30);

        // Get default settings
        $default_settings = Defaults::nuclen_get_default_settings();
        
        // Initialize or update settings repository with defaults
        $settings = $settings ?: \NuclearEngagement\Container::getInstance()->get('settings');
        
        // Only set the setup option if it doesn't already exist
        if (false === get_option('nuclear_engagement_setup')) {
            update_option('nuclear_engagement_setup', $default_settings);
        }

        // Ensure opt-in table exists on activation
        OptinData::maybe_create_table();
    }
}
