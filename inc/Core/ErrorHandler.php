<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consolidated error handling for all core error processing.
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
		'/password["\']?\s*[:=]\s*["\']?[^\s"\']+/',             // Passwords
		'/token["\']?\s*[:=]\s*["\']?[\w-]+/',                   // Tokens
		'/secret["\']?\s*[:=]\s*["\']?[\w-]+/',                  // Secrets
	];

	/**
	 * Error rate limits per category.
	 *
	 * @var array<string, array{limit: int, window: int}>
	 */
	private static array $rate_limits = [
		self::CATEGORY_AUTHENTICATION => [ 'limit' => 5, 'window' => 300 ],   // 5 per 5 min
		self::CATEGORY_DATABASE => [ 'limit' => 10, 'window' => 60 ],         // 10 per minute
		self::CATEGORY_SECURITY => [ 'limit' => 3, 'window' => 600 ],         // 3 per 10 min
		'default' => [ 'limit' => 20, 'window' => 300 ],                      // 20 per 5 min
	];

	/**
	 * Error tracking storage.
	 *
	 * @var array<string, array{count: int, first_seen: int, last_seen: int}>
	 */
	private static array $error_tracking = [];

	/**
	 * Initialize error handler.
	 */
	public static function init(): void {
		// Register PHP error handlers
		set_error_handler( [ self::class, 'handle_php_error' ] );
		set_exception_handler( [ self::class, 'handle_exception' ] );
		register_shutdown_function( [ self::class, 'handle_fatal_error' ] );

		// WordPress error hooks
		add_action( 'wp_die_handler', [ self::class, 'handle_wp_die' ] );
		add_filter( 'wp_die_ajax_handler', [ self::class, 'handle_wp_die' ] );
		add_filter( 'wp_die_json_handler', [ self::class, 'handle_wp_die' ] );
	}

	/**
	 * Handle error with full processing.
	 *
	 * @param string $message Error message.
	 * @param string $category Error category.
	 * @param string $severity Error severity.
	 * @param array $context Additional context.
	 * @return bool Whether error was handled successfully.
	 */
	public static function handle_error( string $message, string $category = 'general', string $severity = self::SEVERITY_MEDIUM, array $context = [] ): bool {
		try {
			// Check rate limiting
			if ( self::should_rate_limit( $category ) ) {
				return false;
			}

			// Create error context
			$error_context = self::create_error_context( $message, $category, $severity, $context );

			// Track error
			self::track_error( $error_context );

			// Log error
			self::log_error( $error_context );

			// Handle security events
			if ( $category === self::CATEGORY_SECURITY ) {
				self::handle_security_event( $error_context );
			}

			// Attempt recovery if possible
			self::attempt_recovery( $error_context );

			return true;

		} catch ( \Throwable $e ) {
			// Fallback logging
			error_log( "Nuclear Engagement: Error handler failed: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Handle PHP errors.
	 *
	 * @param int $errno Error number.
	 * @param string $errstr Error message.
	 * @param string $errfile Error file.
	 * @param int $errline Error line.
	 * @return bool Always returns false to continue with default error handling.
	 */
	public static function handle_php_error( int $errno, string $errstr, string $errfile = '', int $errline = 0 ): bool {
		// Convert PHP error to our format
		$severity = self::map_php_error_severity( $errno );
		$category = self::categorize_php_error( $errstr );
		
		$context = [
			'file' => $errfile,
			'line' => $errline,
			'error_type' => self::get_error_type_name( $errno ),
		];

		self::handle_error( $errstr, $category, $severity, $context );
		
		// Don't suppress default error handling
		return false;
	}

	/**
	 * Handle uncaught exceptions.
	 *
	 * @param \Throwable $exception The exception.
	 */
	public static function handle_exception( \Throwable $exception ): void {
		$severity = self::map_exception_severity( $exception );
		$category = self::categorize_exception( $exception );
		
		$context = [
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => self::redact_sensitive_data( $exception->getTraceAsString() ),
			'exception_class' => get_class( $exception ),
		];

		self::handle_error( $exception->getMessage(), $category, $severity, $context );
	}

	/**
	 * Handle fatal errors.
	 */
	public static function handle_fatal_error(): void {
		$error = error_get_last();
		
		if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
			$context = [
				'file' => $error['file'] ?? '',
				'line' => $error['line'] ?? 0,
				'error_type' => self::get_error_type_name( $error['type'] ),
			];

			self::handle_error( $error['message'], 'fatal', self::SEVERITY_CRITICAL, $context );
		}
	}

	/**
	 * Handle WordPress die events.
	 *
	 * @param callable $handler Original handler.
	 * @return callable Modified handler.
	 */
	public static function handle_wp_die( $handler ) {
		return function( $message, $title = '', $args = [] ) use ( $handler ) {
			// Log WordPress die event
			$context = [
				'title' => $title,
				'args' => $args,
				'wp_die' => true,
			];
			
			self::handle_error( 
				is_string( $message ) ? $message : 'WordPress die event', 
				'wordpress', 
				self::SEVERITY_HIGH, 
				$context 
			);
			
			// Call original handler
			return $handler( $message, $title, $args );
		};
	}

	/**
	 * Create comprehensive error context.
	 *
	 * @param string $message Error message.
	 * @param string $category Error category.
	 * @param string $severity Error severity.
	 * @param array $context Additional context.
	 * @return ErrorContext Error context object.
	 */
	private static function create_error_context( string $message, string $category, string $severity, array $context ): ErrorContext {
		// Redact sensitive data from message and context
		$safe_message = self::redact_sensitive_data( $message );
		$safe_context = self::redact_context_data( $context );
		
		// Add system information
		$system_context = [
			'timestamp' => time(),
			'user_id' => get_current_user_id(),
			'user_ip' => self::get_client_ip(),
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
			'memory_usage' => memory_get_usage( true ),
			'memory_peak' => memory_get_peak_usage( true ),
			'php_version' => PHP_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
		];

		return new ErrorContext(
			wp_generate_uuid4(),
			$safe_message,
			$category,
			$severity,
			array_merge( $safe_context, $system_context )
		);
	}

	/**
	 * Track error occurrence.
	 *
	 * @param ErrorContext $error_context Error context.
	 */
	private static function track_error( ErrorContext $error_context ): void {
		$key = md5( $error_context->get_message() . $error_context->get_category() );
		
		if ( ! isset( self::$error_tracking[$key] ) ) {
			self::$error_tracking[$key] = [
				'count' => 0,
				'first_seen' => time(),
				'last_seen' => time(),
			];
		}
		
		self::$error_tracking[$key]['count']++;
		self::$error_tracking[$key]['last_seen'] = time();
	}

	/**
	 * Check if error should be rate limited.
	 *
	 * @param string $category Error category.
	 * @return bool Whether error should be rate limited.
	 */
	private static function should_rate_limit( string $category ): bool {
		$limits = self::$rate_limits[$category] ?? self::$rate_limits['default'];
		$key = "nuclen_error_limit_{$category}";
		$current_count = (int) get_transient( $key );
		
		if ( $current_count >= $limits['limit'] ) {
			return true;
		}
		
		set_transient( $key, $current_count + 1, $limits['window'] );
		return false;
	}

	/**
	 * Log error to appropriate destinations.
	 *
	 * @param ErrorContext $error_context Error context.
	 */
	private static function log_error( ErrorContext $error_context ): void {
		$log_entry = sprintf(
			'[%s] %s (%s/%s) - %s',
			date( 'Y-m-d H:i:s' ),
			$error_context->get_message(),
			$error_context->get_category(),
			$error_context->get_severity(),
			json_encode( $error_context->get_context() )
		);

		// WordPress error log
		error_log( "Nuclear Engagement: {$log_entry}" );

		// Custom log file for critical errors
		if ( $error_context->get_severity() === self::SEVERITY_CRITICAL ) {
			self::write_to_error_log( $log_entry );
		}
	}

	/**
	 * Handle security-related errors.
	 *
	 * @param ErrorContext $error_context Error context.
	 */
	private static function handle_security_event( ErrorContext $error_context ): void {
		// Notify monitoring systems
		ErrorMonitor::track_security_event( $error_context );
		
		// Block suspicious IPs if needed
		$context = $error_context->get_context();
		if ( isset( $context['user_ip'] ) && self::is_suspicious_activity( $error_context ) ) {
			// Could implement IP blocking here
			do_action( 'nuclen_suspicious_activity', $context['user_ip'], $error_context );
		}
	}

	/**
	 * Attempt error recovery.
	 *
	 * @param ErrorContext $error_context Error context.
	 */
	private static function attempt_recovery( ErrorContext $error_context ): void {
		// Basic recovery based on category
		switch ( $error_context->get_category() ) {
			case self::CATEGORY_DATABASE:
				self::attempt_database_recovery( $error_context );
				break;
			case self::CATEGORY_NETWORK:
				self::attempt_network_recovery( $error_context );
				break;
			case self::CATEGORY_RESOURCE:
				self::attempt_resource_recovery( $error_context );
				break;
		}
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
	 * Redact sensitive data from context arrays.
	 *
	 * @param array $context Context data.
	 * @return array Redacted context.
	 */
	private static function redact_context_data( array $context ): array {
		array_walk_recursive( $context, function( &$value ) {
			if ( is_string( $value ) ) {
				$value = self::redact_sensitive_data( $value );
			}
		} );
		return $context;
	}

	/**
	 * Map PHP error number to severity.
	 *
	 * @param int $errno PHP error number.
	 * @return string Severity level.
	 */
	private static function map_php_error_severity( int $errno ): string {
		switch ( $errno ) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
				return self::SEVERITY_CRITICAL;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
				return self::SEVERITY_HIGH;
			case E_NOTICE:
			case E_USER_NOTICE:
				return self::SEVERITY_MEDIUM;
			default:
				return self::SEVERITY_LOW;
		}
	}

	/**
	 * Map exception to severity.
	 *
	 * @param \Throwable $exception Exception.
	 * @return string Severity level.
	 */
	private static function map_exception_severity( \Throwable $exception ): string {
		if ( $exception instanceof \Error ) {
			return self::SEVERITY_CRITICAL;
		}
		if ( $exception instanceof \RuntimeException ) {
			return self::SEVERITY_HIGH;
		}
		return self::SEVERITY_MEDIUM;
	}

	/**
	 * Categorize PHP error.
	 *
	 * @param string $message Error message.
	 * @return string Category.
	 */
	private static function categorize_php_error( string $message ): string {
		if ( strpos( $message, 'database' ) !== false || strpos( $message, 'mysql' ) !== false ) {
			return self::CATEGORY_DATABASE;
		}
		if ( strpos( $message, 'permission' ) !== false || strpos( $message, 'access' ) !== false ) {
			return self::CATEGORY_PERMISSIONS;
		}
		if ( strpos( $message, 'memory' ) !== false || strpos( $message, 'time' ) !== false ) {
			return self::CATEGORY_RESOURCE;
		}
		return 'general';
	}

	/**
	 * Categorize exception.
	 *
	 * @param \Throwable $exception Exception.
	 * @return string Category.
	 */
	private static function categorize_exception( \Throwable $exception ): string {
		$class = get_class( $exception );
		$message = $exception->getMessage();
		
		if ( strpos( $class, 'Database' ) !== false || strpos( $message, 'database' ) !== false ) {
			return self::CATEGORY_DATABASE;
		}
		if ( strpos( $class, 'Network' ) !== false || strpos( $message, 'network' ) !== false ) {
			return self::CATEGORY_NETWORK;
		}
		if ( strpos( $class, 'Security' ) !== false || strpos( $message, 'security' ) !== false ) {
			return self::CATEGORY_SECURITY;
		}
		return 'general';
	}

	// Helper methods for recovery attempts
	private static function attempt_database_recovery( ErrorContext $context ): void {
		// Database recovery logic
	}

	private static function attempt_network_recovery( ErrorContext $context ): void {
		// Network recovery logic  
	}

	private static function attempt_resource_recovery( ErrorContext $context ): void {
		// Resource recovery logic
	}

	private static function get_error_type_name( int $type ): string {
		$types = [
			E_ERROR => 'E_ERROR',
			E_WARNING => 'E_WARNING',
			E_PARSE => 'E_PARSE',
			E_NOTICE => 'E_NOTICE',
		];
		return $types[$type] ?? 'UNKNOWN';
	}

	private static function get_client_ip(): string {
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}

	private static function is_suspicious_activity( ErrorContext $context ): bool {
		// Implement suspicious activity detection
		return false;
	}

	private static function write_to_error_log( string $entry ): void {
		$log_file = WP_CONTENT_DIR . '/nuclen-errors.log';
		file_put_contents( $log_file, $entry . PHP_EOL, FILE_APPEND | LOCK_EX );
	}
}