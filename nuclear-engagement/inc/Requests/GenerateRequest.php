<?php
/**
 * GenerateRequest.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Requests
 */

declare(strict_types=1);
/**
 * File: includes/Requests/GenerateRequest.php

 * Generate Request DTO
 *
 * @package NuclearEngagement\Requests
 */

namespace NuclearEngagement\Requests;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data transfer object for generation requests
 */
class GenerateRequest {
	/**
	 * Post IDs to generate content for.
	 *
	 * @var array
	 */
	public array $postIds = array();

	/**
	 * Workflow type (quiz|summary).
	 *
	 * @var string
	 */
	public string $workflowType = '';

	/**
	 * Summary format (paragraph|bullet_list).
	 *
	 * @var string
	 */
	public string $summaryFormat = 'paragraph';

	/**
	 * Summary length in words.
	 *
	 * @var int
	 */
	public int $summaryLength = 30; // Default value.

	/**
	 * Number of summary items.
	 *
	 * @var int
	 */
	public int $summaryItems = 3; // Default value.

	/**
	 * Generation ID for tracking.
	 *
	 * @var string
	 */
	public string $generationId = '';

	/**
	 * Post status filter.
	 *
	 * @var string
	 */
	public string $postStatus = 'any';

	/**
	 * Post type filter.
	 *
	 * @var string
	 */
	public string $postType = 'post';

	/**
	 * Create from POST data.
	 *
	 * @param array $post POST data.
	 * @return self
	 * @throws \InvalidArgumentException On validation errors.
	 */
	public static function from_post( array $post ): self {
		$request = new self();

		// Parse the payload.
		$payload = array();
		if ( ! empty( $post['payload'] ) ) {
			$payload = json_decode( wp_unslash( $post['payload'] ), true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new \InvalidArgumentException( 'Invalid JSON payload: ' . json_last_error_msg() );
			}
		}

		// Debug log the payload.
		\NuclearEngagement\Services\LoggingService::log( 'GenerateRequest payload: ' . print_r( $payload, true ) );

		// Extract and validate post IDs.
		$post_ids_json     = $payload['nuclen_selected_post_ids'] ?? '';
		$request->postIds = json_decode( $post_ids_json, true ) ?: array();

		if ( empty( $request->postIds ) || ! is_array( $request->postIds ) ) {
			throw new \InvalidArgumentException( 'No valid posts selected' );
		}

		// Sanitize post IDs.
		$request->postIds = array_map( 'intval', $request->postIds );
		$request->postIds = array_filter(
			$request->postIds,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $request->postIds ) ) {
			throw new \InvalidArgumentException( 'No valid post IDs after sanitization' );
		}

		// Validate post ownership/permissions.
		$request->postIds = array_filter(
			$request->postIds,
			function ( $id ) {
				// Check if user can edit this post.
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return false;
				}

				// Verify post exists and is published.
				$post = get_post( $id );
				if ( ! $post || 'publish' !== $post->post_status ) {
					return false;
				}

				return true;
			}
		);

		if ( empty( $request->postIds ) ) {
			throw new \InvalidArgumentException( 'No posts available for generation - insufficient permissions or invalid post IDs' );
		}

		// Map other fields.
		$request->postStatus   = sanitize_text_field( $payload['nuclen_selected_post_status'] ?? 'any' );
		$request->postType     = sanitize_text_field( $payload['nuclen_selected_post_type'] ?? 'post' );
		$request->workflowType = sanitize_text_field( $payload['nuclen_selected_generate_workflow'] ?? '' );

		// Validate workflow type.
		if ( ! in_array( $request->workflowType, array( 'quiz', 'summary' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid workflow type: ' . $request->workflowType );
		}

		// Summary specific fields.
		$request->summaryFormat = sanitize_text_field( $payload['nuclen_selected_summary_format'] ?? 'paragraph' );
		if ( ! in_array( $request->summaryFormat, array( 'paragraph', 'bullet_list' ), true ) ) {
			$request->summaryFormat = 'paragraph';
		}

		// Use constants if defined, otherwise use defaults.
		$summary_length_min     = defined( 'NUCLEN_SUMMARY_LENGTH_MIN' ) ? NUCLEN_SUMMARY_LENGTH_MIN : 20;
		$summary_length_max     = defined( 'NUCLEN_SUMMARY_LENGTH_MAX' ) ? NUCLEN_SUMMARY_LENGTH_MAX : 50;
		$summary_length_default = defined( 'NUCLEN_SUMMARY_LENGTH_DEFAULT' ) ? NUCLEN_SUMMARY_LENGTH_DEFAULT : 30;

		$summary_items_min     = defined( 'NUCLEN_SUMMARY_ITEMS_MIN' ) ? NUCLEN_SUMMARY_ITEMS_MIN : 3;
		$summary_items_max     = defined( 'NUCLEN_SUMMARY_ITEMS_MAX' ) ? NUCLEN_SUMMARY_ITEMS_MAX : 7;
		$summary_items_default = defined( 'NUCLEN_SUMMARY_ITEMS_DEFAULT' ) ? NUCLEN_SUMMARY_ITEMS_DEFAULT : 3;

		$request->summaryLength = max(
			$summary_length_min,
			min(
				$summary_length_max,
				(int) ( $payload['nuclen_selected_summary_length'] ?? $summary_length_default )
			)
		);
		$request->summaryItems  = max(
			$summary_items_min,
			min(
				$summary_items_max,
				(int) ( $payload['nuclen_selected_summary_number_of_items'] ?? $summary_items_default )
			)
		);

		// Generation ID.
		$request->generationId = ! empty( $payload['generation_id'] )
			? sanitize_text_field( $payload['generation_id'] )
			: 'gen_' . uniqid( 'manual_', true );

		return $request;
	}
}
