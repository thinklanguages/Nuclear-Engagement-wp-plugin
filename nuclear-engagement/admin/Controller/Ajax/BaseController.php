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

use NuclearEngagement\Security\RateLimiter;
use NuclearEngagement\Utils\ServerUtils;
use NuclearEngagement\Utils\ValidationUtils;

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
	 * Check if the current request is rate limited.
	 *
	 * @return bool True if rate limited.
	 */
	protected function isRateLimited(): bool {
		$user_id = get_current_user_id();
		$identifier = $user_id > 0 ? 'user_' . $user_id : ServerUtils::get_client_identifier();
		
		// Check if temporarily blocked
		if ( RateLimiter::is_temporarily_blocked( $identifier ) ) {
			return true;
		}
		
		// Check rate limit for API requests
		if ( RateLimiter::is_rate_limited( 'api_request', $identifier ) ) {
			RateLimiter::record_violation( 'api_request', $identifier );
			return true;
		}
		
		return false;
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
		// Check rate limiting first
		if ( $this->isRateLimited() ) {
			$this->sendError(
				__( 'Too many requests. Please wait before trying again.', 'nuclear-engagement' ),
				429
			);
			return false;
		}

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

		return ValidationUtils::validate_int( $_POST[ $key ], $min, $max );
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

		return ValidationUtils::validate_string( $_POST[ $key ], $max_length, $allowed );
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
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}

		return ValidationUtils::validate_array( $_POST[ $key ], $max_items, $item_type );
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
