<?php
declare(strict_types=1);
/**
 * File: includes/Services/PostsQueryService.php
 *
 * Posts Query Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Requests\PostsCountRequest;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for querying posts
 */
class PostsQueryService {
	/**
	 * Build query args from request
	 *
	 * @param PostsCountRequest $request
	 * @return array
	 */
	public function buildQueryArgs( PostsCountRequest $request ): array {
		$metaQuery = array( 'relation' => 'AND' );

		$queryArgs = array(
			'post_type'      => $request->postType,
			'posts_per_page' => -1,
			'post_status'    => $request->postStatus,
			'fields'         => 'ids',
		);

		if ( $request->categoryId ) {
			$queryArgs['cat'] = $request->categoryId;
		}

		if ( $request->authorId ) {
			$queryArgs['author'] = $request->authorId;
		}

		// Skip existing data if not allowing regeneration
		if ( ! $request->allowRegenerate ) {
			$metaKey     = $request->workflow === 'quiz' ? 'nuclen-quiz-data' : 'nuclen-summary-data';
			$metaQuery[] = array(
				'key'     => $metaKey,
				'compare' => 'NOT EXISTS',
			);
		}

		// Skip protected data if not allowed
		if ( ! $request->regenerateProtected ) {
			$protectedKey = $request->workflow === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
			$metaQuery[]  = array(
				'relation' => 'OR',
				array(
					'key'     => $protectedKey,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $protectedKey,
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}

		// Only add meta_query if we have conditions
		if ( count( $metaQuery ) > 1 ) {
			$queryArgs['meta_query'] = $metaQuery;
		}

		// Disable caching for performance during counts
		$queryArgs['update_post_meta_cache'] = false;
		$queryArgs['update_post_term_cache'] = false;
		$queryArgs['cache_results']          = false;

		return $queryArgs;
	}

	/**
	 * Get posts count and IDs
	 *
	 * @param PostsCountRequest $request
	 * @return array
	 */
       public function getPostsCount( PostsCountRequest $request ): array {
               global $wpdb;

               $joins  = array();
               $wheres = array();

               $wheres[] = $wpdb->prepare( 'p.post_type = %s', $request->postType );

               if ( 'any' !== $request->postStatus ) {
                       $wheres[] = $wpdb->prepare( 'p.post_status = %s', $request->postStatus );
               }

               if ( $request->authorId ) {
                       $wheres[] = $wpdb->prepare( 'p.post_author = %d', $request->authorId );
               }

               if ( $request->categoryId ) {
                       $joins[]  = "JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
                       $joins[]  = "JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'";
                       $wheres[] = $wpdb->prepare( 'tt.term_id = %d', $request->categoryId );
               }

               if ( ! $request->allowRegenerate ) {
                       $meta_key = $request->workflow === 'quiz' ? 'nuclen-quiz-data' : 'nuclen-summary-data';
                       $joins[]  = $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} pm_exist ON pm_exist.post_id = p.ID AND pm_exist.meta_key = %s", $meta_key );
                       $wheres[] = 'pm_exist.meta_id IS NULL';
               }

               if ( ! $request->regenerateProtected ) {
                       $prot_key = $request->workflow === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
                       $joins[]  = $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} pm_prot ON pm_prot.post_id = p.ID AND pm_prot.meta_key = %s", $prot_key );
                       $wheres[] = "(pm_prot.meta_id IS NULL OR pm_prot.meta_value != '1')";
               }

               $sql  = "FROM {$wpdb->posts} p " . implode( ' ', $joins );
               if ( $wheres ) {
                       $sql .= ' WHERE ' . implode( ' AND ', $wheres );
               }

               $post_ids = $wpdb->get_col( "SELECT p.ID $sql" );
               $count    = (int) $wpdb->get_var( "SELECT COUNT(*) $sql" );

               if ( $wpdb->last_error ) {
                       LoggingService::log( 'Posts query error: ' . $wpdb->last_error );
               }

               return array(
                       'count'    => $count,
                       'post_ids' => $post_ids,
               );
       }
}
