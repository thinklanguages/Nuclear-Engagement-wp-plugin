<?php
/**
 * BaseController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Utils\ValidationUtils;
use NuclearEngagement\Utils\ServerUtils;
use NuclearEngagement\Security\RateLimiter;
use NuclearEngagement\Core\UnifiedErrorHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base controller class to reduce code duplication in AJAX and REST controllers.
 *
 * This abstract class provides common functionality for all controller classes
 * including security checks, input validation, and response handling.
 *
 * @package NuclearEngagement\Core
 * @since   1.0.0
 */
abstract class BaseController {

	/**
	 * Controller name for logging and rate limiting.
	 */
	protected string $controller_name;

	/**
	 * Error handler instance.
	 */
	protected UnifiedErrorHandler $error_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller_name = $this->get_controller_name();
		$this->error_handler   = UnifiedErrorHandler::get_instance();
	}

	/**
	 * Get controller name for logging and rate limiting.
	 *
	 * @return string Controller name.
	 */
	abstract protected function get_controller_name(): string;

	/**
	 * Send standardized JSON success response.
	 *
	 * @param mixed  $data    Response data.
	 * @param string $message Success message.
	 * @param int    $code    HTTP status code.
	 * @return void
	 */
	protected function send_success( $data = null, string $message = 'Success', int $code = 200 ): void {
		status_header( $code );
		wp_send_json_success(
			array(
				'message'   => $message,
				'data'      => $data,
				'timestamp' => time(),
			),
			$code
		);
	}

	/**
	 * Send standardized JSON error response.
	 *
	 * @param string $message Error message.
	 * @param int    $code    HTTP status code.
	 * @param array  $data    Additional error data.
	 * @return void
	 */
	protected function send_error( string $message, int $code = 500, array $data = array() ): void {
		// Log error.
		$this->error_handler->handle_error(
			"Controller error: {$message}",
			'general',
			$code >= 500 ? 'high' : 'medium',
			array_merge( $data, array( 'controller' => $this->controller_name ) )
		);

		status_header( $code );
		wp_send_json_error(
			array_merge(
				array(
					'message'   => $message,
					'timestamp' => time(),
				),
				$data
			),
			$code
		);
	}

	/**
	 * Comprehensive security validation for requests.
	 *
	 * Note: Rate limiting is handled by the SaaS backend, not locally.
	 *
	 * @param string $nonce_action  Nonce action name.
	 * @param string $capability    Required capability.
	 * @param string $rate_limit_action Rate limit action name (unused - kept for compatibility).
	 * @param string $nonce_field   Nonce field name.
	 * @return bool True if request is valid and allowed.
	 */
	protected function validate_request(
		string $nonce_action,
		string $capability = 'manage_options',
		string $rate_limit_action = 'api_request',
		string $nonce_field = 'security'
	): bool {
		// Note: Rate limiting is skipped - handled by SaaS backend
		
		// Verify nonce.
		if ( ! $this->verify_nonce( $nonce_action, $nonce_field ) ) {
			$this->send_error(
				__( 'Security check failed. Please refresh the page and try again.', 'nuclear-engagement' ),
				403
			);
			return false;
		}

		// Check user capability.
		if ( ! $this->check_capability( $capability ) ) {
			$this->send_error(
				__( 'You do not have permission to perform this action.', 'nuclear-engagement' ),
				403
			);
			return false;
		}

		return true;
	}

	/**
	 * Check if the current request is rate limited.
	 *
	 * DISABLED: Rate limiting is handled by the SaaS backend.
	 * Always returns false to allow all requests through.
	 *
	 * @param string $action Rate limit action name.
	 * @return bool Always returns false (no rate limiting).
	 */
	protected function is_rate_limited( string $action = 'api_request' ): bool {
		// Rate limiting is handled by the SaaS backend - always allow through
		return false;
	}

	/**
	 * Verify nonce security.
	 *
	 * @param string $action Nonce action.
	 * @param string $field  Nonce field name.
	 * @return bool True if nonce is valid.
	 */
	protected function verify_nonce( string $action, string $field = 'security' ): bool {
		// For AJAX requests.
		if ( wp_doing_ajax() ) {
			return check_ajax_referer( $action, $field, false ) !== false;
		}

		// For regular requests.
		$nonce = $_REQUEST[ $field ] ?? '';
		return ValidationUtils::validate_nonce( $nonce, $action );
	}

	/**
	 * Check user capability.
	 *
	 * @param string $capability Required capability.
	 * @param int    $user_id    User ID (0 for current user).
	 * @return bool True if user has capability.
	 */
	protected function check_capability( string $capability = 'manage_options', int $user_id = 0 ): bool {
		return ValidationUtils::validate_capability( $capability, $user_id );
	}

	/**
	 * Validate and sanitize POST data.
	 *
	 * @param array $rules Validation rules.
	 * @return array|null Validated data or null on failure.
	 */
	protected function validate_post_data( array $rules ): ?array {
		$validated = ValidationUtils::validate_batch( $_POST, $rules );

		if ( $validated === null ) {
			$this->error_handler->handle_error(
				'POST data validation failed',
				'validation',
				'medium',
				array(
					'controller' => $this->controller_name,
					'rules'      => array_keys( $rules ),
				)
			);
		}

		return $validated;
	}

	/**
	 * Get and validate integer from POST data.
	 *
	 * @param string $key POST key.
	 * @param int    $min Minimum value.
	 * @param int    $max Maximum value.
	 * @return int|null Validated integer or null if invalid.
	 */
	protected function get_post_int( string $key, int $min = 0, int $max = PHP_INT_MAX ): ?int {
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}

		return ValidationUtils::validate_int( $_POST[ $key ], $min, $max );
	}

	/**
	 * Get and validate string from POST data.
	 *
	 * @param string $key        POST key.
	 * @param int    $max_length Maximum length.
	 * @param array  $allowed    Allowed values.
	 * @param bool   $allow_html Whether to allow HTML.
	 * @return string|null Validated string or null if invalid.
	 */
	protected function get_post_string(
		string $key,
		int $max_length = 255,
		array $allowed = array(),
		bool $allow_html = false
	): ?string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}

		return ValidationUtils::validate_string( $_POST[ $key ], $max_length, $allowed, $allow_html );
	}

	/**
	 * Get and validate array from POST data.
	 *
	 * @param string $key       POST key.
	 * @param int    $max_items Maximum items.
	 * @param string $item_type Item type validation.
	 * @param array  $options   Additional validation options.
	 * @return array|null Validated array or null if invalid.
	 */
	protected function get_post_array(
		string $key,
		int $max_items = 100,
		string $item_type = 'string',
		array $options = array()
	): ?array {
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}

		return ValidationUtils::validate_array( $_POST[ $key ], $max_items, $item_type, $options );
	}

	/**
	 * Require POST parameter or send error.
	 *
	 * @param string $key        Parameter key.
	 * @param string $type       Expected type (int, string, array).
	 * @param array  $options    Validation options.
	 * @return mixed Parameter value or exits with error.
	 */
	protected function require_post_param( string $key, string $type = 'string', array $options = array() ) {
		$value = null;

		switch ( $type ) {
			case 'int':
				$value = $this->get_post_int(
					$key,
					$options['min'] ?? 0,
					$options['max'] ?? PHP_INT_MAX
				);
				break;
			case 'string':
				$value = $this->get_post_string(
					$key,
					$options['max_length'] ?? 255,
					$options['allowed'] ?? array(),
					$options['allow_html'] ?? false
				);
				break;
			case 'array':
				$value = $this->get_post_array(
					$key,
					$options['max_items'] ?? 100,
					$options['item_type'] ?? 'string',
					$options
				);
				break;
		}

		if ( $value === null ) {
			$this->send_error(
				sprintf(
					__( 'Required parameter "%s" is missing or invalid.', 'nuclear-engagement' ),
					$key
				),
				400
			);
		}

		return $value;
	}

	/**
	 * Log controller action for auditing.
	 *
	 * @param string $action Action performed.
	 * @param array  $data   Action data.
	 * @return void
	 */
	protected function log_action( string $action, array $data = array() ): void {
		$log_data = array_merge(
			array(
				'controller' => $this->controller_name,
				'action'     => $action,
				'user_id'    => get_current_user_id(),
				'ip'         => ServerUtils::get_client_ip(),
				'user_agent' => ServerUtils::get_user_agent(),
				'timestamp'  => time(),
			),
			$data
		);

		if ( class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				"Controller action: {$action}",
				$log_data
			);
		}
	}

	/**
	 * Execute controller action with error handling.
	 *
	 * @param callable $action     Action callback.
	 * @param string   $action_name Action name for logging.
	 * @return mixed Action result or false on failure.
	 */
	protected function execute_action( callable $action, string $action_name ) {
		try {
			$this->log_action( $action_name );
			return call_user_func( $action );

		} catch ( \Throwable $e ) {
			$this->error_handler->handle_error(
				"Controller action failed: {$action_name}",
				'general',
				'high',
				array(
					'controller' => $this->controller_name,
					'action'     => $action_name,
					'exception'  => get_class( $e ),
					'message'    => $e->getMessage(),
					'file'       => $e->getFile(),
					'line'       => $e->getLine(),
				)
			);

			$this->send_error(
				__( 'An error occurred while processing your request.', 'nuclear-engagement' ),
				500
			);

			return false;
		}
	}

	/**
	 * Get request context for logging.
	 *
	 * @return array Request context.
	 */
	protected function get_request_context(): array {
		return array(
			'controller' => $this->controller_name,
			'method'     => ServerUtils::get_request_method(),
			'uri'        => ServerUtils::get_request_uri(),
			'ip'         => ServerUtils::get_client_ip(),
			'user_agent' => ServerUtils::get_user_agent(),
			'user_id'    => get_current_user_id(),
			'is_ajax'    => wp_doing_ajax(),
		);
	}
}
