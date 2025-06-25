<?php
declare(strict_types=1);
/**
 * Helper for fetching post data via $wpdb
 */
namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PostDataFetcher {
    /**
     * Fetch posts excluding protected ones.
     *
     * @param array $postIds
     * @return array
     */
    public function fetch_without_protection( array $postIds ): array {
        global $wpdb;

        if ( empty( $postIds ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $postIds ), '%d' ) );

        $sql = "SELECT p.ID, p.post_title, p.post_content
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_q
                    ON pm_q.post_id = p.ID AND pm_q.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_s
                    ON pm_s.post_id = p.ID AND pm_s.meta_key = %s
                WHERE p.ID IN ($placeholders)
                  AND pm_q.meta_id IS NULL
                  AND pm_s.meta_id IS NULL";

        $query = $wpdb->prepare( $sql, array_merge( array( 'nuclen_quiz_protected', 'nuclen_summary_protected' ), $postIds ) );
        $rows  = $wpdb->get_results( $query );

        $byId = array();
        foreach ( $rows as $row ) {
            $byId[ (int) $row->ID ] = $row;
        }

        $ordered = array();
        foreach ( $postIds as $id ) {
            if ( isset( $byId[ $id ] ) ) {
                $ordered[] = $byId[ $id ];
            }
        }

        return $ordered;
    }
}
