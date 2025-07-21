<?php
/**
 * AutoGenerationService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/AutoGenerationService.php
 *
 * Handles auto-generation of quizzes and summaries on post publish.
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\AutoGenerationScheduler;
use NuclearEngagement\Services\GenerationPoller;
use NuclearEngagement\Services\PublishGenerationHandler;
use NuclearEngagement\Services\GenerationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoGenerationService {
		/** Cron hook used to start a generation task. */
	public const START_HOOK = 'nuclen_start_generation';

	/** @var bool Track if hooks are already registered */
	private static $hooks_registered = false;

	/**
	 * @var SettingsRepository
	 */
	private $settings_repository;


	/**
	 * @var GenerationService
	 */
	private GenerationService $generation_service;

	private AutoGenerationScheduler $scheduler;

	/**
	 * @var PublishGenerationHandler
	 */
	private PublishGenerationHandler $publish_handler;

	/**
	 * @var int Maximum poll attempts
	 */
	private int $max_poll_attempts = NUCLEN_MAX_POLL_ATTEMPTS;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository       $settings_repository
	 * @param GenerationService        $generation_service
	 * @param AutoGenerationScheduler  $scheduler
	 * @param PublishGenerationHandler $publish_handler
	 */
	public function __construct(
		SettingsRepository $settings_repository,
		GenerationService $generation_service,
		AutoGenerationScheduler $scheduler,
		PublishGenerationHandler $publish_handler
	) {
				$this->settings_repository = $settings_repository;
				$this->generation_service  = $generation_service;
				$this->scheduler           = $scheduler;
				$this->publish_handler     = $publish_handler;
	}

	/**
	 * Register WordPress hooks
	 */
	public function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

			$this->scheduler->register_hooks();

			add_action(
				self::START_HOOK,
				array( $this, 'run_generation' ),
				10,
				2 // post_id, workflow_type.
			);

		$this->publish_handler->register_hooks();

		self::$hooks_registered = true;
	}

	/**
	 * Handle post publish transition
	 *
	 * @param string   $new_status New post status
	 * @param string   $old_status Old post status
	 * @param \WP_Post $post Post object
	 */
	public function handle_post_publish( $new_status, $old_status, $post ): void {
		\NuclearEngagement\Services\LoggingService::debug(
			sprintf(
				'Post status transition - PostID: %d | Status: %s->%s',
				$post->ID,
				$old_status,
				$new_status
			)
		);
		$this->publish_handler->handle_post_publish( $new_status, $old_status, $post );
	}

	/**
	 * Generate content for a single post
	 *
	 * @param int    $post_id Post ID
	 * @param string $workflow_type Type of content to generate (quiz/summary)
	 */
	public function generate_single( int $post_id, string $workflow_type ): void {
		\NuclearEngagement\Services\LoggingService::debug(
			sprintf(
				'Starting single auto-generation - PostID: %d | Workflow: %s',
				$post_id,
				$workflow_type
			)
		);

		try {
			// Use the unified generation service with low priority for auto-generation
			$generation_id = $this->generation_service->queueAutoGeneration( array( $post_id ), $workflow_type );

			\NuclearEngagement\Services\LoggingService::debug(
				sprintf(
					'Auto-generation queued successfully - GenID: %s | PostID: %d | Workflow: %s',
					$generation_id,
					$post_id,
					$workflow_type
				)
			);
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[ERROR] Failed to queue auto-generation | PostID: %d | Error: %s',
					$post_id,
					$e->getMessage()
				),
				'error'
			);
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			\NuclearEngagement\Services\LoggingService::notify_admin(
				sprintf( 'Failed to queue auto-generation for post %d: %s', $post_id, $e->getMessage() )
			);
		}
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
		// Polling generation status
		$this->scheduler->poll_generation( $generation_id, $workflow_type, $post_ids, $attempt );
	}

	/**
	 * Cron callback to start a generation task for a post.
	 *
	 * @param int    $post_id       Post ID
	 * @param string $workflow_type Type of content to generate
	 */
	public function run_generation( int $post_id, string $workflow_type ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[AutoGenerationService::run_generation] Cron hook triggered - Post ID: %d, Workflow: %s',
				$post_id,
				$workflow_type
			)
		);
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
