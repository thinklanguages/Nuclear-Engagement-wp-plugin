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
     * @var GenerationTracker
     */
    private GenerationTracker $tracker;

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
        ContentStorageService $storage,
        GenerationTracker $tracker
    ) {
        $this->settings = $settings;
        $this->api = $api;
        $this->storage = $storage;
        $this->tracker = $tracker;
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

        // Create tracker record
        $this->tracker->create([
            'generation_id' => $request->generationId,
            'workflow_type' => $request->workflowType,
            'post_ids'      => $request->postIds,
            'total'         => count($posts),
            'status'        => 'pending',
            'next_poll'     => date('Y-m-d H:i:s', time() + 10),
        ]);
        
        // Send to API
        $result = $this->api->sendPostsToGenerate([
            'posts' => $posts,
            'workflow' => $workflow,
            'generation_id' => $request->generationId,
        ]);
        
        // Create response
        $response = new GenerationResponse();
        $response->generationId = $request->generationId;
        
        // Handle API errors
        if (!empty($result['status_code']) && in_array($result['status_code'], [401, 403], true)) {
            $response->success = false;
            $response->statusCode = $result['status_code'];
            $response->errorCode = $result['error_code'] ?? null;
            
            if (!empty($result['error_code']) && $result['error_code'] === 'invalid_api_key') {
                $response->error = 'Invalid API key.';
            } elseif (!empty($result['error_code']) && $result['error_code'] === 'invalid_wp_app_pass') {
                $response->error = 'Invalid WP App Password.';
            } else {
                $response->error = 'Authentication error.';
            }
            
            return $response;
        }
        
        if (!empty($result['error'])) {
            $response->success = false;
            $response->error = $result['error'];
            return $response;
        }
        
        // Process immediate results if any
        if (!empty($result['results']) && is_array($result['results'])) {
            $this->storage->storeResults($result['results'], $request->workflowType);
            $response->results = $result['results'];

            $processed = count($result['results']);
            $this->tracker->update($request->generationId, [
                'processed' => $processed,
                'status'    => $processed >= count($posts) ? 'complete' : 'processing',
            ]);

            if ($processed >= count($posts)) {
                return $response;
            }
        }

        // Schedule polling via cron
        if (!wp_next_scheduled('nuclen_poll_generation', [$request->generationId, $request->workflowType, 0, 1])) {
            wp_schedule_single_event(time() + 60, 'nuclen_poll_generation', [$request->generationId, $request->workflowType, 0, 1]);
        }

        $this->tracker->update($request->generationId, ['status' => 'processing']);

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
            $this->utils->nuclen_log("Skipping protected {$workflowType} for post {$postId}");
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
                    time() + 30,
                    'nuclen_poll_generation',
                    [$response->generationId, $workflowType, $postId, 1]
                );
                $this->utils->nuclen_log("Scheduled polling for post {$postId}, generation {$response->generationId}");
            }
        } catch (\Exception $e) {
            $this->utils->nuclen_log("Error generating {$workflowType} for post {$postId}: " . $e->getMessage());
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

    /**
     * Get progress for active generations.
     *
     * @param string|null $generation_id Specific generation ID.
     * @return array
     */
    public function getProgress(?string $generation_id = null): array {
        if ($generation_id) {
            $record = $this->tracker->get($generation_id);
            return $record ? [$record] : [];
        }

        $user_id = get_current_user_id();
        $records = $this->tracker->getActive();

        if (!current_user_can('manage_options')) {
            $records = array_filter($records, function ($r) use ($user_id) {
                return $r->user_id == $user_id;
            });
        }

        return array_values($records);
    }
}
