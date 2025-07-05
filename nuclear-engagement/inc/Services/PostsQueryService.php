<?php
/**
 * PostsQueryService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/PostsQueryService.php
 *
 * Posts Query Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Requests\PostsCountRequest;
use NuclearEngagement\Services\LoggingService;
use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Traits\CacheInvalidationTrait;
use NuclearEngagement\Core\Query\QueryOptimizer;
use NuclearEngagement\Utils\DatabaseUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for querying posts
 */
class PostsQueryService {
	use CacheInvalidationTrait;

		/** Cache group for query results. */
	private const CACHE_GROUP = 'nuclen_posts_query';

		/** Cache lifetime in seconds. */
	private const CACHE_TTL = 10 * MINUTE_IN_SECONDS; // 10 minutes.

	/** Option name storing cache version. */
	private const VERSION_OPTION = 'nuclen_posts_query_version';

	/** Maximum posts to process per batch */
	private const BATCH_SIZE = 200;

	/** Maximum total posts to prevent memory issues */
	private const MAX_POSTS = 2000;

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
		 */
	private function get_cache_version(): int {
			return (int) get_option( self::VERSION_OPTION, 1 );
	}

		/**
		 * Generate a cache key for the given request.
		 *
		 * @param PostsCountRequest $request The posts count request.
		 * @return string The cache key.
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
	 * Build query args from request.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array The query arguments.
	 */
	public function build_query_args( PostsCountRequest $request ): array {
		$meta_query = array( 'relation' => 'AND' );

		// Ensure we have a valid post type.
		$post_type = ! empty( $request->postType ) ? $request->postType : 'post';

		// Handle post status properly.
		if ( 'any' === $request->postStatus ) {
			// Use a predefined list of common viewable statuses.
			$post_status = array( 'publish', 'private', 'draft', 'pending', 'future' );
		} else {
			$post_status = $request->postStatus;
		}

		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 500, // Reduced limit for better memory management.
			'post_status'    => $post_status,
			'fields'         => 'ids',
		);

		if ( $request->categoryId ) {
			$query_args['cat'] = $request->categoryId;
		}

		if ( $request->authorId ) {
			$query_args['author'] = $request->authorId;
		}

		// Skip existing data if not allowing regeneration.
		if ( ! $request->allowRegenerate ) {
			$meta_key     = 'quiz' === $request->workflow ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			$meta_query[] = array(
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			);
		}

		// Skip protected data if not allowed.
		if ( ! $request->regenerateProtected ) {
			$protected_key = 'quiz' === $request->workflow ? 'nuclen_quiz_protected' : Summary_Service::PROTECTED_KEY;
			$meta_query[]  = array(
				'relation' => 'OR',
				array(
					'key'     => $protected_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $protected_key,
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}

		// Only add meta_query if we have conditions.
		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query;
		}

		// Disable caching for performance during counts.
		$query_args['update_post_meta_cache'] = false;
		$query_args['update_post_term_cache'] = false;
		$query_args['cache_results']          = false;

		return $query_args;
	}

