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

		$payload = self::parse_payload( $post );
		\NuclearEngagement\Services\LoggingService::log( 'GenerateRequest payload: ' . print_r( $payload, true ) );

		$request->postIds = self::extract_and_validate_post_ids( $payload );
		self::map_basic_fields( $request, $payload );
		self::validate_workflow_type( $request->workflowType );
		self::map_summary_fields( $request, $payload );
		$request->generationId = self::generate_id( $payload );

		return $request;
	}

	/**
	 * Parse JSON payload from POST data.
	 *
	 * @param array $post POST data.
	 * @return array Parsed payload.
	 * @throws \InvalidArgumentException On JSON parsing errors.
	 */
	private static function parse_payload( array $post ): array {
		if ( empty( $post['payload'] ) ) {
			return array();
		}

		$payload = json_decode( wp_unslash( $post['payload'] ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \InvalidArgumentException( 'Invalid JSON payload: ' . json_last_error_msg() );
		}

		return $payload;
	}

	/**
	 * Extract and validate post IDs from payload.
	 *
	 * @param array $payload Payload data.
	 * @return array Valid post IDs.
	 * @throws \InvalidArgumentException On validation errors.
	 */
	private static function extract_and_validate_post_ids( array $payload ): array {
		$post_ids_json = $payload['nuclen_selected_post_ids'] ?? '';
		$post_ids = json_decode( $post_ids_json, true ) ?: array();

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			throw new \InvalidArgumentException( 'No valid posts selected' );
		}

		$post_ids = self::sanitize_post_ids( $post_ids );
		$post_ids = self::filter_accessible_posts( $post_ids );

		if ( empty( $post_ids ) ) {
			throw new \InvalidArgumentException( 'No posts available for generation - insufficient permissions or invalid post IDs' );
		}

		return $post_ids;
	}

	/**
	 * Sanitize post IDs array.
	 *
	 * @param array $post_ids Raw post IDs.
	 * @return array Sanitized post IDs.
	 */
	private static function sanitize_post_ids( array $post_ids ): array {
		$sanitized = array_map( 'intval', $post_ids );
		$sanitized = array_filter( $sanitized, function ( $id ) {
			return $id > 0;
		});

		if ( empty( $sanitized ) ) {
			throw new \InvalidArgumentException( 'No valid post IDs after sanitization' );
		}

		return $sanitized;
	}

	/**
	 * Filter posts that user can access.
	 *
	 * @param array $post_ids Post IDs to filter.
	 * @return array Accessible post IDs.
	 */
	private static function filter_accessible_posts( array $post_ids ): array {
		return array_filter( $post_ids, function ( $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return false;
			}

			$post = get_post( $id );
			return $post && 'publish' === $post->post_status;
		});
	}

	/**
	 * Map basic fields from payload to request.
	 *
	 * @param self  $request Request object.
	 * @param array $payload Payload data.
	 */
	private static function map_basic_fields( self $request, array $payload ): void {
		$request->postStatus   = sanitize_text_field( $payload['nuclen_selected_post_status'] ?? 'any' );
		$request->postType     = sanitize_text_field( $payload['nuclen_selected_post_type'] ?? 'post' );
		$request->workflowType = sanitize_text_field( $payload['nuclen_selected_generate_workflow'] ?? '' );
	}

	/**
	 * Validate workflow type.
	 *
	 * @param string $workflow_type Workflow type to validate.
	 * @throws \InvalidArgumentException On invalid workflow type.
	 */
	private static function validate_workflow_type( string $workflow_type ): void {
		if ( ! in_array( $workflow_type, array( 'quiz', 'summary' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid workflow type: ' . $workflow_type );
		}
	}

	/**
	 * Map summary-specific fields from payload to request.
	 *
	 * @param self  $request Request object.
	 * @param array $payload Payload data.
	 */
	private static function map_summary_fields( self $request, array $payload ): void {
		$request->summaryFormat = sanitize_text_field( $payload['nuclen_selected_summary_format'] ?? 'paragraph' );
		if ( ! in_array( $request->summaryFormat, array( 'paragraph', 'bullet_list' ), true ) ) {
			$request->summaryFormat = 'paragraph';
		}

		$limits = self::get_summary_limits();
		$request->summaryLength = self::clamp_value(
			(int) ( $payload['nuclen_selected_summary_length'] ?? $limits['length_default'] ),
			$limits['length_min'],
			$limits['length_max']
		);
		$request->summaryItems = self::clamp_value(
			(int) ( $payload['nuclen_selected_summary_number_of_items'] ?? $limits['items_default'] ),
			$limits['items_min'],
			$limits['items_max']
		);
	}

	/**
	 * Get summary limits from constants or defaults.
	 *
	 * @return array Summary limits.
	 */
	private static function get_summary_limits(): array {
		return array(
			'length_min'     => defined( 'NUCLEN_SUMMARY_LENGTH_MIN' ) ? NUCLEN_SUMMARY_LENGTH_MIN : 20,
			'length_max'     => defined( 'NUCLEN_SUMMARY_LENGTH_MAX' ) ? NUCLEN_SUMMARY_LENGTH_MAX : 50,
			'length_default' => defined( 'NUCLEN_SUMMARY_LENGTH_DEFAULT' ) ? NUCLEN_SUMMARY_LENGTH_DEFAULT : 30,
			'items_min'      => defined( 'NUCLEN_SUMMARY_ITEMS_MIN' ) ? NUCLEN_SUMMARY_ITEMS_MIN : 3,
			'items_max'      => defined( 'NUCLEN_SUMMARY_ITEMS_MAX' ) ? NUCLEN_SUMMARY_ITEMS_MAX : 7,
			'items_default'  => defined( 'NUCLEN_SUMMARY_ITEMS_DEFAULT' ) ? NUCLEN_SUMMARY_ITEMS_DEFAULT : 3,
		);
	}

	/**
	 * Clamp value between min and max.
	 *
	 * @param int $value Value to clamp.
	 * @param int $min   Minimum value.
	 * @param int $max   Maximum value.
	 * @return int Clamped value.
	 */
	private static function clamp_value( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Generate or extract generation ID.
	 *
	 * @param array $payload Payload data.
	 * @return string Generation ID.
	 */
	private static function generate_id( array $payload ): string {
		return ! empty( $payload['generation_id'] )
			? sanitize_text_field( $payload['generation_id'] )
			: 'gen_' . uniqid( 'manual_', true );
	}
}
