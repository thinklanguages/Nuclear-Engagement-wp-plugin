<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core error handling functionality.
 * 
 * Handles the basic error processing without analytics or monitoring.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ErrorHandler {
	/**
	 * Error severity levels.
	 */
	public const SEVERITY_CRITICAL = 'critical';
	public const SEVERITY_HIGH = 'high';
	public const SEVERITY_MEDIUM = 'medium';
	public const SEVERITY_LOW = 'low';

	/**
	 * Error categories.
	 */
	public const CATEGORY_AUTHENTICATION = 'authentication';
	public const CATEGORY_DATABASE = 'database';
	public const CATEGORY_NETWORK = 'network';
	public const CATEGORY_VALIDATION = 'validation';
	public const CATEGORY_PERMISSIONS = 'permissions';
	public const CATEGORY_RESOURCE = 'resource';
	public const CATEGORY_SECURITY = 'security';
	public const CATEGORY_CONFIGURATION = 'configuration';
	public const CATEGORY_EXTERNAL_API = 'external_api';
	public const CATEGORY_FILE_SYSTEM = 'file_system';

	/**
	 * Sensitive data patterns for redaction.
	 *
	 * @var array<string>
	 */
	private static array $sensitive_patterns = [
		'/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',  // Email
		'/\b(?:\d{4}[-\s]?){3}\d{4}\b/',                          // Credit card
		'/\b\d{3}-\d{2}-\d{4}\b/',                                // SSN
		'/api[_-]?key["\']?\s*[:=]\s*["\']?[\w-]+/',             // API keys
		'/password["\']?\s*[:=]\s*["\']?[^"\'\\s]+/',            // Passwords
		'/token["\']?\s*[:=]\s*["\']?[\w.-]+/',                  // Tokens
		'/secret["\']?\s*[:=]\s*["\']?[\w.-]+/',                 // Secrets
	];

	/**
	 * Handle error with context.
	 *
	 * @param string $message Error message.
	 * @param string $severity Error severity level.
	 * @param string $category Error category.
	 * @param array $context Additional context data.
	 * @param \Throwable|null $exception Related exception if any.
	 * @return array Error data array.
	 */
	public static function handle_error(
		string $message,
		string $severity = self::SEVERITY_MEDIUM,
		string $category = self::CATEGORY_VALIDATION,
		array $context = [],
		?\Throwable $exception = null
	): array {
		$error_data = [
			'id' => self::generate_error_id(),
			'message' => self::redact_sensitive_data( $message ),
			'severity' => $severity,
			'category' => $category,
			'timestamp' => time(),
			'context' => self::prepare_context( $context ),
		];

		if ( $exception !== null ) {
			$error_data['exception'] = [
				'class' => get_class( $exception ),
				'message' => self::redact_sensitive_data( $exception->getMessage() ),
				'file' => $exception->getFile(),
				'line' => $exception->getLine(),
				'trace' => self::redact_stack_trace( $exception->getTraceAsString() ),
			];
		}

		// Log the error
		self::log_error( $error_data );

		return $error_data;
	}

	/**
	 * Handle PHP errors.
	 *
	 * @param int $severity Error severity.
	 * @param string $message Error message.
	 * @param string $file File where error occurred.
	 * @param int $line Line number where error occurred.
	 * @return bool Always returns false to continue normal error handling.
	 */
	public static function handle_php_error( int $severity, string $message, string $file, int $line ): bool {
		$error_severity = self::map_php_severity( $severity );
		
		self::handle_error(
			$message,
			$error_severity,
			self::CATEGORY_CONFIGURATION,
			[
				'file' => $file,
				'line' => $line,
				'php_severity' => $severity,
			]
		);

		return false; // Continue normal error handling
	}

	/**
	 * Handle uncaught exceptions.
	 *
	 * @param \Throwable $exception The uncaught exception.
	 */
	public static function handle_uncaught_exception( \Throwable $exception ): void {
		self::handle_error(
			'Uncaught exception: ' . $exception->getMessage(),
			self::SEVERITY_CRITICAL,
			self::determine_exception_category( $exception ),
			[],
			$exception
		);
	}

	/**
	 * Handle shutdown errors (fatal errors).
	 */
	public static function handle_shutdown_error(): void {
		$error = error_get_last();
		if ( $error !== null && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
			self::handle_error(
				$error['message'],
				self::SEVERITY_CRITICAL,
				self::CATEGORY_CONFIGURATION,
				[
					'file' => $error['file'],
					'line' => $error['line'],
					'type' => $error['type'],
				]
			);
		}
	}

	/**
	 * Generate unique error ID.
	 *
	 * @return string Unique error ID.
	 */
	private static function generate_error_id(): string {
		return 'err_' . wp_generate_password( 10, false, false );
	}

	/**
	 * Redact sensitive data from strings.
	 *
	 * @param string $data Data to redact.
	 * @return string Redacted data.
	 */
	private static function redact_sensitive_data( string $data ): string {
		foreach ( self::$sensitive_patterns as $pattern ) {
			$data = preg_replace( $pattern, '[REDACTED]', $data );
		}
		return $data;
	}

	/**
	 * Prepare context data for logging.
	 *
	 * @param array $context Raw context data.
	 * @return array Prepared context data.
	 */
	private static function prepare_context( array $context ): array {
		$prepared = [];
		
		foreach ( $context as $key => $value ) {
			if ( is_string( $value ) ) {
				$prepared[ $key ] = self::redact_sensitive_data( $value );
			} elseif ( is_scalar( $value ) ) {
				$prepared[ $key ] = $value;
			} else {
				$prepared[ $key ] = '[COMPLEX_DATA_REDACTED]';
			}
		}

		return $prepared;
	}

	/**
	 * Redact stack trace.
	 *
	 * @param string $trace Stack trace string.
	 * @return string Redacted stack trace.
	 */
	private static function redact_stack_trace( string $trace ): string {
		return self::redact_sensitive_data( $trace );
	}

	/**
	 * Log error data.
	 *
	 * @param array $error_data Error data to log.
	 */
	private static function log_error( array $error_data ): void {
		$log_message = sprintf(
			'[%s] %s - %s (ID: %s)',
			strtoupper( $error_data['severity'] ),
			$error_data['category'],
			$error_data['message'],
			$error_data['id']
		);

		error_log( $log_message );
	}

	/**
	 * Map PHP error severity to our severity levels.
	 *
	 * @param int $php_severity PHP error severity constant.
	 * @return string Our severity level.
	 */
	private static function map_php_severity( int $php_severity ): string {
		switch ( $php_severity ) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
				return self::SEVERITY_CRITICAL;
			
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				return self::SEVERITY_HIGH;
			
			case E_NOTICE:
			case E_USER_NOTICE:
			case E_STRICT:
				return self::SEVERITY_MEDIUM;
			
			default:
				return self::SEVERITY_LOW;
		}
	}

	/**
	 * Determine exception category based on exception type.
	 *
	 * @param \Throwable $exception Exception to categorize.
	 * @return string Exception category.
	 */
	private static function determine_exception_category( \Throwable $exception ): string {
		$class = get_class( $exception );
		
		if ( strpos( $class, 'Database' ) !== false || strpos( $class, 'PDO' ) !== false ) {
			return self::CATEGORY_DATABASE;
		}
		
		if ( strpos( $class, 'Network' ) !== false || strpos( $class, 'HTTP' ) !== false ) {
			return self::CATEGORY_NETWORK;
		}
		
		if ( strpos( $class, 'Auth' ) !== false || strpos( $class, 'Permission' ) !== false ) {
			return self::CATEGORY_AUTHENTICATION;
		}
		
		return self::CATEGORY_CONFIGURATION;
	}
}