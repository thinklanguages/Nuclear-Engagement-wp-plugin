<?php
/**
 * QueryOptimizer.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database query optimization layer.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class QueryOptimizer {
	/**
	 * Query cache for repeated queries.
	 * Note: This is kept for backward compatibility but CacheManager is preferred.
	 *
	 * @var array<string, mixed>
	 */
	private static array $query_cache = array();

	/**
	 * Query performance tracking.
	 *
	 * @var array<string, array{count: int, total_time: float, avg_time: float, slow_queries: int}>
	 */
	private static array $query_stats = array();

	/**
	 * Slow query threshold in seconds.
	 */
	private const SLOW_QUERY_THRESHOLD = 0.05;

	/**
	 * Batch size for large operations.
	 */
	private const BATCH_SIZE = 500;

	/**
	 * Maximum batch size for memory-intensive operations.
	 */
	private const MAX_BATCH_SIZE = 1000;

	/**
	 * Initialize query optimizer.
	 */
	public static function init(): void {
		// Hook into WordPress query system.
		add_filter( 'posts_pre_query', array( self::class, 'optimize_wp_query' ), 10, 2 );

		// Track slow queries if debug mode is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'shutdown', array( self::class, 'log_slow_queries' ) );
		}
	}

	/**
	 * Get combined post metadata in a single query.
	 *
	 * @param array $post_ids Post IDs to fetch metadata for.
	 * @param array $meta_keys Specific meta keys to fetch.
	 * @return array<int, array<string, string>> Post metadata by post ID.
	 */
	public static function get_posts_metadata_bulk( array $post_ids, array $meta_keys = array() ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		$cache_key = 'bulk_metadata_' . md5( maybe_serialize( $post_ids ) . maybe_serialize( $meta_keys ) );

		return CacheManager::remember(
			$cache_key,
			function () use ( $post_ids, $meta_keys ) {
				global $wpdb;

				$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
				$where_clause = "pm.post_id IN ({$placeholders})";
				$params       = $post_ids;

				if ( ! empty( $meta_keys ) ) {
					$key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
					$where_clause    .= " AND pm.meta_key IN ({$key_placeholders})";
					$params           = array_merge( $params, $meta_keys );
				}

				$sql = $wpdb->prepare(
					"
				SELECT pm.post_id, pm.meta_key, pm.meta_value
				FROM {$wpdb->postmeta} pm
				WHERE {$where_clause}
				ORDER BY pm.post_id, pm.meta_key
			",
					$params
				);

				$start_time = microtime( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$results    = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->get_results( $sql );
				$query_time = microtime( true ) - $start_time;

				self::track_query_performance( 'bulk_metadata', $query_time );

				$metadata = array();
				foreach ( $results as $row ) {
					$metadata[ $row->post_id ][ $row->meta_key ] = $row->meta_value;
				}

				return $metadata;
			},
			'metadata',
			1800
		); // 30 minutes cache.
	}

	/**
	 * Get posts with their metadata in optimized batches.
	 *
	 * @param array $args WP_Query arguments.
	 * @param array $meta_keys Meta keys to include.
	 * @return array{posts: array, metadata: array, total: int}
	 */
	public static function get_posts_with_metadata( array $args, array $meta_keys = array() ): array {
		$cache_key = 'posts_with_meta_' . md5( maybe_serialize( $args ) . maybe_serialize( $meta_keys ) );

		return CacheManager::remember(
			$cache_key,
			function () use ( $args, $meta_keys ) {
				// First get post IDs efficiently.
				$id_args = array_merge(
					$args,
					array(
						'fields'                 => 'ids',
						'no_found_rows'          => false,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);

				$start_time = microtime( true );
				$query      = new \WP_Query( $id_args );
				$post_ids   = $query->posts;
				$total      = $query->found_posts;
				$query_time = microtime( true ) - $start_time;

				self::track_query_performance( 'post_ids_query', $query_time );

				if ( empty( $post_ids ) ) {
					return array(
						'posts'    => array(),
						'metadata' => array(),
						'total'    => 0,
					);
				}

				// Get posts in batches to avoid memory issues.
				$posts        = array();
				$all_metadata = array();

				// Use larger batch size for simple operations, smaller for complex ones.
				$batch_size = empty( $meta_keys ) ? self::MAX_BATCH_SIZE : self::BATCH_SIZE;
				foreach ( array_chunk( $post_ids, $batch_size ) as $batch_ids ) {
					// Get post data.
					$batch_posts = self::get_posts_batch( $batch_ids );
					$posts       = array_merge( $posts, $batch_posts );

					// Get metadata if requested.
					if ( ! empty( $meta_keys ) ) {
						$batch_metadata = self::get_posts_metadata_bulk( $batch_ids, $meta_keys );
						$all_metadata   = array_merge( $all_metadata, $batch_metadata );
					}
				}

				return array(
					'posts'    => $posts,
					'metadata' => $all_metadata,
					'total'    => $total,
				);
			},
			'queries',
			300
		); // 5 minutes cache.
	}

	/**
	 * Optimized quiz and summary data query.
	 *
	 * @param array $post_ids Post IDs.
	 * @return array Combined quiz and summary data.
	 */
	public static function get_quiz_summary_data( array $post_ids ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		$cache_key = 'quiz_summary_' . md5( maybe_serialize( $post_ids ) );

		return CacheManager::remember(
			$cache_key,
			function () use ( $post_ids ) {
				global $wpdb;

				$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
				$params       = array_merge( $post_ids, $post_ids );

				$sql = $wpdb->prepare(
					"
				SELECT 
					p.ID,
					p.post_title,
					p.post_type,
					p.post_status,
					pm_quiz.meta_value as quiz_data,
					pm_summary.meta_value as summary_data,
					pm_quiz.meta_value IS NOT NULL as has_quiz,
					pm_summary.meta_value IS NOT NULL as has_summary
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_quiz ON (
					pm_quiz.post_id = p.ID AND 
					pm_quiz.meta_key = 'nuclen-quiz-data'
				)
				LEFT JOIN {$wpdb->postmeta} pm_summary ON (
					pm_summary.post_id = p.ID AND 
					pm_summary.meta_key = 'nuclen-summary-data'
				)
				WHERE p.ID IN ({$placeholders})
				AND p.ID IN ({$placeholders})
				ORDER BY p.post_title
			",
					$params
				);

				$start_time = microtime( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$results    = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->get_results( $sql );
				$query_time = microtime( true ) - $start_time;

				self::track_query_performance( 'quiz_summary_combined', $query_time );

				$data = array();
				foreach ( $results as $row ) {
					$data[ $row->ID ] = array(
						'id'           => $row->ID,
						'title'        => $row->post_title,
						'type'         => $row->post_type,
						'status'       => $row->post_status,
						'quiz_data'    => $row->quiz_data ? wp_json_decode( $row->quiz_data, true ) : null,
						'summary_data' => $row->summary_data ? wp_json_decode( $row->summary_data, true ) : null,
						'has_quiz'     => (bool) $row->has_quiz,
						'has_summary'  => (bool) $row->has_summary,
					);
				}

				return $data;
			},
			'queries',
			600
		); // 10 minutes cache.
	}

	/**
	 * Get dashboard statistics with optimized queries.
	 *
	 * @return array Dashboard statistics.
	 */
	public static function get_dashboard_stats(): array {
		return CacheManager::remember(
			'dashboard_stats',
			function () {
				global $wpdb;

				$start_time = microtime( true );

				// Single query for all post counts.
				$sql = "
				SELECT 
					p.post_type,
					p.post_status,
					COUNT(*) as count,
					SUM(CASE WHEN pm_quiz.meta_value IS NOT NULL THEN 1 ELSE 0 END) as quiz_count,
					SUM(CASE WHEN pm_summary.meta_value IS NOT NULL THEN 1 ELSE 0 END) as summary_count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_quiz ON (
					pm_quiz.post_id = p.ID AND 
					pm_quiz.meta_key = 'nuclen-quiz-data'
				)
				LEFT JOIN {$wpdb->postmeta} pm_summary ON (
					pm_summary.post_id = p.ID AND 
					pm_summary.meta_key = 'nuclen-summary-data'
				)
				WHERE p.post_type IN ('post', 'page')
				GROUP BY p.post_type, p.post_status
			";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$results    = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->get_results( $sql );
				$query_time = microtime( true ) - $start_time;

				self::track_query_performance( 'dashboard_stats', $query_time );

				$stats = array(
					'posts' => array(
						'total'        => 0,
						'published'    => 0,
						'draft'        => 0,
						'with_quiz'    => 0,
						'with_summary' => 0,
					),
					'pages' => array(
						'total'        => 0,
						'published'    => 0,
						'draft'        => 0,
						'with_quiz'    => 0,
						'with_summary' => 0,
					),
				);

				foreach ( $results as $row ) {
					$type = $row->post_type === 'post' ? 'posts' : 'pages';

					$stats[ $type ]['total']        += $row->count;
					$stats[ $type ]['with_quiz']    += $row->quiz_count;
					$stats[ $type ]['with_summary'] += $row->summary_count;

					if ( $row->post_status === 'publish' ) {
						$stats[ $type ]['published'] += $row->count;
					} elseif ( $row->post_status === 'draft' ) {
						$stats[ $type ]['draft'] += $row->count;
					}
				}

				return $stats;
			},
			'dashboard',
			180
		); // 3 minutes cache.
	}

	/**
	 * Optimize WP_Query before execution.
	 *
	 * @param array|null $posts  Current posts (null to continue with query).
	 * @param \WP_Query  $query  WP_Query object.
	 * @return array|null Modified posts or null to continue.
	 */
	public static function optimize_wp_query( $posts, \WP_Query $query ) {
		// Only optimize our plugin's queries.
		if ( ! isset( $query->query_vars['nuclen_optimize'] ) ) {
			return $posts;
		}

		// Check if we can serve this from cache.
		$cache_key     = 'wp_query_' . hash( 'xxh3', wp_wp_json_encode( $query->query_vars ) );
		$cached_result = CacheManager::get( $cache_key, 'queries' );

		if ( $cached_result !== false ) {
			// Restore query state from cache.
			$query->posts         = $cached_result['posts'];
			$query->found_posts   = $cached_result['found_posts'];
			$query->max_num_pages = $cached_result['max_num_pages'];

			return $cached_result['posts'];
		}

		return $posts; // Continue with normal query.
	}

	/**
	 * Execute query with automatic optimization and caching.
	 *
	 * @param string $sql        SQL query.
	 * @param array  $params     Query parameters.
	 * @param string $cache_key  Cache key.
	 * @param int    $cache_ttl  Cache TTL in seconds.
	 * @param string $cache_group Cache group.
	 * @return mixed Query results.
	 */
	public static function execute_cached_query( string $sql, array $params = array(), string $cache_key = '', int $cache_ttl = 300, string $cache_group = 'queries' ) {
		if ( empty( $cache_key ) ) {
			$cache_key = 'query_' . md5( $sql . maybe_serialize( $params ) );
		}

		return CacheManager::remember(
			$cache_key,
			function () use ( $sql, $params ) {
				global $wpdb;

				$prepared_sql = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );

				$start_time = microtime( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$results    = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->get_results( $prepared_sql );
				$query_time = microtime( true ) - $start_time;

				self::track_query_performance( 'cached_query', $query_time );

				return $results;
			},
			$cache_group,
			$cache_ttl
		);
	}

	/**
	 * Get posts in optimized batch.
	 *
	 * @param array $post_ids Post IDs.
	 * @return array Post objects.
	 */
	public static function get_posts_batch( array $post_ids ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		$sql = $wpdb->prepare(
			"
			SELECT ID, post_title, post_content, post_excerpt, post_type, post_status, post_date
			FROM {$wpdb->posts}
			WHERE ID IN ({$placeholders})
			ORDER BY post_title
		",
			$post_ids
		);

		$start_time = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results    = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results( $sql );
		$query_time = microtime( true ) - $start_time;

		self::track_query_performance( 'posts_batch', $query_time );

		return $results;
	}

	/**
	 * Track query performance.
	 *
	 * @param string $query_type Query type identifier.
	 * @param float  $query_time Query execution time.
	 */
	private static function track_query_performance( string $query_type, float $query_time ): void {
		if ( ! isset( self::$query_stats[ $query_type ] ) ) {
			self::$query_stats[ $query_type ] = array(
				'count'        => 0,
				'total_time'   => 0.0,
				'avg_time'     => 0.0,
				'slow_queries' => 0,
			);
		}

		$stats = &self::$query_stats[ $query_type ];
		++$stats['count'];
		$stats['total_time'] += $query_time;
		$stats['avg_time']    = $stats['total_time'] / $stats['count'];

		if ( $query_time > self::SLOW_QUERY_THRESHOLD ) {
			++$stats['slow_queries'];
		}

		// Log slow queries immediately.
		if ( $query_time > self::SLOW_QUERY_THRESHOLD && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'Nuclear Engagement Slow Query: %s took %.4fs',
					$query_type,
					$query_time
				)
			);
		}
	}

	/**
	 * Get query performance statistics.
	 *
	 * @return array Performance statistics.
	 */
	public static function get_query_stats(): array {
		return self::$query_stats;
	}

	/**
	 * Log slow queries on shutdown.
	 */
	public static function log_slow_queries(): void {
		$slow_queries = array_filter(
			self::$query_stats,
			function ( $stats ) {
				return $stats['slow_queries'] > 0;
			}
		);

		if ( ! empty( $slow_queries ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Nuclear Engagement Query Performance Summary: ' . wp_json_encode( $slow_queries ) );
		}
	}

	/**
	 * Clear query cache.
	 */
	public static function clear_query_cache(): void {
		CacheManager::invalidate_group( 'queries', 'manual_clear' );
		self::$query_cache = array();
	}

	/**
	 * Warm up frequently used queries.
	 */
	public static function warmup_queries(): void {
		// Warm up dashboard stats.
		self::get_dashboard_stats();

		// Warm up recent posts metadata.
		$recent_posts = get_posts(
			array(
				'numberposts' => 20,
				'fields'      => 'ids',
			)
		);

		if ( ! empty( $recent_posts ) ) {
			self::get_posts_metadata_bulk(
				$recent_posts,
				array(
					'nuclen-quiz-data',
					'nuclen-summary-data',
				)
			);
		}
	}
}
