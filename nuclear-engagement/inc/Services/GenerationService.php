<?php
declare(strict_types=1);
/**
 * File: includes/Services/GenerationService.php

 * Generation Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Requests\GenerateRequest;
use NuclearEngagement\Responses\GenerationResponse;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Utils;
use NuclearEngagement\Services\ApiException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for handling content generation
 */
class GenerationService {
	/** Seconds to wait between polling events. */
	private const DEFAULT_POLL_DELAY = 30;

	private int $pollDelay = self::DEFAULT_POLL_DELAY;
	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $api;

	/**
	 * @var ContentStorageService
	 */
	private ContentStorageService $storage;

	/**
	 * @var Utils
	 */
	private Utils $utils;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository    $settings
	 * @param RemoteApiService      $api
	 * @param ContentStorageService $storage
	 */
	public function __construct(
		SettingsRepository $settings,
		RemoteApiService $api,
		ContentStorageService $storage
	) {
			$this->settings = $settings;
			$this->api      = $api;
			$this->storage  = $storage;
			$this->utils    = new Utils();
		if ( defined( 'NUCLEN_GENERATION_POLL_DELAY' ) ) {
				$this->pollDelay = (int) constant( 'NUCLEN_GENERATION_POLL_DELAY' );
		}
	}

	/**
	 * Generate content for multiple posts
	 *
	 * @param GenerateRequest $request
	 * @return GenerationResponse
	 * @throws \RuntimeException On API errors
	 */
	public function generateContent( GenerateRequest $request ): GenerationResponse {
		// Get posts data
		$posts = $this->getPostsData( $request->postIds, $request->postType, $request->postStatus );
		if ( empty( $posts ) ) {
			throw new \RuntimeException( 'No matching posts found' );
		}

		// Build workflow data
		$workflow = array(
			'type'                    => $request->workflowType,
			'summary_format'          => $request->summaryFormat,
			'summary_length'          => $request->summaryLength,
			'summary_number_of_items' => $request->summaryItems,
		);

		// Send to API
		try {
				$result = $this->api->send_posts_to_generate(
					array(
						'posts'         => $posts,
						'workflow'      => $workflow,
						'generation_id' => $request->generationId,
					)
				);
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			$response               = new GenerationResponse();
			$response->generationId = $request->generationId;
			$response->success      = false;
			$response->error        = $e->getMessage();
			$code                   = $e->getCode();
			$response->statusCode   = is_numeric( $code ) ? (int) $code : 0;
			if ( $e instanceof ApiException ) {
				$response->errorCode = $e->getErrorCode();
			}
			return $response;
		}

		// Create response
		$response               = new GenerationResponse();
		$response->generationId = $request->generationId;

		// Process immediate results if any
                if ( ! empty( $result['results'] ) && is_array( $result['results'] ) ) {
                        $statuses = $this->storage->storeResults( $result['results'], $request->workflowType );
                        if ( array_filter( $statuses, static fn( $s ) => $s !== true ) ) {
                                $response->success    = false;
                                $response->error      = 'Failed to store generated content';
                                return $response;
                        }
                        $response->results = $result['results'];
                }

		return $response;
	}

	/**
	 * Generate content for a single post
	 *
	 * @param int    $postId
	 * @param string $workflowType
	 * @throws \InvalidArgumentException If post not found
	 */
	public function generateSingle( int $postId, string $workflowType ): void {
		$post = get_post( $postId );
		if ( ! $post ) {
			throw new \InvalidArgumentException( "Post {$postId} not found" );
		}

		// Check if protected
		if ( $this->isProtected( $postId, $workflowType ) ) {
			\NuclearEngagement\Services\LoggingService::log( "Skipping protected {$workflowType} for post {$postId}" );
			return;
		}

		$request               = new GenerateRequest();
		$request->postIds      = array( $postId );
		$request->workflowType = $workflowType;
		$request->generationId = 'auto_' . $postId . '_' . time();
		$request->postType     = $post->post_type;
		$request->postStatus   = $post->post_status;

		try {
			$response = $this->generateContent( $request );

			if ( ! $response->success ) {
				throw new ApiException(
					$response->error ?? 'Generation failed',
					$response->statusCode ?? 0,
					$response->errorCode
				);
			}

			// If no immediate results, schedule polling
			if ( empty( $response->results ) ) {
					$scheduled = wp_schedule_single_event(
						time() + $this->pollDelay,
						'nuclen_poll_generation',
						array( $response->generationId, $workflowType, $postId, 1 )
					);
				if ( false === $scheduled ) {
					\NuclearEngagement\Services\LoggingService::log(
						'Failed to schedule event nuclen_poll_generation for generation ' . $response->generationId
					);
				}
					\NuclearEngagement\Services\LoggingService::log( "Scheduled polling for post {$postId}, generation {$response->generationId}" );
			}
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			throw $e;
		}
	}

	/**
	 * Get posts data for generation
	 *
	 * @param array  $postIds
	 * @param string $postType
	 * @param string $postStatus
	 * @return array
	 */
	private function getPostsData( array $postIds, string $postType, string $postStatus ): array {
		global $wpdb;

		$data      = array();
		$postsById = array();

		$chunkSize = defined( 'NUCLEN_POST_FETCH_CHUNK' ) ? (int) constant( 'NUCLEN_POST_FETCH_CHUNK' ) : 200;
		$chunks    = count( $postIds ) <= $chunkSize ? array( $postIds ) : array_chunk( $postIds, $chunkSize );

		foreach ( $chunks as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$sql          = $wpdb->prepare(
				"SELECT ID, post_title, post_content
                 FROM {$wpdb->posts}
                 WHERE ID IN ($placeholders)
                   AND post_type = %s
                   AND post_status = %s",
				array_merge( $chunk, array( $postType, $postStatus ) )
			);

			$posts = $wpdb->get_results( $sql );

			foreach ( $posts as $post ) {
				$postsById[ (int) $post->ID ] = array(
					'id'      => (int) $post->ID,
					'title'   => $post->post_title,
					'content' => wp_strip_all_tags( $post->post_content ),
				);
			}
		}

		foreach ( $postIds as $id ) {
			if ( isset( $postsById[ $id ] ) ) {
				$data[] = $postsById[ $id ];
			}
		}

		return $data;
	}

	/**
	 * Check if content is protected from regeneration
	 *
	 * @param int    $postId
	 * @param string $workflowType
	 * @return bool
	 */
	private function isProtected( int $postId, string $workflowType ): bool {
		$metaKey = $workflowType === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
		return (bool) get_post_meta( $postId, $metaKey, true );
	}
}
