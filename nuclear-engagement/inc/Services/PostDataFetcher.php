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
	 * Posts are filtered to published status and exclude those
	 * with quiz or summary protection meta set.
	 *
	 * @param array  $ids Post IDs.
	 * @param string $workflowType Optional. Workflow type to check protection for. If empty, checks both.
	 * @return array Rows from the posts table.
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
		if ( $workflowType === 'quiz' ) {
			// Only check quiz protection for quiz generation.
			$base_query = "SELECT p.ID, p.post_title, p.post_content
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pmq
				   ON pmq.post_id = p.ID
				  AND pmq.meta_key = %s
				 WHERE p.ID IN ($placeholders)
				   AND p.post_status = 'publish'
				   AND pmq.meta_id IS NULL
				 ORDER BY FIELD(p.ID, $placeholders)";

			// Prepare the complete query with all parameters.
			$sql = $wpdb->prepare(
				$base_query,
				array_merge( array( 'nuclen_quiz_protected' ), $ids, $ids )
			);

		} elseif ( $workflowType === 'summary' ) {
			// Only check summary protection for summary generation.
			$base_query = "SELECT p.ID, p.post_title, p.post_content
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pms
				   ON pms.post_id = p.ID
				  AND pms.meta_key = %s
				 WHERE p.ID IN ($placeholders)
				   AND p.post_status = 'publish'
				   AND pms.meta_id IS NULL
				 ORDER BY FIELD(p.ID, $placeholders)";

			// Prepare the complete query with all parameters.
			$sql = $wpdb->prepare(
				$base_query,
				array_merge( array( Summary_Service::PROTECTED_KEY ), $ids, $ids )
			);

		} else {
			// Default behavior: check both protections (backward compatibility).
			$base_query = "SELECT p.ID, p.post_title, p.post_content
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pmq
				   ON pmq.post_id = p.ID
				  AND pmq.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} pms
				   ON pms.post_id = p.ID
				  AND pms.meta_key = %s
				 WHERE p.ID IN ($placeholders)
				   AND p.post_status = 'publish'
				   AND pmq.meta_id IS NULL
				   AND pms.meta_id IS NULL
				 ORDER BY FIELD(p.ID, $placeholders)";

			// Prepare the complete query with all parameters.
			$sql = $wpdb->prepare(
				$base_query,
				array_merge( array( 'nuclen_quiz_protected', Summary_Service::PROTECTED_KEY ), $ids, $ids )
			);
		}

			$rows = $wpdb->get_results( $sql );

		if ( ! empty( $wpdb->last_error ) ) {
					LoggingService::log( 'Post fetch error: ' . $wpdb->last_error );
					return array();
		}

				wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );

				return $rows;
	}
}
