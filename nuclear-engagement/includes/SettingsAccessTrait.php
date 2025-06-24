<?php
/**
 * File: includes/SettingsAccessTrait.php
 *
 * Provides typed getter and setter helpers for SettingsRepository.
 *
 * @package    NuclearEngagement
 * @subpackage Traits
 * @since      1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait SettingsAccessTrait
 *
 * Provides typed getter and setter methods for settings.
 *
 * @since 1.0.0
 */
trait SettingsAccessTrait {

	/**
	 * Get a string setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param string $default Default value if setting doesn't exist. Default empty string.
	 * @return string Setting value.
	 */
	public function get_string( string $key, string $default = '' ): string {
		$value = $this->get( $key, $default );
		return is_string( $value ) ? $value : (string) $value;
	}

	/**
	 * Get an integer setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param int    $default Default value if setting doesn't exist. Default 0.
	 * @return int Setting value.
	 */
	public function get_int( string $key, int $default = 0 ): int {
		$value = $this->get( $key, $default );
		return is_numeric( $value ) ? (int) $value : $default;
	}

	/**
	 * Get a boolean setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param bool   $default Default value if setting doesn't exist. Default false.
	 * @return bool Setting value.
	 */
	public function get_bool( string $key, bool $default = false ): bool {
		return (bool) $this->get( $key, $default );
	}

	/**
	 * Get an array setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param array  $default Default value if setting doesn't exist. Default empty array.
	 * @return array Setting value.
	 */
	public function get_array( string $key, array $default = array() ): array {
		$value = $this->get( $key, $default );
		return is_array( $value ) ? $value : $default;
	}

	/**
	 * Set a setting value to be saved later.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$this->pending[ $key ] = $value;
		return $this;
	}

	/**
	 * Set a string setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Setting key.
	 * @param string $value Setting value.
	 * @return self
	 */
	public function set_string( string $key, string $value ): self {
		return $this->set( $key, sanitize_text_field( $value ) );
	}

	/**
	 * Set an integer setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Setting key.
	 * @param int    $value Setting value.
	 * @return self
	 */
	public function set_int( string $key, int $value ): self {
		return $this->set( $key, (int) $value );
	}

	/**
	 * Set a boolean setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Setting key.
	 * @param bool   $value Setting value.
	 * @return self
	 */
	public function set_bool( string $key, bool $value ): self {
		return $this->set( $key, (bool) $value );
	}

	/**
	 * Set an array setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Setting key.
	 * @param array  $value Setting value.
	 * @return self
	 */
	public function set_array( string $key, array $value ): self {
		return $this->set( $key, SettingsSanitizer::sanitize_setting( $key, $value ) );
	}
}
