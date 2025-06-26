<?php
declare(strict_types=1);
/**
 * admin/Dashboard.php
 *
 * Dashboard data-prep: now uses direct SQL GROUP BY queries instead of
 * WP_Query/-1 + meta-cache, so it can handle very large sites without
 * running out of memory or timing out.
 *
 * @package NuclearEngagement\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use NuclearEngagement\Utils;
use NuclearEngagement\Services\DashboardDataService;
use NuclearEngagement\SettingsRepository;

global $wpdb;

/*
──────────────────────────────────────────────────────────────
 * 1. Determine which post-types we need to examine
 * ──────────────────────────────────────────────────────────── */
$settings_repo = $settings_repo ?? \NuclearEngagement\SettingsRepository::get_instance();
$data_service  = $data_service  ?? new DashboardDataService();
$allowed_post_types = $settings_repo->get( 'generation_post_types', array( 'post' ) );
$allowed_post_types = is_array( $allowed_post_types ) ? $allowed_post_types : array( 'post' );

/* Attempt to use cached inventory unless refresh requested */
$inventory_cache = \NuclearEngagement\InventoryCache::get();
if (
    isset( $_GET['nuclen_refresh_inventory'] ) &&
    isset( $_GET['nuclen_refresh_inventory_nonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nuclen_refresh_inventory_nonce'] ) ), 'nuclen_refresh_inventory' ) &&
    current_user_can( 'manage_options' )
) {
    \NuclearEngagement\InventoryCache::clear();
    $inventory_cache = null;
    wp_safe_redirect( remove_query_arg( array( 'nuclen_refresh_inventory', 'nuclen_refresh_inventory_nonce' ) ) );
    exit;
}

/*
──────────────────────────────────────────────────────────────
 * 2. Data service
 * ──────────────────────────────────────────────────────────── */

/*
──────────────────────────────────────────────────────────────
 * 3. Build every stats table we need (quiz + summary)
 * ──────────────────────────────────────────────────────────── */
$post_statuses = array( 'publish', 'pending', 'draft', 'future' );

