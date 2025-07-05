<?php
/**
 * FormSanitizer.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Helpers
 */

declare(strict_types=1);
/**
 * File: inc/Helpers/FormSanitizer.php
 *
 * Centralized form input sanitization utility to eliminate code duplication
 * and ensure consistent input validation across the plugin.
 *
 * @package NuclearEngagement\Helpers
 */

namespace NuclearEngagement\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated Use InputValidator for new code. This class is kept for backward compatibility.
 */

class FormSanitizer {

	/**
	 * Sanitize text field from POST data with unslashing.
	 * Enhanced with basic validation.
	 *
	 * @param string $key The POST key to sanitize.
	 * @param string $default Default value if key doesn't exist.
	 * @param array  $validation_rules Optional validation rules.
	 * @return string Sanitized value.
	 */
	public static function sanitize_post_text( string $key, string $default = '', array $validation_rules = array() ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		$value = wp_unslash( $_POST[ $key ] );

		// Apply validation if rules provided.
		if ( ! empty( $validation_rules ) ) {
			$validated = InputValidator::validate_text( $value, $key, $validation_rules );
			return $validated !== false ? $validated : $default;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize email field from POST data.
	 *
	 * @param string $key The POST key to sanitize.
	 * @param string $default Default value if key doesn't exist.
	 * @return string Sanitized email or default.
	 */
	public static function sanitize_post_email( string $key, string $default = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		$email = sanitize_email( wp_unslash( $_POST[ $key ] ) );
		return $email !== false ? $email : $default;
	}

	/**
	 * Sanitize textarea field from POST data.
	 *
	 * @param string $key The POST key to sanitize.
	 * @param string $default Default value if key doesn't exist.
	 * @return string Sanitized textarea content.
	 */
	public static function sanitize_post_textarea( string $key, string $default = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Sanitize URL field from POST data.
	 *
	 * @param string $key The POST key to sanitize.
	 * @param string $default Default value if key doesn't exist.
	 * @return string Sanitized URL or default.
	 */
	public static function sanitize_post_url( string $key, string $default = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		$url = esc_url_raw( wp_unslash( $_POST[ $key ] ) );
		return $url !== '' ? $url : $default;
	}

	/**
	 * Sanitize integer field from POST data.
	 * Enhanced with proper validation including negative numbers.
	 *
	 * @param string $key The POST key to sanitize.
	 * @param int    $default Default value if key doesn't exist.
	 * @param int    $min Minimum allowed value.
	 * @param int    $max Maximum allowed value.
	 * @return int Sanitized integer value.
	 */
	public static function sanitize_post_int( string $key, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX ): int {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		$validation_rules = array(
			'min'     => $min,
			'max'     => $max,
			'default' => $default,
		);

		$validated = InputValidator::validate_integer( $_POST[ $key ], $key, $validation_rules );
		return $validated !== false ? $validated : $default;
	}

	/**
	 * Sanitize boolean field from POST data.
	 *
	 * @param string $key The POST key to check.
	 * @return bool True if key exists and has truthy value, false otherwise.
	 */
	public static function sanitize_post_bool( string $key ): bool {
		return isset( $_POST[ $key ] ) && ! empty( $_POST[ $key ] );
	}

	/**
	 * Sanitize array field from POST data.
	 *
	 * @param string        $key The POST key to sanitize.
	 * @param array         $default Default value if key doesn't exist.
	 * @param callable|null $sanitize_callback Optional callback to sanitize each array element.
	 * @return array Sanitized array.
	 */
	public static function sanitize_post_array( string $key, array $default = array(), ?callable $sanitize_callback = null ): array {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return $default;
		}

		$array = wp_unslash( $_POST[ $key ] );

		if ( $sanitize_callback !== null ) {
			return array_map( $sanitize_callback, $array );
		}

		return array_map( 'sanitize_text_field', $array );
	}

	/**
	 * Sanitize nonce field and verify it.
	 *
	 * @param string $nonce_key The POST key containing the nonce.
	 * @param string $nonce_action The nonce action to verify against.
	 * @return bool True if nonce is valid, false otherwise.
	 */
	public static function verify_post_nonce( string $nonce_key, string $nonce_action ): bool {
		if ( ! isset( $_POST[ $nonce_key ] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) );
		return wp_verify_nonce( $nonce, $nonce_action );
	}

	/**
	 * Collect multiple POST fields at once with sanitization.
	 *
	 * @param array $field_map Array mapping POST keys to sanitization types.
	 *                        Format: ['post_key' => 'sanitization_type']
	 *                        Types: 'text', 'email', 'textarea', 'url', 'int', 'bool', 'array'
	 * @param array $defaults Default values for each field.
	 * @return array Sanitized values array.
	 */
	public static function collect_post_fields( array $field_map, array $defaults = array() ): array {
		$collected = array();

		foreach ( $field_map as $post_key => $sanitize_type ) {
			$default = $defaults[ $post_key ] ?? null;

			switch ( $sanitize_type ) {
				case 'text':
					$collected[ $post_key ] = self::sanitize_post_text( $post_key, $default ?? '' );
					break;
				case 'email':
					$collected[ $post_key ] = self::sanitize_post_email( $post_key, $default ?? '' );
					break;
				case 'textarea':
					$collected[ $post_key ] = self::sanitize_post_textarea( $post_key, $default ?? '' );
					break;
				case 'url':
					$collected[ $post_key ] = self::sanitize_post_url( $post_key, $default ?? '' );
					break;
				case 'int':
					$collected[ $post_key ] = self::sanitize_post_int( $post_key, $default ?? 0 );
					break;
				case 'bool':
					$collected[ $post_key ] = self::sanitize_post_bool( $post_key );
					break;
				case 'array':
					$collected[ $post_key ] = self::sanitize_post_array( $post_key, $default ?? array() );
					break;
				default:
					$collected[ $post_key ] = self::sanitize_post_text( $post_key, $default ?? '' );
					break;
			}
		}

		return $collected;
	}
}
