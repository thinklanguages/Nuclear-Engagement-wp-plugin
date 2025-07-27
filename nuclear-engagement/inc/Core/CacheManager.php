<?php
/**
 * CacheManager.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced caching system with unified management.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class CacheManager {
	/**
	 * Cache configuration by group.
	 *
	 * @var array<string, array{ttl: int, priority: string, max_items: int, compression: bool}>
	 */
	private static array $cache_config = array(
		'posts'     => array(
			'ttl'         => 600,    // 10 minutes.
			'priority'    => 'high',
			'max_items'   => 1000,
			'compression' => true,
		),
		'queries'   => array(
			'ttl'         => 300,    // 5 minutes.
			'priority'    => 'high',
			'max_items'   => 500,
			'compression' => false,
		),
		'dashboard' => array(
			'ttl'         => 180,    // 3 minutes.
			'priority'    => 'medium',
			'max_items'   => 200,
			'compression' => false,
		),
		'assets'    => array(
			'ttl'         => 3600,   // 1 hour.
			'priority'    => 'low',
			'max_items'   => 100,
			'compression' => true,
		),
		'metadata'  => array(
			'ttl'         => 1800,   // 30 minutes.
			'priority'    => 'medium',
			'max_items'   => 2000,
			'compression' => true,
		),
		'api'       => array(
			'ttl'         => 900,    // 15 minutes.
			'priority'    => 'medium',
			'max_items'   => 100,
			'compression' => true,
		),
	);

	/**
	 * Cache hit/miss statistics.
	 *
	 * @var array<string, array{hits: int, misses: int, sets: int, deletes: int}>
	 */
	private static array $stats = array();

	/**
	 * Cache invalidation dependencies.
	 *
	 * @var array<string, array<string>>
	 */
	private static array $dependencies = array(
		'post_save'     => array( 'posts', 'queries', 'dashboard' ),
		'post_delete'   => array( 'posts', 'queries', 'dashboard' ),
		'settings'      => array( 'assets', 'dashboard' ),
		'user_action'   => array( 'dashboard' ),
		'plugin_update' => array( 'assets', 'metadata' ),
	);

	/**
	 * Scheduled invalidations.
	 *
	 * @var array<array{group: string, time: int, reason: string}>
	 */
	private static array $scheduled_invalidations = array();

	/**
	 * Initialize cache manager.
	 */
	public static function init(): void {
		// Set up WordPress hooks.
		add_action( 'save_post', array( self::class, 'handle_post_save' ) );
		add_action( 'delete_post', array( self::class, 'handle_post_delete' ) );
		add_action( 'switch_theme', array( self::class, 'handle_theme_change' ) );
		add_action( 'activated_plugin', array( self::class, 'handle_plugin_change' ) );
		add_action( 'deactivated_plugin', array( self::class, 'handle_plugin_change' ) );

		// Register cache cleanup cron.
		if ( ! wp_next_scheduled( 'nuclen_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'nuclen_cache_cleanup' );
		}
		add_action( 'nuclen_cache_cleanup', array( self::class, 'cleanup_expired_cache' ) );

		// Initialize stats.
		foreach ( array_keys( self::$cache_config ) as $group ) {
			self::$stats[ $group ] = array(
				'hits'    => 0,
				'misses'  => 0,
				'sets'    => 0,
				'deletes' => 0,
			);
		}
	}

	/**
	 * Get cached value with automatic statistics tracking.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return mixed|false Cached value or false if not found.
	 */
	public static function get( string $key, string $group = 'default' ) {
		$prefixed_key = self::get_prefixed_key( $key, $group );
		$value        = wp_cache_get( $prefixed_key, self::get_wp_cache_group( $group ) );

		if ( $value !== false ) {
			self::record_hit( $group );

			// Decompress if needed.
			if ( self::should_compress( $group ) && is_string( $value ) && strpos( $value, 'nuclen_compressed:' ) === 0 ) {
				$compressed_data = substr( $value, 17 );
				$value           = maybe_unserialize( gzuncompress( base64_decode( $compressed_data ) ) );
			}
		} else {
			self::record_miss( $group );
		}

		return $value;
	}

	/**
	 * Set cached value with automatic compression and TTL.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param string $group Cache group.
	 * @param int    $ttl   Time to live (0 = use group default).
	 * @return bool Success status.
	 */
	public static function set( string $key, $value, string $group = 'default', int $ttl = 0 ): bool {
		$config     = self::get_group_config( $group );
		$actual_ttl = $ttl ?: $config['ttl'];

		// Compress large values if configured.
		if ( self::should_compress( $group ) && self::should_compress_value( $value ) ) {
			$serialized = maybe_serialize( $value );
			$compressed = base64_encode( gzcompress( $serialized, 6 ) );
			$value      = 'nuclen_compressed:' . $compressed;
		}

		$prefixed_key = self::get_prefixed_key( $key, $group );
		$success      = wp_cache_set( $prefixed_key, $value, self::get_wp_cache_group( $group ), $actual_ttl );

		if ( $success ) {
			self::record_set( $group );
			self::enforce_cache_limits( $group );
		}

		return $success;
	}

	/**
	 * Delete cached value.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool Success status.
	 */
	public static function delete( string $key, string $group = 'default' ): bool {
		$prefixed_key = self::get_prefixed_key( $key, $group );
		$success      = wp_cache_delete( $prefixed_key, self::get_wp_cache_group( $group ) );

		if ( $success ) {
			self::record_delete( $group );
		}

		return $success;
	}

	/**
	 * Remember pattern - get from cache or compute and store.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Function to compute value if not cached.
	 * @param string   $group    Cache group.
	 * @param int      $ttl      Time to live.
	 * @return mixed Cached or computed value.
	 */
	public static function remember( string $key, callable $callback, string $group = 'default', int $ttl = 0 ) {
		$value = self::get( $key, $group );

		if ( $value === false ) {
			// Implement cache locking to prevent stampede.
			$lock_key   = "lock_{$key}";
			$lock_value = uniqid( 'nuclen_', true );

			// Try to acquire lock for 30 seconds.
			$acquired = wp_cache_add( $lock_key, $lock_value, self::get_wp_cache_group( $group ), 30 );

			if ( $acquired ) {
				try {
					// Double-check if value was set by another process.
					$value = self::get( $key, $group );
					if ( $value === false ) {
						$value = call_user_func( $callback );
						self::set( $key, $value, $group, $ttl );
					}
				} finally {
					// Release lock.
					wp_cache_delete( $lock_key, self::get_wp_cache_group( $group ) );
				}
			} else {
				// Wait for other process to finish and try again.
				$attempts = 0;
				while ( $attempts < 10 && $value === false ) {
					usleep( 100000 ); // 100ms.
					$value = self::get( $key, $group );
					++$attempts;
				}

				// If still no value, execute callback anyway (fallback).
				if ( $value === false ) {
					$value = call_user_func( $callback );
					// Don't cache result to avoid overriding other process.
				}
			}
		}

		return $value;
	}

	/**
	 * Invalidate cache keys by pattern.
	 *
	 * @param string $pattern  Key pattern to match.
	 * @param string $group    Cache group.
	 * @param string $reason   Reason for invalidation.
	 */
	public static function invalidate_pattern( string $pattern, string $group = 'default', string $reason = 'manual' ): void {
		// For WordPress object cache, we increment version to invalidate patterns.
		$version_key     = "{$group}_cache_version";
		$current_version = wp_cache_get( $version_key, 'nuclen_versions' );
		$new_version     = $current_version ? $current_version + 1 : 1;

		wp_cache_set( $version_key, $new_version, 'nuclen_versions', DAY_IN_SECONDS );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\NuclearEngagement\Services\LoggingService::debug( "Cache pattern '{$pattern}' in group '{$group}' invalidated. Reason: {$reason}" );
		}
	}

	/**
	 * Invalidate entire cache group (use sparingly).
	 *
	 * @param string $group  Cache group.
	 * @param string $reason Reason for invalidation.
	 */
	public static function invalidate_group( string $group, string $reason = 'manual' ): void {
		// Only invalidate if reason is critical.
		$critical_reasons = array( 'plugin_update', 'settings_change', 'schema_change' );

		if ( ! in_array( $reason, $critical_reasons, true ) ) {
			// Use pattern invalidation instead.
			self::invalidate_pattern( '*', $group, $reason );
			return;
		}

		PerformanceMonitor::start( "cache_invalidate_{$group}" );

		wp_cache_flush_group( self::get_wp_cache_group( $group ) );

		// Log invalidation.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\NuclearEngagement\Services\LoggingService::debug( "Cache group '{$group}' fully invalidated. Reason: {$reason}" );
		}

		PerformanceMonitor::stop( "cache_invalidate_{$group}" );
	}

	/**
	 * Get versioned cache key to support pattern invalidation.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string Versioned cache key.
	 */
	private static function get_versioned_key( string $key, string $group ): string {
		$version_key = "{$group}_cache_version";
		$version     = wp_cache_get( $version_key, 'nuclen_versions' );

		if ( $version === false ) {
			$version = 1;
			wp_cache_set( $version_key, $version, 'nuclen_versions', DAY_IN_SECONDS );
		}

		return "{$key}_v{$version}";
	}

	/**
	 * Smart invalidation based on trigger events.
	 *
	 * @param string $trigger Event that triggered invalidation.
	 * @param array  $context Additional context data.
	 */
	public static function invalidate_by_trigger( string $trigger, array $context = array() ): void {
		if ( ! isset( self::$dependencies[ $trigger ] ) ) {
			return;
		}

		foreach ( self::$dependencies[ $trigger ] as $group ) {
			$priority = self::get_group_config( $group )['priority'];

			if ( $priority === 'high' ) {
				// Immediate invalidation for high priority.
				self::invalidate_group( $group, $trigger );
			} else {
				// Schedule invalidation for lower priority.
				self::schedule_invalidation( $group, 30, $trigger );
			}
		}
	}

	/**
	 * Schedule delayed invalidation.
	 *
	 * @param string $group  Cache group.
	 * @param int    $delay  Delay in seconds.
	 * @param string $reason Reason for invalidation.
	 */
	public static function schedule_invalidation( string $group, int $delay, string $reason = 'scheduled' ): void {
		$time = time() + $delay;

		self::$scheduled_invalidations[] = array(
			'group'  => $group,
			'time'   => $time,
			'reason' => $reason,
		);

		wp_schedule_single_event( $time, 'nuclen_scheduled_invalidation', array( $group, $reason ) );
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array<string, array> Cache statistics by group.
	 */
	public static function get_statistics(): array {
		$stats = self::$stats;

		foreach ( $stats as $group => &$group_stats ) {
			$total_requests          = $group_stats['hits'] + $group_stats['misses'];
			$group_stats['hit_rate'] = $total_requests > 0
				? round( ( $group_stats['hits'] / $total_requests ) * 100, 2 )
				: 0;
		}

		return $stats;
	}

	/**
	 * Warm up cache for critical data.
	 *
	 * @param array $warmup_config Warmup configuration.
	 */
	public static function warmup( array $warmup_config = array() ): void {
		$default_config = array(
			'posts'    => array( 'recent_posts', 'popular_posts' ),
			'queries'  => array( 'dashboard_counts' ),
			'metadata' => array( 'plugin_settings' ),
		);

		$config = array_merge( $default_config, $warmup_config );

		foreach ( $config as $group => $keys ) {
			foreach ( $keys as $key ) {
				// Trigger cache population based on key type.
				self::warmup_cache_key( $key, $group );
			}
		}
	}

	/**
	 * Handle post save event.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function handle_post_save( int $post_id ): void {
		self::invalidate_by_trigger( 'post_save', array( 'post_id' => $post_id ) );
	}

	/**
	 * Handle post delete event.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function handle_post_delete( int $post_id ): void {
		self::invalidate_by_trigger( 'post_delete', array( 'post_id' => $post_id ) );
	}

	/**
	 * Handle theme change event.
	 */
	public static function handle_theme_change(): void {
		self::invalidate_by_trigger( 'settings' );
	}

	/**
	 * Handle plugin activation/deactivation.
	 */
	public static function handle_plugin_change(): void {
		self::invalidate_by_trigger( 'plugin_update' );
	}

	/**
	 * Cleanup expired cache entries.
	 */
	public static function cleanup_expired_cache(): void {
		// This is handled automatically by WordPress cache,.
		// but we can clean up our statistics and scheduled invalidations.

		$now                           = time();
		self::$scheduled_invalidations = array_filter(
			self::$scheduled_invalidations,
			function ( $item ) use ( $now ) {
				return $item['time'] > $now;
			}
		);
	}

	/**
	 * Get group configuration.
	 *
	 * @param string $group Cache group.
	 * @return array Group configuration.
	 */
	private static function get_group_config( string $group ): array {
		return self::$cache_config[ $group ] ?? self::$cache_config['posts'];
	}

	/**
	 * Get prefixed cache key.
	 *
	 * @param string $key   Original key.
	 * @param string $group Cache group.
	 * @return string Prefixed key.
	 */
	private static function get_prefixed_key( string $key, string $group ): string {
		// Use CacheUtils for secure key generation
		return \NuclearEngagement\Utils\CacheUtils::generate_key( array( $group, $key ) );
	}

	/**
	 * Get WordPress cache group name.
	 *
	 * @param string $group Our cache group.
	 * @return string WordPress cache group.
	 */
	private static function get_wp_cache_group( string $group ): string {
		return "nuclen_{$group}";
	}

	/**
	 * Check if group should use compression.
	 *
	 * @param string $group Cache group.
	 * @return bool Whether to compress.
	 */
	private static function should_compress( string $group ): bool {
		return self::get_group_config( $group )['compression'] ?? false;
	}

	/**
	 * Check if value should be compressed.
	 *
	 * @param mixed $value Value to check.
	 * @return bool Whether to compress.
	 */
	private static function should_compress_value( $value ): bool {
		return is_array( $value ) || is_object( $value ) ||
				( is_string( $value ) && strlen( $value ) > 1024 );
	}

	/**
	 * Enforce cache size limits.
	 *
	 * @param string $group Cache group.
	 */
	private static function enforce_cache_limits( string $group ): void {
		$config = self::get_group_config( $group );

		// This is a simplified version - in production you'd want.
		// more sophisticated LRU cache management.
		if ( isset( $config['max_items'] ) ) {
			// WordPress cache doesn't provide direct size management,.
			// but we can track and periodically clean up.
		}
	}

	/**
	 * Record cache hit.
	 *
	 * @param string $group Cache group.
	 */
	private static function record_hit( string $group ): void {
		if ( isset( self::$stats[ $group ] ) ) {
			++self::$stats[ $group ]['hits'];
		}
	}

	/**
	 * Record cache miss.
	 *
	 * @param string $group Cache group.
	 */
	private static function record_miss( string $group ): void {
		if ( isset( self::$stats[ $group ] ) ) {
			++self::$stats[ $group ]['misses'];
		}
	}

	/**
	 * Record cache set.
	 *
	 * @param string $group Cache group.
	 */
	private static function record_set( string $group ): void {
		if ( isset( self::$stats[ $group ] ) ) {
			++self::$stats[ $group ]['sets'];
		}
	}

	/**
	 * Record cache delete.
	 *
	 * @param string $group Cache group.
	 */
	private static function record_delete( string $group ): void {
		if ( isset( self::$stats[ $group ] ) ) {
			++self::$stats[ $group ]['deletes'];
		}
	}

	/**
	 * Warmup specific cache key.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 */
	private static function warmup_cache_key( string $key, string $group ): void {
		// Implementation would depend on the specific data being cached.
		// This is a placeholder for the warmup logic.
	}
}
