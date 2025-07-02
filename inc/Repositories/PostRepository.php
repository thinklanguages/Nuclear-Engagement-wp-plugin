<?php
declare(strict_types=1);
/**
 * File: inc/Repositories/PostRepository.php
 *
 * Post repository implementation.
 *
 * @package NuclearEngagement\Repositories
 */

namespace NuclearEngagement\Repositories;

use NuclearEngagement\Entities\Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for WordPress posts.
 */
class PostRepository extends AbstractRepository {
	
	/**
	 * Get cache group for this repository.
	 */
	protected function get_cache_group(): string {
		return 'nuclen_posts';
	}
	
	/**
	 * Get table name for this repository.
	 */
	protected function get_table_name(): string {
		return $this->get_wpdb()->posts;
	}
	
	/**
	 * Get primary key column name.
	 */
	protected function get_primary_key(): string {
		return 'ID';
	}
	
	/**
	 * Find posts by criteria with meta query support.
	 *
	 * @param array $criteria      Search criteria.
	 * @param array $meta_criteria Meta query criteria.
	 * @param array $order_by      Order by criteria.
	 * @param int   $limit         Limit results.
	 * @param int   $offset        Offset results.
	 * @return array Found posts.
	 */
	public function find_with_meta( array $criteria = array(), array $meta_criteria = array(), array $order_by = array(), int $limit = 0, int $offset = 0 ): array {
		$cache_key = $this->cache_key( 'find_with_meta_' . md5( serialize( func_get_args() ) ) );
		$cached = $this->cache->get( $cache_key );
		
		if ( $cached !== null ) {
			return $cached;
		}
		
		$args = array(
			'post_type' => $criteria['post_type'] ?? 'post',
			'post_status' => $criteria['post_status'] ?? 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'offset' => $offset,
			'fields' => 'ids',
			'meta_query' => $this->build_meta_query( $meta_criteria ),
			'orderby' => $order_by,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		
		if ( isset( $criteria['author'] ) ) {
			$args['author'] = $criteria['author'];
		}
		
		if ( isset( $criteria['category'] ) ) {
			$args['cat'] = $criteria['category'];
		}
		
		$query = new \WP_Query( $args );
		$post_ids = $query->posts;
		
		if ( empty( $post_ids ) ) {
			return array();
		}
		
		// Load full post objects
		$posts = array();
		foreach ( $post_ids as $post_id ) {
			$post = $this->find( $post_id );
			if ( $post ) {
				$posts[] = $post;
			}
		}
		
		$this->cache->set( $cache_key, $posts, $this->cache_ttl );
		return $posts;
	}
	
	/**
	 * Count posts with meta criteria.
	 *
	 * @param array $criteria      Search criteria.
	 * @param array $meta_criteria Meta query criteria.
	 * @return int Count of posts.
	 */
	public function count_with_meta( array $criteria = array(), array $meta_criteria = array() ): int {
		$cache_key = $this->cache_key( 'count_with_meta_' . md5( serialize( func_get_args() ) ) );
		$cached = $this->cache->get( $cache_key );
		
		if ( $cached !== null ) {
			return (int) $cached;
		}
		
		$args = array(
			'post_type' => $criteria['post_type'] ?? 'post',
			'post_status' => $criteria['post_status'] ?? 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => $this->build_meta_query( $meta_criteria ),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		
		if ( isset( $criteria['author'] ) ) {
			$args['author'] = $criteria['author'];
		}
		
		if ( isset( $criteria['category'] ) ) {
			$args['cat'] = $criteria['category'];
		}
		
		$query = new \WP_Query( $args );
		$count = $query->found_posts;
		
		$this->cache->set( $cache_key, $count, $this->cache_ttl );
		return $count;
	}
	
	/**
	 * Find posts without specific meta key.
	 *
	 * @param string $meta_key   Meta key to check.
	 * @param array  $criteria   Additional criteria.
	 * @param int    $limit      Limit results.
	 * @return array Posts without meta key.
	 */
	public function find_without_meta( string $meta_key, array $criteria = array(), int $limit = 100 ): array {
		$meta_criteria = array(
			array(
				'key' => $meta_key,
				'compare' => 'NOT EXISTS',
			),
		);
		
		return $this->find_with_meta( $criteria, $meta_criteria, array(), $limit );
	}
	
	/**
	 * Find posts by workflow type.
	 *
	 * @param string $workflow_type Workflow type (quiz, summary).
	 * @param bool   $protected_only Only protected posts.
	 * @param int    $limit         Limit results.
	 * @return array Found posts.
	 */
	public function find_by_workflow( string $workflow_type, bool $protected_only = false, int $limit = 100 ): array {
		$meta_criteria = array();
		
		if ( $workflow_type === 'quiz' ) {
			if ( $protected_only ) {
				$meta_criteria[] = array(
					'key' => 'nuclen_quiz_protected',
					'value' => '1',
					'compare' => '=',
				);
			} else {
				$meta_criteria[] = array(
					'key' => 'nuclen-quiz-data',
					'compare' => 'EXISTS',
				);
			}
		} elseif ( $workflow_type === 'summary' ) {
			if ( $protected_only ) {
				$meta_criteria[] = array(
					'key' => 'nuclen_summary_protected',
					'value' => '1',
					'compare' => '=',
				);
			} else {
				$meta_criteria[] = array(
					'key' => 'nuclen-summary-data',
					'compare' => 'EXISTS',
				);
			}
		}
		
		return $this->find_with_meta( array(), $meta_criteria, array(), $limit );
	}
	
	/**
	 * Build meta query from criteria.
	 *
	 * @param array $meta_criteria Meta criteria.
	 * @return array WordPress meta query.
	 */
	private function build_meta_query( array $meta_criteria ): array {
		if ( empty( $meta_criteria ) ) {
			return array();
		}
		
		$meta_query = array(
			'relation' => 'AND',
		);
		
		foreach ( $meta_criteria as $criteria ) {
			$meta_query[] = array(
				'key' => $criteria['key'],
				'value' => $criteria['value'] ?? '',
				'compare' => $criteria['compare'] ?? '=',
			);
		}
		
		return $meta_query;
	}
	
	/**
	 * Hydrate database row into Post entity.
	 *
	 * @param object $row Database row.
	 * @return Post Post entity.
	 */
	protected function hydrate( object $row ): Post {
		return new Post(
			(int) $row->ID,
			$row->post_title,
			$row->post_content,
			$row->post_excerpt,
			$row->post_status,
			$row->post_type,
			(int) $row->post_author,
			$row->post_date,
			$row->post_modified
		);
	}
	
	/**
	 * Extract Post entity data for database storage.
	 *
	 * @param Post $entity Post entity.
	 * @return array Post data.
	 */
	protected function extract( $entity ): array {
		if ( ! $entity instanceof Post ) {
			throw new \InvalidArgumentException( 'Entity must be instance of Post' );
		}
		
		return array(
			'ID' => $entity->get_id(),
			'post_title' => $entity->get_title(),
			'post_content' => $entity->get_content(),
			'post_excerpt' => $entity->get_excerpt(),
			'post_status' => $entity->get_status(),
			'post_type' => $entity->get_type(),
			'post_author' => $entity->get_author_id(),
			'post_date' => $entity->get_date(),
			'post_modified' => $entity->get_modified_date(),
		);
	}
}