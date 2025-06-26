<?php
declare(strict_types=1);
/**
 * File: includes/Services/PostDataFetcher.php
 *
 * Helper for retrieving post data via direct SQL.
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fetches post data for batches using $wpdb.
 */
class PostDataFetcher {
    /**
     * Retrieve post rows for the given IDs.
     *
     * Posts are filtered to published status and exclude those
     * with quiz or summary protection meta set.
     *
     * @param array $ids Post IDs.
     * @return array Rows from the posts table.
     */
    public function fetch( array $ids ): array {
        global $wpdb;

        if ( empty( $ids ) ) {
            return array();
        }

        $ids         = array_map( 'absint', $ids );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $order_ids    = implode( ',', $ids );

        $sql = $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_content
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
             ORDER BY FIELD(p.ID, $order_ids)",
            array_merge( array( 'nuclen_quiz_protected', 'nuclen_summary_protected' ), $ids )
        );

        $rows = $wpdb->get_results( $sql );

        if ( ! empty( $wpdb->last_error ) ) {
            LoggingService::log( 'Post fetch error: ' . $wpdb->last_error );
            return array();
        }

        return $rows;
    }
}
