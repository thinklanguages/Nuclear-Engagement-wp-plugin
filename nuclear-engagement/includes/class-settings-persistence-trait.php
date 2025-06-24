<?php
/**
 * File: includes/class-settings-persistence-trait.php
 *
 * Handles saving logic for SettingsRepository.
 *
 * @package NuclearEngagement
 * @subpackage Traits
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement;

use NuclearEngagement\SettingsSanitizer;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait SettingsPersistenceTrait {
    /**
     * Save pending settings to database.
     *
     * @since 1.0.0
     *
     * @return bool True if settings were saved, false otherwise.
     */
    public function save(): bool {
        if ( empty( $this->pending ) ) {
            return false;
        }

        $current   = $this->all();
        $sanitized = SettingsSanitizer::sanitize_settings( $this->pending );
        $merged    = wp_parse_args( $sanitized, $current );

        // Clear pending settings.
        $this->pending = array();

        // Invalidate cache before save.
        $this->invalidate_cache();

        // Only update if settings have changed.
        if ( $merged !== $current ) {
            $autoload = $this->should_autoload( $merged );
            $result   = update_option( self::OPTION, $merged, $autoload ? 'yes' : 'no' );

            // Also update legacy option for backward compatibility.
            if ( $result && false !== get_option( 'nuclear_engagement_setup' ) ) {
                $legacy_data = array(
                    'api_key'             => $merged['api_key'] ?? '',
                    'connected'           => $merged['connected'] ?? false,
                    'wp_app_pass_created' => $merged['wp_app_pass_created'] ?? false,
                    'wp_app_pass_uuid'    => $merged['wp_app_pass_uuid'] ?? '',
                    'plugin_password'     => $merged['plugin_password'] ?? '',
                );
                update_option( 'nuclear_engagement_setup', $legacy_data );
            }

            return $result;
        }

        return false;
    }

    /**
     * Determine if settings should be autoloaded.
     *
     * @since 1.0.0
     *
     * @param array $settings The settings array to check.
     * @return bool True if settings should be autoloaded, false otherwise.
     */
    private function should_autoload( array $settings ): bool {
        $size = strlen( wp_json_encode( $settings ) );
        return $size <= self::MAX_AUTOLOAD_SIZE;
    }
}
