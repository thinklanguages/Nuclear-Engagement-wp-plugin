<?php
/**
 * PostService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_ServiceLayer
 */

declare(strict_types=1);
/**
 * File: inc/Services/ServiceLayer/PostService.php
 *
 * Post service for business logic.
 *
 * @package NuclearEngagement\Services\ServiceLayer
 */

namespace NuclearEngagement\Services\ServiceLayer;

use NuclearEngagement\Repositories\PostRepository;
use NuclearEngagement\Contracts\LoggerInterface;
use NuclearEngagement\Contracts\ValidatorInterface;
use NuclearEngagement\Events\EventDispatcher;
use NuclearEngagement\Events\Event;
use NuclearEngagement\Entities\Post;
use NuclearEngagement\Exceptions\ValidationException;
use NuclearEngagement\Exceptions\DatabaseException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post service handling post-related business logic.
 */
class PostService {

	/** @var PostRepository */
	private PostRepository $post_repository;

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/** @var ValidatorInterface */
	private ValidatorInterface $validator;

	/** @var EventDispatcher */
	private EventDispatcher $event_dispatcher;

	public function __construct(
		PostRepository $post_repository,
		LoggerInterface $logger,
		ValidatorInterface $validator,
		EventDispatcher $event_dispatcher
	) {
		$this->post_repository  = $post_repository;
		$this->logger           = $logger;
		$this->validator        = $validator;
		$this->event_dispatcher = $event_dispatcher;
	}

