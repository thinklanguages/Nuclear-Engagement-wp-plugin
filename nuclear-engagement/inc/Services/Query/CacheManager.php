<?php
/**
 * CacheManager.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_Query
 */

declare(strict_types=1);

namespace NuclearEngagement\Services\Query;

use NuclearEngagement\Requests\PostsCountRequest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages caching for posts queries
 */
class CacheManager {

	/** Cache group for query results. */
	private const CACHE_GROUP = 'nuclen_posts_query';

	/** Cache lifetime in seconds. */
	private const CACHE_TTL = 60 * MINUTE_IN_SECONDS; // 1 hour default

	/** Short cache TTL for frequently changing data */
	private const SHORT_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/** Long cache TTL for static data */
	private const LONG_CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** Option name storing cache version. */
	private const VERSION_OPTION = 'nuclen_posts_query_version';

	/**
	 * Get cached result for request.
	 *
	 * @param PostsCountRequest $request The request.
	 * @return array|false Cached result or false.
	 */
	public function get_cached_result( PostsCountRequest $request ) {
		$cache_key = $this->get_cache_key( $request );

		// Try object cache first (faster)
		$found  = false;
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );

		if ( $found && is_array( $cached ) ) {
			return $cached;
		}

		// Fall back to transient for persistent cache
		if ( ! wp_using_ext_object_cache() ) {
			$transient_key = 'nuclen_pq_' . substr( $cache_key, 0, 20 ); // Limit key length
			$cached        = get_transient( $transient_key );

			if ( is_array( $cached ) ) {
				// Repopulate object cache
				wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, self::CACHE_TTL );
				return $cached;
			}
		}

		return false;
	}

	/**
	 * Cache result for request.
	 *
	 * @param PostsCountRequest $request The request.
	 * @param array             $result The result to cache.
	 * @param int|null          $ttl Custom TTL in seconds.
	 */
	public function cache_result( PostsCountRequest $request, array $result, ?int $ttl = null ): void {
		$cache_key = $this->get_cache_key( $request );

		// Determine appropriate TTL based on result size and type
		if ( null === $ttl ) {
			$ttl = $this->calculate_optimal_ttl( $request, $result );
		}

		// Always use object cache
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, $ttl );

		// Use transient only if not using external object cache
		if ( ! wp_using_ext_object_cache() ) {
			$transient_key = 'nuclen_pq_' . substr( $cache_key, 0, 20 );
			set_transient( $transient_key, $result, $ttl );
		}
	}

	/**
	 * Get cache key for count-only queries.
	 *
	 * @param PostsCountRequest $request The request.
	 * @return string Cache key.
	 */
	public function get_count_cache_key( PostsCountRequest $request ): string {
		return 'count_only_' . $this->get_cache_key( $request );
	}

	/**
	 * Clear all cached query results.
	 */
	public function clear_cache(): void {
		// Increment version to invalidate all caches
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		update_option( self::VERSION_OPTION, $version + 1, false );

		// Clear object cache group if supported
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} elseif ( function_exists( 'wp_cache_flush_runtime' ) ) {
			// Flush runtime cache only (available in WP 6.0+)
			wp_cache_flush_runtime();
		}

		// Clean up old transients if not using external cache
		if ( ! wp_using_ext_object_cache() ) {
			$this->cleanup_transients();
		}
	}

	/**
	 * Get current cache version.
	 *
	 * @return int Cache version.
	 */
	public function get_cache_version(): int {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * Generate cache key for request.
	 *
	 * @param PostsCountRequest $request The request.
	 * @return string Cache key.
	 */
	private function get_cache_key( PostsCountRequest $request ): string {
		$data = array(
			$request->postType,
			$request->postStatus,
			$request->categoryId,
			$request->authorId,
			$request->allowRegenerate ? 1 : 0,
			$request->regenerateProtected ? 1 : 0,
			$request->workflow,
			$this->get_cache_version(),
			get_current_blog_id(),
		);

		return md5( wp_json_encode( $data ) );
	}

	/**
	 * Calculate optimal TTL based on request and result.
	 *
	 * @param PostsCountRequest $request The request.
	 * @param array             $result The result.
	 * @return int TTL in seconds.
	 */
	private function calculate_optimal_ttl( PostsCountRequest $request, array $result ): int {
		$post_count = $result['count'] ?? 0;

		// Static queries (no regeneration) can be cached longer
		if ( ! $request->allowRegenerate ) {
			return self::LONG_CACHE_TTL;
		}

		// Large result sets change less frequently
		if ( $post_count > 1000 ) {
			return self::CACHE_TTL * 2; // 2 hours
		}

		// Small result sets might change more frequently
		if ( $post_count < 50 ) {
			return self::SHORT_CACHE_TTL;
		}

		// Default TTL for medium-sized results
		return self::CACHE_TTL;
	}

	/**
	 * Clean up old transients.
	 */
	private function cleanup_transients(): void {
		global $wpdb;

		// Delete expired transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_value < %d",
				'_transient_timeout_nuclen_pq_%',
				time()
			)
		);

		// Delete orphaned transients
		$wpdb->query(
			"DELETE o1 FROM {$wpdb->options} o1
			LEFT JOIN {$wpdb->options} o2 
			ON o2.option_name = REPLACE(o1.option_name, '_transient_', '_transient_timeout_')
			WHERE o1.option_name LIKE '_transient_nuclen_pq_%'
			AND o2.option_name IS NULL"
		);
	}

	/**
	 * Warm cache with common queries.
	 *
	 * @param array $post_types Post types to warm cache for.
	 */
	public function warm_cache( array $post_types = array( 'post' ) ): void {
		// This would be called via WP-Cron to pre-populate common queries
		$common_statuses = array( 'publish' );

		foreach ( $post_types as $post_type ) {
			foreach ( $common_statuses as $status ) {
				$request             = new PostsCountRequest();
				$request->postType   = $post_type;
				$request->postStatus = $status;

				// Trigger cache population
				$cache_key = $this->get_cache_key( $request );
				wp_cache_add( $cache_key . '_warming', true, self::CACHE_GROUP, 60 );
			}
		}
	}
}
