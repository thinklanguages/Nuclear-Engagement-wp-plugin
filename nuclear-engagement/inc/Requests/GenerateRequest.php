<?php
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
	 * @var array Post IDs to generate content for
	 */
	public array $postIds = array();

	/**
	 * @var string Workflow type (quiz|summary)
	 */
	public string $workflowType = '';

	/**
	 * @var string Summary format (paragraph|bullet_list)
	 */
	public string $summaryFormat = 'paragraph';

	/**
	 * @var int Summary length in words
	 */
	public int $summaryLength = 30; // Default value

	/**
	 * @var int Number of summary items
	 */
	public int $summaryItems = 3; // Default value

	/**
	 * @var string Generation ID for tracking
	 */
	public string $generationId = '';

	/**
	 * @var string Post status filter
	 */
	public string $postStatus = 'any';

	/**
	 * @var string Post type filter
	 */
	public string $postType = 'post';

	/**
	 * Create from POST data
	 *
	 * @param array $post POST data
	 * @return self
	 * @throws \InvalidArgumentException On validation errors
	 */
	public static function fromPost( array $post ): self {
		$request = new self();

		// Parse the payload
		$payload = array();
		if ( ! empty( $post['payload'] ) ) {
			$payload = json_decode( wp_unslash( $post['payload'] ), true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new \InvalidArgumentException( 'Invalid JSON payload: ' . json_last_error_msg() );
			}
		}
		
		// Debug log the payload
		\NuclearEngagement\Services\LoggingService::log( 'GenerateRequest payload: ' . print_r( $payload, true ) );

		// Extract and validate post IDs
		$postIdsJson      = $payload['nuclen_selected_post_ids'] ?? '';
		$request->postIds = json_decode( $postIdsJson, true ) ?: array();

		if ( empty( $request->postIds ) || ! is_array( $request->postIds ) ) {
			throw new \InvalidArgumentException( 'No valid posts selected' );
		}

		// Sanitize post IDs
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

		// Validate post ownership/permissions
		$request->postIds = array_filter(
			$request->postIds,
			function ( $id ) {
				// Check if user can edit this post
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return false;
				}
				
				// Verify post exists and is published
				$post = get_post( $id );
				if ( ! $post || $post->post_status !== 'publish' ) {
					return false;
				}
				
				return true;
			}
		);

		if ( empty( $request->postIds ) ) {
			throw new \InvalidArgumentException( 'No posts available for generation - insufficient permissions or invalid post IDs' );
		}

		// Map other fields
		$request->postStatus   = sanitize_text_field( $payload['nuclen_selected_post_status'] ?? 'any' );
		$request->postType     = sanitize_text_field( $payload['nuclen_selected_post_type'] ?? 'post' );
		$request->workflowType = sanitize_text_field( $payload['nuclen_selected_generate_workflow'] ?? '' );

		// Validate workflow type
		if ( ! in_array( $request->workflowType, array( 'quiz', 'summary' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid workflow type: ' . $request->workflowType );
		}

		// Summary specific fields
		$request->summaryFormat = sanitize_text_field( $payload['nuclen_selected_summary_format'] ?? 'paragraph' );
		if ( ! in_array( $request->summaryFormat, array( 'paragraph', 'bullet_list' ), true ) ) {
			$request->summaryFormat = 'paragraph';
		}

		// Use constants if defined, otherwise use defaults
		$summaryLengthMin = defined( 'NUCLEN_SUMMARY_LENGTH_MIN' ) ? NUCLEN_SUMMARY_LENGTH_MIN : 20;
		$summaryLengthMax = defined( 'NUCLEN_SUMMARY_LENGTH_MAX' ) ? NUCLEN_SUMMARY_LENGTH_MAX : 50;
		$summaryLengthDefault = defined( 'NUCLEN_SUMMARY_LENGTH_DEFAULT' ) ? NUCLEN_SUMMARY_LENGTH_DEFAULT : 30;
		
		$summaryItemsMin = defined( 'NUCLEN_SUMMARY_ITEMS_MIN' ) ? NUCLEN_SUMMARY_ITEMS_MIN : 3;
		$summaryItemsMax = defined( 'NUCLEN_SUMMARY_ITEMS_MAX' ) ? NUCLEN_SUMMARY_ITEMS_MAX : 7;
		$summaryItemsDefault = defined( 'NUCLEN_SUMMARY_ITEMS_DEFAULT' ) ? NUCLEN_SUMMARY_ITEMS_DEFAULT : 3;
		
		$request->summaryLength = max(
			$summaryLengthMin,
			min(
				$summaryLengthMax,
				(int) ( $payload['nuclen_selected_summary_length'] ?? $summaryLengthDefault )
			)
		);
		$request->summaryItems  = max(
			$summaryItemsMin,
			min(
				$summaryItemsMax,
				(int) ( $payload['nuclen_selected_summary_number_of_items'] ?? $summaryItemsDefault )
			)
		);

		// Generation ID
		$request->generationId = ! empty( $payload['generation_id'] )
			? sanitize_text_field( $payload['generation_id'] )
			: 'gen_' . uniqid( 'manual_', true );

		return $request;
	}
}
