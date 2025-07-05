<?php
/**
 * InputValidator.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Helpers
 */

declare(strict_types=1);

namespace NuclearEngagement\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InputValidator {

	/** Validation error messages */
	private static array $errors = array();

	/**
	 * Validate and sanitize text field
	 *
	 * @param string $value     The value to validate.
	 * @param string $field     Field name for error messages.
	 * @param array  $rules     Validation rules.
	 * @return string|false Sanitized value or false on failure.
	 */
	public static function validate_text( string $value, string $field, array $rules = array() ) {
		self::clear_field_errors( $field );

		// Required validation.
		if ( isset( $rules['required'] ) && $rules['required'] && empty( trim( $value ) ) ) {
			self::add_error( $field, sprintf( '%s is required.', $field ) );
			return false;
		}

		// Length validation.
		if ( isset( $rules['min_length'] ) && strlen( $value ) < $rules['min_length'] ) {
			self::add_error( $field, sprintf( '%s must be at least %d characters.', $field, $rules['min_length'] ) );
			return false;
		}

		if ( isset( $rules['max_length'] ) && strlen( $value ) > $rules['max_length'] ) {
			self::add_error( $field, sprintf( '%s must not exceed %d characters.', $field, $rules['max_length'] ) );
			return false;
		}

		// Pattern validation.
		if ( isset( $rules['pattern'] ) && ! preg_match( $rules['pattern'], $value ) ) {
			$message = $rules['pattern_message'] ?? sprintf( '%s format is invalid.', $field );
			self::add_error( $field, $message );
			return false;
		}

		// Alphanumeric validation.
		if ( isset( $rules['alphanumeric'] ) && $rules['alphanumeric'] && ! ctype_alnum( str_replace( array( '-', '_', ' ' ), '', $value ) ) ) {
			self::add_error( $field, sprintf( '%s can only contain letters, numbers, hyphens, underscores, and spaces.', $field ) );
			return false;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Validate and sanitize email field
	 *
	 * @param string $value The email value to validate.
	 * @param string $field Field name for error messages.
	 * @param bool   $required Whether the field is required.
	 * @return string|false Sanitized email or false on failure.
	 */
	public static function validate_email( string $value, string $field, bool $required = false ) {
		self::clear_field_errors( $field );

		if ( $required && empty( trim( $value ) ) ) {
			self::add_error( $field, sprintf( '%s is required.', $field ) );
			return false;
		}

		if ( ! empty( $value ) && ! is_email( $value ) ) {
			self::add_error( $field, sprintf( '%s must be a valid email address.', $field ) );
			return false;
		}

		return sanitize_email( $value );
	}

	/**
	 * Validate and sanitize URL field
	 *
	 * @param string $value    The URL value to validate.
	 * @param string $field    Field name for error messages.
	 * @param array  $rules    Validation rules.
	 * @return string|false Sanitized URL or false on failure.
	 */
	public static function validate_url( string $value, string $field, array $rules = array() ) {
		self::clear_field_errors( $field );

		if ( isset( $rules['required'] ) && $rules['required'] && empty( trim( $value ) ) ) {
			self::add_error( $field, sprintf( '%s is required.', $field ) );
			return false;
		}

		if ( ! empty( $value ) ) {
			// Basic URL format validation.
			if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				self::add_error( $field, sprintf( '%s must be a valid URL.', $field ) );
				return false;
			}

			// Protocol validation.
			if ( isset( $rules['allowed_protocols'] ) ) {
				$parsed = parse_url( $value );
				if ( ! in_array( $parsed['scheme'] ?? '', $rules['allowed_protocols'], true ) ) {
					self::add_error( $field, sprintf( '%s must use an allowed protocol (%s).', $field, implode( ', ', $rules['allowed_protocols'] ) ) );
					return false;
				}
			}

			// Domain validation.
			if ( isset( $rules['allowed_domains'] ) ) {
				$parsed = parse_url( $value );
				$domain = $parsed['host'] ?? '';
				if ( ! in_array( $domain, $rules['allowed_domains'], true ) ) {
					self::add_error( $field, sprintf( '%s must be from an allowed domain.', $field ) );
					return false;
				}
			}
		}

		return esc_url_raw( $value );
	}

	/**
	 * Validate and sanitize integer field
	 *
	 * @param mixed  $value The value to validate.
	 * @param string $field Field name for error messages.
	 * @param array  $rules Validation rules.
	 * @return int|false Sanitized integer or false on failure.
	 */
	public static function validate_integer( $value, string $field, array $rules = array() ) {
		self::clear_field_errors( $field );

		if ( isset( $rules['required'] ) && $rules['required'] && ( $value === '' || $value === null ) ) {
			self::add_error( $field, sprintf( '%s is required.', $field ) );
			return false;
		}

		if ( $value !== '' && $value !== null ) {
			if ( ! is_numeric( $value ) ) {
				self::add_error( $field, sprintf( '%s must be a number.', $field ) );
				return false;
			}

			$int_value = (int) $value;

			// Range validation.
			if ( isset( $rules['min'] ) && $int_value < $rules['min'] ) {
				self::add_error( $field, sprintf( '%s must be at least %d.', $field, $rules['min'] ) );
				return false;
			}

			if ( isset( $rules['max'] ) && $int_value > $rules['max'] ) {
				self::add_error( $field, sprintf( '%s must not exceed %d.', $field, $rules['max'] ) );
				return false;
			}

			// Positive validation.
			if ( isset( $rules['positive'] ) && $rules['positive'] && $int_value <= 0 ) {
				self::add_error( $field, sprintf( '%s must be a positive number.', $field ) );
				return false;
			}

			return $int_value;
		}

		return $rules['default'] ?? 0;
	}

	/**
	 * Validate array field
	 *
	 * @param mixed  $value The value to validate.
	 * @param string $field Field name for error messages.
	 * @param array  $rules Validation rules.
	 * @return array|false Sanitized array or false on failure.
	 */
	public static function validate_array( $value, string $field, array $rules = array() ) {
		self::clear_field_errors( $field );

		if ( isset( $rules['required'] ) && $rules['required'] && empty( $value ) ) {
			self::add_error( $field, sprintf( '%s is required.', $field ) );
			return false;
		}

		if ( ! is_array( $value ) ) {
			self::add_error( $field, sprintf( '%s must be an array.', $field ) );
			return false;
		}

		// Size validation.
		if ( isset( $rules['min_items'] ) && count( $value ) < $rules['min_items'] ) {
			self::add_error( $field, sprintf( '%s must contain at least %d items.', $field, $rules['min_items'] ) );
			return false;
		}

		if ( isset( $rules['max_items'] ) && count( $value ) > $rules['max_items'] ) {
			self::add_error( $field, sprintf( '%s must not contain more than %d items.', $field, $rules['max_items'] ) );
			return false;
		}

		// Element validation.
		if ( isset( $rules['element_rules'] ) ) {
			$sanitized = array();
			foreach ( $value as $key => $item ) {
				$sanitized_item = self::validate_by_type( $item, $field . '[' . $key . ']', $rules['element_rules'] );
				if ( $sanitized_item === false ) {
					return false;
				}
				$sanitized[ $key ] = $sanitized_item;
			}
			return $sanitized;
		}

		return array_map( 'sanitize_text_field', $value );
	}

	/**
	 * Validate by type with rules
	 *
	 * @param mixed  $value The value to validate.
	 * @param string $field Field name.
	 * @param array  $config Configuration with 'type' and 'rules'.
	 * @return mixed Validated value or false on failure.
	 */
	public static function validate_by_type( $value, string $field, array $config ) {
		$type  = $config['type'] ?? 'text';
		$rules = $config['rules'] ?? array();

		switch ( $type ) {
			case 'text':
				return self::validate_text( (string) $value, $field, $rules );
			case 'email':
				return self::validate_email( (string) $value, $field, $rules['required'] ?? false );
			case 'url':
				return self::validate_url( (string) $value, $field, $rules );
			case 'integer':
				return self::validate_integer( $value, $field, $rules );
			case 'array':
				return self::validate_array( $value, $field, $rules );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Get all validation errors
	 *
	 * @return array Array of errors grouped by field.
	 */
	public static function get_errors(): array {
		return self::$errors;
	}

	/**
	 * Check if there are any validation errors
	 *
	 * @return bool True if there are errors.
	 */
	public static function has_errors(): bool {
		return ! empty( self::$errors );
	}

	/**
	 * Clear all validation errors
	 *
	 * @return void
	 */
	public static function clear_errors(): void {
		self::$errors = array();
	}

	/**
	 * Get errors for a specific field
	 *
	 * @param string $field The field name.
	 * @return array Array of error messages for the field.
	 */
	public static function get_field_errors( string $field ): array {
		return self::$errors[ $field ] ?? array();
	}

	/**
	 * Add an error for a field
	 *
	 * @param string $field   The field name.
	 * @param string $message The error message.
	 * @return void
	 */
	private static function add_error( string $field, string $message ): void {
		self::$errors[ $field ][] = $message;
	}

	/**
	 * Clear errors for a specific field
	 *
	 * @param string $field The field name.
	 * @return void
	 */
	private static function clear_field_errors( string $field ): void {
		unset( self::$errors[ $field ] );
	}
}
