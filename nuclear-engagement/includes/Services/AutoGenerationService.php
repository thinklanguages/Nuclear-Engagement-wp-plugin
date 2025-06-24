<?php
declare(strict_types=1);
/**
 * File: includes/Services/AutoGenerationService.php
 *
 * Handles auto-generation of quizzes and summaries on post publish.
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Services\GenerationPoller;
use NuclearEngagement\Services\PublishGenerationHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoGenerationService {
        /** Cron hook used to start a generation task. */
        public const START_HOOK = 'nuclen_start_generation';

        /** Cron hook for processing the queued posts. */
        public const QUEUE_HOOK = 'nuclen_process_autogen_queue';

        /** Maximum number of posts to send in one batch. */
        public const BATCH_SIZE = 5;

        /** Option name storing the queued post IDs. */
        private const QUEUE_OPTION = 'nuclen_autogen_queue';

        /** Number of seconds before the first poll runs. */
        public const INITIAL_POLL_DELAY = 15;

        /** Maximum number of API polling attempts. */
        public const MAX_ATTEMPTS = 10;

        /** Delay in seconds between poll attempts. */
        public const RETRY_DELAY = 60;

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
	 * @var GenerationPoller
	 */
	private GenerationPoller $poller;

	/**
	 * @var PublishGenerationHandler
	 */
	private PublishGenerationHandler $publish_handler;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository    $settings_repository
	 * @param RemoteApiService      $remote_api
	 * @param ContentStorageService $content_storage
	 */
	public function __construct(
		SettingsRepository $settings_repository,
		RemoteApiService $remote_api,
		ContentStorageService $content_storage,
		GenerationPoller $poller,
		PublishGenerationHandler $publish_handler
	) {
		$this->settings_repository = $settings_repository;
		$this->remote_api          = $remote_api;
		$this->content_storage     = $content_storage;
		$this->poller              = $poller;
		$this->publish_handler     = $publish_handler;
	}

	/**
	 * Register WordPress hooks
	 */
	public function register_hooks(): void {
		$this->poller->register_hooks();

                add_action(
                        self::START_HOOK,
                        array( $this, 'run_generation' ),
                        10,
                        2 // post_id, workflow_type
                );

                add_action( self::QUEUE_HOOK, array( $this, 'process_queue' ) );

		$this->publish_handler->register_hooks();
	}

	/**
	 * Handle post publish transition
	 *
	 * @param string   $new_status New post status
	 * @param string   $old_status Old post status
	 * @param \WP_Post $post Post object
	 */
	public function handle_post_publish( $new_status, $old_status, $post ): void {
		$this->publish_handler->handle_post_publish( $new_status, $old_status, $post );
	}

	/**
	 * Generate content for a single post
	 *
	 * @param int    $post_id Post ID
	 * @param string $workflow_type Type of content to generate (quiz/summary)
	 */
    public function generate_single( int $post_id, string $workflow_type ): void {
        $this->queue_post( $post_id, $workflow_type );
    }

    /**
     * Add a post to the pending generation queue and schedule processing.
     */
    private function queue_post( int $post_id, string $workflow_type ): void {
        $queue = get_option( self::QUEUE_OPTION, array() );
        if ( ! isset( $queue[ $workflow_type ] ) ) {
            $queue[ $workflow_type ] = array();
        }
        if ( ! in_array( $post_id, $queue[ $workflow_type ], true ) ) {
            $queue[ $workflow_type ][] = $post_id;
            update_option( self::QUEUE_OPTION, $queue, 'no' );
        }
        if ( ! wp_next_scheduled( self::QUEUE_HOOK ) ) {
            $scheduled = wp_schedule_single_event( time(), self::QUEUE_HOOK, array() );
            if ( false === $scheduled ) {
                \NuclearEngagement\Services\LoggingService::log(
                    'Failed to schedule event ' . self::QUEUE_HOOK
                );
            }
        }
    }

    /**
     * Process queued posts in batches.
     */
    public function process_queue(): void {
        $queue = get_option( self::QUEUE_OPTION, array() );
        if ( empty( $queue ) ) {
            return;
        }

        $generations = get_option( 'nuclen_active_generations', array() );

        foreach ( $queue as $workflow_type => $ids ) {
            $ids = array_map( 'absint', $ids );
            while ( ! empty( $ids ) ) {
                $batch       = array_splice( $ids, 0, self::BATCH_SIZE );
                $post_data   = array();

                $posts = get_posts(
                    array(
                        'post__in'               => $batch,
                        'posts_per_page'         => count( $batch ),
                        'post_type'              => 'any',
                        'orderby'                => 'post__in',
                        'update_post_meta_cache' => false,
                        'update_post_term_cache' => false,
                    )
                );

                foreach ( $posts as $post ) {
                    $pid      = (int) $post->ID;
                    $meta_key = $workflow_type === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
                    if ( get_post_meta( $pid, $meta_key, true ) ) {
                        continue;
                    }
                    $post_data[] = array(
                        'id'      => $pid,
                        'title'   => get_the_title( $pid ),
                        'content' => wp_strip_all_tags( $post->post_content ),
                    );
                }

                if ( empty( $post_data ) ) {
                    continue;
                }

                $workflow = array(
                    'type'                    => $workflow_type,
                    'summary_format'          => 'paragraph',
                    'summary_length'          => NUCLEN_SUMMARY_LENGTH_DEFAULT,
                    'summary_number_of_items' => NUCLEN_SUMMARY_ITEMS_DEFAULT,
                );

                $generation_id = 'gen_' . uniqid( 'auto_', true );

                try {
                    $this->remote_api->send_posts_to_generate(
                        array(
                            'posts'         => $post_data,
                            'workflow'      => $workflow,
                            'generation_id' => $generation_id,
                        )
                    );
                } catch ( ApiException $e ) {
                    \NuclearEngagement\Services\LoggingService::log( 'Failed to start generation: ' . $e->getMessage() );
                    \NuclearEngagement\Services\LoggingService::notify_admin( 'Auto-generation failed: ' . $e->getMessage() );
                    continue;
                }

                $next_poll                    = time() + self::INITIAL_POLL_DELAY + mt_rand( 1, 5 );
                $generations[ $generation_id ] = array(
                    'started_at'    => current_time( 'mysql' ),
                    'post_ids'      => $batch,
                    'next_poll'     => $next_poll,
                    'attempt'       => 1,
                    'workflow_type' => $workflow_type,
                );

                update_option( 'nuclen_active_generations', $generations, 'no' );
                $scheduled = wp_schedule_single_event( $next_poll, 'nuclen_poll_generation', array( $generation_id, $workflow_type, $batch, 1 ) );
                if ( false === $scheduled ) {
                    \NuclearEngagement\Services\LoggingService::log(
                        'Failed to schedule event nuclen_poll_generation for generation ' . $generation_id
                    );
                }
            }

            $queue[ $workflow_type ] = $ids;
        }

        foreach ( $queue as $type => $ids ) {
            if ( empty( $ids ) ) {
                unset( $queue[ $type ] );
            }
        }

        if ( empty( $queue ) ) {
            delete_option( self::QUEUE_OPTION );
        } else {
            update_option( self::QUEUE_OPTION, $queue, 'no' );
            $scheduled = wp_schedule_single_event( time() + 1, self::QUEUE_HOOK, array() );
            if ( false === $scheduled ) {
                \NuclearEngagement\Services\LoggingService::log(
                    'Failed to schedule event ' . self::QUEUE_HOOK
                );
            }
        }
    }

	/**
	 * Poll for generation updates
	 *
	 * @param string $generation_id Generation ID
	 * @param string $workflow_type Type of workflow (quiz/summary)
         * @param array $post_ids List of post IDs
         * @param int   $attempt  Current attempt number
         */
    public function poll_generation( string $generation_id, string $workflow_type, array $post_ids, int $attempt ): void {
        $this->poller->poll_generation( $generation_id, $workflow_type, $post_ids, $attempt );
    }

	/**
	 * Cron callback to start a generation task for a post.
	 *
	 * @param int    $post_id       Post ID
	 * @param string $workflow_type Type of content to generate
	 */
	public function run_generation( int $post_id, string $workflow_type ): void {
		$this->generate_single( $post_id, $workflow_type );
	}

	/**
	 * Generate content for a single post (public alias for backward compatibility)
	 *
	 * @param int    $post_id Post ID
	 * @param string $workflow_type Type of content to generate (quiz/summary)
	 */
	public function generateSingle( int $post_id, string $workflow_type ): void {
		$this->generate_single( $post_id, $workflow_type );
	}
}
