<?php
/**
 * PostDataFetcher.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/PostDataFetcher.php
 *
 * Helper for retrieving post data via direct SQL.
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Exceptions\DatabaseException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches post data for batches using $wpdb.
 */
class PostDataFetcher {
		/** Cache group for post fetch results. */
	private const CACHE_GROUP = 'nuclen_post_fetch';

		/** Short cache lifetime in seconds. */
	private const CACHE_TTL = 30;

		/**
		 * Register hooks to clear cached entries when posts change.
		 */
	public static function register_hooks(): void {
			$cb = array( self::class, 'clear_cache' );
		foreach ( array( 'save_post', 'deleted_post', 'clean_post_cache', 'switch_blog' ) as $hook ) {
				add_action( $hook, $cb );
		}
	}

		/**
		 * Flush the cached post data.
		 */
	public static function clear_cache(): void {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( self::CACHE_GROUP );
		} else {
					wp_cache_flush();
		}
	}
	/**
	 * Retrieve post rows for the given IDs.
	 *
	 * Fetches all requested posts without filtering. The validation
	 * and filtering should be done in the request processing stage,
	 * not during data fetching.
	 *
	 * @param array  $ids Post IDs.
	 * @param string $workflowType Optional. Workflow type (for future use).
	 * @return array Rows from the posts table.
	 * @throws DatabaseException When database query fails
	 */
	public function fetch( array $ids, string $workflowType = '' ): array {
			global $wpdb;

		if ( empty( $ids ) ) {
				return array();
		}

			$ids       = array_map( 'absint', $ids );
			$cache_key = md5( implode( ',', $ids ) . '|' . $workflowType . '|' . get_current_blog_id() );
			$found     = false;
			$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
		if ( $found && is_array( $cached ) ) {
					return $cached;
		}

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$order_ids    = implode( ',', $ids );

				// Build SQL based on workflow type.
		// Since posts were already validated in Step 1, we should fetch ALL requested posts
		// The protection check should be done at the storage level, not here
		$base_query = "SELECT p.ID, p.post_title, p.post_content
			 FROM {$wpdb->posts} p
			 WHERE p.ID IN ($placeholders)
			 ORDER BY FIELD(p.ID, $placeholders)";

		// Prepare the complete query with all parameters.
		$sql = $wpdb->prepare(
			$base_query,
			array_merge( $ids, $ids )
		);

			LoggingService::log( 'PostDataFetcher SQL: ' . $sql );

			$rows = $wpdb->get_results( $sql );

		if ( ! empty( $wpdb->last_error ) ) {
					LoggingService::log( 'Post fetch error: ' . $wpdb->last_error );
					throw new DatabaseException(
						'Failed to fetch post data',
						$wpdb->last_error,
						$sql
					);
		}

				LoggingService::log( 'PostDataFetcher found ' . count( $rows ) . ' posts out of ' . count( $ids ) . ' requested' );

				// Log which posts were not found
		if ( count( $rows ) < count( $ids ) ) {
			$found_ids   = array_map(
				function ( $row ) {
					return (int) $row->ID;
				},
				$rows
			);
			$missing_ids = array_diff( $ids, $found_ids );
			LoggingService::log( 'PostDataFetcher missing post IDs: ' . implode( ', ', $missing_ids ) );
		}

				wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );

				return $rows;
	}
}
