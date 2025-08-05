<?php
/**
 * CacheUtils.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Utils
 */

declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified caching utilities for consistent cache management.
 *
 * This class provides a consistent interface for all caching operations
 * across the plugin, preventing cache inconsistencies and improving performance.
 *
 * @package NuclearEngagement\Utils
 * @since   1.0.0
 */
final class CacheUtils {

	/**
	 * Default cache group for the plugin.
	 */
	private const DEFAULT_CACHE_GROUP = 'nuclear_engagement';

	/**
	 * Default cache TTL in seconds.
	 */
	private const DEFAULT_TTL = 3600; // 1 hour.

	/**
	 * Cache key prefix for the plugin.
	 */
	private const KEY_PREFIX = 'nuclen_';

	/**
	 * Maximum cache key length.
	 */
	private const MAX_KEY_LENGTH = 250;

	/**
	 * Cache key salt for security.
	 */
	private static ?string $cache_salt = null;

	/**
	 * Get cached data with fallback to transients.
	 *
	 * @param string $key           Cache key.
	 * @param string $group         Cache group.
	 * @param bool   $use_transient Whether to fallback to transients.
	 * @return mixed|false Cached data or false if not found.
	 */
	public static function get( string $key, string $group = self::DEFAULT_CACHE_GROUP, bool $use_transient = true ) {
		$safe_key = self::sanitize_key( $key );

		// Try object cache first.
		$found = false;
		$data  = wp_cache_get( $safe_key, $group, false, $found );

		if ( $found ) {
			// Validate cached data before returning
			if ( self::validate_cached_data( $data ) ) {
				return $data;
			} else {
				// Invalid data, delete it
				wp_cache_delete( $safe_key, $group );
			}
		}

		// Fallback to transients if enabled.
		if ( $use_transient ) {
			$transient_key = self::get_transient_key( $safe_key, $group );
			$data          = get_transient( $transient_key );

			if ( $data !== false ) {
				// Validate transient data
				if ( self::validate_cached_data( $data ) ) {
					// Store back in object cache for faster subsequent access.
					wp_cache_set( $safe_key, $data, $group, self::DEFAULT_TTL );
					return $data;
				} else {
					// Invalid data, delete it
					delete_transient( $transient_key );
				}
			}
		}

		return false;
	}

	/**
	 * Set cached data with consistent storage.
	 *
	 * @param string $key           Cache key.
	 * @param mixed  $data          Data to cache.
	 * @param string $group         Cache group.
	 * @param int    $ttl           Time to live in seconds.
	 * @param bool   $use_transient Whether to also store in transients.
	 * @return bool True on success, false on failure.
	 */
	public static function set(
		string $key,
		$data,
		string $group = self::DEFAULT_CACHE_GROUP,
		int $ttl = self::DEFAULT_TTL,
		bool $use_transient = true
	): bool {
		$safe_key = self::sanitize_key( $key );

		// Store in object cache.
		$object_cache_result = wp_cache_set( $safe_key, $data, $group, $ttl );

		// Store in transients for persistence.
		$transient_result = true;
		if ( $use_transient ) {
			$transient_key    = self::get_transient_key( $safe_key, $group );
			$transient_result = set_transient( $transient_key, $data, $ttl );
		}

		return $object_cache_result && $transient_result;
	}

	/**
	 * Delete cached data from all storage mechanisms.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $key, string $group = self::DEFAULT_CACHE_GROUP ): bool {
		$safe_key = self::sanitize_key( $key );

		// Delete from object cache.
		$object_cache_result = wp_cache_delete( $safe_key, $group );

		// Delete from transients.
		$transient_key    = self::get_transient_key( $safe_key, $group );
		$transient_result = delete_transient( $transient_key );

		return $object_cache_result && $transient_result;
	}

	/**
	 * Flush cache group.
	 *
	 * @param string $group Cache group to flush.
	 * @return bool True on success, false on failure.
	 */
	public static function flush_group( string $group = self::DEFAULT_CACHE_GROUP ): bool {
		// Use WordPress function if available.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			$result = wp_cache_flush_group( $group );
		} else {
			// Fallback to full cache flush.
			$result = wp_cache_flush();
		}

		// Also clean related transients.
		self::clean_group_transients( $group );

