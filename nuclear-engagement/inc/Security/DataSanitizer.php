<?php
/**
 * DataSanitizer.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Security
 */

declare(strict_types=1);

namespace NuclearEngagement\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides secure data sanitization for JavaScript output.
 *
 * @package NuclearEngagement\Security
 * @since 1.0.0
 */
final class DataSanitizer {

	/**
	 * Sanitize data for safe JavaScript output.
	 *
	 * @param mixed $data Data to sanitize.
	 * @return mixed Sanitized data.
	 */
	public static function sanitize_for_js( $data ) {
		if ( is_string( $data ) ) {
			// Remove any script tags and escape HTML
			$data = wp_kses( $data, array() );
			// Additional escaping for JS context
			$data = esc_js( $data );
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = self::sanitize_for_js( $value );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = self::sanitize_for_js( $value );
			}
		}
		
		return $data;
	}

	/**
	 * Prepare data for wp_localize_script with security measures.
	 *
	 * @param array $data Data array to prepare.
	 * @return array Sanitized data array.
	 */
	public static function prepare_for_localize( array $data ): array {
		$sanitized = array();
		
		foreach ( $data as $key => $value ) {
			// Sanitize the key
			$safe_key = sanitize_key( $key );
			
			// Sanitize the value based on type
			if ( is_bool( $value ) ) {
				$sanitized[ $safe_key ] = $value;
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $safe_key ] = $value;
			} elseif ( is_string( $value ) ) {
				// Check if it's a URL
				if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$sanitized[ $safe_key ] = esc_url( $value );
				} else {
					// General string sanitization
					$sanitized[ $safe_key ] = sanitize_text_field( $value );
				}
			} elseif ( is_array( $value ) ) {
				$sanitized[ $safe_key ] = self::prepare_for_localize( $value );
			} else {
				// Skip unsupported types
				continue;
			}
		}
		
		return $sanitized;
	}

	/**
	 * Create a secure inline script variable.
	 *
	 * @param string $var_name Variable name.
	 * @param mixed  $value Variable value.
	 * @return string Inline script string.
	 */
	public static function create_inline_var( string $var_name, $value ): string {
		// Validate variable name
		if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $var_name ) ) {
			return ''; // Invalid variable name
		}
		
		// Handle different value types
		if ( is_bool( $value ) ) {
			$js_value = $value ? 'true' : 'false';
		} elseif ( is_null( $value ) ) {
			$js_value = 'null';
		} elseif ( is_numeric( $value ) ) {
			$js_value = (string) $value;
		} else {
			// Use wp_json_encode for complex types
			$js_value = wp_json_encode( $value );
		}
		
		return sprintf( 'window.%s = %s;', esc_js( $var_name ), $js_value );
	}

	/**
	 * Sanitize HTML content for safe output.
	 *
	 * @param string $html HTML content.
	 * @param array  $allowed_tags Optional allowed tags.
	 * @return string Sanitized HTML.
	 */
	public static function sanitize_html( string $html, array $allowed_tags = array() ): string {
		if ( empty( $allowed_tags ) ) {
			// Use wp_kses_post by default
			return wp_kses_post( $html );
		}
		
		return wp_kses( $html, $allowed_tags );
	}

	/**
	 * Create Content Security Policy nonce.
	 *
	 * @return string CSP nonce.
	 */
	public static function create_csp_nonce(): string {
		return wp_create_nonce( 'nuclen_csp_' . get_current_user_id() );
	}

	/**
	 * Add CSP header for inline scripts.
	 *
	 * @param string $nonce CSP nonce.
	 */
	public static function add_csp_header( string $nonce ): void {
		$csp = sprintf(
			"script-src 'self' 'nonce-%s' https://*.wordpress.com https://*.wp.com; object-src 'none'; base-uri 'self';",
			esc_attr( $nonce )
		);
		
		header( 'Content-Security-Policy: ' . $csp );
	}
}