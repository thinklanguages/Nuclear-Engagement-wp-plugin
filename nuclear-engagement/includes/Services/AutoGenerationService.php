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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AutoGenerationService {
    /** Number of seconds before the first poll runs. */
    public const INITIAL_POLL_DELAY = NUCLEN_INITIAL_POLL_DELAY;

    /** Maximum number of API polling attempts. */
    public const MAX_ATTEMPTS      = NUCLEN_MAX_POLL_ATTEMPTS;

    /** Delay in seconds between poll attempts. */
    public const RETRY_DELAY       = NUCLEN_POLL_RETRY_DELAY;

    /** Default length used when summarizing posts. */
    public const SUMMARY_LENGTH    = NUCLEN_SUMMARY_LENGTH_DEFAULT;

    /** Default number of items in auto summaries. */
    public const SUMMARY_ITEMS     = NUCLEN_SUMMARY_ITEMS_DEFAULT;
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
     * Constructor
     *
     * @param SettingsRepository $settings_repository
     * @param RemoteApiService $remote_api
     * @param ContentStorageService $content_storage
     */
    public function __construct(
        SettingsRepository $settings_repository,
        RemoteApiService $remote_api,
        ContentStorageService $content_storage
    ) {
        $this->settings_repository = $settings_repository;
        $this->remote_api = $remote_api;
        $this->content_storage = $content_storage;
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
                'summary_length' => self::SUMMARY_LENGTH,
                'summary_number_of_items' => self::SUMMARY_ITEMS,
            ];

            $generation_id = 'gen_' . uniqid('auto_', true);

            $data_to_send = [
                'posts' => $post_data,
                'workflow' => $workflow,
                'generation_id' => $generation_id,
            ];

            $result = $this->remote_api->sendPostsToGenerate($data_to_send);

            if (is_wp_error($result)) {
                \NuclearEngagement\Services\LoggingService::log(
                    'Failed to start generation: ' . $result->get_error_message()
                );
                return;
            }

            // Schedule the first poll
            $next_poll = time() + self::INITIAL_POLL_DELAY;

            // Store the generation ID in options for the cron job
            $generations = get_option('nuclen_active_generations', []);
            $generations[$generation_id] = [
                'started_at' => current_time('mysql'),
                'post_ids' => [$post_id],
                'next_poll' => $next_poll,
                'attempt' => 1,
                'workflow_type' => $workflow_type,
            ];
            // Do not autoload active generation state
            update_option('nuclen_active_generations', $generations, 'no');

            // Schedule the cron event
            $event_args = [ $generation_id, $workflow_type, $post_id, 1 ];
            if (!wp_next_scheduled('nuclen_poll_generation', $event_args)) {
                wp_schedule_single_event($next_poll, 'nuclen_poll_generation', $event_args);
            }

        } catch (\Exception $e) {
            \NuclearEngagement\Services\LoggingService::log(
                'Error in generate_single: ' . $e->getMessage()
            );
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
        $max_attempts = self::MAX_ATTEMPTS;
        $retry_delay = self::RETRY_DELAY; // 1 minute between retries

        try {
            // Check if auto-generation is enabled for this post type
            $connected = $this->settings_repository->get('connected', false);
            $wp_app_pass_created = $this->settings_repository->get('wp_app_pass_created', false);
            if (!$connected || !$wp_app_pass_created) {
                return;
            }

            // Get updates from the API
            $data = $this->remote_api->fetchUpdates($generation_id);

            // Check if we have results
            if (!empty($data['results']) && is_array($data['results'])) {
                $this->content_storage->storeResults($data['results'], $workflow_type);
                \NuclearEngagement\Services\LoggingService::log(
                    "Poll success for post {$post_id} ({$workflow_type}), generation {$generation_id}"
                );
                $this->cleanup_generation($generation_id);
                return;
            }

            // Check if still processing
            if (isset($data['success']) && $data['success'] === true) {
                // Still processing, log the attempt
                \NuclearEngagement\Services\LoggingService::log(
                    "Still processing post {$post_id} ({$workflow_type}), attempt {$attempt}/{$max_attempts}"
                );
            }

        } catch (\Exception $e) {
            \NuclearEngagement\Services\LoggingService::log(
                "Polling error for post {$post_id} ({$workflow_type}): " . $e->getMessage()
            );
        }

        // Schedule next poll if not at max attempts
        if ($attempt < $max_attempts) {
            $event_args = [ $generation_id, $workflow_type, $post_id, $attempt + 1 ];
            wp_schedule_single_event(
                time() + $retry_delay,
                'nuclen_poll_generation',
                $event_args
            );
        } else {
            \NuclearEngagement\Services\LoggingService::log(
                "Polling aborted after {$max_attempts} attempts for post {$post_id} ({$workflow_type})"
            );
            $this->cleanup_generation($generation_id);
        }
    }

    /**
     * Remove a completed or failed generation from the tracking option.
     *
     * @param string $generation_id Generation ID to remove
     */
    private function cleanup_generation(string $generation_id): void {
        $generations = get_option('nuclen_active_generations', []);
        if (isset($generations[$generation_id])) {
            unset($generations[$generation_id]);
            if (empty($generations)) {
                delete_option('nuclen_active_generations');
            } else {
                update_option('nuclen_active_generations', $generations, 'no');
            }
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
