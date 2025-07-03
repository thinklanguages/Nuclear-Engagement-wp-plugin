<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Utils\ServerUtils;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified error handling system that consolidates all error processing.
 *
 * This class replaces the multiple error handling classes with a single,
 * simpler, and more secure implementation.
 *
 * @package NuclearEngagement\Core
 * @since   1.0.0
 */
final class UnifiedErrorHandler {

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
	public const CATEGORY_SECURITY = 'security';
	public const CATEGORY_DATABASE = 'database';
	public const CATEGORY_NETWORK = 'network';
	public const CATEGORY_VALIDATION = 'validation';
	public const CATEGORY_PERMISSIONS = 'permissions';
	public const CATEGORY_RESOURCE = 'resource';
	public const CATEGORY_GENERAL = 'general';

	/**
	 * Rate limiting configuration.
	 */
	private const RATE_LIMITS = [
		self::CATEGORY_SECURITY => 3,   // 3 per 10 minutes
		self::CATEGORY_DATABASE => 10,  // 10 per 5 minutes  
		'default' => 20,                // 20 per 5 minutes
	];

	/**
	 * Instance for singleton pattern.
	 */
	private static ?self $instance = null;

	/**
	 * Error statistics.
	 */
	private array $error_stats = [];

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton.
	 */
	private function __construct() {
		$this->init_error_handlers();
	}

	/**
	 * Initialize error handlers.
	 */
	private function init_error_handlers(): void {
		// PHP error handlers
		set_error_handler( [ $this, 'handle_php_error' ] );
		set_exception_handler( [ $this, 'handle_exception' ] );
		register_shutdown_function( [ $this, 'handle_fatal_error' ] );

		// WordPress hooks
		add_action( 'wp_die_handler', [ $this, 'handle_wp_die' ] );
	}

