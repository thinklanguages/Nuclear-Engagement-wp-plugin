<?php
/**
 * ValidationUtils.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Utils
 */

declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprehensive input validation utilities.
 *
 * This class provides secure, consistent input validation methods
 * for all plugin components to prevent security vulnerabilities.
 */
final class ValidationUtils {

	/**
	 * Validate and sanitize integer input.
	 *
	 * @param mixed $value The value to validate.
	 * @param int   $min   Minimum allowed value.
	 * @param int   $max   Maximum allowed value.
	 * @return int|null    The validated integer or null if invalid.
	 */
	public static function validate_int( $value, int $min = 0, int $max = PHP_INT_MAX ): ?int {
		if ( is_null( $value ) ) {
			return null;
		}

		// Explicitly reject boolean values
		if ( is_bool( $value ) ) {
			return null;
		}

		$int_value = filter_var( $value, FILTER_VALIDATE_INT );

		if ( false === $int_value || $min > $int_value || $max < $int_value ) {
			return null;
		}

		return $int_value;
	}

	/**
	 * Validate and sanitize string input.
	 *
	 * @param mixed $value      The value to validate.
	 * @param int   $max_length Maximum allowed length.
	 * @param array $allowed    Array of allowed values.
	 * @param bool  $allow_html Whether to allow HTML tags.
	 * @return string|null      The validated string or null if invalid.
	 */
	public static function validate_string(
		$value,
		int $max_length = 255,
		array $allowed = array(),
		bool $allow_html = false
	): ?string {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		$string_value = (string) $value;

		if ( $allow_html ) {
			$string_value = wp_kses_post( $string_value );
		} else {
			$string_value = sanitize_text_field( $string_value );
		}

		// If the sanitized value is null or becomes empty after trimming, handle appropriately
		if ( $string_value === null ) {
			return null;
		}

		// Trim and check if it becomes empty (for whitespace-only strings)
		$trimmed_value = trim( $string_value );
		if ( $trimmed_value === '' && $string_value !== '' ) {
			$string_value = '';
		}

		if ( $max_length < strlen( $string_value ) ) {
			return null;
		}

		if ( ! empty( $allowed ) && ! in_array( $string_value, $allowed, true ) ) {
			return null;
		}

		return $string_value;
	}

	/**
	 * Validate and sanitize array input.
	 *
	 * @param mixed  $value     The value to validate.
	 * @param int    $max_items Maximum number of items.
	 * @param string $item_type Type of items to validate.
	 * @param array  $options   Additional validation options.
	 * @return array|null       The validated array or null if invalid.
	 */
	public static function validate_array(
		$value,
		int $max_items = 100,
		string $item_type = 'string',
		array $options = array()
	): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		if ( $max_items < count( $value ) ) {
			return null;
		}

		$validated = array();
		foreach ( $value as $index => $item ) {
			switch ( $item_type ) {
				case 'int':
					$validated_item = self::validate_int(
						$item,
						$options['min'] ?? 0,
						$options['max'] ?? PHP_INT_MAX
					);
					break;
				case 'string':
					$validated_item = self::validate_string(
						$item,
						$options['max_length'] ?? 255,
						$options['allowed'] ?? array(),
						$options['allow_html'] ?? false
					);
					break;
				default:
					return null;
			}

			if ( null === $validated_item ) {
				return null;
			}

			$validated[ $index ] = $validated_item;
		}

