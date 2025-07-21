<?php
/**
 * ApiException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/ApiException.php
 *
 * Exception thrown when the remote API returns an error.
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApiException extends \RuntimeException {
	private ?string $error_code;

	public function __construct( string $message, int $code = 500, ?string $error_code = null ) {
		parent::__construct( $message, $code );
		$this->error_code = $error_code;
	}

	public function getErrorCode(): ?string {
		return $this->error_code;
	}

	/**
	 * Check if this error is retryable
	 *
	 * @return bool
	 */
	public function is_retryable(): bool {
		// Check HTTP status codes
		$retryable_codes = array( 408, 429, 500, 502, 503, 504 );
		if ( in_array( $this->getCode(), $retryable_codes, true ) ) {
			return true;
		}

		// Check error message for retryable patterns
		$message            = strtolower( $this->getMessage() );
		$retryable_patterns = array(
			'timeout',
			'timed out',
			'connection',
			'network',
			'temporary',
			'try again',
			'rate limit',
			'too many requests',
		);

		foreach ( $retryable_patterns as $pattern ) {
			if ( strpos( $message, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
