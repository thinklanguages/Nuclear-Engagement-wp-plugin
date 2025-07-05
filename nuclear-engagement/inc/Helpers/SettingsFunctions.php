<?php
/**
 * Class providing static wrappers for retrieving settings values.
 *
 * @package NuclearEngagement\Helpers
 */
declare(strict_types=1);

namespace NuclearEngagement\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static wrappers around {@see SettingsHelper}.
 */
final class SettingsFunctions {

	/**
	 * Get any setting or all settings if no key provided.
	 *
	 * @param string|null $key     Setting key or null for all.
	 * @param mixed       $default Default value.
	 * @return mixed
	 */
	public static function get( ?string $key = null, $default = null ) {
		return SettingsHelper::get( $key, $default );
	}

	/**
	 * Get a boolean setting.
	 */
	public static function get_bool( string $key, bool $default = false ): bool {
		return SettingsHelper::get_bool( $key, $default );
	}

	/**
	 * Get an integer setting.
	 */
	public static function get_int( string $key, int $default = 0 ): int {
		return SettingsHelper::get_int( $key, $default );
	}

	/**
	 * Get a string setting.
	 */
	public static function get_string( string $key, string $default = '' ): string {
		return SettingsHelper::get_string( $key, $default );
	}

	/**
	 * Get an array setting.
	 */
	public static function get_array( string $key, array $default = array() ): array {
		return SettingsHelper::get_array( $key, $default );
	}
}
