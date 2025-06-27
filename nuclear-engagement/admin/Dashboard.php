<?php
declare(strict_types=1);
/**
 * File: admin/Dashboard.php
 *
 * Handles data preparation and rendering for the admin dashboard.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

use NuclearEngagement\Services\DashboardDataService;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Modules\Summary\Summary_Service;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Dashboard {

/** Settings repository instance. */
private $settings_repo;

/** Dashboard data service. */
private $data_service;

public function __construct( SettingsRepository $settings_repo, DashboardDataService $data_service ) {
$this->settings_repo = $settings_repo;
$this->data_service = $data_service;
}

/**
 * Render the dashboard page.
 */
public function render(): void {
       if (
               isset( $_GET['nuclen_refresh_inventory'] ) &&
               isset( $_GET['nuclen_refresh_inventory_nonce'] ) &&
               wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nuclen_refresh_inventory_nonce'] ) ), 'nuclen_refresh_inventory' ) &&
               current_user_can( 'manage_options' )
       ) {
               \NuclearEngagement\Core\InventoryCache::clear();
               wp_safe_redirect( remove_query_arg( array( 'nuclen_refresh_inventory', 'nuclen_refresh_inventory_nonce' ) ) );
               exit;
       }

       $data = $this->gather_dashboard_data();
       $this->render_dashboard_view( $data );
}

/**
 * Collect all dashboard stats.
 *
 * @return array Dashboard data arrays.
 */
private function gather_dashboard_data(): array {
       global $wpdb;

       $allowed_post_types = $this->settings_repo->get( 'generation_post_types', array( 'post' ) );
       $allowed_post_types = is_array( $allowed_post_types ) ? $allowed_post_types : array( 'post' );

       $post_statuses  = array( 'publish', 'pending', 'draft', 'future' );
       $inventory_cache = \NuclearEngagement\Core\InventoryCache::get();

       if ( null === $inventory_cache ) {
               $status_rows    = $this->data_service->get_dual_counts( 'p.post_status', $allowed_post_types, $post_statuses );
               $status_objects = get_post_stati( array(), 'objects' );
               $by_status_quiz = $by_status_summary = array();
               foreach ( $status_rows as $r ) {
                       $label                                  = $status_objects[ $r['g'] ]->label ?? ucfirst( $r['g'] );
                       $by_status_quiz[ $label ]['with']       = (int) $r['quiz_with'];
                       $by_status_quiz[ $label ]['without']    = (int) $r['quiz_without'];
                       $by_status_summary[ $label ]['with']    = (int) $r['summary_with'];
                       $by_status_summary[ $label ]['without'] = (int) $r['summary_without'];
               }

               $ptype_rows = $this->data_service->get_dual_counts( 'p.post_type', $allowed_post_types, $post_statuses );
               $by_post_type_quiz = $by_post_type_summary = array();
               foreach ( $ptype_rows as $r ) {
                       $pt_obj                                    = get_post_type_object( $r['g'] );
                       $label                                     = $pt_obj->labels->name ?? ucfirst( $r['g'] );
                       $by_post_type_quiz[ $label ]['with']       = (int) $r['quiz_with'];
                       $by_post_type_quiz[ $label ]['without']    = (int) $r['quiz_without'];
                       $by_post_type_summary[ $label ]['with']    = (int) $r['summary_with'];
                       $by_post_type_summary[ $label ]['without'] = (int) $r['summary_without'];
               }

               $author_rows = $this->data_service->get_dual_counts( 'p.post_author', $allowed_post_types, $post_statuses );
               $author_ids  = array_map( static fn ( $row ) => (int) $row['g'], $author_rows );

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

               $with_cat_pt      = array_filter( $allowed_post_types, fn( $pt ) => in_array( 'category', get_object_taxonomies( $pt ), true ) );
               $by_category_quiz = $by_category_summary = array();
               if ( $with_cat_pt ) {
                       $sanitized_pt    = array_map( 'sanitize_key', $with_cat_pt );
                       $sanitized_st    = array_map( 'sanitize_key', $post_statuses );
                       $placeholders_pt = implode( ',', array_fill( 0, count( $sanitized_pt ), '%s' ) );
                       $placeholders_st = implode( ',', array_fill( 0, count( $sanitized_st ), '%s' ) );
                       $sql_cat         = $wpdb->prepare(
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
                       LEFT JOIN {$wpdb->postmeta}  pm_s ON pm_s.post_id = p.ID AND pm_s.meta_key = '" . Summary_Service::META_KEY . "'
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

               $drop_zeros = static function ( array $arr ) {
                       return array_filter( $arr, static fn ( $c ) => ( ( $c['with'] ?? 0 ) + ( $c['without'] ?? 0 ) ) > 0 );
               };

               $by_status_quiz       = $drop_zeros( $by_status_quiz );
               $by_status_summary    = $drop_zeros( $by_status_summary );
               $by_post_type_quiz    = $drop_zeros( $by_post_type_quiz );
               $by_post_type_summary = $drop_zeros( $by_post_type_summary );
               $by_author_quiz       = $drop_zeros( $by_author_quiz );
               $by_author_summary    = $drop_zeros( $by_author_summary );
               $by_category_quiz     = $drop_zeros( $by_category_quiz );
               $by_category_summary  = $drop_zeros( $by_category_summary );

               \NuclearEngagement\Core\InventoryCache::set(
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

       $scheduled_tasks = $this->data_service->get_scheduled_generations();

       return array(
               'by_status_quiz'       => $by_status_quiz,
               'by_status_summary'    => $by_status_summary,
               'by_post_type_quiz'    => $by_post_type_quiz,
               'by_post_type_summary' => $by_post_type_summary,
               'by_author_quiz'       => $by_author_quiz,
               'by_author_summary'    => $by_author_summary,
               'by_category_quiz'     => $by_category_quiz,
               'by_category_summary'  => $by_category_summary,
               'scheduled_tasks'      => $scheduled_tasks,
       );
}

/**
 * Render the dashboard template.
 *
 * @param array $data Dashboard context.
 */
private function render_dashboard_view( array $data ): void {
       extract( $data );
       require NUCLEN_PLUGIN_DIR . 'templates/admin/nuclen-dashboard-page.php';
}

/**
 * Helper: build a small "with / without" stats table.
 *
 * @param array $data Stats array.
 * @return string HTML table.
 */
private function nuclen_render_dashboard_stats_table( $data ) {
if ( empty( $data ) ) {
return '<p>' . esc_html__( 'No items found.', 'nuclear-engagement' ) . '</p>';
}

$html  = '<table class="nuclen-stats-table">';
$html .= '<tr><th></th><th>' . esc_html__( 'With', 'nuclear-engagement' ) . '</th><th>' . esc_html__( 'Without', 'nuclear-engagement' ) . '</th></tr>';
foreach ( $data as $name => $counts ) {
$with    = isset( $counts['with'] ) ? (int) $counts['with'] : 0;
$without = isset( $counts['without'] ) ? (int) $counts['without'] : 0;
$total   = $with + $without;
if ( $total > 0 ) {
$html .= '<tr>';
$html .= '<td>' . esc_html( $name ) . '</td>';
$html .= '<td>' . esc_html( $with ) . '</td>';
$html .= '<td>' . esc_html( $without ) . '</td>';
$html .= '</tr>';
}
}

$html .= '</table>';
return $html;
}
}
