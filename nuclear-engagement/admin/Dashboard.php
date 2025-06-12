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
$settings_repo = \NuclearEngagement\SettingsRepository::get_instance();
$admin = new \NuclearEngagement\Admin\Admin('nuclear-engagement', NUCLEN_PLUGIN_VERSION, $settings_repo);
$allowed_post_types = $settings_repo->get('generation_post_types', array('post'));
$allowed_post_types = is_array($allowed_post_types) ? $allowed_post_types : array('post');

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

/**
 * Run one grouped count query to fetch quiz and summary counts together.
 *
 * @param string $group_by   Column to group by (e.g. "p.post_status").
 * @param array  $post_types Allowed post-types.
 * @param array  $statuses   Allowed post-statuses.
 * @return array             Rows with quiz and summary counts.
 */
function nuclen_get_group_counts_combined( string $group_by, array $post_types, array $statuses ): array {
        global $wpdb;

        $post_types = array_map( 'sanitize_key', $post_types );
        $statuses   = array_map( 'sanitize_key', $statuses );

        $placeholders_pt = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $placeholders_st = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        $sql = $wpdb->prepare(
                "SELECT $group_by AS g,
                        SUM( CASE WHEN q.meta_id IS NOT NULL THEN 1 ELSE 0 END ) AS quiz_with,
                        SUM( CASE WHEN q.meta_id IS NULL  THEN 1 ELSE 0 END ) AS quiz_without,
                        SUM( CASE WHEN s.meta_id IS NOT NULL THEN 1 ELSE 0 END ) AS summary_with,
                        SUM( CASE WHEN s.meta_id IS NULL  THEN 1 ELSE 0 END ) AS summary_without
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} q ON q.post_id = p.ID AND q.meta_key = 'nuclen-quiz-data'
                 LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = 'nuclen-summary-data'
                 WHERE p.post_type IN ($placeholders_pt)
                   AND p.post_status IN ($placeholders_st)
                 GROUP BY $group_by",
                array_merge( $post_types, $statuses )
        );

        return $wpdb->get_results( $sql, ARRAY_A );
}

/* ──────────────────────────────────────────────────────────────
 * 3. Build every stats table we need (quiz + summary)
 * ──────────────────────────────────────────────────────────── */
$post_statuses = array( 'publish', 'pending', 'draft', 'future' );

/* — By Post Status — */
$status_rows = nuclen_get_group_counts_combined( 'p.post_status', $allowed_post_types, $post_statuses );

$status_objects      = get_post_stati( array(), 'objects' );
$by_status_quiz      = $by_status_summary = array();

foreach ( $status_rows as $r ) {
        $label = $status_objects[ $r['g'] ]->label ?? ucfirst( $r['g'] );
        $by_status_quiz[ $label ]['with']    = (int) $r['quiz_with'];
        $by_status_quiz[ $label ]['without'] = (int) $r['quiz_without'];
        $by_status_summary[ $label ]['with']    = (int) $r['summary_with'];
        $by_status_summary[ $label ]['without'] = (int) $r['summary_without'];
}

/* — By Post Type — */
$ptype_rows = nuclen_get_group_counts_combined( 'p.post_type', $allowed_post_types, $post_statuses );

$by_post_type_quiz    = $by_post_type_summary = array();
foreach ( $ptype_rows as $r ) {
        $pt_obj = get_post_type_object( $r['g'] );
        $label  = $pt_obj->labels->name ?? ucfirst( $r['g'] );
        $by_post_type_quiz[ $label ]['with']    = (int) $r['quiz_with'];
        $by_post_type_quiz[ $label ]['without'] = (int) $r['quiz_without'];
        $by_post_type_summary[ $label ]['with']    = (int) $r['summary_with'];
        $by_post_type_summary[ $label ]['without'] = (int) $r['summary_without'];
}

/* — By Author — */
$author_rows = nuclen_get_group_counts_combined( 'p.post_author', $allowed_post_types, $post_statuses );

$by_author_quiz    = $by_author_summary = array();
foreach ( $author_rows as $r ) {
        $name = get_the_author_meta( 'display_name', (int) $r['g'] ) ?: __( 'Unknown Author', 'nuclear-engagement' );
        $by_author_quiz[ $name ]['with']    = (int) $r['quiz_with'];
        $by_author_quiz[ $name ]['without'] = (int) $r['quiz_without'];
        $by_author_summary[ $name ]['with']    = (int) $r['summary_with'];
        $by_author_summary[ $name ]['without'] = (int) $r['summary_without'];
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
                       t.name AS cat_name,
                       SUM( CASE WHEN q.meta_id IS NOT NULL THEN 1 ELSE 0 END ) AS quiz_with,
                       SUM( CASE WHEN q.meta_id IS NULL  THEN 1 ELSE 0 END ) AS quiz_without,
                       SUM( CASE WHEN s.meta_id IS NOT NULL THEN 1 ELSE 0 END ) AS summary_with,
                       SUM( CASE WHEN s.meta_id IS NULL  THEN 1 ELSE 0 END ) AS summary_without
                FROM {$wpdb->posts} p
                JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                JOIN {$wpdb->term_taxonomy}  tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
                JOIN {$wpdb->terms}          t  ON t.term_id = tt.term_id
                LEFT JOIN {$wpdb->postmeta}  q ON q.post_id = p.ID AND q.meta_key = 'nuclen-quiz-data'
                LEFT JOIN {$wpdb->postmeta}  s ON s.post_id = p.ID AND s.meta_key = 'nuclen-summary-data'
                WHERE p.post_type  IN ($in_cat_pt)
                  AND p.post_status IN ($in_st)
                GROUP BY t.term_id
        ";
        $cat_rows = $wpdb->get_results( $sql_cat, ARRAY_A );

        foreach ( $cat_rows as $r ) {
                $by_category_quiz[ $r['cat_name'] ]['with']    = (int) $r['quiz_with'];
                $by_category_quiz[ $r['cat_name'] ]['without'] = (int) $r['quiz_without'];
                $by_category_summary[ $r['cat_name'] ]['with']    = (int) $r['summary_with'];
                $by_category_summary[ $r['cat_name'] ]['without'] = (int) $r['summary_without'];
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