	/**
	 * Get post by ID.
	 *
	 * @param int $post_id Post ID.
	 * @return Post|null Post or null if not found.
	 */
	public function get_post( int $post_id ): ?Post {
		$this->logger->debug( 'Getting post', array( 'post_id' => $post_id ) );

		try {
			$post = $this->post_repository->find( $post_id );

			if ( $post ) {
				$event = new Event( 'nuclen.post.retrieved', array( 'post' => $post ) );
				$this->event_dispatcher->dispatch( $event );
			}

			return $post;
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Failed to get post',
				array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				)
			);
			return null;
		}
	}

	/**
	 * Find posts for generation queue.
	 *
	 * @param string $workflow_type Workflow type (quiz, summary).
	 * @param array  $criteria      Search criteria.
	 * @param int    $limit         Limit results.
	 * @return array Found posts.
	 */
	public function find_posts_for_generation( string $workflow_type, array $criteria = array(), int $limit = 100 ): array {
		$validation_result = $this->validator->validate(
			array( 'workflow_type' => $workflow_type ),
			array( 'workflow_type' => 'required|in:quiz,summary' )
		);

		if ( ! $validation_result->is_valid() ) {
			throw new ValidationException( $validation_result->get_errors() );
		}

		$this->logger->debug(
			'Finding posts for generation',
			array(
				'workflow_type' => $workflow_type,
				'criteria'      => $criteria,
				'limit'         => $limit,
			)
		);

		try {
			// Set up meta criteria based on workflow.
			$meta_criteria = array();

			if ( $workflow_type === 'quiz' ) {
				$meta_criteria[] = array(
					'key'     => 'nuclen-quiz-data',
					'compare' => 'NOT EXISTS',
				);

				// Exclude protected posts unless specifically requested.
				if ( ! ( $criteria['include_protected'] ?? false ) ) {
					$meta_criteria[] = array(
						'key'     => 'nuclen_quiz_protected',
						'value'   => '1',
						'compare' => '!=',
					);
				}
			} elseif ( $workflow_type === 'summary' ) {
				$meta_criteria[] = array(
					'key'     => 'nuclen-summary-data',
					'compare' => 'NOT EXISTS',
				);

				// Exclude protected posts unless specifically requested.
				if ( ! ( $criteria['include_protected'] ?? false ) ) {
					$meta_criteria[] = array(
						'key'     => 'nuclen_summary_protected',
						'value'   => '1',
						'compare' => '!=',
					);
				}
			}

			// Default criteria.
			$default_criteria = array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			);

			$final_criteria = array_merge( $default_criteria, $criteria );
			unset( $final_criteria['include_protected'] ); // Remove our custom flag.

			$posts = $this->post_repository->find_with_meta(
				$final_criteria,
				$meta_criteria,
				array( 'post_date' => 'DESC' ),
				$limit
			);

			$event = new Event(
				'nuclen.posts.found_for_generation',
				array(
					'workflow_type' => $workflow_type,
					'posts'         => $posts,
					'count'         => count( $posts ),
				)
			);
			$this->event_dispatcher->dispatch( $event );

			return $posts;

		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Failed to find posts for generation',
				array(
					'workflow_type' => $workflow_type,
					'criteria'      => $criteria,
					'error'         => $e->getMessage(),
				)
			);
			throw new DatabaseException( 'Failed to find posts for generation', $e->getMessage() );
		}
	}

	/**
	 * Update post meta data.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return bool Success status.
	 */
	public function update_post_meta( int $post_id, string $meta_key, $meta_value ): bool {
		$validation_result = $this->validator->validate(
			array(
				'post_id'  => $post_id,
				'meta_key' => $meta_key,
			),
			array(
				'post_id'  => 'required|integer|min:1',
				'meta_key' => 'required|string|max:255',
			)
		);

		if ( ! $validation_result->is_valid() ) {
			throw new ValidationException( $validation_result->get_errors() );
		}

		$this->logger->debug(
			'Updating post meta',
			array(
				'post_id'  => $post_id,
				'meta_key' => $meta_key,
			)
		);

		try {
			$post = $this->get_post( $post_id );
			if ( ! $post ) {
				throw new \InvalidArgumentException( "Post {$post_id} not found" );
			}

			$old_value = $post->get_meta( $meta_key );
			$post->set_meta( $meta_key, $meta_value );

			// Update in WordPress.
			$result = update_post_meta( $post_id, $meta_key, $meta_value );

			if ( $result ) {
				$event = new Event(
					'nuclen.post.meta_updated',
					array(
						'post_id'   => $post_id,
						'meta_key'  => $meta_key,
						'old_value' => $old_value,
						'new_value' => $meta_value,
					)
				);
				$this->event_dispatcher->dispatch( $event );
			}

			return (bool) $result;

		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Failed to update post meta',
				array(
					'post_id'  => $post_id,
					'meta_key' => $meta_key,
					'error'    => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Protect post from generation.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $workflow_type Workflow type.
	 * @return bool Success status.
	 */
	public function protect_post( int $post_id, string $workflow_type ): bool {
		$protection_key = $workflow_type === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';

		$result = $this->update_post_meta( $post_id, $protection_key, '1' );

		if ( $result ) {
			$this->logger->info(
				'Post protected from generation',
				array(
					'post_id'       => $post_id,
					'workflow_type' => $workflow_type,
				)
			);

			$event = new Event(
				'nuclen.post.protected',
				array(
					'post_id'       => $post_id,
					'workflow_type' => $workflow_type,
				)
			);
			$this->event_dispatcher->dispatch( $event );
		}

		return $result;
	}

	/**
	 * Unprotect post from generation.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $workflow_type Workflow type.
	 * @return bool Success status.
	 */
	public function unprotect_post( int $post_id, string $workflow_type ): bool {
		$protection_key = $workflow_type === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';

		$result = delete_post_meta( $post_id, $protection_key );

		if ( $result ) {
			$this->logger->info(
				'Post unprotected from generation',
				array(
					'post_id'       => $post_id,
					'workflow_type' => $workflow_type,
				)
			);

			$event = new Event(
				'nuclen.post.unprotected',
				array(
					'post_id'       => $post_id,
					'workflow_type' => $workflow_type,
				)
			);
			$this->event_dispatcher->dispatch( $event );
		}

		return (bool) $result;
	}

	/**
	 * Get posts statistics.
	 *
	 * @param array $criteria Search criteria.
	 * @return array Statistics.
	 */
	public function get_statistics( array $criteria = array() ): array {
		$this->logger->debug( 'Getting post statistics', array( 'criteria' => $criteria ) );

		try {
			$total_posts = $this->post_repository->count_with_meta( $criteria );

			$quiz_criteria   = array(
				array(
					'key'     => 'nuclen-quiz-data',
					'compare' => 'EXISTS',
				),
			);
			$posts_with_quiz = $this->post_repository->count_with_meta( $criteria, $quiz_criteria );

			$summary_criteria   = array(
				array(
					'key'     => 'nuclen-summary-data',
					'compare' => 'EXISTS',
				),
			);
			$posts_with_summary = $this->post_repository->count_with_meta( $criteria, $summary_criteria );

			$quiz_protected_criteria = array(
				array(
					'key'     => 'nuclen_quiz_protected',
					'value'   => '1',
					'compare' => '=',
				),
			);
			$quiz_protected          = $this->post_repository->count_with_meta( $criteria, $quiz_protected_criteria );

			$summary_protected_criteria = array(
				array(
					'key'     => 'nuclen_summary_protected',
					'value'   => '1',
					'compare' => '=',
				),
			);
			$summary_protected          = $this->post_repository->count_with_meta( $criteria, $summary_protected_criteria );

			$statistics = array(
				'total_posts'          => $total_posts,
				'posts_with_quiz'      => $posts_with_quiz,
				'posts_with_summary'   => $posts_with_summary,
				'quiz_protected'       => $quiz_protected,
				'summary_protected'    => $summary_protected,
				'eligible_for_quiz'    => $total_posts - $posts_with_quiz - $quiz_protected,
				'eligible_for_summary' => $total_posts - $posts_with_summary - $summary_protected,
			);

			$event = new Event(
				'nuclen.post.statistics_calculated',
				array(
					'statistics' => $statistics,
					'criteria'   => $criteria,
				)
			);
			$this->event_dispatcher->dispatch( $event );

			return $statistics;

		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Failed to get post statistics',
				array(
					'criteria' => $criteria,
					'error'    => $e->getMessage(),
				)
			);
			return array();
		}
	}
}