if ( null === $inventory_cache ) {

    /* — By Post Status — */
        $status_rows    = $data_service->get_dual_counts( 'p.post_status', $allowed_post_types, $post_statuses );
    $status_objects = get_post_stati( array(), 'objects' );
    $by_status_quiz = $by_status_summary = array();

    foreach ( $status_rows as $r ) {
        $label                                  = $status_objects[ $r['g'] ]->label ?? ucfirst( $r['g'] );
        $by_status_quiz[ $label ]['with']       = (int) $r['quiz_with'];
        $by_status_quiz[ $label ]['without']    = (int) $r['quiz_without'];
        $by_status_summary[ $label ]['with']    = (int) $r['summary_with'];
        $by_status_summary[ $label ]['without'] = (int) $r['summary_without'];
    }

    /* — By Post Type — */
        $ptype_rows = $data_service->get_dual_counts( 'p.post_type', $allowed_post_types, $post_statuses );

    $by_post_type_quiz = $by_post_type_summary = array();
    foreach ( $ptype_rows as $r ) {
        $pt_obj                                    = get_post_type_object( $r['g'] );
        $label                                     = $pt_obj->labels->name ?? ucfirst( $r['g'] );
        $by_post_type_quiz[ $label ]['with']       = (int) $r['quiz_with'];
        $by_post_type_quiz[ $label ]['without']    = (int) $r['quiz_without'];
        $by_post_type_summary[ $label ]['with']    = (int) $r['summary_with'];
        $by_post_type_summary[ $label ]['without'] = (int) $r['summary_without'];
    }

        /* — By Author — */
        $author_rows = $data_service->get_dual_counts( 'p.post_author', $allowed_post_types, $post_statuses );

        $author_ids = array_map(
                static fn ( $row ) => (int) $row['g'],
                $author_rows
        );

        $author_names = array();
        if ( $author_ids ) {
                $users = get_users(
                        array(
                                'include' => $author_ids,
                                'fields'  => array( 'ID', 'display_name' ),
                        )
                );

                foreach ( $users as $user ) {
                        $author_names[ (int) $user->ID ] = $user->display_name;
                }
        }

        $by_author_quiz = $by_author_summary = array();
        foreach ( $author_rows as $r ) {
                $id                                    = (int) $r['g'];
                $name                                  = $author_names[ $id ] ?? __( 'Unknown Author', 'nuclear-engagement' );
                $by_author_quiz[ $name ]['with']       = (int) $r['quiz_with'];
                $by_author_quiz[ $name ]['without']    = (int) $r['quiz_without'];
                $by_author_summary[ $name ]['with']    = (int) $r['summary_with'];
                $by_author_summary[ $name ]['without'] = (int) $r['summary_without'];
        }

    /* — By Category — (only for post-types that use the “category” taxonomy) */
    $with_cat_pt      = array_filter(
        $allowed_post_types,
        fn( $pt ) => in_array( 'category', get_object_taxonomies( $pt ), true )
    );
    $by_category_quiz = $by_category_summary = array();

    if ( $with_cat_pt ) {

        // Sanitize inputs and build placeholders for prepare()
        $sanitized_pt = array_map( 'sanitize_key', $with_cat_pt );
        $sanitized_st = array_map( 'sanitize_key', $post_statuses );

        $placeholders_pt = implode( ',', array_fill( 0, count( $sanitized_pt ), '%s' ) );
        $placeholders_st = implode( ',', array_fill( 0, count( $sanitized_st ), '%s' ) );

        $sql_cat      = $wpdb->prepare(
            "SELECT t.term_id,
                       t.name AS cat_name,
                       SUM(CASE WHEN pm_q.meta_id IS NULL THEN 0 ELSE 1 END) AS quiz_with,
                       SUM(CASE WHEN pm_q.meta_id IS NULL THEN 1 ELSE 0 END) AS quiz_without,
                       SUM(CASE WHEN pm_s.meta_id IS NULL THEN 0 ELSE 1 END) AS summary_with,
                       SUM(CASE WHEN pm_s.meta_id IS NULL THEN 1 ELSE 0 END) AS summary_without
                FROM {$wpdb->posts} p
                JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                JOIN {$wpdb->term_taxonomy}  tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
                JOIN {$wpdb->terms}          t  ON t.term_id = tt.term_id
                LEFT JOIN {$wpdb->postmeta}  pm_q ON pm_q.post_id = p.ID AND pm_q.meta_key = 'nuclen-quiz-data'
                LEFT JOIN {$wpdb->postmeta}  pm_s ON pm_s.post_id = p.ID AND pm_s.meta_key = 'nuclen-summary-data'
                WHERE p.post_type  IN ($placeholders_pt)
                  AND p.post_status IN ($placeholders_st)
                GROUP BY t.term_id",
            array_merge( $sanitized_pt, $sanitized_st )
        );
        $cat_rows = $wpdb->get_results( $sql_cat, ARRAY_A );

        if ( ! empty( $wpdb->last_error ) ) {
            \NuclearEngagement\Services\LoggingService::log( 'Category stats query error: ' . $wpdb->last_error );
            $cat_rows = array();
        }

        foreach ( $cat_rows as $r ) {
            $by_category_quiz[ $r['cat_name'] ]['with']       = (int) $r['quiz_with'];
            $by_category_quiz[ $r['cat_name'] ]['without']    = (int) $r['quiz_without'];
            $by_category_summary[ $r['cat_name'] ]['with']    = (int) $r['summary_with'];
            $by_category_summary[ $r['cat_name'] ]['without'] = (int) $r['summary_without'];
        }
    }

    /*
    ──────────────────────────────────────────────────────────────
    * 4. Drop any rows where total = 0
    * ──────────────────────────────────────────────────────────── */
    $drop_zeros = static function ( array $arr ) {
        return array_filter(
            $arr,
            static fn ( $c ) => ( ( $c['with'] ?? 0 ) + ( $c['without'] ?? 0 ) ) > 0
        );
    };

    $by_status_quiz       = $drop_zeros( $by_status_quiz );
    $by_status_summary    = $drop_zeros( $by_status_summary );
    $by_post_type_quiz    = $drop_zeros( $by_post_type_quiz );
    $by_post_type_summary = $drop_zeros( $by_post_type_summary );
    $by_author_quiz       = $drop_zeros( $by_author_quiz );
    $by_author_summary    = $drop_zeros( $by_author_summary );
    $by_category_quiz     = $drop_zeros( $by_category_quiz );
    $by_category_summary  = $drop_zeros( $by_category_summary );

    \NuclearEngagement\InventoryCache::set(
        array(
            'by_status_quiz'       => $by_status_quiz,
            'by_status_summary'    => $by_status_summary,
            'by_post_type_quiz'    => $by_post_type_quiz,
            'by_post_type_summary' => $by_post_type_summary,
            'by_author_quiz'       => $by_author_quiz,
            'by_author_summary'    => $by_author_summary,
            'by_category_quiz'     => $by_category_quiz,
            'by_category_summary'  => $by_category_summary,
        )
    );
} else {
    $by_status_quiz       = $inventory_cache['by_status_quiz'] ?? array();
    $by_status_summary    = $inventory_cache['by_status_summary'] ?? array();
    $by_post_type_quiz    = $inventory_cache['by_post_type_quiz'] ?? array();
    $by_post_type_summary = $inventory_cache['by_post_type_summary'] ?? array();
    $by_author_quiz       = $inventory_cache['by_author_quiz'] ?? array();
    $by_author_summary    = $inventory_cache['by_author_summary'] ?? array();
    $by_category_quiz     = $inventory_cache['by_category_quiz'] ?? array();
    $by_category_summary  = $inventory_cache['by_category_summary'] ?? array();
}

/*
──────────────────────────────────────────────────────────────
 * 4b. Gather scheduled generation tasks
 * ──────────────────────────────────────────────────────────── */
$scheduled_tasks = $data_service->get_scheduled_generations();

/*
──────────────────────────────────────────────────────────────
 * 5. Render dashboard (same partial as before)
 * ──────────────────────────────────────────────────────────── */
require NUCLEN_PLUGIN_DIR . 'templates/admin/nuclen-dashboard-page.php';

