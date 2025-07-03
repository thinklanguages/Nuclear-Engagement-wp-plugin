<?php
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
	 */
	public static function validate_int( $value, int $min = 0, int $max = PHP_INT_MAX ): ?int {
		if ( is_null( $value ) ) {
			return null;
		}

		$int_value = filter_var( $value, FILTER_VALIDATE_INT );
		
		if ( $int_value === false || $int_value < $min || $int_value > $max ) {
			return null;
		}

		return $int_value;
	}

	/**
	 * Validate and sanitize string input.
	 */
	public static function validate_string( 
		$value, 
		int $max_length = 255, 
		array $allowed = [], 
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
		
		if ( strlen( $string_value ) > $max_length ) {
			return null;
		}

		if ( ! empty( $allowed ) && ! in_array( $string_value, $allowed, true ) ) {
			return null;
		}

		return $string_value;
	}

	/**
	 * Validate and sanitize array input.
	 */
	public static function validate_array( 
		$value, 
		int $max_items = 100, 
		string $item_type = 'string',
		array $options = []
	): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		if ( count( $value ) > $max_items ) {
			return null;
		}

		$validated = [];
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
						$options['allowed'] ?? [],
						$options['allow_html'] ?? false
					);
					break;
				default:
					return null;
			}
			
			if ( $validated_item === null ) {
				return null;
			}
			
			$validated[$index] = $validated_item;
		}

		return $validated;
	}

	public static function validate_nonce( string $nonce_value, string $nonce_action ): bool {
		return wp_verify_nonce( $nonce_value, $nonce_action ) !== false;
	}

	public static function validate_capability( string $capability = 'manage_options' ): bool {
		return current_user_can( $capability );
	}

	public static function validate_ajax_request( string $nonce_action, string $capability = 'manage_options' ): bool {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			return false;
		}

		return self::validate_capability( $capability );
	}

	public static function sanitize_api_key( string $api_key ): string {
		return sanitize_text_field( trim( $api_key ) );
	}

	public static function is_valid_uuid( string $uuid ): bool {
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid );
	}

	public static function validate_url( string $url ): bool {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	public static function validate_email( string $email ): bool {
		return is_email( $email ) !== false;
	}

	/**
	 * Validate WordPress post ID.
	 */
	public static function validate_post_id( $value ): ?int {
		$post_id = self::validate_int( $value, 1 );
		
		if ( $post_id === null ) {
			return null;
		}

		if ( ! get_post( $post_id ) ) {
			return null;
		}

		return $post_id;
	}

	/**
	 * Validate boolean input.
	 */
	public static function validate_bool( $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, [ 'true', '1', 'yes', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $lower, [ 'false', '0', 'no', 'off', '' ], true ) ) {
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
	 */
	public static function validate_batch( array $inputs, array $rules ): ?array {
		$validated = [];
		
		foreach ( $rules as $field => $rule ) {
			$value = $inputs[$field] ?? null;
			$type = $rule['type'] ?? 'string';
			$required = $rule['required'] ?? false;
			$options = $rule['options'] ?? [];
			
			if ( $required && ( $value === null || $value === '' ) ) {
				return null;
			}
			
			if ( ! $required && ( $value === null || $value === '' ) ) {
				$validated[$field] = $value;
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
						$options['allowed'] ?? [],
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
			
			if ( $validated_value === null ) {
				return null;
			}
			
			$validated[$field] = $validated_value;
		}
		
		return $validated;
	}
}