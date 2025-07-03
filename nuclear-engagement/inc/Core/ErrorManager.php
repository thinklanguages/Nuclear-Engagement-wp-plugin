<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprehensive error management system.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ErrorManager {
	/**
	 * Error severity levels.
	 */
	public const SEVERITY_CRITICAL = 'critical';  // Service unavailable
	public const SEVERITY_HIGH = 'high';         // Feature broken
	public const SEVERITY_MEDIUM = 'medium';     // Degraded performance  
	public const SEVERITY_LOW = 'low';           // Minor issues

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
	 * Error tracking and analytics.
	 *
	 * @var array<string, array>
	 */
	private static array $error_tracking = [];

	/**
	 * Security event monitoring.
	 *
	 * @var array<string, array{count: int, first_seen: int, last_seen: int, sources: array}>
	 */
	private static array $security_events = [];

	/**
	 * Rate limiting for error responses.
	 *
	 * @var array<string, array{count: int, window_start: int}>
	 */
	private static array $rate_limits = [];

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
	 * Initialize error manager.
	 */
	public static function init(): void {
		// Set up WordPress error hooks
		add_action( 'wp_die_handler', [ self::class, 'handle_wp_die' ] );
		add_filter( 'wp_die_ajax_handler', [ self::class, 'handle_ajax_error' ] );
		add_filter( 'wp_die_json_handler', [ self::class, 'handle_json_error' ] );

		// Hook into PHP error handling
		set_error_handler( [ self::class, 'handle_php_error' ] );
		set_exception_handler( [ self::class, 'handle_uncaught_exception' ] );

		// Register shutdown handler for fatal errors
		register_shutdown_function( [ self::class, 'handle_shutdown_error' ] );

		// Clean up old error data periodically
		if ( ! wp_next_scheduled( 'nuclen_cleanup_error_data' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_error_data' );
		}
		add_action( 'nuclen_cleanup_error_data', [ self::class, 'cleanup_error_data' ] );
	}

	/**
	 * Handle error with comprehensive context and recovery.
	 *
	 * @param \Throwable|string $error        Error or message.
	 * @param string            $severity     Error severity.
	 * @param string            $category     Error category.
	 * @param array             $context      Additional context.
	 * @param callable|null     $recovery     Recovery callback.
	 * @return ErrorContext Error context object.
	 */
	public static function handle_error( 
		$error, 
		string $severity = self::SEVERITY_MEDIUM, 
		string $category = self::CATEGORY_VALIDATION,
		array $context = [],
		?callable $recovery = null
	): ErrorContext {
		$error_context = self::create_error_context( $error, $severity, $category, $context );
		
		// Security screening
		if ( self::is_security_related( $error_context ) ) {
			self::handle_security_event( $error_context );
		}

		// Rate limiting check
		if ( self::should_rate_limit( $error_context ) ) {
			self::apply_rate_limit( $error_context );
			return $error_context;
		}

		// Track error for analytics
		self::track_error( $error_context );

		// Attempt recovery if provided
		if ( $recovery ) {
			try {
				$recovery_result = call_user_func( $recovery, $error_context );
				$error_context->set_recovery_attempted( true );
				$error_context->set_recovery_successful( $recovery_result !== false );
			} catch ( \Throwable $recovery_error ) {
				$error_context->add_context( 'recovery_error', $recovery_error->getMessage() );
			}
		}

		// Process error asynchronously for non-critical errors
		if ( $severity !== self::SEVERITY_CRITICAL ) {
			self::queue_async_processing( $error_context );
		} else {
			self::process_critical_error( $error_context );
		}

		// Log error with appropriate level
		self::log_error( $error_context );

		// Notify monitoring systems
		self::notify_monitoring( $error_context );

		return $error_context;
	}

	/**
	 * Handle database-specific errors.
	 *
	 * @param \Throwable $error Database error.
	 * @param array      $query_context Query context.
	 * @return ErrorContext Error context.
	 */
	public static function handle_database_error( \Throwable $error, array $query_context = [] ): ErrorContext {
		$context = array_merge( $query_context, [
			'database_error_code' => $error->getCode(),
			'query_type' => self::detect_query_type( $query_context['sql'] ?? '' ),
			'affected_tables' => self::extract_table_names( $query_context['sql'] ?? '' ),
		] );

		// Determine severity based on error type
		$severity = self::classify_database_error_severity( $error );
		
		return self::handle_error( $error, $severity, self::CATEGORY_DATABASE, $context, function( $error_context ) {
			// Attempt database recovery
			return self::attempt_database_recovery( $error_context );
		} );
	}

	/**
	 * Handle API-related errors.
	 *
	 * @param \Throwable $error API error.
	 * @param array      $api_context API request context.
	 * @return ErrorContext Error context.
	 */
	public static function handle_api_error( \Throwable $error, array $api_context = [] ): ErrorContext {
		$context = array_merge( $api_context, [
			'endpoint' => $api_context['url'] ?? 'unknown',
			'method' => $api_context['method'] ?? 'unknown',
			'response_code' => $api_context['response_code'] ?? null,
			'timeout' => $api_context['timeout'] ?? null,
		] );

		$severity = self::classify_api_error_severity( $error, $api_context );

		return self::handle_error( $error, $severity, self::CATEGORY_EXTERNAL_API, $context, function( $error_context ) {
			// Attempt API recovery with circuit breaker
			return ErrorRecovery::executeWithCircuitBreaker(
				'api_' . ($error_context->get_context()['endpoint'] ?? 'unknown'),
				function() use ( $error_context ) {
					// Retry with exponential backoff
					return true; // Placeholder for actual retry logic
				},
				[ 'failure_threshold' => 3, 'timeout' => 300 ]
			);
		} );
	}

	/**
	 * Handle security-related errors.
	 *
	 * @param string $event_type Security event type.
	 * @param array  $context Security context.
	 * @return ErrorContext Error context.
	 */
	public static function handle_security_error( string $event_type, array $context = [] ): ErrorContext {
		$enhanced_context = array_merge( $context, [
			'security_event_type' => $event_type,
			'user_ip' => self::get_client_ip(),
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
			'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
			'timestamp' => time(),
		] );

		$error_message = "Security event detected: {$event_type}";
		
		return self::handle_error( 
			$error_message, 
			self::SEVERITY_HIGH, 
			self::CATEGORY_SECURITY, 
			$enhanced_context,
			function( $error_context ) use ( $event_type ) {
				return self::apply_security_mitigation( $event_type, $error_context );
			}
		);
	}

	/**
	 * Get user-friendly error message based on context.
	 *
	 * @param ErrorContext $error_context Error context.
	 * @param bool         $is_admin Whether user is admin.
	 * @return string User-friendly message.
	 */
	public static function get_user_message( ErrorContext $error_context, bool $is_admin = false ): string {
		$category = $error_context->get_category();
		$severity = $error_context->get_severity();

		// Admin users get more detailed messages
		if ( $is_admin && $error_context->get_admin_message() ) {
			return $error_context->get_admin_message();
		}

		// Return user-friendly message or generate one
		if ( $error_context->get_user_message() ) {
			return $error_context->get_user_message();
		}

		return self::generate_user_friendly_message( $category, $severity );
	}

	/**
	 * Get error analytics and trends.
	 *
	 * @param int $time_window Time window in seconds.
	 * @return array Error analytics data.
	 */
	public static function get_error_analytics( int $time_window = 3600 ): array {
		$cutoff = time() - $time_window;
		$analytics = [
			'total_errors' => 0,
			'by_severity' => [],
			'by_category' => [],
			'trending_errors' => [],
			'recovery_rate' => 0,
			'security_events' => 0,
		];

		foreach ( self::$error_tracking as $error_id => $error_data ) {
			if ( $error_data['timestamp'] < $cutoff ) {
				continue;
			}

			$analytics['total_errors']++;
			
			$severity = $error_data['severity'];
			$category = $error_data['category'];
			
			$analytics['by_severity'][$severity] = ( $analytics['by_severity'][$severity] ?? 0 ) + 1;
			$analytics['by_category'][$category] = ( $analytics['by_category'][$category] ?? 0 ) + 1;

			if ( $category === self::CATEGORY_SECURITY ) {
				$analytics['security_events']++;
			}

			if ( $error_data['recovery_attempted'] ?? false ) {
				$analytics['recovery_rate'] += ( $error_data['recovery_successful'] ?? false ) ? 1 : 0;
			}
		}

		if ( $analytics['total_errors'] > 0 ) {
			$analytics['recovery_rate'] = $analytics['recovery_rate'] / $analytics['total_errors'];
		}

		return $analytics;
	}

	/**
	 * Create comprehensive error context.
	 *
	 * @param \Throwable|string $error Error or message.
	 * @param string            $severity Error severity.
	 * @param string            $category Error category.
	 * @param array             $context Additional context.
	 * @return ErrorContext Error context object.
	 */
	private static function create_error_context( $error, string $severity, string $category, array $context ): ErrorContext {
		$error_id = wp_generate_uuid4();
		$timestamp = time();

		if ( $error instanceof \Throwable ) {
			$message = $error->getMessage();
			$file = $error->getFile();
			$line = $error->getLine();
			$stack_trace = $error->getTraceAsString();
			$error_type = get_class( $error );
		} else {
			$message = (string) $error;
			$file = '';
			$line = 0;
			$stack_trace = '';
			$error_type = 'string';
		}

		// Enhanced context collection
		$enhanced_context = array_merge( $context, [
			'timestamp' => $timestamp,
			'error_type' => $error_type,
			'file' => $file,
			'line' => $line,
			'user_context' => self::get_user_context(),
			'request_context' => self::get_request_context(),
			'system_context' => self::get_system_context(),
			'wordpress_context' => self::get_wordpress_context(),
		] );

		// Sanitize sensitive data
		$enhanced_context = self::sanitize_context( $enhanced_context );

		return new ErrorContext(
			$error_id,
			$message,
			$severity,
			$category,
			$enhanced_context,
			$stack_trace,
			$timestamp
		);
	}

	/**
	 * Get comprehensive user context.
	 *
	 * @return array User context.
	 */
	private static function get_user_context(): array {
		$user = wp_get_current_user();
		
		return [
			'user_id' => $user->ID,
			'user_login' => $user->user_login,
			'user_roles' => $user->roles,
			'user_capabilities' => array_keys( $user->caps ),
			'is_admin' => current_user_can( 'manage_options' ),
			'is_logged_in' => is_user_logged_in(),
		];
	}

	/**
	 * Get comprehensive request context.
	 *
	 * @return array Request context.
	 */
	private static function get_request_context(): array {
		return [
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
			'query_string' => $_SERVER['QUERY_STRING'] ?? '',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
			'referer' => $_SERVER['HTTP_REFERER'] ?? '',
			'client_ip' => self::get_client_ip(),
			'is_ajax' => defined( 'DOING_AJAX' ) && DOING_AJAX,
			'is_rest' => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'is_cron' => defined( 'DOING_CRON' ) && DOING_CRON,
			'is_admin' => is_admin(),
		];
	}

	/**
	 * Get system context information.
	 *
	 * @return array System context.
	 */
	private static function get_system_context(): array {
		return [
			'php_version' => PHP_VERSION,
			'memory_usage' => memory_get_usage( true ),
			'memory_peak' => memory_get_peak_usage( true ),
			'memory_limit' => ini_get( 'memory_limit' ),
			'time_limit' => ini_get( 'max_execution_time' ),
			'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
			'load_average' => function_exists( 'sys_getloadavg' ) ? sys_getloadavg() : null,
		];
	}

	/**
	 * Get WordPress context information.
	 *
	 * @return array WordPress context.
	 */
	private static function get_wordpress_context(): array {
		global $wp_version, $wpdb;

		return [
			'wp_version' => $wp_version,
			'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'multisite' => is_multisite(),
			'active_theme' => get_option( 'stylesheet' ),
			'active_plugins' => get_option( 'active_plugins' ),
			'db_version' => $wpdb->db_version(),
			'db_queries' => $wpdb->num_queries,
			'current_screen' => function_exists( 'get_current_screen' ) ? get_current_screen()?->id : null,
		];
	}

	/**
	 * Sanitize context to remove sensitive information.
	 *
	 * @param array $context Context to sanitize.
	 * @return array Sanitized context.
	 */
	private static function sanitize_context( array $context ): array {
		$sanitized = [];

		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$sanitized[$key] = self::sanitize_context( $value );
			} elseif ( is_string( $value ) ) {
				$sanitized[$key] = self::redact_sensitive_data( $value );
			} else {
				$sanitized[$key] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Redact sensitive data from strings.
	 *
	 * @param string $text Text to redact.
	 * @return string Redacted text.
	 */
	private static function redact_sensitive_data( string $text ): string {
		foreach ( self::$sensitive_patterns as $pattern ) {
			$text = preg_replace( $pattern, '[REDACTED]', $text );
		}

		return $text;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP.
	 */
	private static function get_client_ip(): string {
		$headers = [
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_X_REAL_IP',           // Nginx
			'HTTP_X_FORWARDED_FOR',     // Load balancer/proxy
			'HTTP_X_FORWARDED',         // Proxy
			'HTTP_X_CLUSTER_CLIENT_IP', // Cluster
			'HTTP_FORWARDED_FOR',       // Proxy
			'HTTP_FORWARDED',           // Proxy
			'REMOTE_ADDR'               // Standard
		];

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[$header] ) ) {
				$ips = explode( ',', $_SERVER[$header] );
				$ip = trim( $ips[0] );
				
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	}

	/**
	 * Additional methods for error classification, security handling, recovery attempts, etc.
	 * [Implementation continues with remaining private methods...]
	 */

	// Placeholder implementations for brevity
	private static function is_security_related( ErrorContext $context ): bool { return false; }
	private static function handle_security_event( ErrorContext $context ): void {}
	private static function should_rate_limit( ErrorContext $context ): bool { return false; }
	private static function apply_rate_limit( ErrorContext $context ): void {}
	private static function track_error( ErrorContext $context ): void {}
	private static function queue_async_processing( ErrorContext $context ): void {}
	private static function process_critical_error( ErrorContext $context ): void {}
	private static function log_error( ErrorContext $context ): void {}
	private static function notify_monitoring( ErrorContext $context ): void {}
	private static function classify_database_error_severity( \Throwable $error ): string { return self::SEVERITY_MEDIUM; }
	private static function attempt_database_recovery( ErrorContext $context ): bool { return false; }
	private static function classify_api_error_severity( \Throwable $error, array $context ): string { return self::SEVERITY_MEDIUM; }
	private static function apply_security_mitigation( string $event_type, ErrorContext $context ): bool { return false; }
	private static function generate_user_friendly_message( string $category, string $severity ): string { return 'An error occurred. Please try again.'; }
	private static function detect_query_type( string $sql ): string { return 'unknown'; }
	private static function extract_table_names( string $sql ): array { return []; }
	public static function handle_wp_die( $handler ) { return $handler; }
	public static function handle_ajax_error( $handler ) { return $handler; }
	public static function handle_json_error( $handler ) { return $handler; }
	public static function handle_php_error( $errno, $errstr, $errfile, $errline ) { return false; }
	public static function handle_uncaught_exception( \Throwable $exception ): void {}
	public static function handle_shutdown_error(): void {}
	public static function cleanup_error_data(): void {}
}