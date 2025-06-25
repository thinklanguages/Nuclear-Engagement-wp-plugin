<?php
declare(strict_types=1);
/**
 * Global helper functions for Nuclear Engagement plugin.
 *
 * @package NuclearEngagement
 */

use NuclearEngagement\SettingsRepository;

if ( ! function_exists( 'nuclen_settings' ) ) {
    /**
     * Get or set Nuclear Engagement settings.
     *
     * @param string|null $key   Optional. The setting key to retrieve.
     * @param mixed       $default Optional. Default value if setting doesn't exist.
     * @return mixed The setting value, or all settings if no key provided.
     */
    function nuclen_settings( ?string $key = null, $default = null ) {
        static $repo = null;

        if ( $repo === null ) {
            $repo = SettingsRepository::get_instance();
        }

        if ( $key === null ) {
            return $repo->all();
        }

        return $repo->get( $key, $default );
    }
}

if ( ! function_exists( 'nuclen_settings_bool' ) ) {
    /**
     * Get a boolean setting.
     *
     * @param string $key    The setting key.
     * @param bool   $default Default value if not set.
     * @return bool
     */
    function nuclen_settings_bool( string $key, bool $default = false ): bool {
        static $repo = null;

        if ( $repo === null ) {
            $repo = SettingsRepository::get_instance();
        }

        return $repo->get_bool( $key, $default );
    }
}

if ( ! function_exists( 'nuclen_settings_int' ) ) {
    /**
     * Get an integer setting.
     *
     * @param string $key    The setting key.
     * @param int    $default Default value if not set.
     * @return int
     */
    function nuclen_settings_int( string $key, int $default = 0 ): int {
        static $repo = null;

        if ( $repo === null ) {
            $repo = SettingsRepository::get_instance();
        }

        return $repo->get_int( $key, $default );
    }
}

if ( ! function_exists( 'nuclen_settings_string' ) ) {
    /**
     * Get a string setting.
     *
     * @param string $key    The setting key.
     * @param string $default Default value if not set.
     * @return string
     */
    function nuclen_settings_string( string $key, string $default = '' ): string {
        static $repo = null;

        if ( $repo === null ) {
            $repo = SettingsRepository::get_instance();
        }

        return $repo->get_string( $key, $default );
    }
}

if ( ! function_exists( 'nuclen_settings_array' ) ) {
    /**
     * Get an array setting.
     *
     * @param string $key    The setting key.
     * @param array  $default Default value if not set.
     * @return array
     */
    function nuclen_settings_array( string $key, array $default = array() ): array {
        static $repo = null;

        if ( $repo === null ) {
            $repo = SettingsRepository::get_instance();
        }

        return $repo->get_array( $key, $default );
    }
}
