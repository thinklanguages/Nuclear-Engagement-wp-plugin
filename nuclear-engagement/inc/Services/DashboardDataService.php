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
    /** Cache group for dashboard queries. */
    private const CACHE_GROUP = 'nuclen_dashboard';

    /** Cache lifetime in seconds. */
    private const CACHE_TTL = 10 * MINUTE_IN_SECONDS; // 10 minutes.

    /** Option name storing cache version. */
    private const VERSION_OPTION = 'nuclen_dashboard_version';

    /**
     * Get current cache version.
     */
    private function get_cache_version(): int {
        return (int) get_option( self::VERSION_OPTION, 1 );
    }

    /**
     * Clear cached dashboard query results.
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

        $cache_key = md5( wp_json_encode( array( 'grp', $group_by, $meta_key, $post_types, $statuses, $this->get_cache_version(), get_current_blog_id() ) ) );
        $transient = 'nuclen_dash_' . $cache_key;
        $found     = false;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
        if ( ! $found ) {
            $cached = get_transient( $transient );
        }

        if ( is_array( $cached ) ) {
            return $cached;
        }

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

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        if ( ! empty( $wpdb->last_error ) ) {
            LoggingService::log( 'Dashboard query error: ' . $wpdb->last_error );
            return array();
        }

        wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
        set_transient( $transient, $rows, self::CACHE_TTL );

        return $rows;
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

        $cache_key = md5( wp_json_encode( array( 'dual', $group_by, $post_types, $statuses, $this->get_cache_version(), get_current_blog_id() ) ) );
        $transient = 'nuclen_dash_' . $cache_key;
        $found     = false;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
        if ( ! $found ) {
            $cached = get_transient( $transient );
        }

        if ( is_array( $cached ) ) {
            return $cached;
        }

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

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        if ( ! empty( $wpdb->last_error ) ) {
            LoggingService::log( 'Dashboard query error: ' . $wpdb->last_error );
            return array();
        }

        wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
        set_transient( $transient, $rows, self::CACHE_TTL );

        return $rows;
    }

    /**
     * Retrieve any scheduled generation tasks.
     *
     * @return array List of scheduled tasks.
     */
    public function get_scheduled_generations(): array {
        $active_generations = get_option( 'nuclen_active_generations', array() );
        $scheduled_tasks    = array();
        $date_format        = get_option( 'date_format' );
        $time_format        = get_option( 'time_format' );

        foreach ( $active_generations as $gen_id => $info ) {
            $post_id   = (int) ( $info['post_ids'][0] ?? 0 );
            $title     = $post_id ? get_the_title( $post_id ) : $gen_id;
            $next_poll = isset( $info['next_poll'] )
                ? date_i18n( $date_format . ' ' . $time_format, (int) $info['next_poll'] )
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