		return $validated;
	}

	/**
	 * Validate nonce value.
	 *
	 * @param string $nonce_value  The nonce value to validate.
	 * @param string $nonce_action The nonce action.
	 * @return bool                True if valid, false otherwise.
	 */
	public static function validate_nonce( string $nonce_value, string $nonce_action ): bool {
		return wp_verify_nonce( $nonce_value, $nonce_action ) !== false;
	}

	/**
	 * Validate user capability.
	 *
	 * @param string $capability The capability to check.
	 * @return bool              True if user has capability, false otherwise.
	 */
	public static function validate_capability( string $capability = 'manage_options' ): bool {
		return current_user_can( $capability );
	}

	/**
	 * Validate AJAX request with nonce and capability check.
	 *
	 * @param string $nonce_action The nonce action.
	 * @param string $capability   The required capability.
	 * @return bool                True if valid, false otherwise.
	 */
	public static function validate_ajax_request( string $nonce_action, string $capability = 'manage_options' ): bool {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			return false;
		}

		return self::validate_capability( $capability );
	}

	/**
	 * Sanitize API key.
	 *
	 * @param string $api_key The API key to sanitize.
	 * @return string         The sanitized API key.
	 */
	public static function sanitize_api_key( string $api_key ): string {
		return sanitize_text_field( trim( $api_key ) );
	}

	/**
	 * Check if string is a valid UUID.
	 *
	 * @param string $uuid The UUID to validate.
	 * @return bool        True if valid UUID, false otherwise.
	 */
	public static function is_valid_uuid( string $uuid ): bool {
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid );
	}

	/**
	 * Validate URL format.
	 *
	 * @param string $url The URL to validate.
	 * @return bool       True if valid URL, false otherwise.
	 */
	public static function validate_url( string $url ): bool {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Validate email address.
	 *
	 * @param string $email The email to validate.
	 * @return bool         True if valid email, false otherwise.
	 */
	public static function validate_email( string $email ): bool {
		return is_email( $email ) !== false;
	}

	/**
	 * Validate WordPress post ID.
	 *
	 * @param mixed $value The value to validate.
	 * @return int|null    Valid post ID or null if invalid.
	 */
	public static function validate_post_id( $value ): ?int {
		$post_id = self::validate_int( $value, 1 );

		if ( null === $post_id ) {
			return null;
		}

		if ( ! get_post( $post_id ) ) {
			return null;
		}

		return $post_id;
	}

	/**
	 * Validate boolean input.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool|null   The validated boolean or null if invalid.
	 */
	public static function validate_bool( $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, array( 'true', '1', 'yes', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $lower, array( 'false', '0', 'no', 'off', '' ), true ) ) {
				return false;
			}
		}

		if ( is_numeric( $value ) ) {
			return (bool) $value;
		}

		return null;
	}

	/**
	 * Batch validate multiple inputs.
	 *
	 * @param array $inputs The input values to validate.
	 * @param array $rules  The validation rules.
	 * @return array|null   The validated values or null if validation fails.
	 */
	public static function validate_batch( array $inputs, array $rules ): ?array {
		$validated = array();

		foreach ( $rules as $field => $rule ) {
			$value    = $inputs[ $field ] ?? null;
			$type     = $rule['type'] ?? 'string';
			$required = $rule['required'] ?? false;
			$options  = $rule['options'] ?? array();

			if ( $required && ( null === $value || '' === $value ) ) {
				return null;
			}

			if ( ! $required && ( null === $value || '' === $value ) ) {
				$validated[ $field ] = $value;
				continue;
			}

			$validated_value = null;
			switch ( $type ) {
				case 'int':
					$validated_value = self::validate_int(
						$value,
						$options['min'] ?? 0,
						$options['max'] ?? PHP_INT_MAX
					);
					break;
				case 'string':
					$validated_value = self::validate_string(
						$value,
						$options['max_length'] ?? 255,
						$options['allowed'] ?? array(),
						$options['allow_html'] ?? false
					);
					break;
				case 'bool':
					$validated_value = self::validate_bool( $value );
					break;
				case 'array':
					$validated_value = self::validate_array(
						$value,
						$options['max_items'] ?? 100,
						$options['item_type'] ?? 'string',
						$options
					);
					break;
				default:
					return null;
			}

			if ( null === $validated_value ) {
				return null;
			}

			$validated[ $field ] = $validated_value;
		}

		return $validated;
	}
}
