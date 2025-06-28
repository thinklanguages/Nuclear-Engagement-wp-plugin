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
		$allowed_post_types = $this->settings_repo->get( 'generation_post_types', array( 'post' ) );
		$allowed_post_types = is_array( $allowed_post_types ) ? $allowed_post_types : array( 'post' );
		
		$post_statuses  = array( 'publish', 'pending', 'draft', 'future' );
		$inventory_cache = \NuclearEngagement\Core\InventoryCache::get();
		
		if ( null === $inventory_cache ) {
		$status  = $this->get_status_stats( $allowed_post_types, $post_statuses );
		$ptype   = $this->get_post_type_stats( $allowed_post_types, $post_statuses );
		$author  = $this->get_author_stats( $allowed_post_types, $post_statuses );
		$cat     = $this->get_category_stats( $allowed_post_types, $post_statuses );
		
		$by_status_quiz       = $status['quiz'];
		$by_status_summary    = $status['summary'];
		$by_post_type_quiz    = $ptype['quiz'];
		$by_post_type_summary = $ptype['summary'];
		$by_author_quiz       = $author['quiz'];
		$by_author_summary    = $author['summary'];
		$by_category_quiz     = $cat['quiz'];
		$by_category_summary  = $cat['summary'];
		
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

	private function drop_zero_rows( array $arr ): array {
		return array_filter( $arr, static fn ( $c ) => ( ( $c['with'] ?? 0 ) + ( $c['without'] ?? 0 ) ) > 0 );
		}
		
		private function get_status_stats( array $post_types, array $statuses ): array {
		$rows = $this->data_service->get_dual_counts( 'p.post_status', $post_types, $statuses );
		$status_objects = get_post_stati( array(), 'objects' );
		$quiz = $summary = array();
		foreach ( $rows as $r ) {
		$label = $status_objects[ $r['g'] ]->label ?? ucfirst( $r['g'] );
		$quiz[ $label ]['with'] = (int) $r['quiz_with'];
		$quiz[ $label ]['without'] = (int) $r['quiz_without'];
		$summary[ $label ]['with'] = (int) $r['summary_with'];
		$summary[ $label ]['without'] = (int) $r['summary_without'];
		}
		return array(
		'quiz'    => $this->drop_zero_rows( $quiz ),
		'summary' => $this->drop_zero_rows( $summary ),
		);
		}
		
		private function get_post_type_stats( array $post_types, array $statuses ): array {
		$rows = $this->data_service->get_dual_counts( 'p.post_type', $post_types, $statuses );
		$quiz = $summary = array();
		foreach ( $rows as $r ) {
		$pt_obj = get_post_type_object( $r['g'] );
		$label  = $pt_obj->labels->name ?? ucfirst( $r['g'] );
		$quiz[ $label ]['with'] = (int) $r['quiz_with'];
		$quiz[ $label ]['without'] = (int) $r['quiz_without'];
		$summary[ $label ]['with'] = (int) $r['summary_with'];
		$summary[ $label ]['without'] = (int) $r['summary_without'];
		}
		return array(
		'quiz'    => $this->drop_zero_rows( $quiz ),
		'summary' => $this->drop_zero_rows( $summary ),
		);
		}
		
		private function get_author_stats( array $post_types, array $statuses ): array {
		$rows = $this->data_service->get_dual_counts( 'p.post_author', $post_types, $statuses );
		$author_ids = array_map( static fn ( $row ) => (int) $row['g'], $rows );
		$author_names = array();
		if ( $author_ids ) {
		$users = get_users( array( 'include' => $author_ids, 'fields' => array( 'ID', 'display_name' ) ) );
		foreach ( $users as $user ) {
		$author_names[ (int) $user->ID ] = $user->display_name;
		}
		}
		$quiz = $summary = array();
		foreach ( $rows as $r ) {
		$id = (int) $r['g'];
		$name = $author_names[ $id ] ?? __( 'Unknown Author', 'nuclear-engagement' );
		$quiz[ $name ]['with'] = (int) $r['quiz_with'];
		$quiz[ $name ]['without'] = (int) $r['quiz_without'];
		$summary[ $name ]['with'] = (int) $r['summary_with'];
		$summary[ $name ]['without'] = (int) $r['summary_without'];
		}
		return array(
		'quiz'    => $this->drop_zero_rows( $quiz ),
		'summary' => $this->drop_zero_rows( $summary ),
		);
		}
		
		private function get_category_stats( array $post_types, array $statuses ): array {
		$with_cat_pt = array_filter( $post_types, fn( $pt ) => in_array( 'category', get_object_taxonomies( $pt ), true ) );
		$quiz = $summary = array();
		if ( $with_cat_pt ) {
		$rows = $this->data_service->get_category_dual_counts( $with_cat_pt, $statuses );
		foreach ( $rows as $r ) {
		$quiz[ $r['cat_name'] ]['with'] = (int) $r['quiz_with'];
		$quiz[ $r['cat_name'] ]['without'] = (int) $r['quiz_without'];
		$summary[ $r['cat_name'] ]['with'] = (int) $r['summary_with'];
		$summary[ $r['cat_name'] ]['without'] = (int) $r['summary_without'];
		}
		}
		return array(
		'quiz'    => $this->drop_zero_rows( $quiz ),
		'summary' => $this->drop_zero_rows( $summary ),
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
