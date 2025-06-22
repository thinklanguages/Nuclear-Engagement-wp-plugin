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
    public array $postIds = [];

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
    public int $summaryLength = NUCLEN_SUMMARY_LENGTH_DEFAULT;

    /**
     * @var int Number of summary items
     */
    public int $summaryItems = NUCLEN_SUMMARY_ITEMS_DEFAULT;

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
    public static function fromPost(array $post): self {
        $request = new self();

        // Parse the payload
        $payload = [];
        if (!empty($post['payload'])) {
            $payload = json_decode(wp_unslash($post['payload']), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON payload: ' . json_last_error_msg());
            }
        }

        // Extract and validate post IDs
        $postIdsJson = $payload['nuclen_selected_post_ids'] ?? '';
        $request->postIds = json_decode($postIdsJson, true) ?: [];

        if (empty($request->postIds) || !is_array($request->postIds)) {
            throw new \InvalidArgumentException('No valid posts selected');
        }

        // Sanitize post IDs
        $request->postIds = array_map('intval', $request->postIds);
        $request->postIds = array_filter($request->postIds, function($id) {
            return $id > 0;
        });

        if (empty($request->postIds)) {
            throw new \InvalidArgumentException('No valid post IDs after sanitization');
        }

        // Map other fields
        $request->postStatus = sanitize_text_field($payload['nuclen_selected_post_status'] ?? 'any');
        $request->postType = sanitize_text_field($payload['nuclen_selected_post_type'] ?? 'post');
        $request->workflowType = sanitize_text_field($payload['nuclen_selected_generate_workflow'] ?? '');

        // Validate workflow type
        if (!in_array($request->workflowType, ['quiz', 'summary'], true)) {
            throw new \InvalidArgumentException('Invalid workflow type: ' . $request->workflowType);
        }

        // Summary specific fields
        $request->summaryFormat = sanitize_text_field($payload['nuclen_selected_summary_format'] ?? 'paragraph');
        if (!in_array($request->summaryFormat, ['paragraph', 'bullet_list'], true)) {
            $request->summaryFormat = 'paragraph';
        }

        $request->summaryLength = max(
            NUCLEN_SUMMARY_LENGTH_MIN,
            min(
                NUCLEN_SUMMARY_LENGTH_MAX,
                (int) ( $payload['nuclen_selected_summary_length'] ?? NUCLEN_SUMMARY_LENGTH_DEFAULT )
            )
        );
        $request->summaryItems = max(
            NUCLEN_SUMMARY_ITEMS_MIN,
            min(
                NUCLEN_SUMMARY_ITEMS_MAX,
                (int) ( $payload['nuclen_selected_summary_number_of_items'] ?? NUCLEN_SUMMARY_ITEMS_DEFAULT )
            )
        );

        // Generation ID
        $request->generationId = !empty($payload['generation_id'])
            ? sanitize_text_field($payload['generation_id'])
            : 'gen_' . uniqid('manual_', true);

        return $request;
    }
}