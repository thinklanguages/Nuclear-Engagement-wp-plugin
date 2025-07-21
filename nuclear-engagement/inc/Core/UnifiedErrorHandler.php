<?php
/**
 * UnifiedErrorHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

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
	public const SEVERITY_HIGH     = 'high';
	public const SEVERITY_MEDIUM   = 'medium';
	public const SEVERITY_LOW      = 'low';

	/**
	 * Error categories.
	 */
	public const CATEGORY_SECURITY    = 'security';
	public const CATEGORY_DATABASE    = 'database';
	public const CATEGORY_NETWORK     = 'network';
	public const CATEGORY_VALIDATION  = 'validation';
	public const CATEGORY_PERMISSIONS = 'permissions';
	public const CATEGORY_RESOURCE    = 'resource';
	public const CATEGORY_GENERAL     = 'general';

	/**
	 * Rate limiting configuration.
	 */
	private const RATE_LIMITS = array(
		self::CATEGORY_SECURITY => 3,   // 3 per 10 minutes.
		self::CATEGORY_DATABASE => 10,  // 10 per 5 minutes.
		'default'               => 20,                // 20 per 5 minutes.
	);

	/**
	 * Instance for singleton pattern.
	 */
	private static ?self $instance = null;

	/**
	 * Error statistics.
	 */
	private array $error_stats = array();

	/**
	 * Recovery strategies registry.
	 */
	private array $recovery_strategies = array();

	/**
	 * Recovery attempt history.
	 */
	private array $recovery_history = array();

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
		$this->register_recovery_strategies();
	}

	/**
	 * Initialize error handlers.
	 */
	private function init_error_handlers(): void {
		// PHP error handlers.
		set_error_handler( array( $this, 'handle_php_error' ) );
		set_exception_handler( array( $this, 'handle_exception' ) );
		register_shutdown_function( array( $this, 'handle_fatal_error' ) );

		// WordPress hooks.
		add_action( 'wp_die_handler', array( $this, 'handle_wp_die' ) );
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
		array $context = array()
	): bool {
		try {
			// Check rate limiting.
			if ( $this->is_rate_limited( $category ) ) {
				return false;
			}

			// Create error context.
			$error_data = $this->create_error_data( $message, $category, $severity, $context );

			// Track statistics.
			$this->track_error( $error_data );

			// Log error.
			$this->log_error( $error_data );

			// Trigger metrics tracking
			do_action( 'nuclen_error_logged', $error_data, $context['service'] ?? 'unknown' );

			// Handle security events specially.
			if ( $category === self::CATEGORY_SECURITY ) {
				$this->handle_security_event( $error_data );
			}

			// Attempt automatic recovery.
			$this->attempt_recovery( $error_data );

			return true;

		} catch ( \Throwable $e ) {
			// Fallback logging to prevent error loops.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[CRITICAL] Error handler failed: %s | File: %s:%d',
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				)
			);
			return false;
		}
	}

	/**
	 * Handle PHP errors.
	 */
	public function handle_php_error( int $errno, string $errstr, string $errfile = '', int $errline = 0 ): bool {
		// Only handle errors from our plugin
		if ( ! $this->is_plugin_error( $errfile ) ) {
			return false;
		}

		$severity = $this->map_php_error_severity( $errno );
		$category = $this->categorize_error_message( $errstr );

		$context = array(
			'file'       => $errfile,
			'line'       => $errline,
			'error_type' => $this->get_error_type_name( $errno ),
		);

		$this->handle_error( $errstr, $category, $severity, $context );

		// Don't suppress default error handling.
		return false;
	}

	/**
	 * Handle uncaught exceptions.
	 */
	public function handle_exception( \Throwable $exception ): void {
		// Only handle exceptions from our plugin
		if ( ! $this->is_plugin_error( $exception->getFile() ) && ! $this->is_plugin_exception( $exception ) ) {
			return;
		}

		$severity = $this->map_exception_severity( $exception );
		$category = $this->categorize_error_message( $exception->getMessage() );

		$context = array(
			'file'            => $exception->getFile(),
			'line'            => $exception->getLine(),
			'trace'           => $this->sanitize_stack_trace( $exception->getTraceAsString() ),
			'exception_class' => get_class( $exception ),
		);

		$this->handle_error( $exception->getMessage(), $category, $severity, $context );
	}

	/**
	 * Handle fatal errors.
	 */
	public function handle_fatal_error(): void {
		$error = error_get_last();

		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			// Only handle fatal errors from our plugin
			if ( ! $this->is_plugin_error( $error['file'] ?? '' ) ) {
				return;
			}

			$context = array(
				'file'       => $error['file'] ?? '',
				'line'       => $error['line'] ?? 0,
				'error_type' => $this->get_error_type_name( $error['type'] ),
			);

			$this->handle_error( $error['message'], self::CATEGORY_GENERAL, self::SEVERITY_CRITICAL, $context );
		}
	}

	/**
	 * Handle WordPress die events.
	 */
	public function handle_wp_die( $handler ) {
		return function ( $message, $title = '', $args = array() ) use ( $handler ) {
			$context = array(
				'title'  => $title,
				'args'   => $args,
				'wp_die' => true,
			);

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
		// Sanitize message.
		$safe_message = $this->sanitize_error_message( $message );

		// Merge with system context.
		$system_context = array_merge(
			ServerUtils::get_safe_context(),
			array(
				'user_id'      => get_current_user_id(),
				'memory_usage' => memory_get_usage( true ),
				'memory_peak'  => memory_get_peak_usage( true ),
				'php_version'  => PHP_VERSION,
				'wp_version'   => get_bloginfo( 'version' ),
			),
			$this->sanitize_context( $context )
		);

		return array(
			'id'        => wp_generate_uuid4(),
			'message'   => $safe_message,
			'category'  => $category,
			'severity'  => $severity,
			'context'   => $system_context,
			'timestamp' => time(),
		);
	}

	/**
	 * Track error statistics.
	 */
	private function track_error( array $error_data ): void {
		$key = $error_data['category'] . '_' . $error_data['severity'];

		if ( ! isset( $this->error_stats[ $key ] ) ) {
			$this->error_stats[ $key ] = array(
				'count'      => 0,
				'first_seen' => time(),
				'last_seen'  => time(),
			);
		}

		++$this->error_stats[ $key ]['count'];
		$this->error_stats[ $key ]['last_seen'] = time();
	}

	/**
	 * Check if error should be rate limited.
	 */
	private function is_rate_limited( string $category ): bool {
		$limit         = self::RATE_LIMITS[ $category ] ?? self::RATE_LIMITS['default'];
		$key           = "nuclen_error_limit_{$category}";
		$current_count = (int) get_transient( $key );

		if ( $current_count >= $limit ) {
			return true;
		}

		set_transient( $key, $current_count + 1, 300 ); // 5 minutes.
		return false;
	}

	/**
	 * Log error to appropriate destinations.
	 */
	private function log_error( array $error_data ): void {
		// Format log entry with consistent structure
		$log_entry = sprintf(
			'[%s] %s | File: %s:%d | User: %d | Memory: %.2fMB | Context: %s',
			$error_data['severity'],
			$error_data['message'],
			$error_data['context']['file'] ?? 'unknown',
			$error_data['context']['line'] ?? 0,
			$error_data['context']['user_id'] ?? 0,
			( $error_data['context']['memory_usage'] ?? 0 ) / MB_IN_BYTES,
			wp_json_encode( $this->get_essential_context( $error_data['context'] ) )
		);

		// Use LoggingService if available.
		if ( class_exists( LoggingService::class ) ) {
			LoggingService::log( $log_entry );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Nuclear Engagement] ' . $log_entry );
		}

		// Critical errors are now logged through LoggingService only
	}

	/**
	 * Handle security-related errors.
	 */
	private function handle_security_event( array $error_data ): void {
		// Log security event with high priority.
		$security_log = sprintf(
			'[SECURITY] [%s] %s | IP: %s | User: %d | Request: %s %s',
			$error_data['severity'],
			$error_data['message'],
			$error_data['context']['ip'] ?? 'unknown',
			$error_data['context']['user_id'] ?? 0,
			$error_data['context']['request_method'] ?? 'UNKNOWN',
			$error_data['context']['request_uri'] ?? 'unknown'
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Nuclear Engagement] ' . $security_log );

		// Trigger action for security monitoring.
		do_action( 'nuclen_security_event', $error_data );
	}

	/**
	 * Attempt automatic error recovery.
	 */
	private function attempt_recovery( array $error_data ): void {
		// Check if recovery should be attempted
		if ( ! $this->should_attempt_recovery( $error_data ) ) {
			return;
		}

		// Get applicable strategies
		$strategies = $this->get_applicable_strategies( $error_data );

		foreach ( $strategies as $strategy ) {
			try {
				$recovery_id = wp_generate_uuid4();
				$this->log_recovery_attempt( $recovery_id, $strategy['name'], $error_data );

				if ( call_user_func( $strategy['handler'], $error_data ) ) {
					$this->log_recovery_success( $recovery_id, $strategy['name'] );

					// Execute post-recovery actions
					if ( isset( $strategy['post_recovery'] ) ) {
						call_user_func( $strategy['post_recovery'], $error_data );
					}

					break; // Stop on first successful recovery
				} else {
					$this->log_recovery_failure( $recovery_id, $strategy['name'] );
				}
			} catch ( \Throwable $e ) {
				// Log recovery strategy failure
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[WARNING] Recovery strategy %s failed: %s | Category: %s',
						$strategy['name'],
						$e->getMessage(),
						$error_data['category']
					)
				);
			}
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
		// Remove sensitive patterns.
		$patterns = array(
			'/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[EMAIL]',
			'/api[_-]?key["\']?\s*[:=]\s*["\']?[\w-]+/' => 'api_key=[REDACTED]',
			'/password["\']?\s*[:=]\s*["\']?[^\s"\']+/' => 'password=[REDACTED]',
			'/token["\']?\s*[:=]\s*["\']?[\w-]+/'       => 'token=[REDACTED]',
		);

		return preg_replace( array_keys( $patterns ), array_values( $patterns ), $message );
	}

	/**
	 * Sanitize context data.
	 */
	private function sanitize_context( array $context ): array {
		array_walk_recursive(
			$context,
			function ( &$value ) {
				if ( is_string( $value ) ) {
					$value = $this->sanitize_error_message( $value );
				}
			}
		);
		return $context;
	}

	/**
	 * Sanitize stack trace.
	 */
	private function sanitize_stack_trace( string $trace ): string {
		// Remove file paths and sensitive information.
		$sanitized = preg_replace( '/\/[^\s]+\//', '/[PATH]/', $trace );
		return $this->sanitize_error_message( $sanitized );
	}

	/**
	 * Get error type name.
	 */
	private function get_error_type_name( int $type ): string {
		$types = array(
			E_ERROR         => 'E_ERROR',
			E_WARNING       => 'E_WARNING',
			E_PARSE         => 'E_PARSE',
			E_NOTICE        => 'E_NOTICE',
			E_CORE_ERROR    => 'E_CORE_ERROR',
			E_CORE_WARNING  => 'E_CORE_WARNING',
			E_COMPILE_ERROR => 'E_COMPILE_ERROR',
			E_USER_ERROR    => 'E_USER_ERROR',
			E_USER_WARNING  => 'E_USER_WARNING',
			E_USER_NOTICE   => 'E_USER_NOTICE',
		);
		return $types[ $type ] ?? 'UNKNOWN';
	}


	/**
	 * Attempt database recovery.
	 */
	private function attempt_database_recovery( array $error_data ): void {
		// Clear any problematic caches.
		wp_cache_flush();

		// Log recovery attempt.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Nuclear Engagement] INFO: Attempting database recovery - clearing caches and reconnecting' );
	}

	/**
	 * Attempt resource recovery.
	 */
	private function attempt_resource_recovery( array $error_data ): void {
		// Clear memory-intensive caches.
		wp_cache_flush();

		// Force garbage collection.
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[INFO] Attempting resource recovery - Memory: %.2fMB/%.2fMB',
				memory_get_usage( true ) / MB_IN_BYTES,
				$this->get_memory_limit() / MB_IN_BYTES
			)
		);
	}

	/**
	 * Get essential context for logging.
	 * Reduces context to most important fields to avoid log bloat.
	 */
	private function get_essential_context( array $context ): array {
		$essential = array(
			'error_type'      => $context['error_type'] ?? null,
			'exception_class' => $context['exception_class'] ?? null,
			'wp_die'          => $context['wp_die'] ?? null,
			'service'         => $context['service'] ?? null,
			'operation'       => $context['operation'] ?? null,
			'post_id'         => $context['post_id'] ?? null,
			'generation_id'   => $context['generation_id'] ?? null,
		);

		// Remove null values
		return array_filter(
			$essential,
			function ( $value ) {
				return $value !== null;
			}
		);
	}

	/**
	 * Get error statistics.
	 */
	public function get_error_stats(): array {
		return $this->error_stats;
	}

	/**
	 * Register recovery strategies.
	 */
	private function register_recovery_strategies(): void {
		// Database recovery strategies
		$this->register_strategy( self::CATEGORY_DATABASE, 'reconnect_database', array( $this, 'recover_database_connection' ), 1 );
		$this->register_strategy( self::CATEGORY_DATABASE, 'clear_db_cache', array( $this, 'recover_database_cache' ), 2 );
		$this->register_strategy( self::CATEGORY_DATABASE, 'repair_tables', array( $this, 'recover_database_tables' ), 3 );

		// Resource recovery strategies
		$this->register_strategy( self::CATEGORY_RESOURCE, 'free_memory', array( $this, 'recover_memory' ), 1 );
		$this->register_strategy( self::CATEGORY_RESOURCE, 'extend_limits', array( $this, 'recover_execution_limits' ), 2 );
		$this->register_strategy( self::CATEGORY_RESOURCE, 'cleanup_temp', array( $this, 'recover_disk_space' ), 3 );

		// Network recovery strategies
		$this->register_strategy( self::CATEGORY_NETWORK, 'clear_dns', array( $this, 'recover_network_dns' ), 1 );
		$this->register_strategy( self::CATEGORY_NETWORK, 'reset_http', array( $this, 'recover_http_transport' ), 2 );

		// Security recovery strategies
		$this->register_strategy( self::CATEGORY_SECURITY, 'clear_auth', array( $this, 'recover_security_auth' ), 1 );
		$this->register_strategy( self::CATEGORY_SECURITY, 'reset_nonces', array( $this, 'recover_security_nonces' ), 2 );

		// Allow external strategies
		do_action( 'nuclen_register_recovery_strategies', $this );
	}

	/**
	 * Register a recovery strategy.
	 */
	public function register_strategy( string $category, string $name, callable $handler, int $priority = 10, ?callable $post_recovery = null ): void {
		if ( ! isset( $this->recovery_strategies[ $category ] ) ) {
			$this->recovery_strategies[ $category ] = array();
		}

		$this->recovery_strategies[ $category ][] = array(
			'name'          => $name,
			'handler'       => $handler,
			'priority'      => $priority,
			'post_recovery' => $post_recovery,
		);

		// Sort by priority
		usort(
			$this->recovery_strategies[ $category ],
			function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);
	}

	/**
	 * Check if recovery should be attempted.
	 */
	private function should_attempt_recovery( array $error_data ): bool {
		// Don't attempt recovery for low severity errors
		if ( $error_data['severity'] === self::SEVERITY_LOW ) {
			return false;
		}

		// Check recovery history to prevent loops
		$history_key = md5( $error_data['category'] . $error_data['message'] );
		if ( isset( $this->recovery_history[ $history_key ] ) ) {
			$attempts     = $this->recovery_history[ $history_key ]['attempts'];
			$last_attempt = $this->recovery_history[ $history_key ]['last_attempt'];

			// Don't retry if attempted 3 times in last hour
			if ( $attempts >= 3 && ( time() - $last_attempt ) < 3600 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get applicable recovery strategies.
	 */
	private function get_applicable_strategies( array $error_data ): array {
		$strategies = array();

		// Get category-specific strategies
		if ( isset( $this->recovery_strategies[ $error_data['category'] ] ) ) {
			$strategies = array_merge( $strategies, $this->recovery_strategies[ $error_data['category'] ] );
		}

		// Add general strategies for critical errors
		if ( $error_data['severity'] === self::SEVERITY_CRITICAL && isset( $this->recovery_strategies[ self::CATEGORY_GENERAL ] ) ) {
			$strategies = array_merge( $strategies, $this->recovery_strategies[ self::CATEGORY_GENERAL ] );
		}

		// Filter strategies based on context
		return apply_filters( 'nuclen_recovery_strategies', $strategies, $error_data );
	}

	/**
	 * Log recovery attempt.
	 */
	private function log_recovery_attempt( string $recovery_id, string $strategy_name, array $error_data ): void {
		$history_key = md5( $error_data['category'] . $error_data['message'] );

		if ( ! isset( $this->recovery_history[ $history_key ] ) ) {
			$this->recovery_history[ $history_key ] = array(
				'attempts'     => 0,
				'last_attempt' => 0,
				'strategies'   => array(),
			);
		}

		++$this->recovery_history[ $history_key ]['attempts'];
		$this->recovery_history[ $history_key ]['last_attempt'] = time();
		$this->recovery_history[ $history_key ]['strategies'][] = $strategy_name;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[INFO] Attempting recovery [%s] using strategy: %s | Error: %s (%s)',
				$recovery_id,
				$strategy_name,
				substr( $error_data['message'], 0, 100 ),
				$error_data['category']
			)
		);
	}

	/**
	 * Log recovery success.
	 */
	private function log_recovery_success( string $recovery_id, string $strategy_name ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[SUCCESS] Recovery successful [%s] using strategy: %s',
				$recovery_id,
				$strategy_name
			)
		);

		do_action( 'nuclen_recovery_success', $recovery_id, $strategy_name );
	}

	/**
	 * Log recovery failure.
	 */
	private function log_recovery_failure( string $recovery_id, string $strategy_name ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[WARNING] Recovery failed [%s] using strategy: %s',
				$recovery_id,
				$strategy_name
			)
		);
	}

	/**
	 * Database connection recovery.
	 */
	private function recover_database_connection( array $error_data ): bool {
		global $wpdb;

		if ( ! $wpdb->check_connection( false ) ) {
			// Attempt to reconnect
			$wpdb->db_connect( false );
			return $wpdb->check_connection( false );
		}

		return true;
	}

	/**
	 * Database cache recovery.
	 */
	private function recover_database_cache( array $error_data ): bool {
		global $wpdb;

		// Clear query cache
		$wpdb->flush();

		// Clear object cache
		wp_cache_flush();

		// Clear transients if possible
		if ( ! wp_using_ext_object_cache() ) {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
		}

		return true;
	}

	/**
	 * Database table repair recovery.
	 */
	private function recover_database_tables( array $error_data ): bool {
		global $wpdb;

		// Only attempt if error mentions specific table
		if ( preg_match( '/Table\s+[\'"]?([^\s\'"]+)[\'"]?/i', $error_data['message'], $matches ) ) {
			$table = $matches[1];

			// Only repair plugin tables
			if ( strpos( $table, 'nuclen_' ) !== false ) {
				$result = $wpdb->query( "REPAIR TABLE {$table}" );
				return $result !== false;
			}
		}

		return false;
	}

	/**
	 * Memory recovery.
	 */
	private function recover_memory( array $error_data ): bool {
		// Clear all caches
		wp_cache_flush();

		// Clear plugin-specific caches
		delete_transient( 'nuclen_batch_jobs' );
		delete_transient( 'nuclen_generation_queue' );

		// Force garbage collection
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		// Check if memory usage improved
		$current_usage = memory_get_usage( true );
		$limit         = $this->get_memory_limit();

		return ( $current_usage / $limit ) < 0.8; // Less than 80% usage
	}

	/**
	 * Execution limits recovery.
	 */
	private function recover_execution_limits( array $error_data ): bool {
		// Try to extend time limit
		if ( ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 300 ); // 5 minutes
			return true;
		}

		return false;
	}

	/**
	 * Disk space recovery.
	 */
	private function recover_disk_space( array $error_data ): bool {
		// Clear temporary files
		$temp_dir = get_temp_dir();
		$cleared  = 0;

		// Clean old Nuclear Engagement temp files
		foreach ( glob( $temp_dir . '/nuclen_*' ) as $file ) {
			if ( filemtime( $file ) < time() - 3600 ) { // Older than 1 hour
				if ( @unlink( $file ) ) {
					++$cleared;
				}
			}
		}

		// Log file cleanup is now handled by LoggingService rotation

		return $cleared > 0;
	}

	/**
	 * Network DNS recovery.
	 */
	private function recover_network_dns( array $error_data ): bool {
		// Clear DNS cache if possible
		if ( function_exists( 'dns_get_record' ) ) {
			// Force DNS refresh by doing a lookup
			@dns_get_record( 'api.nuclearengagement.com', DNS_A );
		}

		// Clear WordPress HTTP cache
		delete_transient( '_transient_timeout_http_api_' );

		return true;
	}

	/**
	 * HTTP transport recovery.
	 */
	private function recover_http_transport( array $error_data ): bool {
		// Reset HTTP transport settings
		delete_option( 'nuclen_http_transport_error' );

		// Clear any stuck requests
		delete_transient( 'nuclen_api_request_lock' );

		return true;
	}

	/**
	 * Security auth recovery.
	 */
	private function recover_security_auth( array $error_data ): bool {
		// Clear auth cookies for current user
		wp_clear_auth_cookie();

		// Clear any stuck auth transients
		delete_transient( 'nuclen_auth_check' );

		return true;
	}

	/**
	 * Security nonces recovery.
	 */
	private function recover_security_nonces( array $error_data ): bool {
		// Clear nonce-related transients
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'%_transient_nuclen_nonce_%'
			)
		);

		return true;
	}

	/**
	 * Get memory limit in bytes.
	 */
	private function get_memory_limit(): int {
		$limit = ini_get( 'memory_limit' );

		if ( $limit == -1 ) {
			return PHP_INT_MAX;
		}

		$value = (int) $limit;
		$unit  = strtolower( substr( $limit, -1 ) );

		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
	}

	/**
	 * Handle custom exceptions.
	 */
	public function handle_custom_exception( \NuclearEngagement\Exceptions\NuclenException $exception ): void {
		$error_data = array(
			'message'  => $exception->getMessage(),
			'category' => $this->map_exception_category( $exception ),
			'severity' => $exception->getSeverity(),
			'context'  => array_merge(
				$exception->getContext(),
				array(
					'file'         => $exception->getFile(),
					'line'         => $exception->getLine(),
					'user_message' => $exception->getUserMessage(),
				)
			),
		);

		$this->handle_error(
			$error_data['message'],
			$error_data['category'],
			$error_data['severity'],
			$error_data['context']
		);
	}

	/**
	 * Map exception to category.
	 */
	private function map_exception_category( \Throwable $exception ): string {
		if ( $exception instanceof \NuclearEngagement\Exceptions\DatabaseException ) {
			return self::CATEGORY_DATABASE;
		}
		if ( $exception instanceof \NuclearEngagement\Exceptions\ApiException ) {
			return self::CATEGORY_NETWORK;
		}
		if ( $exception instanceof \NuclearEngagement\Exceptions\ValidationException ) {
			return self::CATEGORY_VALIDATION;
		}
		if ( $exception instanceof \NuclearEngagement\Exceptions\ResourceException ) {
			return self::CATEGORY_RESOURCE;
		}
		if ( $exception instanceof \NuclearEngagement\Exceptions\SecurityException ) {
			return self::CATEGORY_SECURITY;
		}

		return self::CATEGORY_GENERAL;
	}

	/**
	 * Check if error file is from our plugin.
	 *
	 * @param string $file File path where error occurred.
	 * @return bool True if error is from our plugin.
	 */
	private function is_plugin_error( string $file ): bool {
		if ( empty( $file ) ) {
			return false;
		}

		// Normalize paths for comparison
		$file       = wp_normalize_path( $file );
		$plugin_dir = wp_normalize_path( NUCLEN_PLUGIN_DIR );

		// Check if error file is within our plugin directory
		return strpos( $file, $plugin_dir ) === 0;
	}

	/**
	 * Check if exception originated from our plugin.
	 *
	 * @param \Throwable $exception The exception to check.
	 * @return bool True if exception is from our plugin.
	 */
	private function is_plugin_exception( \Throwable $exception ): bool {
		// Check if it's a Nuclear Engagement custom exception
		if ( strpos( get_class( $exception ), 'NuclearEngagement\\' ) === 0 ) {
			return true;
		}

		// Check the stack trace for plugin files
		$trace      = $exception->getTrace();
		$plugin_dir = wp_normalize_path( NUCLEN_PLUGIN_DIR );

		foreach ( $trace as $frame ) {
			if ( isset( $frame['file'] ) ) {
				$file = wp_normalize_path( $frame['file'] );
				if ( strpos( $file, $plugin_dir ) === 0 ) {
					return true;
				}
			}
		}

		return false;
	}
}
