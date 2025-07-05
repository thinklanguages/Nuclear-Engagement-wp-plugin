<?php
/**
 * Typed settings access helpers.
 *
 * @package NuclearEngagement\Helpers
 */

declare(strict_types=1);

namespace NuclearEngagement\Helpers;

use NuclearEngagement\Core\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed helper methods for retrieving settings values.
 */
final class SettingsHelper {

	/** Singleton repository instance */
	private static ?SettingsRepository $repo = null;

	/**
	 * Retrieve the settings repository instance.
	 */
	private static function repo(): SettingsRepository {
		if ( null === self::$repo ) {
			self::$repo = SettingsRepository::get_instance();
		}
		return self::$repo;
	}

	/**
	 * Get any setting or all settings if no key provided.
	 *
	 * @param string|null $key     Setting key or null for all.
	 * @param mixed       $default Default value.
	 * @return mixed
	 */
	public static function get( ?string $key = null, $default = null ) {
		$repository = self::repo();
		if ( $key === null ) {
			return $repository->all();
		}
		return $repository->get( $key, $default );
	}

	/**
	 * Get a boolean setting.
	 */
	public static function get_bool( string $key, bool $default = false ): bool {
		return self::repo()->get_bool( $key, $default );
	}

	/**
	 * Get an integer setting.
	 */
	public static function get_int( string $key, int $default = 0 ): int {
		return self::repo()->get_int( $key, $default );
	}

	/**
	 * Get a string setting.
	 */
	public static function get_string( string $key, string $default = '' ): string {
		return self::repo()->get_string( $key, $default );
	}

	/**
	 * Get an array setting.
	 */
	public static function get_array( string $key, array $default = array() ): array {
		return self::repo()->get_array( $key, $default );
	}
}
