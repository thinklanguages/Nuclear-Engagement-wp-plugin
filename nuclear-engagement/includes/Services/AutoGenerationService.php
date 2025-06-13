<?php
/**
 * File: includes/Services/AutoGenerationService.php
 *
 * Handles auto-generation of quizzes and summaries on post publish.
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Services\GenerationTracker;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AutoGenerationService {
    /**
     * @var SettingsRepository
     */
    private $settings_repository;

    /**
     * @var RemoteApiService
     */
    private $remote_api;

    /**
     * @var ContentStorageService
     */
    private $content_storage;

    /**
     * @var GenerationTracker
     */
    private $tracker;

    /**
     * Constructor
     *
     * @param SettingsRepository $settings_repository
     * @param RemoteApiService $remote_api
     * @param ContentStorageService $content_storage
     */
    public function __construct(
        SettingsRepository $settings_repository,
        RemoteApiService $remote_api,
        ContentStorageService $content_storage,
        GenerationTracker $tracker
    ) {
        $this->settings_repository = $settings_repository;
        $this->remote_api = $remote_api;
        $this->content_storage = $content_storage;
        $this->tracker = $tracker;
    }

    /**
     * Register WordPress hooks
     */
    public function register_hooks(): void {
        add_action(
            'nuclen_poll_generation',
            [ $this, 'poll_generation' ],
            10,
            4 // generation_id, workflow_type, post_id, attempt
        );

        add_action('transition_post_status', [ $this, 'handle_post_publish' ], 10, 3);
    }

    /**
     * Handle post publish transition
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param \WP_Post $post Post object
     */
    public function handle_post_publish($new_status, $old_status, $post): void {
        // Only when we enter publish
        if ($old_status === 'publish' || $new_status !== 'publish') {
            return;
        }

        $allowed_post_types = $this->settings_repository->get('generation_post_types', ['post']);
        if (!in_array($post->post_type, (array) $allowed_post_types, true)) {
            return;
        }

        $gen_quiz = (bool) $this->settings_repository->get('auto_generate_quiz_on_publish', false);
        $gen_summary = (bool) $this->settings_repository->get('auto_generate_summary_on_publish', false);

        if (!$gen_quiz && !$gen_summary) {
            return;
        }

        // Auto-generate quiz
        if ($gen_quiz) {
            $protected = get_post_meta($post->ID, 'nuclen_quiz_protected', true);
            if (!$protected) {
                $this->generate_single($post->ID, 'quiz');
            }
        }

        // Auto-generate summary
        if ($gen_summary) {
            $protected = get_post_meta($post->ID, 'nuclen_summary_protected', true);
            if (!$protected) {
                $this->generate_single($post->ID, 'summary');
            }
        }
    }

    /**
     * Generate content for a single post
     *
     * @param int $post_id Post ID
     * @param string $workflow_type Type of content to generate (quiz/summary)
     */
    public function generate_single(int $post_id, string $workflow_type): void {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        // Skip if protected (double-check)
        $meta_key = $workflow_type === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
        if (get_post_meta($post_id, $meta_key, true)) {
            return;
        }

        try {
            $post_data = [
                [
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'content' => wp_strip_all_tags($post->post_content),
                ],
            ];

            $workflow = [
                'type' => $workflow_type,
                'summary_format' => 'paragraph',
                'summary_length' => 30,
                'summary_number_of_items' => 3,
            ];

            $generation_id = 'gen_' . uniqid('auto_', true);

            // Create tracker record
            $this->tracker->create([
                'generation_id' => $generation_id,
                'workflow_type' => $workflow_type,
                'post_ids'      => [$post_id],
                'total'         => 1,
                'status'        => 'pending',
                'next_poll'     => date('Y-m-d H:i:s', time() + 15),
            ]);

            $data_to_send = [
                'posts' => $post_data,
                'workflow' => $workflow,
                'generation_id' => $generation_id,
            ];

            $result = $this->remote_api->sendPostsToGenerate($data_to_send);

            if (is_wp_error($result)) {
                error_log('Failed to start generation: ' . $result->get_error_message());
                return;
            }

            // Schedule the first poll
            $next_poll = time() + 60;
            if (!wp_next_scheduled('nuclen_poll_generation', [$generation_id, $workflow_type, $post_id, 1])) {
                wp_schedule_single_event($next_poll, 'nuclen_poll_generation', [
                    $generation_id,
                    $workflow_type,
                    $post_id,
                    1
                ]);
            }

            $this->tracker->update($generation_id, ['status' => 'processing']);

        } catch (\Exception $e) {
            error_log('Error in generate_single: ' . $e->getMessage());
        }
    }

    /**
     * Poll for generation updates
     *
     * @param string $generation_id Generation ID
     * @param string $workflow_type Type of workflow (quiz/summary)
     * @param int $post_id Post ID
     * @param int $attempt Current attempt number
     */
    public function poll_generation(string $generation_id, string $workflow_type, int $post_id, int $attempt): void {
        $max_attempts = 10;
        $retry_delay = 60; // 1 minute between retries

        // Get tracker record
        $record = $this->tracker->get($generation_id);
        if (!$record || $record->status === 'complete' || $record->status === 'failed') {
            return;
        }

        try {
            $connected = $this->settings_repository->get('connected', false);
            $wp_app_pass_created = $this->settings_repository->get('wp_app_pass_created', false);
            if (!$connected || !$wp_app_pass_created) {
                $this->tracker->update($generation_id, ['status' => 'failed']);
                return;
            }

            $data = $this->remote_api->fetchUpdates($generation_id);

            if (!empty($data['results']) && is_array($data['results'])) {
                $this->content_storage->storeResults($data['results'], $workflow_type);
                $processed = $record->processed + count($data['results']);

                $this->tracker->update($generation_id, [
                    'processed' => $processed,
                    'status'    => $processed >= $record->total ? 'complete' : 'processing',
                ]);

                if ($processed >= $record->total) {
                    return;
                }
            }
        } catch (\Exception $e) {
            error_log("Polling error for generation {$generation_id}: " . $e->getMessage());
        }

        if ($attempt < $max_attempts) {
            $next_poll = time() + $retry_delay;
            $this->tracker->update($generation_id, [
                'next_poll' => date('Y-m-d H:i:s', $next_poll),
                'attempt'   => $attempt + 1,
            ]);

            wp_schedule_single_event(
                $next_poll,
                'nuclen_poll_generation',
                [$generation_id, $workflow_type, $post_id, $attempt + 1]
            );
        } else {
            $this->tracker->update($generation_id, ['status' => 'failed']);
        }
    }

    /**
     * Generate content for a single post (public alias for backward compatibility)
     *
     * @param int $post_id Post ID
     * @param string $workflow_type Type of content to generate (quiz/summary)
     */
    public function generateSingle(int $post_id, string $workflow_type): void {
        $this->generate_single($post_id, $workflow_type);
    }
}
