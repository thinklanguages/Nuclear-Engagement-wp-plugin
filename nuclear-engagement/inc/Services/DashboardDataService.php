<?php
declare(strict_types=1);
/**
 * File: includes/Services/DashboardDataService.php
 *
 * Provides data retrieval helpers for the admin dashboard.
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service for fetching dashboard data.
 */
class DashboardDataService {
    /**
     * Run a grouped count query (status, post type, author, etc.).
     *
     * @param string $group_by   Column to group by (prefixed, e.g. "p.post_status").
     * @param string $meta_key   Meta key to test for existence (quiz/summary).
     * @param array  $post_types Allowed post types.
     * @param array  $statuses   Allowed post statuses.
     * @return array             Rows of counts.
     */
    public function get_group_counts( string $group_by, string $meta_key, array $post_types, array $statuses ): array {
        global $wpdb;

        $post_types = array_map( 'sanitize_key', $post_types );
        $statuses   = array_map( 'sanitize_key', $statuses );

        $placeholders_pt = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $placeholders_st = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        $sql = $wpdb->prepare(
            "SELECT $group_by AS g,
                   CASE WHEN pm.meta_id IS NULL THEN 'without' ELSE 'with' END AS w,
                   COUNT(*) AS c
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
              ON pm.post_id = p.ID
             AND pm.meta_key = %s
            WHERE p.post_type IN ($placeholders_pt)
              AND p.post_status IN ($placeholders_st)
            GROUP BY $group_by, w",
            array_merge( array( $meta_key ), $post_types, $statuses )
        );

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Run a grouped count query for both quiz and summary meta in one go.
     *
     * @param string $group_by   Column to group by.
     * @param array  $post_types Allowed post types.
     * @param array  $statuses   Allowed post statuses.
     * @return array             Rows with counts for quiz and summary.
     */
    public function get_dual_counts( string $group_by, array $post_types, array $statuses ): array {
        global $wpdb;

        $post_types = array_map( 'sanitize_key', $post_types );
        $statuses   = array_map( 'sanitize_key', $statuses );

        $placeholders_pt = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $placeholders_st = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        $sql = $wpdb->prepare(
            "SELECT $group_by AS g,
                   SUM(CASE WHEN pm_q.meta_id IS NULL THEN 0 ELSE 1 END) AS quiz_with,
                   SUM(CASE WHEN pm_q.meta_id IS NULL THEN 1 ELSE 0 END) AS quiz_without,
                   SUM(CASE WHEN pm_s.meta_id IS NULL THEN 0 ELSE 1 END) AS summary_with,
                   SUM(CASE WHEN pm_s.meta_id IS NULL THEN 1 ELSE 0 END) AS summary_without
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_q ON pm_q.post_id = p.ID AND pm_q.meta_key = 'nuclen-quiz-data'
            LEFT JOIN {$wpdb->postmeta} pm_s ON pm_s.post_id = p.ID AND pm_s.meta_key = 'nuclen-summary-data'
            WHERE p.post_type IN ($placeholders_pt)
              AND p.post_status IN ($placeholders_st)
            GROUP BY $group_by",
            array_merge( $post_types, $statuses )
        );

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Retrieve any scheduled generation tasks.
     *
     * @return array List of scheduled tasks.
     */
    public function get_scheduled_generations(): array {
        $active_generations = get_option( 'nuclen_active_generations', array() );
        $scheduled_tasks    = array();

        foreach ( $active_generations as $gen_id => $info ) {
            $post_id   = (int) ( $info['post_ids'][0] ?? 0 );
            $title     = $post_id ? get_the_title( $post_id ) : $gen_id;
            $next_poll = isset( $info['next_poll'] )
                ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $info['next_poll'] )
                : '';

            $scheduled_tasks[] = array(
                'post_title'    => $title,
                'workflow_type' => $info['workflow_type'] ?? '',
                'attempt'       => (int) ( $info['attempt'] ?? 1 ),
                'next_poll'     => $next_poll,
            );
        }

        return $scheduled_tasks;
    }
}
