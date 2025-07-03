<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Utils\CacheUtils;
use NuclearEngagement\Utils\ValidationUtils;
use NuclearEngagement\Core\UnifiedErrorHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base service class to reduce code duplication.
 *
 * This abstract class provides common functionality for all service classes
 * including error handling, caching, validation, and logging.
 *
 * @package NuclearEngagement\Core
 * @since   1.0.0
 */
abstract class BaseService {

	/**
	 * Service name for logging and caching.
	 */
	protected string $service_name;

	/**
	 * Default cache TTL for this service.
	 */
	protected int $cache_ttl = 3600;

	/**
	 * Error handler instance.
	 */
	protected UnifiedErrorHandler $error_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service_name = $this->get_service_name();
		$this->error_handler = UnifiedErrorHandler::get_instance();
	}

	/**
	 * Get service name for logging and caching.
	 *
	 * @return string Service name.
	 */
	abstract protected function get_service_name(): string;

	/**
	 * Handle errors consistently across services.
	 *
	 * @param string $message  Error message.
	 * @param string $category Error category.
	 * @param string $severity Error severity.
	 * @param array  $context  Additional context.
	 * @return bool Whether error was handled successfully.
	 */
	protected function handle_error( 
		string $message, 
		string $category = 'general', 
		string $severity = 'medium',
		array $context = []
	): bool {
		$context['service'] = $this->service_name;
		return $this->error_handler->handle_error( $message, $category, $severity, $context );
	}

	/**
	 * Get cached data with service-specific key.
	 *
	 * @param string $key    Cache key suffix.
	 * @param array  $params Parameters to include in key.
	 * @return mixed|false Cached data or false if not found.
	 */
	protected function get_cache( string $key, array $params = [] ) {
		$cache_key = $this->build_cache_key( $key, $params );
		return CacheUtils::get( $cache_key, $this->service_name );
	}

	/**
	 * Set cached data with service-specific key.
	 *
	 * @param string $key    Cache key suffix.
	 * @param mixed  $data   Data to cache.
	 * @param array  $params Parameters to include in key.
	 * @param int    $ttl    Time to live (optional).
	 * @return bool True on success, false on failure.
	 */
	protected function set_cache( string $key, $data, array $params = [], int $ttl = 0 ): bool {
		$cache_key = $this->build_cache_key( $key, $params );
		$cache_ttl = $ttl > 0 ? $ttl : $this->cache_ttl;
		return CacheUtils::set( $cache_key, $data, $this->service_name, $cache_ttl );
	}

	/**
	 * Delete cached data with service-specific key.
	 *
	 * @param string $key    Cache key suffix.
	 * @param array  $params Parameters to include in key.
	 * @return bool True on success, false on failure.
	 */
	protected function delete_cache( string $key, array $params = [] ): bool {
		$cache_key = $this->build_cache_key( $key, $params );
		return CacheUtils::delete( $cache_key, $this->service_name );
	}

	/**
	 * Build cache key with service prefix and parameters.
	 *
	 * @param string $key    Base cache key.
	 * @param array  $params Parameters to include.
	 * @return string Full cache key.
	 */
	private function build_cache_key( string $key, array $params = [] ): string {
		$components = [ $this->service_name, $key ];
		
		if ( ! empty( $params ) ) {
			$components[] = md5( serialize( $params ) );
		}
		
		return CacheUtils::generate_key( $components );
	}

	/**
	 * Validate input data using batch validation.
	 *
	 * @param array $data  Input data.
	 * @param array $rules Validation rules.
	 * @return array|null Validated data or null on failure.
	 */
	protected function validate_input( array $data, array $rules ): ?array {
		$validated = ValidationUtils::validate_batch( $data, $rules );
		
		if ( $validated === null ) {
			$this->handle_error(
				'Input validation failed',
				'validation',
				'medium',
				[ 'data_keys' => array_keys( $data ), 'rules' => array_keys( $rules ) ]
			);
		}
		
		return $validated;
	}

	/**
	 * Execute database operation with error handling.
	 *
	 * @param callable $operation Database operation callback.
	 * @param string   $operation_name Operation name for logging.
	 * @return mixed Operation result or false on failure.
	 */
	protected function execute_db_operation( callable $operation, string $operation_name ) {
		try {
			$result = call_user_func( $operation );
			
			// Check for WordPress database errors
			global $wpdb;
			if ( $wpdb->last_error ) {
				$this->handle_error(
					"Database error in {$operation_name}: {$wpdb->last_error}",
					'database',
					'high',
					[ 'operation' => $operation_name ]
				);
				return false;
			}
			
			return $result;
			
		} catch ( \Throwable $e ) {
			$this->handle_error(
				"Exception in {$operation_name}: {$e->getMessage()}",
				'database',
				'high',
				[
					'operation' => $operation_name,
					'exception' => get_class( $e ),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				]
			);
			return false;
		}
	}

	/**
	 * Sanitize array of data recursively.
	 *
	 * @param array $data     Data to sanitize.
	 * @param bool  $allow_html Whether to allow HTML content.
	 * @return array Sanitized data.
	 */
	protected function sanitize_array( array $data, bool $allow_html = false ): array {
		array_walk_recursive( $data, function( &$value ) use ( $allow_html ) {
			if ( is_string( $value ) ) {
				$value = $allow_html ? wp_kses_post( $value ) : sanitize_text_field( $value );
			} elseif ( is_int( $value ) ) {
				$value = absint( $value );
			} elseif ( is_float( $value ) ) {
				$value = floatval( $value );
			}
		} );
		
		return $data;
	}

	/**
	 * Check if user has required capability.
	 *
	 * @param string $capability Required capability.
	 * @param int    $user_id    User ID (0 for current user).
	 * @return bool True if user has capability.
	 */
	protected function check_capability( string $capability = 'manage_options', int $user_id = 0 ): bool {
		if ( ! ValidationUtils::validate_capability( $capability, $user_id ) ) {
			$this->handle_error(
				"Capability check failed: {$capability}",
				'permissions',
				'high',
				[ 'capability' => $capability, 'user_id' => $user_id ?: get_current_user_id() ]
			);
			return false;
		}
		
		return true;
	}

	/**
	 * Log informational message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function log_info( string $message, array $context = [] ): void {
		$context['service'] = $this->service_name;
		
		if ( class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
			\NuclearEngagement\Services\LoggingService::log( $message, $context );
		} else {
			error_log( "Nuclear Engagement [{$this->service_name}]: {$message}" );
		}
	}

	/**
	 * Get WordPress post safely.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|null Post object or null if not found.
	 */
	protected function get_post_safely( int $post_id ): ?\WP_Post {
		$post = get_post( $post_id );
		
		if ( ! $post ) {
			$this->handle_error(
				"Post not found: {$post_id}",
				'validation',
				'medium',
				[ 'post_id' => $post_id ]
			);
			return null;
		}
		
		return $post;
	}

	/**
	 * Get WordPress user safely.
	 *
	 * @param int $user_id User ID.
	 * @return \WP_User|null User object or null if not found.
	 */
	protected function get_user_safely( int $user_id ): ?\WP_User {
		$user = get_user_by( 'id', $user_id );
		
		if ( ! $user ) {
			$this->handle_error(
				"User not found: {$user_id}",
				'validation',
				'medium',
				[ 'user_id' => $user_id ]
			);
			return null;
		}
		
		return $user;
	}

	/**
	 * Execute with memory monitoring.
	 *
	 * @param callable $operation Operation to execute.
	 * @param string   $operation_name Operation name for logging.
	 * @return mixed Operation result.
	 */
	protected function execute_with_memory_monitoring( callable $operation, string $operation_name ) {
		$initial_memory = memory_get_usage( true );
		
		$result = call_user_func( $operation );
		
		$final_memory = memory_get_usage( true );
		$memory_used = $final_memory - $initial_memory;
		
		// Log if significant memory usage
		if ( $memory_used > 1024 * 1024 ) { // 1MB
			$this->log_info(
				"High memory usage in {$operation_name}: " . size_format( $memory_used ),
				[ 'operation' => $operation_name, 'memory_used' => $memory_used ]
			);
		}
		
		return $result;
	}

	/**
	 * Clear all cache for this service.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_service_cache(): bool {
		return CacheUtils::flush_group( $this->service_name );
	}

	/**
	 * Get service statistics for monitoring.
	 *
	 * @return array Service statistics.
	 */
	public function get_service_stats(): array {
		return [
			'service_name' => $this->service_name,
			'cache_ttl' => $this->cache_ttl,
			'error_stats' => $this->error_handler->get_error_stats(),
		];
	}
}