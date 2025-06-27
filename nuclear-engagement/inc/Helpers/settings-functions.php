<?php
/**
 * Global wrapper functions for plugin settings.
 *
 * @package NuclearEngagement\Helpers
 */

declare(strict_types=1);

namespace {

use NuclearEngagement\Helpers\SettingsHelper;

if ( ! function_exists( 'nuclen_settings' ) ) {
	function nuclen_settings( ?string $key = null, $default = null ) {
		return SettingsHelper::get( $key, $default );
	}
}

if ( ! function_exists( 'nuclen_settings_bool' ) ) {
	function nuclen_settings_bool( string $key, bool $default = false ): bool {
		return SettingsHelper::get_bool( $key, $default );
	}
}

if ( ! function_exists( 'nuclen_settings_int' ) ) {
	function nuclen_settings_int( string $key, int $default = 0 ): int {
		return SettingsHelper::get_int( $key, $default );
	}
}

if ( ! function_exists( 'nuclen_settings_string' ) ) {
	function nuclen_settings_string( string $key, string $default = '' ): string {
		return SettingsHelper::get_string( $key, $default );
	}
}

if ( ! function_exists( 'nuclen_settings_array' ) ) {
	function nuclen_settings_array( string $key, array $default = array() ): array {
		return SettingsHelper::get_array( $key, $default );
	}
}

}
