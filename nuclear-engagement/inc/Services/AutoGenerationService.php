<?php
declare(strict_types=1);
/**
 * File: includes/Services/AutoGenerationService.php
 *
 * Handles auto-generation of quizzes and summaries on post publish.
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\AutoGenerationQueue;
use NuclearEngagement\Services\AutoGenerationScheduler;
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



	/**
	 * @var SettingsRepository
	 */
	private $settings_repository;


	private AutoGenerationQueue $queue;

	private AutoGenerationScheduler $scheduler;


	/**
	 * @var PublishGenerationHandler
	 */
	private PublishGenerationHandler $publish_handler;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository      $settings_repository
	 * @param AutoGenerationQueue     $queue
	 * @param AutoGenerationScheduler $scheduler
	 */
	public function __construct(
		SettingsRepository $settings_repository,
		AutoGenerationQueue $queue,
		AutoGenerationScheduler $scheduler,
		PublishGenerationHandler $publish_handler
	) {
				$this->settings_repository = $settings_repository;
				$this->queue               = $queue;
				$this->scheduler           = $scheduler;
				$this->publish_handler     = $publish_handler;
	}

	/**
	 * Register WordPress hooks
	 */
	public function register_hooks(): void {
			$this->scheduler->register_hooks();

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
		$this->queue->queue_post( $post_id, $workflow_type );
	}

	/**
	 * Process queued posts in batches.
	 */
	public function process_queue(): void {
		$this->queue->process_queue();
	}


	/**
	 * Poll for generation updates
	 *
	 * @param string $generation_id Generation ID
	 * @param string $workflow_type Type of workflow (quiz/summary)
	 * @param array  $post_ids List of post IDs
	 * @param int    $attempt  Current attempt number
	 */
	public function poll_generation( string $generation_id, string $workflow_type, array $post_ids, int $attempt ): void {
		$this->scheduler->poll_generation( $generation_id, $workflow_type, $post_ids, $attempt );
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
