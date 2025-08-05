<?php
/**
 * CacheWarmer.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Async cache warming system.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class CacheWarmer {

	/**
	 * Warming job type.
	 */
	private const JOB_TYPE = 'cache_warm';

	/**
	 * Warming strategies.
	 */
	private const STRATEGY_EAGER      = 'eager';
	private const STRATEGY_LAZY       = 'lazy';
	private const STRATEGY_PREDICTIVE = 'predictive';

	/**
	 * Initialize cache warmer.
	 */
	public static function init(): void {
		// Register job handler
		BackgroundProcessor::register_handler( self::JOB_TYPE, array( self::class, 'handle_warm_job' ) );

		// Schedule periodic warming
		if ( ! wp_next_scheduled( 'nuclen_warm_cache' ) ) {
			wp_schedule_event( time(), 'hourly', 'nuclen_warm_cache' );
		}

		add_action( 'nuclen_warm_cache', array( self::class, 'schedule_warming' ) );

		// Warm cache after content changes
		add_action( 'save_post', array( self::class, 'warm_post_cache' ), 10, 3 );
		add_action( 'deleted_post', array( self::class, 'invalidate_post_cache' ) );
		add_action( 'nuclen_settings_updated', array( self::class, 'warm_settings_cache' ) );
	}

	/**
	 * Schedule cache warming based on strategy.
	 *
	 * @param string $strategy Warming strategy.
	 */
	public static function schedule_warming( string $strategy = self::STRATEGY_PREDICTIVE ): void {
		$items_to_warm = self::get_items_to_warm( $strategy );

		if ( empty( $items_to_warm ) ) {
			return;
		}

		// Queue warming job
		BackgroundProcessor::queue_job(
			self::JOB_TYPE,
			array(
				'items'    => $items_to_warm,
				'strategy' => $strategy,
			),
			5 // Higher priority
		);
	}

	/**
	 * Handle cache warming job.
	 *
	 * @param array $data Job data.
	 * @return bool Success status.
	 */
	public static function handle_warm_job( array $data ): bool {
		$items    = $data['items'] ?? array();
		$strategy = $data['strategy'] ?? self::STRATEGY_LAZY;

		if ( empty( $items ) ) {
			return true;
		}

		$warmed = 0;
		$total  = count( $items );

		foreach ( $items as $index => $item ) {
			// Check memory before warming
			if ( PerformanceMonitor::isMemoryUsageHigh( 75.0 ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'Cache warming stopped due to high memory usage at %d/%d items',
						$warmed,
						$total
					),
					'warning'
				);
				break;
			}

			if ( self::warm_cache_item( $item ) ) {
				++$warmed;
			}

			// Update progress
			$progress = (int) ( ( $index + 1 ) / $total * 100 );
			BackgroundProcessor::update_progress(
				$data['job_id'] ?? '',
				$progress,
				sprintf( 'Warmed %d of %d cache items', $warmed, $total )
			);

			// Yield to prevent blocking
			if ( $index % 10 === 0 ) {
				usleep( 10000 ); // 10ms
			}
		}

		\NuclearEngagement\Services\LoggingService::log(
			sprintf( 'Cache warming completed: %d/%d items warmed', $warmed, $total )
		);

		return true;
	}

	/**
	 * Get items to warm based on strategy.
	 *
	 * @param string $strategy Warming strategy.
	 * @return array Items to warm.
	 */
	private static function get_items_to_warm( string $strategy ): array {
		$items = array();

		switch ( $strategy ) {
			case self::STRATEGY_EAGER:
				// Warm everything
				$items = array_merge(
					self::get_post_cache_items( 100 ),
					self::get_query_cache_items(),
					self::get_settings_cache_items()
				);
				break;

			case self::STRATEGY_LAZY:
				// Only warm critical items
				$items = array_merge(
					self::get_post_cache_items( 10 ), // Recent posts only
					self::get_settings_cache_items()
				);
				break;

			case self::STRATEGY_PREDICTIVE:
				// Warm based on usage patterns
				$items = self::get_predictive_cache_items();
				break;
		}

		return $items;
	}

	/**
	 * Get post cache items to warm.
	 *
	 * @param int $limit Maximum number of posts.
	 * @return array Cache items.
	 */
	private static function get_post_cache_items( int $limit = 50 ): array {
		$items = array();

		// Get recent posts with nuclear engagement content
		$posts = get_posts(
			array(
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'nuclen-quiz-data',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'nuclen-summary-data',
						'compare' => 'EXISTS',
					),
				),
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		foreach ( $posts as $post_id ) {
			$items[] = array(
				'type'  => 'post',
				'id'    => $post_id,
				'group' => 'posts',
			);
		}

		return $items;
	}

	/**
	 * Get query cache items to warm.
	 *
	 * @return array Cache items.
	 */
	private static function get_query_cache_items(): array {
		$items = array();

		// Common query patterns
		$query_types = array(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			),
		);

		foreach ( $query_types as $query ) {
			$items[] = array(
				'type'  => 'query',
				'query' => $query,
				'group' => 'queries',
			);
		}

		return $items;
	}

	/**
	 * Get settings cache items to warm.
	 *
	 * @return array Cache items.
	 */
	private static function get_settings_cache_items(): array {
		return array(
			array(
				'type'  => 'settings',
				'key'   => 'all_settings',
				'group' => 'nuclen_settings',
			),
			array(
				'type'  => 'settings',
				'key'   => 'active_theme',
				'group' => 'nuclen_settings',
			),
		);
	}

	/**
	 * Get predictive cache items based on usage.
	 *
	 * @return array Cache items.
	 */
	private static function get_predictive_cache_items(): array {
		$items = array();

		// Get cache statistics
		$stats = CacheManager::get_statistics();

		// Find cache groups with high miss rates
		foreach ( $stats as $group => $group_stats ) {
			if ( isset( $group_stats['hit_rate'] ) && $group_stats['hit_rate'] < 50 ) {
				// This group has a low hit rate, warm it
				$items = array_merge( $items, self::get_group_items( $group ) );
			}
		}

		// Add frequently accessed items from access logs
		$frequent_items = self::get_frequently_accessed_items();
		$items          = array_merge( $items, $frequent_items );

		return array_slice( $items, 0, 100 ); // Limit to 100 items
	}

	/**
	 * Warm a single cache item.
	 *
	 * @param array $item Cache item to warm.
	 * @return bool Success status.
	 */
	private static function warm_cache_item( array $item ): bool {
		switch ( $item['type'] ) {
			case 'post':
				return self::warm_post_item( $item['id'] );

			case 'query':
				return self::warm_query_item( $item['query'] );

			case 'settings':
				return self::warm_settings_item( $item['key'] );

			default:
				return false;
		}
	}

	/**
	 * Warm post cache.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Success status.
	 */
	private static function warm_post_item( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		// Warm quiz data
		$quiz_data = get_post_meta( $post_id, 'nuclen-quiz-data', true );
		if ( $quiz_data ) {
			CacheManager::set( 'quiz_' . $post_id, $quiz_data, 'posts' );
		}

		// Warm summary data
		$summary_data = get_post_meta( $post_id, 'nuclen-summary-data', true );
		if ( $summary_data ) {
			CacheManager::set( 'summary_' . $post_id, $summary_data, 'posts' );
		}

		return true;
	}

	/**
	 * Warm query cache.
	 *
	 * @param array $query Query parameters.
	 * @return bool Success status.
	 */
	private static function warm_query_item( array $query ): bool {
		// Execute query to populate cache
		$posts = get_posts(
			array_merge(
				$query,
				array(
					'posts_per_page' => 20,
					'fields'         => 'ids',
				)
			)
		);

		return ! empty( $posts );
	}

	/**
	 * Warm settings cache.
	 *
	 * @param string $key Settings key.
	 * @return bool Success status.
	 */
	private static function warm_settings_item( string $key ): bool {
		$container = ServiceContainer::getInstance();

		if ( $container->has( 'settings_repository' ) ) {
			$settings = $container->get( 'settings_repository' );

			// Trigger cache population
			if ( $key === 'all_settings' ) {
				$settings->get_all();
			} else {
				$settings->get( $key );
			}

			return true;
		}

		return false;
	}

	/**
	 * Get cache items for a group.
	 *
	 * @param string $group Cache group.
	 * @return array Cache items.
	 */
	private static function get_group_items( string $group ): array {
		// Implementation would depend on group type
		switch ( $group ) {
			case 'posts':
				return self::get_post_cache_items( 20 );
			case 'queries':
				return self::get_query_cache_items();
			case 'settings':
				return self::get_settings_cache_items();
			default:
				return array();
		}
	}

	/**
	 * Get frequently accessed items.
	 *
	 * @return array Cache items.
	 */
	private static function get_frequently_accessed_items(): array {
		// This would typically read from an access log
		// For now, return common items
		return array(
			array(
				'type'  => 'settings',
				'key'   => 'theme',
				'group' => 'nuclen_settings',
			),
			array(
				'type'  => 'settings',
				'key'   => 'display_quiz',
				'group' => 'nuclen_settings',
			),
		);
	}

	/**
	 * Warm cache after post save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether this is an update.
	 */
	public static function warm_post_cache( int $post_id, $post, bool $update ): void {
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		// Queue async warming
		BackgroundProcessor::queue_job(
			self::JOB_TYPE,
			array(
				'items'    => array(
					array(
						'type'  => 'post',
						'id'    => $post_id,
						'group' => 'posts',
					),
				),
				'strategy' => self::STRATEGY_LAZY,
			),
			1 // High priority
		);
	}

	/**
	 * Invalidate post cache.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function invalidate_post_cache( int $post_id ): void {
		CacheManager::delete( 'quiz_' . $post_id, 'posts' );
		CacheManager::delete( 'summary_' . $post_id, 'posts' );
	}

	/**
	 * Warm settings cache after update.
	 */
	public static function warm_settings_cache(): void {
		// Queue async warming
		BackgroundProcessor::queue_job(
			self::JOB_TYPE,
			array(
				'items'    => self::get_settings_cache_items(),
				'strategy' => self::STRATEGY_EAGER,
			),
			1 // High priority
		);
	}
}
