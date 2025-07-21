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
		$this->query_builder   = new QueryBuilder();
		$this->cache_manager   = new CacheManager();
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

		$sql      = $this->build_sql_clauses( $request );
		$post_ids = $this->batch_processor->fetch_posts_in_batches( $sql );

		if ( $wpdb->last_error ) {
			LoggingService::log( 'Posts query error: ' . $wpdb->last_error );
		}

		$post_ids = array_unique( array_map( 'intval', $post_ids ) );
		$count    = count( $post_ids );

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

		// Get total count first
		$total_count = $this->get_posts_count_only( $request );

		// Fetch all post IDs in batches
		$all_post_ids = array();
		$batch_size   = 500;
		$offset       = 0;

		while ( $offset < $total_count ) {
			$optimizer = QueryOptimizer::getInstance();
			$sql       = $this->build_optimized_sql( $request, $batch_size, $offset );
			$params    = $this->get_query_params( $request );

			$batch_results = $optimizer->query( $sql, $params, 600 );
			if ( empty( $batch_results ) ) {
				break;
			}

			$all_post_ids = array_merge( $all_post_ids, array_column( $batch_results, 'ID' ) );
			$offset      += $batch_size;

			// Memory check
			if ( memory_get_usage( true ) > $this->get_memory_limit() * 0.8 ) {
				LoggingService::log( 'Memory limit approaching, stopping batch fetch' );
				break;
			}
		}

		$result = array(
			'count'    => count( $all_post_ids ),
			'post_ids' => array_unique( $all_post_ids ),
		);

		$this->cache_manager->cache_result( $request, $result );

		return $result;
	}

	/**
	 * Build optimized SQL query.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @param int               $limit Optional limit for the query.
	 * @param int               $offset Optional offset for the query.
	 * @return string The optimized SQL query.
	 */
	private function build_optimized_sql( PostsCountRequest $request, int $limit = 0, int $offset = 0 ): string {
		$clauses = $this->build_sql_clauses( $request );
		$sql     = "SELECT DISTINCT p.ID {$clauses} ORDER BY p.ID ASC";

		if ( $limit > 0 ) {
			$sql .= " LIMIT {$limit}";
			if ( $offset > 0 ) {
				$sql .= " OFFSET {$offset}";
			}
		}

		return $sql;
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
		$cached    = wp_cache_get( $cache_key, 'nuclen_posts_query' );

		if ( $cached !== false ) {
			return (int) $cached;
		}

		global $wpdb;

		$sql         = $this->build_sql_clauses( $request );
		$count_query = "SELECT COUNT(DISTINCT p.ID) {$sql}";
		$count       = (int) DatabaseUtils::execute_query( $count_query, 'posts_count_query' );

		// Cache for longer period (1 hour instead of 10 minutes)
		wp_cache_set( $cache_key, $count, 'nuclen_posts_query', 3600 );

		return $count;
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int Memory limit in bytes.
	 */
	private function get_memory_limit(): int {
		$memory_limit = ini_get( 'memory_limit' );

		if ( $memory_limit == -1 ) {
			return PHP_INT_MAX;
		}

		$value = (int) $memory_limit;
		$unit  = strtolower( substr( $memory_limit, -1 ) );

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
}