	/**
	 * Handle error with unified processing.
	 *
	 * @param string $message   Error message.
	 * @param string $category  Error category.
	 * @param string $severity  Error severity.
	 * @param array  $context   Additional context.
	 * @return bool Whether error was handled successfully.
	 */
	public function handle_error(
		string $message,
		string $category = self::CATEGORY_GENERAL,
		string $severity = self::SEVERITY_MEDIUM,
		array $context = []
	): bool {
		try {
			// Check rate limiting
			if ( $this->is_rate_limited( $category ) ) {
				return false;
			}

			// Create error context
			$error_data = $this->create_error_data( $message, $category, $severity, $context );

			// Track statistics
			$this->track_error( $error_data );

			// Log error
			$this->log_error( $error_data );

			// Handle security events specially
			if ( $category === self::CATEGORY_SECURITY ) {
				$this->handle_security_event( $error_data );
			}

			// Attempt automatic recovery
			$this->attempt_recovery( $error_data );

			return true;

		} catch ( \Throwable $e ) {
			// Fallback logging to prevent error loops
			error_log( 'Nuclear Engagement: Error handler failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Handle PHP errors.
	 */
	public function handle_php_error( int $errno, string $errstr, string $errfile = '', int $errline = 0 ): bool {
		$severity = $this->map_php_error_severity( $errno );
		$category = $this->categorize_error_message( $errstr );
		
		$context = [
			'file' => $errfile,
			'line' => $errline,
			'error_type' => $this->get_error_type_name( $errno ),
		];

		$this->handle_error( $errstr, $category, $severity, $context );
		
		// Don't suppress default error handling
		return false;
	}

	/**
	 * Handle uncaught exceptions.
	 */
	public function handle_exception( \Throwable $exception ): void {
		$severity = $this->map_exception_severity( $exception );
		$category = $this->categorize_error_message( $exception->getMessage() );
		
		$context = [
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $this->sanitize_stack_trace( $exception->getTraceAsString() ),
			'exception_class' => get_class( $exception ),
		];

		$this->handle_error( $exception->getMessage(), $category, $severity, $context );
	}

	/**
	 * Handle fatal errors.
	 */
	public function handle_fatal_error(): void {
		$error = error_get_last();
		
		if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
			$context = [
				'file' => $error['file'] ?? '',
				'line' => $error['line'] ?? 0,
				'error_type' => $this->get_error_type_name( $error['type'] ),
			];

			$this->handle_error( $error['message'], self::CATEGORY_GENERAL, self::SEVERITY_CRITICAL, $context );
		}
	}

	/**
	 * Handle WordPress die events.
	 */
	public function handle_wp_die( $handler ) {
		return function( $message, $title = '', $args = [] ) use ( $handler ) {
			$context = [
				'title' => $title,
				'args' => $args,
				'wp_die' => true,
			];
			
			$this->handle_error( 
				is_string( $message ) ? $message : 'WordPress die event', 
				self::CATEGORY_GENERAL, 
				self::SEVERITY_HIGH, 
				$context 
			);
			
			return $handler( $message, $title, $args );
		};
	}

	/**
	 * Create error data structure.
	 */
	private function create_error_data( string $message, string $category, string $severity, array $context ): array {
		// Sanitize message
		$safe_message = $this->sanitize_error_message( $message );
		
		// Merge with system context
		$system_context = array_merge(
			ServerUtils::get_safe_context(),
			[
				'user_id' => get_current_user_id(),
				'memory_usage' => memory_get_usage( true ),
				'memory_peak' => memory_get_peak_usage( true ),
				'php_version' => PHP_VERSION,
				'wp_version' => get_bloginfo( 'version' ),
			],
			$this->sanitize_context( $context )
		);

		return [
			'id' => wp_generate_uuid4(),
			'message' => $safe_message,
			'category' => $category,
			'severity' => $severity,
			'context' => $system_context,
			'timestamp' => time(),
		];
	}

	/**
	 * Track error statistics.
	 */
	private function track_error( array $error_data ): void {
		$key = $error_data['category'] . '_' . $error_data['severity'];
		
		if ( ! isset( $this->error_stats[$key] ) ) {
			$this->error_stats[$key] = [
				'count' => 0,
				'first_seen' => time(),
				'last_seen' => time(),
			];
		}
		
		$this->error_stats[$key]['count']++;
		$this->error_stats[$key]['last_seen'] = time();
	}

	/**
	 * Check if error should be rate limited.
	 */
	private function is_rate_limited( string $category ): bool {
		$limit = self::RATE_LIMITS[$category] ?? self::RATE_LIMITS['default'];
		$key = "nuclen_error_limit_{$category}";
		$current_count = (int) get_transient( $key );
		
		if ( $current_count >= $limit ) {
			return true;
		}
		
		set_transient( $key, $current_count + 1, 300 ); // 5 minutes
		return false;
	}

	/**
	 * Log error to appropriate destinations.
	 */
	private function log_error( array $error_data ): void {
		$log_entry = sprintf(
			'[%s] %s (%s/%s) - Context: %s',
			gmdate( 'Y-m-d H:i:s' ),
			$error_data['message'],
			$error_data['category'],
			$error_data['severity'],
			wp_json_encode( $error_data['context'] )
		);

		// Use LoggingService if available
		if ( class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
			LoggingService::log( $log_entry );
		} else {
			error_log( "Nuclear Engagement: {$log_entry}" );
		}

		// Log critical errors to separate file
		if ( $error_data['severity'] === self::SEVERITY_CRITICAL ) {
			$this->log_critical_error( $log_entry );
		}
	}

	/**
	 * Handle security-related errors.
	 */
	private function handle_security_event( array $error_data ): void {
		// Log security event with high priority
		$security_log = sprintf(
			'SECURITY ALERT [%s]: %s | IP: %s | User: %d',
			$error_data['severity'],
			$error_data['message'],
			$error_data['context']['ip'] ?? 'unknown',
			$error_data['context']['user_id'] ?? 0
		);
		
		error_log( $security_log );
		
		// Trigger action for security monitoring
		do_action( 'nuclen_security_event', $error_data );
	}

	/**
	 * Attempt automatic error recovery.
	 */
	private function attempt_recovery( array $error_data ): void {
		switch ( $error_data['category'] ) {
			case self::CATEGORY_DATABASE:
				$this->attempt_database_recovery( $error_data );
				break;
			case self::CATEGORY_RESOURCE:
				$this->attempt_resource_recovery( $error_data );
				break;
		}
	}

	/**
	 * Map PHP error to severity.
	 */
	private function map_php_error_severity( int $errno ): string {
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
	 */
	private function map_exception_severity( \Throwable $exception ): string {
		if ( $exception instanceof \Error ) {
			return self::SEVERITY_CRITICAL;
		}
		if ( $exception instanceof \RuntimeException ) {
			return self::SEVERITY_HIGH;
		}
		return self::SEVERITY_MEDIUM;
	}

	/**
	 * Categorize error based on message content.
	 */
	private function categorize_error_message( string $message ): string {
		$message_lower = strtolower( $message );
		
		if ( strpos( $message_lower, 'database' ) !== false || strpos( $message_lower, 'mysql' ) !== false ) {
			return self::CATEGORY_DATABASE;
		}
		if ( strpos( $message_lower, 'permission' ) !== false || strpos( $message_lower, 'access' ) !== false ) {
			return self::CATEGORY_PERMISSIONS;
		}
		if ( strpos( $message_lower, 'memory' ) !== false || strpos( $message_lower, 'time' ) !== false ) {
			return self::CATEGORY_RESOURCE;
		}
		if ( strpos( $message_lower, 'security' ) !== false || strpos( $message_lower, 'token' ) !== false ) {
			return self::CATEGORY_SECURITY;
		}
		if ( strpos( $message_lower, 'validation' ) !== false || strpos( $message_lower, 'invalid' ) !== false ) {
			return self::CATEGORY_VALIDATION;
		}
		
		return self::CATEGORY_GENERAL;
	}

	/**
	 * Sanitize error message.
	 */
	private function sanitize_error_message( string $message ): string {
		// Remove sensitive patterns
		$patterns = [
			'/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[EMAIL]',
			'/api[_-]?key["\']?\s*[:=]\s*["\']?[\w-]+/' => 'api_key=[REDACTED]',
			'/password["\']?\s*[:=]\s*["\']?[^\s"\']+/' => 'password=[REDACTED]',
			'/token["\']?\s*[:=]\s*["\']?[\w-]+/' => 'token=[REDACTED]',
		];
		
		return preg_replace( array_keys( $patterns ), array_values( $patterns ), $message );
	}

	/**
	 * Sanitize context data.
	 */
	private function sanitize_context( array $context ): array {
		array_walk_recursive( $context, function( &$value ) {
			if ( is_string( $value ) ) {
				$value = $this->sanitize_error_message( $value );
			}
		} );
		return $context;
	}

	/**
	 * Sanitize stack trace.
	 */
	private function sanitize_stack_trace( string $trace ): string {
		// Remove file paths and sensitive information
		$sanitized = preg_replace( '/\/[^\s]+\//', '/[PATH]/', $trace );
		return $this->sanitize_error_message( $sanitized );
	}

	/**
	 * Get error type name.
	 */
	private function get_error_type_name( int $type ): string {
		$types = [
			E_ERROR => 'E_ERROR',
			E_WARNING => 'E_WARNING',
			E_PARSE => 'E_PARSE',
			E_NOTICE => 'E_NOTICE',
			E_CORE_ERROR => 'E_CORE_ERROR',
			E_CORE_WARNING => 'E_CORE_WARNING',
			E_COMPILE_ERROR => 'E_COMPILE_ERROR',
			E_USER_ERROR => 'E_USER_ERROR',
			E_USER_WARNING => 'E_USER_WARNING',
			E_USER_NOTICE => 'E_USER_NOTICE',
		];
		return $types[$type] ?? 'UNKNOWN';
	}

	/**
	 * Log critical errors to separate file.
	 */
	private function log_critical_error( string $entry ): void {
		$log_file = WP_CONTENT_DIR . '/nuclen-critical-errors.log';
		file_put_contents( $log_file, $entry . PHP_EOL, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Attempt database recovery.
	 */
	private function attempt_database_recovery( array $error_data ): void {
		// Clear any problematic caches
		wp_cache_flush();
		
		// Log recovery attempt
		error_log( 'Nuclear Engagement: Attempting database recovery after error' );
	}

	/**
	 * Attempt resource recovery.
	 */
	private function attempt_resource_recovery( array $error_data ): void {
		// Clear memory-intensive caches
		wp_cache_flush();
		
		// Force garbage collection
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
		
		error_log( 'Nuclear Engagement: Attempting resource recovery after error' );
	}

	/**
	 * Get error statistics.
	 */
	public function get_error_stats(): array {
		return $this->error_stats;
	}
}