	/**
	 * Build SQL JOIN and WHERE clauses for a posts count query.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return string The SQL clauses.
	 */
	private function build_sql_clauses( PostsCountRequest $request ): string {
		global $wpdb;

		$joins  = array();
		$wheres = array();

		// Ensure we have a valid post type.
		$post_type = ! empty( $request->postType ) ? $request->postType : 'post';
		$wheres[]  = $wpdb->prepare( 'p.post_type = %s', $post_type );

		if ( 'any' !== $request->postStatus ) {
			$wheres[] = $wpdb->prepare( 'p.post_status = %s', $request->postStatus );
		} else {
			// When 'any' is selected, use a predefined list of common viewable statuses.
			$viewable_statuses = array( 'publish', 'private', 'draft', 'pending', 'future' );

			if ( ! empty( $viewable_statuses ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $viewable_statuses ), '%s' ) );
				// Build the query with proper escaping.
				$prepared_args = array( "p.post_status IN ($placeholders)" );
				$prepared_args = array_merge( $prepared_args, $viewable_statuses );
				$wheres[]      = call_user_func_array( array( $wpdb, 'prepare' ), $prepared_args );
			}
		}

		if ( $request->authorId ) {
			$wheres[] = $wpdb->prepare( 'p.post_author = %d', $request->authorId );
		}

		if ( $request->categoryId ) {
			$joins[]  = "JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
			$joins[]  = "JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'";
			$wheres[] = $wpdb->prepare( 'tt.term_id = %d', $request->categoryId );
		}

		if ( ! $request->allowRegenerate ) {
			$meta_key = 'quiz' === $request->workflow ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			$joins[]  = $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} pm_exist ON pm_exist.post_id = p.ID AND pm_exist.meta_key = %s", $meta_key );
			$wheres[] = 'pm_exist.meta_id IS NULL';
		}

		if ( ! $request->regenerateProtected ) {
			$prot_key = 'quiz' === $request->workflow ? 'nuclen_quiz_protected' : Summary_Service::PROTECTED_KEY;
			$joins[]  = $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} pm_prot ON pm_prot.post_id = p.ID AND pm_prot.meta_key = %s", $prot_key );
			$wheres[] = "(pm_prot.meta_id IS NULL OR pm_prot.meta_value != '1')";
		}

		$sql = "FROM {$wpdb->posts} p " . implode( ' ', $joins );
		if ( $wheres ) {
			$sql .= ' WHERE ' . implode( ' AND ', $wheres );
		}

		return $sql;
	}


	/**
	 * Get posts count and IDs.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array Array containing count and post IDs.
	 */
	public function get_posts_count( PostsCountRequest $request ): array {
			$cache_key     = $this->get_cache_key( $request );
			$transient_key = 'nuclen_pq_' . $cache_key;
			$found         = false;
			$cached        = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
				$cached = get_transient( $transient_key );
		}

		if ( is_array( $cached ) ) {
					return $cached;
		}

		global $wpdb;

		$sql = $this->build_sql_clauses( $request );

		// Debug logging (commented out temporarily to isolate 500 error).
		// LoggingService::log( 'PostsQueryService: Request post type: ' . $request->postType );
		// LoggingService::log( 'PostsQueryService: Request post status: ' . $request->postStatus );
		// LoggingService::log( 'PostsQueryService: SQL clauses: ' . $sql );

		// Use optimized memory-efficient batch processing.
		$post_ids = $this->fetch_posts_in_batches( $sql );

		if ( $wpdb->last_error ) {
			LoggingService::log( 'Posts query error: ' . $wpdb->last_error );
		}

		// Ensure unique post IDs.
		$post_ids = array_unique( array_map( 'intval', $post_ids ) );

		// Count is the actual number of unique post IDs found.
		$count = count( $post_ids );

		// Debug logging (commented out temporarily).
		// LoggingService::log( 'PostsQueryService: Total posts found: ' . $count );
		// LoggingService::log( 'PostsQueryService: Allow regenerate: ' . ( $request->allowRegenerate ? 'true' : 'false' ) );
		// LoggingService::log( 'PostsQueryService: Workflow: ' . $request->workflow );

		$result = array(
			'count'    => $count,
			'post_ids' => array_values( $post_ids ), // Re-index array.
		);

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );
		set_transient( $transient_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Optimized posts query using QueryOptimizer.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array Array containing count and post IDs.
	 */
	public function countPostsOptimized( PostsCountRequest $request ): array {
		$cache_key = $this->get_cache_key( $request );

		// Try cache first.
		$cached_result = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $cached_result !== false ) {
			return $cached_result;
		}

		$optimizer = QueryOptimizer::getInstance();

		// Build optimized SQL.
		$sql    = $this->build_optimized_sql( $request );
		$params = $this->get_query_params( $request );

		// Execute with optimization.
		$post_ids = $optimizer->query( $sql, $params, self::CACHE_TTL );

		$result = array(
			'count'    => count( $post_ids ),
			'post_ids' => array_column( $post_ids, 'ID' ),
		);

		// Cache result.
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Build optimized SQL query.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return string The optimized SQL query.
	 */
	private function build_optimized_sql( PostsCountRequest $request ): string {
		global $wpdb;

		$select = 'SELECT DISTINCT p.ID';
		$from   = "FROM {$wpdb->posts} p";
		$joins  = array();
		$where  = array( '1=1' );

		// Post type filter.
		$where[] = 'p.post_type = %s';

		// Status filter.
		if ( 'any' !== $request->postStatus ) {
			$where[] = 'p.post_status = %s';
		} else {
			$where[] = "p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')";
		}

		// Author filter.
		if ( $request->authorId ) {
			$where[] = 'p.post_author = %d';
		}

		// Category filter.
		if ( $request->categoryId ) {
			$joins[] = "JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
			$joins[] = "JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'";
			$where[] = 'tt.term_id = %d';
		}

		// Regenerate filters.
		if ( ! $request->allowRegenerate ) {
			$meta_key = 'quiz' === $request->workflow ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			$joins[]  = "LEFT JOIN {$wpdb->postmeta} pm_exist ON pm_exist.post_id = p.ID AND pm_exist.meta_key = %s";
			$where[]  = 'pm_exist.meta_id IS NULL';
		}

		if ( ! $request->regenerateProtected ) {
			$prot_key = 'quiz' === $request->workflow ? 'nuclen_quiz_protected' : Summary_Service::PROTECTED_KEY;
			$joins[]  = "LEFT JOIN {$wpdb->postmeta} pm_prot ON pm_prot.post_id = p.ID AND pm_prot.meta_key = %s";
			$where[]  = "(pm_prot.meta_id IS NULL OR pm_prot.meta_value != '1')";
		}

		$join_sql  = $joins ? ' ' . implode( ' ', $joins ) : '';
		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		return "{$select} {$from}{$join_sql} {$where_sql} ORDER BY p.ID ASC LIMIT 1000";
	}

	/**
	 * Get query parameters for prepared statement.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array The query parameters.
	 */
	private function get_query_params( PostsCountRequest $request ): array {
		$params = array();

		// Post type.
		$params[] = $request->postType ?: 'post';

		// Status (only if not 'any').
		if ( 'any' !== $request->postStatus ) {
			$params[] = $request->postStatus;
		}

		// Author.
		if ( $request->authorId ) {
			$params[] = $request->authorId;
		}

		// Category.
		if ( $request->categoryId ) {
			$params[] = $request->categoryId;
		}

		// Meta keys for regenerate filters.
		if ( ! $request->allowRegenerate ) {
			$meta_key = 'quiz' === $request->workflow ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			$params[] = $meta_key;
		}

		if ( ! $request->regenerateProtected ) {
			$prot_key = 'quiz' === $request->workflow ? 'nuclen_quiz_protected' : Summary_Service::PROTECTED_KEY;
			$params[] = $prot_key;
		}

		return $params;
	}

	/**
	 * Fetch posts in memory-efficient batches.
	 *
	 * @param string $sql_clauses SQL WHERE/JOIN clauses.
	 * @return array Array of post IDs.
	 */
	private function fetch_posts_in_batches( string $sql_clauses ): array {
		global $wpdb;

		$post_ids        = array();
		$offset          = 0;
		$processed_total = 0;

		// Monitor memory usage.
		$initial_memory = memory_get_usage( true );
		$memory_limit   = $this->get_memory_limit();

		do {
			// Adjust batch size based on available memory.
			$batch_size = $this->calculate_safe_batch_size( $processed_total );

			$query = $wpdb->prepare(
				"SELECT DISTINCT p.ID $sql_clauses ORDER BY p.ID ASC LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			);

			$batch = $wpdb->get_col( $query );

			if ( empty( $batch ) ) {
				break;
			}

			// Convert to integers and merge.
			$batch_ids = array_map( 'intval', $batch );
			$post_ids  = array_merge( $post_ids, $batch_ids );

			$processed_total += count( $batch );
			$offset          += $batch_size;

			// Check memory usage and break if approaching limit.
			$current_memory       = memory_get_usage( true );
			$memory_usage_percent = ( $current_memory / $memory_limit ) * 100;

			if ( $memory_usage_percent > 80 ) {
				LoggingService::log( "PostsQueryService: Memory usage at {$memory_usage_percent}%, stopping batch processing" );
				break;
			}

			// Break if we've hit the maximum post limit.
			if ( $processed_total >= self::MAX_POSTS ) {
				LoggingService::log( 'PostsQueryService: Reached maximum post limit (' . self::MAX_POSTS . ')' );
				break;
			}

			// Force garbage collection every few batches.
			if ( $offset % ( self::BATCH_SIZE * 5 ) === 0 ) {
				if ( function_exists( 'gc_collect_cycles' ) ) {
					gc_collect_cycles();
				}
			}
		} while ( count( $batch ) === $batch_size );

		// Final cleanup.
		$post_ids = array_unique( $post_ids );

		$final_memory = memory_get_usage( true );
		$memory_used  = $final_memory - $initial_memory;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			LoggingService::log(
				sprintf(
					'PostsQueryService: Processed %d posts in %d batches, memory used: %s',
					count( $post_ids ),
					ceil( $offset / self::BATCH_SIZE ),
					size_format( $memory_used )
				)
			);
		}

		return $post_ids;
	}

	/**
	 * Calculate safe batch size based on available memory.
	 *
	 * @param int $processed_so_far Number of posts processed so far.
	 * @return int Safe batch size.
	 */
	private function calculate_safe_batch_size( int $processed_so_far ): int {
		$current_memory       = memory_get_usage( true );
		$memory_limit         = $this->get_memory_limit();
		$memory_usage_percent = ( $current_memory / $memory_limit ) * 100;

		// Reduce batch size as memory usage increases.
		if ( $memory_usage_percent > 70 ) {
			return max( 50, self::BATCH_SIZE / 4 );
		} elseif ( $memory_usage_percent > 50 ) {
			return max( 100, self::BATCH_SIZE / 2 );
		}

		return self::BATCH_SIZE;
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int Memory limit in bytes.
	 */
	private function get_memory_limit(): int {
		$memory_limit = ini_get( 'memory_limit' );

		if ( $memory_limit === -1 ) {
			return PHP_INT_MAX; // No limit.
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

	/**
	 * Optimized count-only query for better performance.
	 *
	 * @param PostsCountRequest $request Request parameters.
	 * @return int Post count.
	 */
	public function get_posts_count_only( PostsCountRequest $request ): int {
		$cache_key = 'count_only_' . $this->get_cache_key( $request );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( $cached !== false ) {
			return (int) $cached;
		}

		global $wpdb;

		$sql         = $this->build_sql_clauses( $request );
		$count_query = "SELECT COUNT(DISTINCT p.ID) $sql";

		$count = (int) DatabaseUtils::execute_query( $count_query, 'posts_count_query' );

		// Cache the count result.
		wp_cache_set( $cache_key, $count, self::CACHE_GROUP, self::CACHE_TTL );

		return $count;
	}

	/**
	 * Get memory usage statistics for monitoring.
	 *
	 * @return array Memory usage statistics.
	 */
	public function get_memory_stats(): array {
		return array(
			'current_usage' => memory_get_usage( true ),
			'peak_usage'    => memory_get_peak_usage( true ),
			'limit'         => $this->get_memory_limit(),
			'usage_percent' => ( memory_get_usage( true ) / $this->get_memory_limit() ) * 100,
			'batch_size'    => self::BATCH_SIZE,
			'max_posts'     => self::MAX_POSTS,
		);
	}
}
