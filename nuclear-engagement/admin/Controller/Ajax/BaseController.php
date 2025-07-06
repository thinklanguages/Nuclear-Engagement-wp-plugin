<?php
/**
 * BaseController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Controller_Ajax
 */

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
	protected function send_error( string $message, int $code = 500 ): void {
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
	 * DISABLED: Rate limiting is handled by the SaaS backend.
	 * Always returns false to allow all requests through.
	 *
	 * @return bool Always returns false (no rate limiting).
	 */
	protected function is_rate_limited(): bool {
		// Rate limiting is handled by the SaaS backend - always allow through
		return false;
	}

	/**
	 * Verify nonce and permissions.
	 *
	 * Note: Rate limiting is handled by the SaaS backend, not locally.
	 *
	 * @param string $nonce_action Nonce action.
	 * @param string $nonce_field  Nonce field name.
	 * @param string $capability  Capability to check.
	 * @return bool Whether the request is valid.
	 */
	protected function verify_request(
		string $nonce_action,
		string $nonce_field = 'security',
		string $capability = 'manage_options'
	): bool {
		// Note: Rate limiting is skipped - handled by SaaS backend

		if ( ! check_ajax_referer( $nonce_action, $nonce_field, false ) ) {
			$this->send_error(
				__( 'Security check failed', 'nuclear-engagement' ),
				403
			);
			return false;
		}

		if ( ! current_user_can( $capability ) ) {
			$this->send_error( __( 'Not allowed', 'nuclear-engagement' ), 403 );
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
	protected function validate_post_int( string $key, int $min = 0, int $max = PHP_INT_MAX ): ?int {
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

		$decoded = wp_json_decode( $json_string, true, $max_depth );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $decoded;
	}
}
