<?php
/**
 * QueryBuilder.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_Query
 */

declare(strict_types=1);

namespace NuclearEngagement\Services\Query;

use NuclearEngagement\Requests\PostsCountRequest;
use NuclearEngagement\Modules\Summary\Summary_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds query arguments and SQL clauses for posts queries
 */
class QueryBuilder {

	/**
	 * Build query args from request.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return array The query arguments.
	 */
	public function build_query_args( PostsCountRequest $request ): array {
		$meta_query  = array( 'relation' => 'AND' );
		$post_type   = ! empty( $request->postType ) ? $request->postType : 'post';
		$post_status = $this->get_post_status( $request->postStatus );

		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 500,
			'post_status'    => $post_status,
			'fields'         => 'ids',
		);

		$this->add_taxonomy_filters( $query_args, $request );
		$this->add_author_filter( $query_args, $request );
		$this->add_meta_query_filters( $meta_query, $request );
		$this->add_cache_optimization( $query_args );

		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query;
		}

		return $query_args;
	}

	/**
	 * Build SQL clauses for posts count query.
	 *
	 * @param PostsCountRequest $request The posts count request.
	 * @return string The SQL clauses.
	 */
	public function build_sql_clauses( PostsCountRequest $request ): string {
		global $wpdb;

		$joins     = array();
		$wheres    = array();
		$post_type = ! empty( $request->postType ) ? $request->postType : 'post';

		$this->add_basic_filters( $wheres, $request, $post_type );
		$this->add_taxonomy_joins( $joins, $wheres, $request );
		$this->add_meta_joins( $joins, $wheres, $request );

		$sql = "FROM {$wpdb->posts} p " . implode( ' ', $joins );
		if ( $wheres ) {
			$sql .= ' WHERE ' . implode( ' AND ', $wheres );
		}

		return $sql;
	}

	/**
	 * Get post status for query.
	 *
	 * @param string $status Requested status.
	 * @return array|string Post status.
	 */
	private function get_post_status( string $status ) {
		if ( 'any' === $status ) {
			return array( 'publish', 'private', 'draft', 'pending', 'future' );
		}
		return $status;
	}

	/**
	 * Add taxonomy filters to query args.
	 *
	 * @param array             $query_args Query arguments.
	 * @param PostsCountRequest $request The request.
	 */
	private function add_taxonomy_filters( array &$query_args, PostsCountRequest $request ): void {
		if ( $request->categoryId ) {
			$query_args['cat'] = $request->categoryId;
		}
	}

	/**
	 * Add author filter to query args.
	 *
	 * @param array             $query_args Query arguments.
	 * @param PostsCountRequest $request The request.
	 */
	private function add_author_filter( array &$query_args, PostsCountRequest $request ): void {
		if ( $request->authorId ) {
			$query_args['author'] = $request->authorId;
		}
	}

	/**
	 * Add meta query filters.
	 *
	 * @param array             $meta_query Meta query array.
	 * @param PostsCountRequest $request The request.
	 */
	private function add_meta_query_filters( array &$meta_query, PostsCountRequest $request ): void {
		if ( ! $request->allowRegenerate ) {
			$meta_key     = 'quiz' === $request->workflow ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			$meta_query[] = array(
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			);
		}

		if ( ! $request->regenerateProtected ) {
			$protected_key = 'quiz' === $request->workflow ? 'nuclen_quiz_protected' : Summary_Service::PROTECTED_KEY;
			$meta_query[]  = array(
				'relation' => 'OR',
				array(
					'key'     => $protected_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $protected_key,
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}
	}

	/**
	 * Add cache optimization settings.
	 *
	 * @param array $query_args Query arguments.
	 */
	private function add_cache_optimization( array &$query_args ): void {
		$query_args['update_post_meta_cache'] = false;
		$query_args['update_post_term_cache'] = false;
		$query_args['cache_results']          = false;
	}

	/**
	 * Add basic filters to WHERE clause.
	 *
	 * @param array             $wheres WHERE conditions.
	 * @param PostsCountRequest $request The request.
	 * @param string            $post_type Post type.
	 */
	private function add_basic_filters( array &$wheres, PostsCountRequest $request, string $post_type ): void {
		global $wpdb;

		$wheres[] = $wpdb->prepare( 'p.post_type = %s', $post_type );

		if ( 'any' !== $request->postStatus ) {
			$wheres[] = $wpdb->prepare( 'p.post_status = %s', $request->postStatus );
		} else {
			$viewable_statuses = array( 'publish', 'private', 'draft', 'pending', 'future' );
			$placeholders      = implode( ', ', array_fill( 0, count( $viewable_statuses ), '%s' ) );
			$prepared_args     = array( "p.post_status IN ($placeholders)" );
			$prepared_args     = array_merge( $prepared_args, $viewable_statuses );
			$wheres[]          = call_user_func_array( array( $wpdb, 'prepare' ), $prepared_args );
		}

		if ( $request->authorId ) {
			$wheres[] = $wpdb->prepare( 'p.post_author = %d', $request->authorId );
		}
	}

	/**
	 * Add taxonomy joins to query.
	 *
	 * @param array             $joins JOIN clauses.
	 * @param array             $wheres WHERE conditions.
	 * @param PostsCountRequest $request The request.
	 */
	private function add_taxonomy_joins( array &$joins, array &$wheres, PostsCountRequest $request ): void {
		global $wpdb;

		if ( $request->categoryId ) {
			$joins[]  = "JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
			$joins[]  = "JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'";
			$wheres[] = $wpdb->prepare( 'tt.term_id = %d', $request->categoryId );
		}
	}

	/**
	 * Add meta joins to query.
	 *
	 * @param array             $joins JOIN clauses.
	 * @param array             $wheres WHERE conditions.
	 * @param PostsCountRequest $request The request.
	 */
	private function add_meta_joins( array &$joins, array &$wheres, PostsCountRequest $request ): void {
		global $wpdb;

		if ( ! $request->allowRegenerate ) {
			$meta_key = 'quiz' === $request->workflow ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			$joins[]  = $wpdb->prepare(
				"LEFT JOIN {$wpdb->postmeta} pm_exist ON pm_exist.post_id = p.ID AND pm_exist.meta_key = %s",
				$meta_key
			);
			$wheres[] = 'pm_exist.meta_id IS NULL';
		}

		if ( ! $request->regenerateProtected ) {
			$prot_key = 'quiz' === $request->workflow ? 'nuclen_quiz_protected' : Summary_Service::PROTECTED_KEY;
			$joins[]  = $wpdb->prepare(
				"LEFT JOIN {$wpdb->postmeta} pm_prot ON pm_prot.post_id = p.ID AND pm_prot.meta_key = %s",
				$prot_key
			);
			$wheres[] = "(pm_prot.meta_id IS NULL OR pm_prot.meta_value != '1')";
		}
	}
}
