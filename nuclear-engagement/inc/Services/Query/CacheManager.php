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
	private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

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
		$transient_key = 'nuclen_pq_' . $cache_key;
		
		$found = false;
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
		
		if ( ! $found ) {
			$cached = get_transient( $transient_key );
		}

		return is_array( $cached ) ? $cached : false;
	}

	/**
	 * Cache result for request.
	 *
	 * @param PostsCountRequest $request The request.
	 * @param array $result The result to cache.
	 */
	public function cache_result( PostsCountRequest $request, array $result ): void {
		$cache_key = $this->get_cache_key( $request );
		$transient_key = 'nuclen_pq_' . $cache_key;

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );
		set_transient( $transient_key, $result, self::CACHE_TTL );
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
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		update_option( self::VERSION_OPTION, $version + 1, false );

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_flush();
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
}