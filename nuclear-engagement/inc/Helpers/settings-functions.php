<?php
/**
 * Global wrapper functions for plugin settings.
 *
 * @package NuclearEngagement\Helpers
 */

declare(strict_types=1);

namespace {

use NuclearEngagement\Helpers\SettingsFunctions;

if ( ! function_exists( 'nuclen_settings' ) ) {
       /**
        * @deprecated 1.2 Use SettingsFunctions::get() instead.
        */
       function nuclen_settings( ?string $key = null, $default = null ) {
               return SettingsFunctions::get( $key, $default );
       }
}

if ( ! function_exists( 'nuclen_settings_bool' ) ) {
       /**
        * @deprecated 1.2 Use SettingsFunctions::get_bool() instead.
        */
       function nuclen_settings_bool( string $key, bool $default = false ): bool {
               return SettingsFunctions::get_bool( $key, $default );
       }
}

if ( ! function_exists( 'nuclen_settings_int' ) ) {
       /**
        * @deprecated 1.2 Use SettingsFunctions::get_int() instead.
        */
       function nuclen_settings_int( string $key, int $default = 0 ): int {
               return SettingsFunctions::get_int( $key, $default );
       }
}

if ( ! function_exists( 'nuclen_settings_string' ) ) {
       /**
        * @deprecated 1.2 Use SettingsFunctions::get_string() instead.
        */
       function nuclen_settings_string( string $key, string $default = '' ): string {
               return SettingsFunctions::get_string( $key, $default );
       }
}

if ( ! function_exists( 'nuclen_settings_array' ) ) {
       /**
        * @deprecated 1.2 Use SettingsFunctions::get_array() instead.
        */
       function nuclen_settings_array( string $key, array $default = array() ): array {
               return SettingsFunctions::get_array( $key, $default );
       }
}

}
