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

use NuclearEngagement\Exceptions\ValidationException;

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
	 * Generation priority (high for manual, low for auto).
	 *
	 * @var string
	 */
	public string $priority = 'high';

	/**
	 * Source of generation request (manual, auto, bulk).
	 *
	 * @var string
	 */
	public string $source = 'manual';

	/**
	 * Current retry attempt number.
	 *
	 * @var int
	 */
	public int $retryCount = 0;

	/**
	 * Maximum retry attempts.
	 *
	 * @var int
	 */
	public int $maxRetries = 0;

	/**
	 * Create from POST data.
	 *
	 * @param array $post POST data.
	 * @return self
	 * @throws ValidationException On validation errors.
	 */
	public static function from_post( array $post ): self {
		$request           = new self();
		$validation_errors = array();

		try {
			$payload = self::parse_payload( $post );
			\NuclearEngagement\Services\LoggingService::log( 'GenerateRequest payload received' );
		} catch ( \InvalidArgumentException $e ) {
			$validation_errors['payload'] = $e->getMessage();
			throw new ValidationException( $validation_errors );
		}

		try {
			$request->postIds = self::extract_and_validate_post_ids( $payload );
		} catch ( \InvalidArgumentException $e ) {
			$validation_errors['post_ids'] = $e->getMessage();
		}

		self::map_basic_fields( $request, $payload );

		try {
			self::validate_workflow_type( $request->workflowType );
		} catch ( \InvalidArgumentException $e ) {
			$validation_errors['workflow_type'] = $e->getMessage();
		}

		if ( ! empty( $validation_errors ) ) {
			throw new ValidationException( $validation_errors );
		}

		self::map_summary_fields( $request, $payload );
		$request->generationId = self::generate_id( $payload );
		self::map_optional_fields( $request, $payload );

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
			// If no payload field, validate and sanitize POST data
			$required_fields = array( 'nuclen_selected_post_ids', 'nuclen_selected_generate_workflow' );
			foreach ( $required_fields as $field ) {
				if ( empty( $post[ $field ] ) ) {
					throw new \InvalidArgumentException(
						sprintf( 'Missing required field: %s', $field )
					);
				}
			}

			// Ensure POST data is properly structured
			return self::sanitize_post_data( $post );
		}

		$payload = json_decode( wp_unslash( $post['payload'] ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid JSON payload: %s', json_last_error_msg() )
			);
		}

		return $payload;
	}

	/**
	 * Sanitize raw POST data
	 *
	 * @param array $post Raw POST data
	 * @return array Sanitized data
	 */
	private static function sanitize_post_data( array $post ): array {
		$sanitized = array();

		// Define allowed fields and their sanitization methods
		$field_map = array(
			'nuclen_selected_post_ids'                => 'sanitize_text_field',
			'nuclen_selected_generate_workflow'       => 'sanitize_text_field',
			'nuclen_selected_post_status'             => 'sanitize_text_field',
			'nuclen_selected_post_type'               => 'sanitize_text_field',
			'nuclen_selected_summary_format'          => 'sanitize_text_field',
			'nuclen_selected_summary_length'          => 'absint',
			'nuclen_selected_summary_number_of_items' => 'absint',
			'generation_id'                           => 'sanitize_text_field',
			'priority'                                => 'sanitize_text_field',
			'source'                                  => 'sanitize_text_field',
			'retry_count'                             => 'absint',
			'max_retries'                             => 'absint',
		);

		foreach ( $field_map as $field => $sanitizer ) {
			if ( isset( $post[ $field ] ) ) {
				$sanitized[ $field ] = call_user_func( $sanitizer, $post[ $field ] );
			}
		}

		return $sanitized;
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

		\NuclearEngagement\Services\LoggingService::log(
			'Processing post IDs from request'
		);

		$post_ids = json_decode( $post_ids_json, true ) ?: array();

		\NuclearEngagement\Services\LoggingService::log(
			'Post IDs decoded successfully: ' . count( $post_ids ) . ' posts'
		);

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			throw new ValidationException(
				__( 'Please select at least one post to generate content for.', 'nuclear-engagement' ),
				array( 'post_ids' => $post_ids )
			);
		}

		try {
			$post_ids = self::sanitize_post_ids( $post_ids );
		} catch ( \InvalidArgumentException $e ) {
			throw new ValidationException(
				__( 'Invalid post IDs provided. Please refresh and try again.', 'nuclear-engagement' ),
				array( 'invalid_ids' => array_diff( $post_ids, $sanitized ) )
			);
		}

		$post_ids = self::filter_accessible_posts( $post_ids );

		if ( empty( $post_ids ) ) {
			throw new ValidationException(
				__( 'You do not have permission to generate content for the selected posts.', 'nuclear-engagement' ),
				array(
					'user_id'             => get_current_user_id(),
					'required_capability' => 'edit_post',
				)
			);
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
		$sanitized = array_filter(
			$sanitized,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $sanitized ) ) {
			throw new ValidationException(
				'No valid post IDs after sanitization',
				array(
					'original_count'  => count( $post_ids ),
					'sanitized_count' => 0,
				)
			);
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
		$filtered = array_filter(
			$post_ids,
			function ( $id ) {
				if ( ! current_user_can( 'edit_post', $id ) ) {
					\NuclearEngagement\Services\LoggingService::log(
						"Post ID {$id} filtered out - user cannot edit"
					);
					return false;
				}

				$post = get_post( $id );
				if ( ! $post ) {
					\NuclearEngagement\Services\LoggingService::log(
						"Post ID {$id} filtered out - post not found"
					);
					return false;
				}

				if ( 'publish' !== $post->post_status ) {
					\NuclearEngagement\Services\LoggingService::log(
						"Post ID {$id} filtered out - status is '{$post->post_status}', not 'publish'"
					);
					return false;
				}

				return true;
			}
		);

		\NuclearEngagement\Services\LoggingService::log(
			'Filtered post IDs: ' . var_export( array_values( $filtered ), true )
		);

		return $filtered;
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
		if ( empty( $workflow_type ) ) {
			throw new ValidationException(
				__( 'Please select a content type (Quiz or Summary).', 'nuclear-engagement' ),
				array( 'workflow_type' => 'empty' )
			);
		}

		if ( ! in_array( $workflow_type, array( 'quiz', 'summary' ), true ) ) {
			throw new ValidationException(
				__( 'Invalid content type selected. Please choose Quiz or Summary.', 'nuclear-engagement' ),
				array(
					'workflow_type' => $workflow_type,
					'allowed_types' => array( 'quiz', 'summary' ),
				)
			);
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

		$limits                 = self::get_summary_limits();
		$request->summaryLength = self::clamp_value(
			(int) ( $payload['nuclen_selected_summary_length'] ?? $limits['length_default'] ),
			$limits['length_min'],
			$limits['length_max']
		);
		$request->summaryItems  = self::clamp_value(
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

	/**
	 * Map optional fields from payload to request.
	 *
	 * @param self  $request Request object.
	 * @param array $payload Payload data.
	 */
	private static function map_optional_fields( self $request, array $payload ): void {
		if ( isset( $payload['priority'] ) ) {
			$priority          = sanitize_text_field( $payload['priority'] );
			$request->priority = in_array( $priority, array( 'high', 'low' ), true ) ? $priority : 'high';
		}

		if ( isset( $payload['source'] ) ) {
			$source          = sanitize_text_field( $payload['source'] );
			$request->source = in_array( $source, array( 'manual', 'auto', 'bulk' ), true ) ? $source : 'manual';
		}

		if ( isset( $payload['retry_count'] ) ) {
			$request->retryCount = absint( $payload['retry_count'] );
		}

		if ( isset( $payload['max_retries'] ) ) {
			$request->maxRetries = absint( $payload['max_retries'] );
		}
	}
}
