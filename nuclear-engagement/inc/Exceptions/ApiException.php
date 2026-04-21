<?php
/**
 * ApiException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Exceptions
 */

declare(strict_types=1);

namespace NuclearEngagement\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception for API-related errors
 */
class ApiException extends BaseException {

	/** @var int */
	private int $http_status_code = 0;

	/** @var array */
	private array $response_data = array();

	/** @var bool */
	private bool $is_retryable = false;

	/** @var string */
	private string $user_message = '';

	public function __construct(
		string $message,
		int $http_status_code = 0,
		array $response_data = array(),
		bool $is_retryable = false,
		int $code = 0,
		?\Throwable $previous = null
	) {
		$this->http_status_code = $http_status_code;
		$this->response_data    = $response_data;
		$this->is_retryable     = $is_retryable;

		$context = array(
			'http_status' => $http_status_code,
			'response'    => $response_data,
			'retryable'   => $is_retryable,
		);

		parent::__construct( $message, $code, $previous, $context );
		$this->error_code = 'API_ERROR';
	}

	/**
	 * Create from timeout
	 */
	public static function timeout( string $url, int $timeout_seconds ): self {
		return new self(
			sprintf( 'API request to %s timed out after %d seconds', $url, $timeout_seconds ),
			0,
			array(),
			true
		);
	}

	/**
	 * Create from network error
	 */
	public static function networkError( string $url, string $error ): self {
		return new self(
			sprintf( 'Network error when calling %s: %s', $url, $error ),
			0,
			array( 'network_error' => $error ),
			true
		);
	}

	/**
	 * Create from HTTP error
	 */
	public static function httpError( string $url, int $status_code, array $response = array() ): self {
		$retryable = in_array( $status_code, array( 408, 429, 500, 502, 503, 504 ), true );
		$message   = self::extract_response_message( $response );
		$exception = new self(
			'' !== $message ? $message : sprintf( 'HTTP %d error from %s', $status_code, $url ),
			$status_code,
			$response,
			$retryable
		);

		$error_code = self::extract_response_error_code( $response );
		if ( '' !== $error_code ) {
			$exception->set_error_code( $error_code );
		}

		$user_message = self::derive_user_message( $status_code, $response, $error_code, $message );
		if ( '' !== $user_message ) {
			$exception->setUserMessage( $user_message );
		}

		return $exception;
	}

	/**
	 * Get HTTP status code
	 */
	public function get_http_status_code(): int {
		return $this->http_status_code;
	}

	/**
	 * Get response data
	 */
	public function get_response_data(): array {
		return $this->response_data;
	}

	/**
	 * Check if error is retryable
	 */
	public function is_retryable(): bool {
		return $this->is_retryable;
	}

	/**
	 * Get user-friendly message
	 */
	public function get_user_message(): string {
		// Return custom user message if set
		if ( ! empty( $this->user_message ) ) {
			return $this->user_message;
		}

		$response_message = self::extract_response_message( $this->response_data );
		$error_code       = self::extract_response_error_code( $this->response_data );
		$user_message     = self::derive_user_message( $this->http_status_code, $this->response_data, $error_code, $response_message );
		if ( '' !== $user_message ) {
			return $user_message;
		}

		if ( $this->is_retryable ) {
			return __( 'Temporary connection issue. Please try again in a few moments.', 'nuclear-engagement' );
		}

		switch ( $this->http_status_code ) {
			case 401:
			case 403:
				return __( 'API authentication failed. Please check your API key.', 'nuclear-engagement' );
			case 404:
				return __( 'The requested resource was not found.', 'nuclear-engagement' );
			case 429:
				return __( 'Rate limit exceeded. Please try again later.', 'nuclear-engagement' );
			default:
				return __( 'An error occurred while communicating with the API.', 'nuclear-engagement' );
		}
	}

	/**
	 * Set user-friendly message
	 *
	 * @param string $message User-friendly message
	 * @return self
	 */
	public function setUserMessage( string $message ): self {
		$this->user_message = $message;
		return $this;
	}

	/**
	 * Extract a message from a structured API response.
	 *
	 * @param array $response API response payload.
	 * @return string
	 */
	private static function extract_response_message( array $response ): string {
		foreach ( array( 'message', 'error' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_string( $response[ $key ] ) ) {
				$message = trim( $response[ $key ] );
				if ( '' !== $message ) {
					return $message;
				}
			}
		}

		return '';
	}

	/**
	 * Extract a normalized error code from a structured API response.
	 *
	 * @param array $response API response payload.
	 * @return string
	 */
	private static function extract_response_error_code( array $response ): string {
		if ( isset( $response['error_code'] ) && is_string( $response['error_code'] ) ) {
			return trim( $response['error_code'] );
		}

		return '';
	}

	/**
	 * Build a user-facing message from an API error response when possible.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param array  $response    API response payload.
	 * @param string $error_code  Parsed error code.
	 * @param string $message     Parsed response message.
	 * @return string
	 */
	private static function derive_user_message( int $status_code, array $response, string $error_code, string $message ): string {
		if ( 'invalid_api_key' === $error_code ) {
			return __( 'Invalid API key. Please update it on the Setup page.', 'nuclear-engagement' );
		}

		if ( 'invalid_wp_app_pass' === $error_code ) {
			return __( 'Invalid plugin password. Please re-generate on the Setup page.', 'nuclear-engagement' );
		}

		if ( '' !== $message && stripos( $message, 'not enough credits' ) !== false ) {
			return __( 'Not enough credits. Please top up your account or reduce the number of posts.', 'nuclear-engagement' );
		}

		if ( '' !== $message && 403 !== $status_code ) {
			return $message;
		}

		return '';
	}

	/**
	 * Create from service unavailable error
	 *
	 * @param string $message Error message
	 * @param int    $retry_after Seconds to wait before retry
	 * @return self
	 */
	public static function serviceUnavailable( string $message, int $retry_after = 60 ): self {
		return new self(
			$message,
			503,
			array( 'retry_after' => $retry_after ),
			true
		);
	}

	/**
	 * Create from a generic Throwable
	 *
	 * @param \Throwable $e The exception to convert
	 * @return self
	 */
	public static function fromThrowable( \Throwable $e ): self {
		// Check if it's a WP_Error
		if ( method_exists( $e, 'get_error_message' ) ) {
			return new self(
				$e->get_error_message(),
				0,
				array(
					'wp_error_code' => method_exists( $e, 'get_error_code' ) ? $e->get_error_code() : '',
					'wp_error_data' => method_exists( $e, 'get_error_data' ) ? $e->get_error_data() : array(),
				),
				false,
				$e->getCode(),
				$e instanceof \Exception ? $e : null
			);
		}

		// Check for specific error patterns to determine if retryable
		$message      = $e->getMessage();
		$is_retryable = false;

		if ( strpos( $message, 'timeout' ) !== false ||
			strpos( $message, 'timed out' ) !== false ||
			strpos( $message, 'connection' ) !== false ||
			strpos( $message, 'network' ) !== false ) {
			$is_retryable = true;
		}

		return new self(
			$message,
			0,
			array(
				'original_class' => get_class( $e ),
				'file'           => $e->getFile(),
				'line'           => $e->getLine(),
			),
			$is_retryable,
			$e->getCode(),
			$e instanceof \Exception ? $e : null
		);
	}
}
