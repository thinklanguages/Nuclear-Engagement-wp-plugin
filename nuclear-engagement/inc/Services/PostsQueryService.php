<?php
/**
 * PostsQueryService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Requests\PostsCountRequest;
use NuclearEngagement\Services\LoggingService;
use NuclearEngagement\Services\Query\QueryBuilder;
use NuclearEngagement\Services\Query\CacheManager;
use NuclearEngagement\Services\Query\BatchProcessor;
use NuclearEngagement\Traits\CacheInvalidationTrait;
use NuclearEngagement\Core\Query\QueryOptimizer;
use NuclearEngagement\Utils\DatabaseUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for querying posts - refactored for better maintainability
 */
class PostsQueryService {
	use CacheInvalidationTrait;

	/** @var QueryBuilder */
	private $query_builder;

	/** @var CacheManager */
	private $cache_manager;

	/** @var BatchProcessor */
	private $batch_processor;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->query_builder = new QueryBuilder();
		$this->cache_manager = new CacheManager();
		$this->batch_processor = new BatchProcessor();
	}

	/**
	 * Register hooks to invalidate caches when posts or terms change.
	 */
	public static function register_hooks(): void {
		$cb = array( self::class, 'clear_cache' );
		self::register_invalidation_hooks( $cb );
	}

	/**
	 * Clear all cached query results.
	 */
	public static function clear_cache(): void {
		$cache_manager = new CacheManager();
		$cache_manager->clear_cache();
	}

	/**
	 * Build query args from request.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array The query arguments.
	 */
	public function build_query_args( PostsCountRequest $request ): array {
		return $this->query_builder->build_query_args( $request );
	}

	/**
	 * Build SQL JOIN and WHERE clauses for a posts count query.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return string The SQL clauses.
	 */
	private function build_sql_clauses( PostsCountRequest $request ): string {
		return $this->query_builder->build_sql_clauses( $request );
	}


	/**
	 * Get posts count and IDs.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array Array containing count and post IDs.
	 */
	public function get_posts_count( PostsCountRequest $request ): array {
		// Check cache first
		$cached = $this->cache_manager->get_cached_result( $request );
		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;

		$sql = $this->build_sql_clauses( $request );
		$post_ids = $this->batch_processor->fetch_posts_in_batches( $sql );

		if ( $wpdb->last_error ) {
			LoggingService::log( 'Posts query error: ' . $wpdb->last_error );
		}

		$post_ids = array_unique( array_map( 'intval', $post_ids ) );
		$count = count( $post_ids );

		$result = array(
			'count'    => $count,
			'post_ids' => array_values( $post_ids ),
		);

		$this->cache_manager->cache_result( $request, $result );

		return $result;
	}

	/**
	 * Optimized posts query using QueryOptimizer.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array Array containing count and post IDs.
	 */
	public function countPostsOptimized( PostsCountRequest $request ): array {
		$cached = $this->cache_manager->get_cached_result( $request );
		if ( $cached !== false ) {
			return $cached;
		}

		$optimizer = QueryOptimizer::getInstance();
		$sql = $this->build_optimized_sql( $request );
		$params = $this->get_query_params( $request );

		$post_ids = $optimizer->query( $sql, $params, 600 );

		$result = array(
			'count'    => count( $post_ids ),
			'post_ids' => array_column( $post_ids, 'ID' ),
		);

		$this->cache_manager->cache_result( $request, $result );

		return $result;
	}

	/**
	 * Build optimized SQL query.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return string The optimized SQL query.
	 */
	private function build_optimized_sql( PostsCountRequest $request ): string {
		$clauses = $this->build_sql_clauses( $request );
		return "SELECT DISTINCT p.ID {$clauses} ORDER BY p.ID ASC LIMIT 1000";
	}

	/**
	 * Get query parameters for prepared statement.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array The query parameters.
	 */
	private function get_query_params( PostsCountRequest $request ): array {
		$params = array( $request->postType ?: 'post' );

		if ( 'any' !== $request->postStatus ) {
			$params[] = $request->postStatus;
		}

		if ( $request->authorId ) {
			$params[] = $request->authorId;
		}

		if ( $request->categoryId ) {
			$params[] = $request->categoryId;
		}

		return $params;
	}

	/**
	 * Optimized count-only query for better performance.
	 *
	 * @param PostsCountRequest $request Request parameters.
	 * @return int Post count.
	 */
	public function get_posts_count_only( PostsCountRequest $request ): int {
		$cache_key = $this->cache_manager->get_count_cache_key( $request );
		$cached = wp_cache_get( $cache_key, 'nuclen_posts_query' );

		if ( $cached !== false ) {
			return (int) $cached;
		}

		global $wpdb;

		$sql = $this->build_sql_clauses( $request );
		$count_query = "SELECT COUNT(DISTINCT p.ID) {$sql}";
		$count = (int) DatabaseUtils::execute_query( $count_query, 'posts_count_query' );

		wp_cache_set( $cache_key, $count, 'nuclen_posts_query', 600 );

		return $count;
	}

}
