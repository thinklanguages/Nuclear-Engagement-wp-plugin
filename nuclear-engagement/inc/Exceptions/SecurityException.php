<?php
/**
 * SecurityException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Exceptions
 */

declare(strict_types=1);

namespace NuclearEngagement\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception for security-related errors
 */
class SecurityException extends BaseException {
	/**
	 * @var string Exception severity - always critical for security issues
	 */
	protected string $severity = 'critical';

	/**
	 * @var bool Whether to log this security event
	 */
	protected bool $shouldLog = true;

	/**
	 * Create exception for authentication failure
	 *
	 * @param string $reason Reason for authentication failure
	 * @param array  $context Additional context
	 * @return self
	 */
	public static function authenticationFailed( string $reason, array $context = array() ): self {
		return new self(
			'Authentication failed: ' . $reason,
			401,
			null,
			array_merge(
				$context,
				array(
					'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
					'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
					'timestamp'  => time(),
				)
			)
		);
	}

	/**
	 * Create exception for authorization failure
	 *
	 * @param string $resource Resource being accessed
	 * @param string $permission Required permission
	 * @return self
	 */
	public static function authorizationFailed( string $resource, string $permission ): self {
		return new self(
			sprintf( 'Unauthorized access to %s (requires %s)', $resource, $permission ),
			403,
			null,
			array(
				'resource'            => $resource,
				'required_permission' => $permission,
				'user_id'             => get_current_user_id(),
				'user_caps'           => wp_get_current_user()->allcaps,
			)
		);
	}

	/**
	 * Create exception for nonce verification failure
	 *
	 * @param string $action Nonce action
	 * @return self
	 */
	public static function nonceVerificationFailed( string $action ): self {
		return new self(
			'Nonce verification failed',
			403,
			null,
			array(
				'action'         => $action,
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
				'referer'        => $_SERVER['HTTP_REFERER'] ?? 'none',
			)
		);
	}

	/**
	 * Create exception for CSRF attack detection
	 *
	 * @param string $details Attack details
	 * @return self
	 */
	public static function csrfDetected( string $details ): self {
		return new self(
			'Potential CSRF attack detected',
			403,
			null,
			array(
				'details'    => $details,
				'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
				'referer'    => $_SERVER['HTTP_REFERER'] ?? 'none',
			)
		);
	}

	/**
	 * Create exception for rate limit exceeded
	 *
	 * @param string $action Rate-limited action
	 * @param int    $limit Rate limit
	 * @param int    $window Time window in seconds
	 * @return self
	 */
	public static function rateLimitExceeded( string $action, int $limit, int $window ): self {
		return new self(
			sprintf( 'Rate limit exceeded for %s: %d requests per %d seconds', $action, $limit, $window ),
			429,
			null,
			array(
				'action'     => $action,
				'limit'      => $limit,
				'window'     => $window,
				'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
			)
		);
	}

	/**
	 * Create exception for suspicious activity
	 *
	 * @param string $activity Suspicious activity description
	 * @param array  $indicators Indicators of suspicious behavior
	 * @return self
	 */
	public static function suspiciousActivity( string $activity, array $indicators = array() ): self {
		return new self(
			'Suspicious activity detected: ' . $activity,
			403,
			null,
			array(
				'activity'   => $activity,
				'indicators' => $indicators,
				'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
				'user_id'    => get_current_user_id(),
			)
		);
	}

	/**
	 * Create exception for invalid API key
	 *
	 * @param string $key_hint Partial API key for logging (first 4 chars)
	 * @return self
	 */
	public static function invalidApiKey( string $key_hint = '' ): self {
		return new self(
			'Invalid or missing API key',
			401,
			null,
			array(
				'key_hint'   => $key_hint,
				'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
			)
		);
	}

	/**
	 * Get user-friendly message
	 *
	 * @return string
	 */
	public function getUserMessage(): string {
		if ( ! empty( $this->userMessage ) ) {
			return $this->userMessage;
		}

		// Provide generic security messages to avoid information disclosure
		switch ( $this->getCode() ) {
			case 401:
				return __( 'Authentication required. Please log in and try again.', 'nuclear-engagement' );
			case 403:
				return __( 'You do not have permission to perform this action.', 'nuclear-engagement' );
			case 429:
				return __( 'Too many requests. Please wait a moment and try again.', 'nuclear-engagement' );
			default:
				return __( 'A security error occurred. Please contact support if this persists.', 'nuclear-engagement' );
		}
	}

	/**
	 * Determine if this exception should trigger additional security measures
	 *
	 * @return bool
	 */
	public function requiresSecurityResponse(): bool {
		// Critical security exceptions require immediate response
		$critical_patterns = array(
			'CSRF',
			'SQL injection',
			'XSS',
			'suspicious activity',
		);

		foreach ( $critical_patterns as $pattern ) {
			if ( stripos( $this->getMessage(), $pattern ) !== false ) {
				return true;
			}
		}

		// Multiple failed auth attempts
		if ( $this->getCode() === 401 && isset( $this->context['attempts'] ) && $this->context['attempts'] > 3 ) {
			return true;
		}

		return false;
	}
}
