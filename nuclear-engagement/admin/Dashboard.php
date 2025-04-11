<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * admin/Dashboard.php
 *
 * Nuclear Engagement Admin Dashboard
 */

use NuclearEngagement\Utils;

// 1) Get which post types are allowed
$settings           = get_option( 'nuclear_engagement_settings', array() );
$allowed_post_types = $settings['generation_post_types'] ?? array( 'post' );

// 2) Query all posts for these allowed types
$all_posts_query = new WP_Query(
	array(
		'post_type'      => $allowed_post_types,
		'post_status'    => array( 'publish', 'pending', 'draft', 'future' ),
		'fields'         => 'ids',
		'posts_per_page' => -1,
	)
);
$post_ids        = $all_posts_query->posts;

if ( empty( $post_ids ) ) {
	echo '<div class="wrap">';
	echo '<h1>Nuclear Engagement</h1>';
	echo '<p>' . esc_html__( 'No posts found in these post types.', 'nuclear-engagement' ) . '</p>';
	echo '</div>';
	return;
}

// 3) Cache postmeta for all
update_postmeta_cache( $post_ids );

// 4) If ANY post type has the "category" taxonomy, update object term cache
// so we can quickly fetch categories (only for those that support them).
$all_post_type_slugs  = array_unique(
	array_map(
		function ( $pid ) {
			$p = get_post( $pid );
			return $p ? $p->post_type : '';
		},
		$post_ids
	)
);
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

// ---------------------------------------------
// Data structures to count QUIZ vs. SUMMARY
// ---------------------------------------------
$by_status_quiz    = array();
$by_category_quiz  = array();
$by_author_quiz    = array();
$by_post_type_quiz = array();

$by_status_summary    = array();
$by_category_summary  = array();
$by_author_summary    = array();
$by_post_type_summary = array();

// ----------------------
// Populate those arrays
// ----------------------
foreach ( $post_ids as $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		continue;
	}

	$post_type   = $post->post_type;
	$post_status = get_post_status( $post_id );

	// Check quiz meta
	$quiz_data = get_post_meta( $post_id, 'nuclen-quiz-data', true );
	$has_quiz  = ! empty( $quiz_data );

	// Check summary meta
	$summary_data = get_post_meta( $post_id, 'nuclen-summary-data', true );
	$has_summary  = ( ! empty( $summary_data ) && ! empty( $summary_data['summary'] ) );

	// Status
	$status_obj   = get_post_status_object( $post_status );
	$status_label = ( $status_obj && isset( $status_obj->label ) ) ? $status_obj->label : ucfirst( $post_status );

	if ( ! isset( $by_status_quiz[ $status_label ] ) ) {
		$by_status_quiz[ $status_label ] = array(
			'with'    => 0,
			'without' => 0,
		);
	}
	++$by_status_quiz[ $status_label ][ $has_quiz ? 'with' : 'without' ];

	if ( ! isset( $by_status_summary[ $status_label ] ) ) {
		$by_status_summary[ $status_label ] = array(
			'with'    => 0,
			'without' => 0,
		);
	}
	++$by_status_summary[ $status_label ][ $has_summary ? 'with' : 'without' ];

	// Post type label
	$ptype_obj   = get_post_type_object( $post_type );
	$ptype_label = ( $ptype_obj && isset( $ptype_obj->labels->name ) )
		? $ptype_obj->labels->name
		: ucfirst( $post_type );

	if ( ! isset( $by_post_type_quiz[ $ptype_label ] ) ) {
		$by_post_type_quiz[ $ptype_label ] = array(
			'with'    => 0,
			'without' => 0,
		);
	}
	++$by_post_type_quiz[ $ptype_label ][ $has_quiz ? 'with' : 'without' ];

	if ( ! isset( $by_post_type_summary[ $ptype_label ] ) ) {
		$by_post_type_summary[ $ptype_label ] = array(
			'with'    => 0,
			'without' => 0,
		);
	}
	++$by_post_type_summary[ $ptype_label ][ $has_summary ? 'with' : 'without' ];

	// Author (only if post type supports it)
	if ( post_type_supports( $post_type, 'author' ) ) {
		$post_author = $post->post_author;
		$author_name = get_the_author_meta( 'display_name', $post_author );
		if ( ! $author_name ) {
			$author_name = esc_html__( 'Unknown Author', 'nuclear-engagement' );
		}

		if ( ! isset( $by_author_quiz[ $author_name ] ) ) {
			$by_author_quiz[ $author_name ] = array(
				'with'    => 0,
				'without' => 0,
			);
		}
		++$by_author_quiz[ $author_name ][ $has_quiz ? 'with' : 'without' ];

		if ( ! isset( $by_author_summary[ $author_name ] ) ) {
			$by_author_summary[ $author_name ] = array(
				'with'    => 0,
				'without' => 0,
			);
		}
		++$by_author_summary[ $author_name ][ $has_summary ? 'with' : 'without' ];
	}

	// Categories (only if this post type has "category" in its taxonomies)
	$taxonomies_for_pt = get_object_taxonomies( $post_type );
	if ( in_array( 'category', $taxonomies_for_pt, true ) ) {
		$cats = get_the_terms( $post_id, 'category' );
		if ( $cats && ! is_wp_error( $cats ) ) {
			foreach ( $cats as $cat ) {
				if ( ! isset( $by_category_quiz[ $cat->name ] ) ) {
					$by_category_quiz[ $cat->name ] = array(
						'with'    => 0,
						'without' => 0,
					);
				}
				++$by_category_quiz[ $cat->name ][ $has_quiz ? 'with' : 'without' ];

				if ( ! isset( $by_category_summary[ $cat->name ] ) ) {
					$by_category_summary[ $cat->name ] = array(
						'with'    => 0,
						'without' => 0,
					);
				}
				++$by_category_summary[ $cat->name ][ $has_summary ? 'with' : 'without' ];
			}
		}
	}
}

// -----------------------------------
// Filter out zero-usage items
// -----------------------------------
$by_status_quiz    = array_filter( $by_status_quiz, fn( $c ) => ( $c['with'] + $c['without'] ) > 0 );
$by_category_quiz  = array_filter( $by_category_quiz, fn( $c ) => ( $c['with'] + $c['without'] ) > 0 );
$by_author_quiz    = array_filter( $by_author_quiz, fn( $c ) => ( $c['with'] + $c['without'] ) > 0 );
$by_post_type_quiz = array_filter( $by_post_type_quiz, fn( $c ) => ( $c['with'] + $c['without'] ) > 0 );

$by_status_summary    = array_filter( $by_status_summary, fn( $c ) => ( $c['with'] + $c['without'] ) > 0 );
$by_category_summary  = array_filter( $by_category_summary, fn( $c ) => ( $c['with'] + $c['without'] ) > 0 );
$by_author_summary    = array_filter( $by_author_summary, fn( $c ) => ( $c['with'] + $c['without'] ) > 0 );
$by_post_type_summary = array_filter( $by_post_type_summary, fn( $c ) => ( $c['with'] + $c['without'] ) > 0 );

// --------------------------------------------
// Include the partial for rendering the tables
// --------------------------------------------
require plugin_dir_path( __FILE__ ) . 'partials/nuclen-dashboard-page.php';
