<?php
/**
 * File: includes/Services/GenerationService.php

 * Generation Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Requests\GenerateRequest;
use NuclearEngagement\Responses\GenerationResponse;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for handling content generation
 */
class GenerationService {
    /** Seconds to wait between polling events. */
    public const POLL_DELAY = NUCLEN_GENERATION_POLL_DELAY;
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
     * @param SettingsRepository $settings
     * @param RemoteApiService $api
     * @param ContentStorageService $storage
     */
    public function __construct(
        SettingsRepository $settings,
        RemoteApiService $api,
        ContentStorageService $storage
    ) {
        $this->settings = $settings;
        $this->api = $api;
        $this->storage = $storage;
        $this->utils = new Utils();
    }

    /**
     * Generate content for multiple posts
     *
     * @param GenerateRequest $request
     * @return GenerationResponse
     * @throws \RuntimeException On API errors
     */
    public function generateContent(GenerateRequest $request): GenerationResponse {
        // Get posts data
        $posts = $this->getPostsData($request->postIds, $request->postType, $request->postStatus);
        if (empty($posts)) {
            throw new \RuntimeException('No matching posts found');
        }

        // Build workflow data
        $workflow = [
            'type' => $request->workflowType,
            'summary_format' => $request->summaryFormat,
            'summary_length' => $request->summaryLength,
            'summary_number_of_items' => $request->summaryItems,
        ];

        // Prepare response
        $response = new GenerationResponse();
        $response->generationId = $request->generationId;

        try {
            $result = $this->api->sendPostsToGenerate([
                'posts'        => $posts,
                'workflow'     => $workflow,
                'generation_id' => $request->generationId,
            ]);
        } catch (\RuntimeException $e) {
            $response->success = false;
            $response->error   = $e->getMessage();
            $code = $e->getCode();
            if ($code > 0) {
                $response->statusCode = $code;
            }
            return $response;
        }

        // Process immediate results if any
        if (!empty($result['results']) && is_array($result['results'])) {
            $this->storage->storeResults($result['results'], $request->workflowType);
            $response->results = $result['results'];
        }

        return $response;
    }

    /**
     * Generate content for a single post
     *
     * @param int $postId
     * @param string $workflowType
     * @throws \InvalidArgumentException If post not found
     */
    public function generateSingle(int $postId, string $workflowType): void {
        $post = get_post($postId);
        if (!$post) {
            throw new \InvalidArgumentException("Post {$postId} not found");
        }

        // Check if protected
        if ($this->isProtected($postId, $workflowType)) {
\NuclearEngagement\Services\LoggingService::log("Skipping protected {$workflowType} for post {$postId}");
            return;
        }

        $request = new GenerateRequest();
        $request->postIds = [$postId];
        $request->workflowType = $workflowType;
        $request->generationId = 'auto_' . $postId . '_' . time();
        $request->postType = $post->post_type;
        $request->postStatus = $post->post_status;

        try {
            $response = $this->generateContent($request);

            // If no immediate results, schedule polling
            if (empty($response->results)) {
                wp_schedule_single_event(
                    time() + self::POLL_DELAY,
                    'nuclen_poll_generation',
                    [$response->generationId, $workflowType, $postId, 1]
                );
\NuclearEngagement\Services\LoggingService::log("Scheduled polling for post {$postId}, generation {$response->generationId}");
            }
        } catch (\Exception $e) {
\NuclearEngagement\Services\LoggingService::log("Error generating {$workflowType} for post {$postId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get posts data for generation
     *
     * @param array $postIds
     * @param string $postType
     * @param string $postStatus
     * @return array
     */
    private function getPostsData(array $postIds, string $postType, string $postStatus): array {
        $args = [
            'post__in' => $postIds,
            'numberposts' => -1,
            'post_type' => $postType,
            'post_status' => $postStatus,
            'orderby' => 'post__in',
        ];

        $posts = get_posts($args);
        $data = [];

        foreach ($posts as $post) {
            $data[] = [
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
                'content' => wp_strip_all_tags($post->post_content),
            ];
        }

        return $data;
    }

    /**
     * Check if content is protected from regeneration
     *
     * @param int $postId
     * @param string $workflowType
     * @return bool
     */
    private function isProtected(int $postId, string $workflowType): bool {
        $metaKey = $workflowType === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
        return (bool) get_post_meta($postId, $metaKey, true);
    }
}
