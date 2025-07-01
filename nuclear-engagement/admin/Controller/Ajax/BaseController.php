<?php
declare(strict_types=1);
/**
 * File: admin/Controller/Ajax/BaseController.php
 *
 * Base class for AJAX controllers.
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides common security helpers for AJAX controllers.
 */
abstract class BaseController {
	/**
	 * Send a standardized JSON error response.
	 *
	 * @param string $message Error message.
	 * @param int    $code    HTTP status code.
	 */
	protected function sendError( string $message, int $code = 500 ): void {
		status_header( $code );
		wp_send_json_error(
			array(
				'message' => $message,
			),
			$code
		);
	}

	/**
	 * Verify nonce and permissions.
	 *
	 * @param string $nonceAction Nonce action.
	 * @param string $nonceField  Nonce field name.
	 * @param string $capability  Capability to check.
	 * @return bool Whether the request is valid.
	 */
	protected function verifyRequest(
		string $nonceAction,
		string $nonceField = 'security',
		string $capability = 'manage_options'
	): bool {
		if ( ! check_ajax_referer( $nonceAction, $nonceField, false ) ) {
			$this->sendError(
				__( 'Security check failed', 'nuclear-engagement' ),
				403
			);
			return false;
		}

		if ( ! current_user_can( $capability ) ) {
			$this->sendError( __( 'Not allowed', 'nuclear-engagement' ), 403 );
			return false;
		}

		return true;
	}

	/**
	 * Validate and sanitize POST integer value.
	 *
	 * @param string $key POST key.
	 * @param int    $min Minimum allowed value.
	 * @param int    $max Maximum allowed value.
	 * @return int|null Sanitized value or null if invalid.
	 */
	protected function validatePostInt( string $key, int $min = 0, int $max = PHP_INT_MAX ): ?int {
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}

		$value = filter_var( $_POST[ $key ], FILTER_VALIDATE_INT );
		if ( $value === false || $value < $min || $value > $max ) {
			return null;
		}

		return $value;
	}

	/**
	 * Validate and sanitize POST string value.
	 *
	 * @param string $key         POST key.
	 * @param int    $max_length  Maximum allowed length.
	 * @param array  $allowed     Allowed values (whitelist).
	 * @return string|null Sanitized value or null if invalid.
	 */
	protected function validatePostString( string $key, int $max_length = 255, array $allowed = array() ): ?string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}

		$value = sanitize_text_field( $_POST[ $key ] );
		
		if ( strlen( $value ) > $max_length ) {
			return null;
		}

		if ( ! empty( $allowed ) && ! in_array( $value, $allowed, true ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Validate and sanitize POST array value.
	 *
	 * @param string $key         POST key.
	 * @param int    $max_items   Maximum allowed items.
	 * @param string $item_type   Type validation for items ('int', 'string').
	 * @return array|null Sanitized array or null if invalid.
	 */
	protected function validatePostArray( string $key, int $max_items = 100, string $item_type = 'string' ): ?array {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return null;
		}

		$array = $_POST[ $key ];
		
		if ( count( $array ) > $max_items ) {
			return null;
		}

		$sanitized = array();
		foreach ( $array as $item ) {
			if ( $item_type === 'int' ) {
				$clean_item = filter_var( $item, FILTER_VALIDATE_INT );
				if ( $clean_item === false ) {
					return null;
				}
				$sanitized[] = $clean_item;
			} else {
				$clean_item = sanitize_text_field( $item );
				if ( strlen( $clean_item ) > 255 ) {
					return null;
				}
				$sanitized[] = $clean_item;
			}
		}

		return $sanitized;
	}

	/**
	 * Validate POST JSON payload.
	 *
	 * @param string $key         POST key.
	 * @param int    $max_depth   Maximum JSON depth.
	 * @param int    $max_length  Maximum JSON string length.
	 * @return mixed|null Decoded JSON or null if invalid.
	 */
	protected function validatePostJson( string $key, int $max_depth = 10, int $max_length = 10000 ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}

		$json_string = $_POST[ $key ];
		
		if ( ! is_string( $json_string ) || strlen( $json_string ) > $max_length ) {
			return null;
		}

		$decoded = json_decode( $json_string, true, $max_depth );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $decoded;
	}
}