		return $result;
	}

	/**
	 * Generate cache key with automatic hashing for long keys.
	 *
	 * @param array  $components Key components.
	 * @param string $separator  Component separator.
	 * @return string Generated cache key.
	 */
	public static function generate_key( array $components, string $separator = '_' ): string {
		// Get cache salt
		$salt = self::get_cache_salt();

		// Filter and sanitize components.
		$clean_components = array_filter(
			array_map(
				function ( $component ) {
					if ( is_scalar( $component ) ) {
							return sanitize_key( (string) $component );
					} elseif ( is_array( $component ) || is_object( $component ) ) {
						// Use SHA256 instead of MD5 for better security
						return hash( 'sha256', serialize( $component ) );
					}
					return '';
				},
				$components
			)
		);

		// Add salt to components for security
		$clean_components[] = $salt;

		$key = implode( $separator, $clean_components );

		// Hash if too long using SHA256.
		if ( strlen( $key ) > self::MAX_KEY_LENGTH ) {
			$key = substr( hash( 'sha256', $key ), 0, 64 );
		}

		return $key;
	}

	/**
	 * Increment cached counter.
	 *
	 * @param string $key   Cache key.
	 * @param int    $step  Increment step.
	 * @param string $group Cache group.
	 * @param int    $ttl   Time to live if creating new counter.
	 * @return int New counter value.
	 */
	public static function increment(
		string $key,
		int $step = 1,
		string $group = self::DEFAULT_CACHE_GROUP,
		int $ttl = self::DEFAULT_TTL
	): int {
		$safe_key = self::sanitize_key( $key );

		// Try to increment in object cache.
		$new_value = wp_cache_incr( $safe_key, $step, $group );

		if ( $new_value === false ) {
			// Counter doesn't exist, create it.
			$new_value = $step;
			self::set( $safe_key, $new_value, $group, $ttl );
		}

		return $new_value;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	public static function get_stats(): array {
		global $wpdb;

		// Count transients related to our plugin.
		$transient_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . self::KEY_PREFIX . '%'
			)
		);

		return array(
			'object_cache_enabled' => wp_using_ext_object_cache(),
			'transient_count'      => (int) $transient_count,
			'default_ttl'          => self::DEFAULT_TTL,
			'default_group'        => self::DEFAULT_CACHE_GROUP,
			'key_prefix'           => self::KEY_PREFIX,
		);
	}

	/**
	 * Clean up expired cache data.
	 *
	 * @return int Number of items cleaned up.
	 */
	public static function cleanup(): int {
		global $wpdb;

		// Clean expired transients.
		$deleted = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b 
				WHERE a.option_name LIKE %s 
				AND a.option_name = CONCAT('_transient_timeout_', SUBSTRING(b.option_name, 12))
				AND b.option_name LIKE %s 
				AND b.option_value < %d",
				'_transient_timeout_' . self::KEY_PREFIX . '%',
				'_transient_' . self::KEY_PREFIX . '%',
				time()
			)
		);

		return $deleted ?: 0;
	}

	/**
	 * Sanitize cache key.
	 *
	 * @param string $key Raw cache key.
	 * @return string Sanitized key.
	 */
	private static function sanitize_key( string $key ): string {
		// Get cache salt for security
		$salt = self::get_cache_salt();

		// Add plugin prefix and salt.
		$prefixed_key = self::KEY_PREFIX . $key . '_' . $salt;

		// WordPress sanitize_key function.
		$sanitized = sanitize_key( $prefixed_key );

		// Additional sanitization for special characters.
		$sanitized = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', $sanitized );

		// Limit length using SHA256 for security.
		if ( strlen( $sanitized ) > self::MAX_KEY_LENGTH ) {
			$sanitized = substr( $sanitized, 0, self::MAX_KEY_LENGTH - 64 ) . '_' . hash( 'sha256', $sanitized );
		}

		return $sanitized;
	}

	/**
	 * Get transient key with group prefix.
	 *
	 * @param string $key   Sanitized cache key.
	 * @param string $group Cache group.
	 * @return string Transient key.
	 */
	private static function get_transient_key( string $key, string $group ): string {
		$transient_key = $group . '_' . $key;

		// WordPress transient keys have a 172 character limit.
		if ( strlen( $transient_key ) > 172 ) {
			$transient_key = substr( $transient_key, 0, 140 ) . '_' . md5( $transient_key );
		}

		return $transient_key;
	}

	/**
	 * Clean transients for a specific group.
	 *
	 * @param string $group Cache group.
	 * @return int Number of transients deleted.
	 */
	private static function clean_group_transients( string $group ): int {
		global $wpdb;

		$pattern = '_transient_' . $group . '_' . self::KEY_PREFIX . '%';

		$deleted = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		// Also delete timeout transients.
		$timeout_pattern = '_transient_timeout_' . $group . '_' . self::KEY_PREFIX . '%';
		$timeout_deleted = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$timeout_pattern
			)
		);

		return ( $deleted ?: 0 ) + ( $timeout_deleted ?: 0 );
	}

	/**
	 * Create cache key for database queries.
	 *
	 * @param string $query_type   Type of query.
	 * @param array  $parameters   Query parameters.
	 * @param int    $blog_id      Blog ID for multisite.
	 * @return string Cache key.
	 */
	public static function query_cache_key( string $query_type, array $parameters = array(), int $blog_id = 0 ): string {
		if ( $blog_id === 0 ) {
			$blog_id = get_current_blog_id();
		}

		// Add user context for cache isolation
		$user_id   = get_current_user_id();
		$user_role = '';
		if ( $user_id > 0 ) {
			$user      = wp_get_current_user();
			$user_role = ! empty( $user->roles ) ? $user->roles[0] : 'none';
		}

		$components = array(
			'query',
			$query_type,
			$blog_id,
			$user_id,
			$user_role,
			hash( 'sha256', serialize( $parameters ) ),
		);

		return self::generate_key( $components );
	}

	/**
	 * Cache wrapper for expensive operations.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Callback to execute if cache miss.
	 * @param string   $group    Cache group.
	 * @param int      $ttl      Time to live.
	 * @return mixed Result from callback or cache.
	 */
	public static function remember( string $key, callable $callback, string $group = self::DEFAULT_CACHE_GROUP, int $ttl = self::DEFAULT_TTL ) {
		$cached = self::get( $key, $group );

		if ( $cached !== false ) {
			return $cached;
		}

		$result = call_user_func( $callback );

		if ( $result !== null ) {
			self::set( $key, $result, $group, $ttl );
		}

		return $result;
	}

	/**
	 * Invalidate cache based on patterns or tags.
	 *
	 * @param array  $patterns Array of key patterns to invalidate.
	 * @param string $group   Cache group.
	 * @return int Number of items invalidated.
	 */
	public static function invalidate_by_pattern( array $patterns, string $group = self::DEFAULT_CACHE_GROUP ): int {
		global $wpdb;

		$invalidated = 0;

		foreach ( $patterns as $pattern ) {
			$safe_pattern      = self::sanitize_key( $pattern );
			$transient_pattern = '_transient_' . self::get_transient_key( $safe_pattern, $group );

			$deleted = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					str_replace( '*', '%', $transient_pattern )
				)
			);

			$invalidated += $deleted ?: 0;
		}

		return $invalidated;
	}

	/**
	 * Get cache salt for security.
	 *
	 * @return string Cache salt.
	 */
	private static function get_cache_salt(): string {
		if ( self::$cache_salt === null ) {
			// Use WordPress salt for cache key generation
			self::$cache_salt = substr( hash( 'sha256', wp_salt( 'auth' ) . ABSPATH ), 0, 16 );
		}
		return self::$cache_salt;
	}

	/**
	 * Validate cached data to prevent cache poisoning.
	 *
	 * @param mixed $data Cached data to validate.
	 * @return bool True if data is valid, false otherwise.
	 */
	private static function validate_cached_data( $data ): bool {
		// Reject if data contains unexpected object types
		if ( is_object( $data ) ) {
			$allowed_classes = array(
				'stdClass',
				'WP_Post',
				'WP_User',
				'WP_Term',
				'WP_Comment',
			);
			$class_name      = get_class( $data );
			if ( ! in_array( $class_name, $allowed_classes, true ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( 'Cache validation failed: unexpected object type %s', $class_name ),
					'warning'
				);
				return false;
			}
		}

		// Validate array data recursively
		if ( is_array( $data ) ) {
			foreach ( $data as $item ) {
				if ( ! self::validate_cached_data( $item ) ) {
					return false;
				}
			}
		}

		// Check for suspicious patterns in string data
		if ( is_string( $data ) ) {
			// Check for potential serialized objects
			if ( preg_match( '/^[Oa]:\d+:/', $data ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					'Cache validation failed: suspicious serialized data detected',
					'warning'
				);
				return false;
			}
		}

		return true;
	}
}
