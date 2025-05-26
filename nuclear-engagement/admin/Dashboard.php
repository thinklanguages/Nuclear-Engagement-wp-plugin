<?php
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

global $wpdb;

/* ──────────────────────────────────────────────────────────────
 * 1. Determine which post-types we need to examine
 * ──────────────────────────────────────────────────────────── */
$settings           = get_option( 'nuclear_engagement_settings', array() );
$allowed_post_types = $settings['generation_post_types'] ?? array( 'post' );

/* ──────────────────────────────────────────────────────────────
 * 2. Convenience helpers
 * ──────────────────────────────────────────────────────────── */

/**
 * Run a grouped count query (status, post-type, author, etc.).
 *
 * @param  string $group_by    Column to group by (already prefixed, e.g. "p.post_status").
 * @param  string $meta_key    Meta key to test for existence (quiz / summary).
 * @param  array  $post_types  Allowed post-types.
 * @param  array  $statuses    Allowed post-statuses.
 * @return array               Rows: [ [ g => value, w => 'with|without', c => count ], … ]
 */
function nuclen_get_group_counts( string $group_by, string $meta_key, array $post_types, array $statuses ): array {
	global $wpdb;

	// Sanitize post types and statuses
	$post_types = array_map( 'sanitize_key', $post_types );
	$statuses   = array_map( 'sanitize_key', $statuses );

	// Create placeholders for the IN clauses
	$placeholders_pt = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
	$placeholders_st = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	// Prepare the query with placeholders
	$sql = $wpdb->prepare(
		"SELECT $group_by               AS g,
		       CASE WHEN pm.meta_id IS NULL THEN 'without' ELSE 'with' END AS w,
		       COUNT(*)                 AS c
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm
		  ON  pm.post_id = p.ID
		  AND pm.meta_key = %s
		WHERE p.post_type  IN ($placeholders_pt)
		  AND p.post_status IN ($placeholders_st)
		GROUP BY $group_by, w",
		array_merge( [ $meta_key ], $post_types, $statuses )
	);

	return $wpdb->get_results( $sql, ARRAY_A );
}

/* ──────────────────────────────────────────────────────────────
 * 3. Build every stats table we need (quiz + summary)
 * ──────────────────────────────────────────────────────────── */
$post_statuses = array( 'publish', 'pending', 'draft', 'future' );

/* — By Post Status — */
$status_quiz_rows    = nuclen_get_group_counts( 'p.post_status', 'nuclen-quiz-data',    $allowed_post_types, $post_statuses );
$status_summary_rows = nuclen_get_group_counts( 'p.post_status', 'nuclen-summary-data', $allowed_post_types, $post_statuses );

$status_objects      = get_post_stati( array(), 'objects' );
$by_status_quiz      = $by_status_summary = array();

foreach ( $status_quiz_rows as $r ) {
	$label = $status_objects[ $r['g'] ]->label ?? ucfirst( $r['g'] );
	$by_status_quiz[ $label ][ $r['w'] ] = (int) $r['c'];
}
foreach ( $status_summary_rows as $r ) {
	$label = $status_objects[ $r['g'] ]->label ?? ucfirst( $r['g'] );
	$by_status_summary[ $label ][ $r['w'] ] = (int) $r['c'];
}

/* — By Post Type — */
$ptype_quiz_rows    = nuclen_get_group_counts( 'p.post_type', 'nuclen-quiz-data',    $allowed_post_types, $post_statuses );
$ptype_summary_rows = nuclen_get_group_counts( 'p.post_type', 'nuclen-summary-data', $allowed_post_types, $post_statuses );

$by_post_type_quiz    = $by_post_type_summary = array();
foreach ( $ptype_quiz_rows as $r ) {
	$pt_obj = get_post_type_object( $r['g'] );
	$label  = $pt_obj->labels->name ?? ucfirst( $r['g'] );
	$by_post_type_quiz[ $label ][ $r['w'] ] = (int) $r['c'];
}
foreach ( $ptype_summary_rows as $r ) {
	$pt_obj = get_post_type_object( $r['g'] );
	$label  = $pt_obj->labels->name ?? ucfirst( $r['g'] );
	$by_post_type_summary[ $label ][ $r['w'] ] = (int) $r['c'];
}

/* — By Author — */
$author_quiz_rows    = nuclen_get_group_counts( 'p.post_author', 'nuclen-quiz-data',    $allowed_post_types, $post_statuses );
$author_summary_rows = nuclen_get_group_counts( 'p.post_author', 'nuclen-summary-data', $allowed_post_types, $post_statuses );

$by_author_quiz    = $by_author_summary = array();
foreach ( $author_quiz_rows as $r ) {
	$name = get_the_author_meta( 'display_name', (int) $r['g'] ) ?: __( 'Unknown Author', 'nuclear-engagement' );
	$by_author_quiz[ $name ][ $r['w'] ] = (int) $r['c'];
}
foreach ( $author_summary_rows as $r ) {
	$name = get_the_author_meta( 'display_name', (int) $r['g'] ) ?: __( 'Unknown Author', 'nuclear-engagement' );
	$by_author_summary[ $name ][ $r['w'] ] = (int) $r['c'];
}

/* — By Category — (only for post-types that use the “category” taxonomy) */
$with_cat_pt = array_filter(
	$allowed_post_types,
	fn( $pt ) => in_array( 'category', get_object_taxonomies( $pt ), true )
);
$by_category_quiz = $by_category_summary = array();

if ( $with_cat_pt ) {
	$in_cat_pt  = "'" . implode( "','", array_map( 'esc_sql', $with_cat_pt ) ) . "'";
	$in_st      = "'" . implode( "','", $post_statuses ) . "'";
	$sql_cat = "
		SELECT t.term_id,
		       t.name               AS cat_name,
		       CASE WHEN pm.meta_id IS NULL THEN 'without' ELSE 'with' END AS w,
		       COUNT(*)             AS c
		FROM {$wpdb->posts} p
		JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		JOIN {$wpdb->term_taxonomy}  tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
		JOIN {$wpdb->terms}          t  ON t.term_id = tt.term_id
		LEFT JOIN {$wpdb->postmeta}  pm ON pm.post_id = p.ID AND pm.meta_key = %s
		WHERE p.post_type  IN ($in_cat_pt)
		  AND p.post_status IN ($in_st)
		GROUP BY t.term_id, w
	";
	$cat_quiz_rows    = $wpdb->get_results( $wpdb->prepare( $sql_cat, 'nuclen-quiz-data' ),    ARRAY_A );
	$cat_summary_rows = $wpdb->get_results( $wpdb->prepare( $sql_cat, 'nuclen-summary-data' ), ARRAY_A );

	foreach ( $cat_quiz_rows as $r ) {
		$by_category_quiz[ $r['cat_name'] ][ $r['w'] ] = (int) $r['c'];
	}
	foreach ( $cat_summary_rows as $r ) {
		$by_category_summary[ $r['cat_name'] ][ $r['w'] ] = (int) $r['c'];
	}
}

/* ──────────────────────────────────────────────────────────────
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

/* ──────────────────────────────────────────────────────────────
 * 5. Render dashboard (same partial as before)
 * ──────────────────────────────────────────────────────────── */
require plugin_dir_path( __FILE__ ) . 'partials/nuclen-dashboard-page.php';
