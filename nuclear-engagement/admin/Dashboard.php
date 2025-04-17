<?php
namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This is the free plugin's Dashboard code.
 * We removed references to "credits" or "setup" pages. 
 */

use WP_Query;

$settings           = get_option( 'nuclear_engagement_settings', [] );
$allowed_post_types = $settings['generation_post_types'] ?? [ 'post' ];

$all_posts_query = new WP_Query(
	[
		'post_type'      => $allowed_post_types,
		'post_status'    => [ 'publish', 'pending', 'draft', 'future' ],
		'fields'         => 'ids',
		'posts_per_page' => -1,
	]
);
$post_ids = $all_posts_query->posts;

if ( empty( $post_ids ) ) {
	echo '<div class="wrap"><h1>Nuclear Engagement</h1><p>';
	esc_html_e( 'No posts found in the selected post types.', 'nuclear-engagement' );
	echo '</p></div>';
	return;
}

update_postmeta_cache( $post_ids );

$all_post_type_slugs = array_unique(
	array_map(
		function ( $pid ) {
			$p = get_post( $pid );
			return $p ? $p->post_type : '';
		},
		$post_ids
	)
);

// If any post type supports “category”, update object term cache
$any_has_category_tax = false;
foreach ( $all_post_type_slugs as $pt_slug ) {
	$taxes = get_object_taxonomies( $pt_slug );
	if ( in_array( 'category', $taxes, true ) ) {
		$any_has_category_tax = true;
		break;
	}
}
if ( $any_has_category_tax ) {
	update_object_term_cache( $post_ids, 'category' );
}

// Structures to count quiz vs. summary
$by_status_quiz    = [];
$by_category_quiz  = [];
$by_author_quiz    = [];
$by_post_type_quiz = [];

$by_status_summary    = [];
$by_category_summary  = [];
$by_author_summary    = [];
$by_post_type_summary = [];

foreach ( $post_ids as $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		continue;
	}

	$post_type   = $post->post_type;
	$post_status = get_post_status( $post_id );

	$quiz_data    = get_post_meta( $post_id, 'nuclen-quiz-data', true );
	$has_quiz     = ! empty( $quiz_data );
	$summary_data = get_post_meta( $post_id, 'nuclen-summary-data', true );
	$has_summary  = ! empty( $summary_data ) && ! empty( $summary_data['summary'] );

	$status_obj   = get_post_status_object( $post_status );
	$status_label = $status_obj && isset( $status_obj->label ) ? $status_obj->label : ucfirst( $post_status );

	if ( ! isset( $by_status_quiz[ $status_label ] ) ) {
		$by_status_quiz[ $status_label ] = [ 'with' => 0, 'without' => 0 ];
	}
	++$by_status_quiz[ $status_label ][ $has_quiz ? 'with' : 'without' ];

	if ( ! isset( $by_status_summary[ $status_label ] ) ) {
		$by_status_summary[ $status_label ] = [ 'with' => 0, 'without' => 0 ];
	}
	++$by_status_summary[ $status_label ][ $has_summary ? 'with' : 'without' ];

	$ptype_obj   = get_post_type_object( $post_type );
	$ptype_label = $ptype_obj && isset( $ptype_obj->labels->name ) ? $ptype_obj->labels->name : ucfirst( $post_type );

	if ( ! isset( $by_post_type_quiz[ $ptype_label ] ) ) {
		$by_post_type_quiz[ $ptype_label ] = [ 'with' => 0, 'without' => 0 ];
	}
	++$by_post_type_quiz[ $ptype_label ][ $has_quiz ? 'with' : 'without' ];

	if ( ! isset( $by_post_type_summary[ $ptype_label ] ) ) {
		$by_post_type_summary[ $ptype_label ] = [ 'with' => 0, 'without' => 0 ];
	}
	++$by_post_type_summary[ $ptype_label ][ $has_summary ? 'with' : 'without' ];

	if ( post_type_supports( $post_type, 'author' ) ) {
		$author_name = get_the_author_meta( 'display_name', $post->post_author ) ?: __( 'Unknown Author', 'nuclear-engagement' );

		if ( ! isset( $by_author_quiz[ $author_name ] ) ) {
			$by_author_quiz[ $author_name ] = [ 'with' => 0, 'without' => 0 ];
		}
		++$by_author_quiz[ $author_name ][ $has_quiz ? 'with' : 'without' ];

		if ( ! isset( $by_author_summary[ $author_name ] ) ) {
			$by_author_summary[ $author_name ] = [ 'with' => 0, 'without' => 0 ];
		}
		++$by_author_summary[ $author_name ][ $has_summary ? 'with' : 'without' ];
	}

	// Category stats
	$taxonomies_for_pt = get_object_taxonomies( $post_type );
	if ( in_array( 'category', $taxonomies_for_pt, true ) ) {
		$cats = get_the_terms( $post_id, 'category' );
		if ( $cats && ! is_wp_error( $cats ) ) {
			foreach ( $cats as $cat ) {
				if ( ! isset( $by_category_quiz[ $cat->name ] ) ) {
					$by_category_quiz[ $cat->name ] = [ 'with' => 0, 'without' => 0 ];
				}
				++$by_category_quiz[ $cat->name ][ $has_quiz ? 'with' : 'without' ];

				if ( ! isset( $by_category_summary[ $cat->name ] ) ) {
					$by_category_summary[ $cat->name ] = [ 'with' => 0, 'without' => 0 ];
				}
				++$by_category_summary[ $cat->name ][ $has_summary ? 'with' : 'without' ];
			}
		}
	}
}

// Filter out items that have zero total
$by_status_quiz    = array_filter( $by_status_quiz,    fn( $c ) => $c['with'] + $c['without'] > 0 );
$by_category_quiz  = array_filter( $by_category_quiz,  fn( $c ) => $c['with'] + $c['without'] > 0 );
$by_author_quiz    = array_filter( $by_author_quiz,    fn( $c ) => $c['with'] + $c['without'] > 0 );
$by_post_type_quiz = array_filter( $by_post_type_quiz, fn( $c ) => $c['with'] + $c['without'] > 0 );

$by_status_summary    = array_filter( $by_status_summary,    fn( $c ) => $c['with'] + $c['without'] > 0 );
$by_category_summary  = array_filter( $by_category_summary,  fn( $c ) => $c['with'] + $c['without'] > 0 );
$by_author_summary    = array_filter( $by_author_summary,    fn( $c ) => $c['with'] + $c['without'] > 0 );
$by_post_type_summary = array_filter( $by_post_type_summary, fn( $c ) => $c['with'] + $c['without'] > 0 );

require plugin_dir_path( __FILE__ ) . 'partials/nuclen-dashboard-page.php';
