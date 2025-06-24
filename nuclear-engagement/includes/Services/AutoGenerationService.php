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

	/** Number of seconds before the first poll runs. */
	public const INITIAL_POLL_DELAY = NUCLEN_INITIAL_POLL_DELAY;

	/** Maximum number of API polling attempts. */
	public const MAX_ATTEMPTS = NUCLEN_MAX_POLL_ATTEMPTS;

	/** Delay in seconds between poll attempts. */
	public const RETRY_DELAY = NUCLEN_POLL_RETRY_DELAY;

	/** Default length used when summarizing posts. */
	public const SUMMARY_LENGTH = NUCLEN_SUMMARY_LENGTH_DEFAULT;

	/** Default number of items in auto summaries. */
	public const SUMMARY_ITEMS = NUCLEN_SUMMARY_ITEMS_DEFAULT;
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
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Skip if protected (double-check)
		$meta_key = $workflow_type === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
		if ( get_post_meta( $post_id, $meta_key, true ) ) {
			return;
		}

		try {
			$post_data = array(
				array(
					'id'      => $post_id,
					'title'   => get_the_title( $post_id ),
					'content' => wp_strip_all_tags( $post->post_content ),
				),
			);

			$workflow = array(
				'type'                    => $workflow_type,
				'summary_format'          => 'paragraph',
				'summary_length'          => self::SUMMARY_LENGTH,
				'summary_number_of_items' => self::SUMMARY_ITEMS,
			);

			$generation_id = 'gen_' . uniqid( 'auto_', true );

			$data_to_send = array(
				'posts'         => $post_data,
				'workflow'      => $workflow,
				'generation_id' => $generation_id,
			);

			try {
					$this->remote_api->send_posts_to_generate( $data_to_send );
			} catch ( ApiException $e ) {
				\NuclearEngagement\Services\LoggingService::log(
					'Failed to start generation: ' . $e->getMessage()
				);
				\NuclearEngagement\Services\LoggingService::notify_admin( 'Auto-generation failed: ' . $e->getMessage() );
				$gens = get_option( 'nuclen_active_generations', array() );
				if ( isset( $gens[ $generation_id ] ) ) {
					unset( $gens[ $generation_id ] );
					if ( empty( $gens ) ) {
						delete_option( 'nuclen_active_generations' );
					} else {
						update_option( 'nuclen_active_generations', $gens, 'no' );
					}
				}
				return;
			}

			// Schedule the first poll with a slight random offset to avoid collisions
			$next_poll = time() + self::INITIAL_POLL_DELAY + mt_rand( 1, 5 );

			// Store the generation ID in options for the cron job
			$generations                   = get_option( 'nuclen_active_generations', array() );
			$generations[ $generation_id ] = array(
				'started_at'    => current_time( 'mysql' ),
				'post_ids'      => array( $post_id ),
				'next_poll'     => $next_poll,
				'attempt'       => 1,
				'workflow_type' => $workflow_type,
			);
			// Do not autoload active generation state
			update_option( 'nuclen_active_generations', $generations, 'no' );

			// Schedule the cron event
			$event_args = array( $generation_id, $workflow_type, $post_id, 1 );
			if ( ! wp_next_scheduled( 'nuclen_poll_generation', $event_args ) ) {
				wp_schedule_single_event( $next_poll, 'nuclen_poll_generation', $event_args );
			}
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				'Error in generate_single: ' . $e->getMessage()
			);
			\NuclearEngagement\Services\LoggingService::notify_admin( 'Auto-generation error: ' . $e->getMessage() );
		}
	}

	/**
	 * Poll for generation updates
	 *
	 * @param string $generation_id Generation ID
	 * @param string $workflow_type Type of workflow (quiz/summary)
	 * @param int    $post_id Post ID
	 * @param int    $attempt Current attempt number
	 */
	public function poll_generation( string $generation_id, string $workflow_type, int $post_id, int $attempt ): void {
		$this->poller->poll_generation( $generation_id, $workflow_type, $post_id, $attempt );
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
