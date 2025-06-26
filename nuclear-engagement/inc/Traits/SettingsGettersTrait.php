<?php
/**
 * File: includes/Traits/SettingsGettersTrait.php
 *
 * Provides getter helpers for SettingsRepository.
 *
 * @package NuclearEngagement
 * @subpackage Traits
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement\Traits;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait SettingsGettersTrait {
    /**
     * Get all settings with defaults merged in.
     *
     * @since 1.0.0
     *
     * @return array The complete settings array with defaults merged in.
     */
    public function all(): array {
        $cached = $this->cache->get();
        if ( null !== $cached ) {
            return $cached;
        }

        // Not in cache, fetch from database.
        $saved    = get_option( self::OPTION, array() );
        $settings = wp_parse_args(
            is_array( $saved ) ? $saved : array(),
            $this->defaults
        );

        // Store in cache.
        $this->cache->set( $settings );

        return $settings;
    }

    /**
     * Get all settings from database (bypasses cache).
     *
     * @since 1.0.0
     *
     * @return array The complete settings array.
     */
    public function get_all(): array {
        return $this->all();
    }

    /**
     * Get a specific setting by key.
     *
     * @since 1.0.0
     *
     * @param string $key      The setting key to retrieve.
     * @param mixed  $fallback Optional. Fallback value if the setting doesn't exist.
     * @return mixed The setting value, or fallback if not found.
     */
    public function get( string $key, $fallback = null ) {
        $all   = $this->all();
        $value = $all[ $key ] ?? $fallback;

        // Allow filtering of individual settings.
        if ( 1 === func_num_args() ) {
            $value = apply_filters( "nuclen_setting_{$key}", $value, $key );
        }

        return $value;
    }

    /**
     * Check if a setting exists.
     *
     * @since 1.0.0
     *
     * @param string $key The setting key to check.
     * @return bool True if the setting exists, false otherwise.
     */
    public function has( string $key ): bool {
        $all = $this->all();
        return array_key_exists( $key, $all );
    }
}
