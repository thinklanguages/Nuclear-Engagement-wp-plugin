<?php
/**
 * PublishGenerationHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * PublishGenerationHandler service.
 *
 * Handles auto-generation triggers on post publish.
 *
 * @package NuclearEngagement\Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Modules\Quiz\Quiz_Service;

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

/**
 * Handles generation events when a post is published.
 */
class PublishGenerationHandler {
	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings_repository;

		/**
		 * Constructor.
		 *
		 * @param SettingsRepository $settings_repository Repository of plugin settings.
		 */
	public function __construct( SettingsRepository $settings_repository ) {
			$this->settings_repository = $settings_repository;
	}

	/**
	 * Register the publish transition hook.
	 */
	public function register_hooks(): void {
		add_action( 'transition_post_status', array( $this, 'handle_post_publish' ), 10, 3 );

		// Also register for save_post as a backup
		add_action( 'save_post', array( $this, 'handle_save_post' ), 99, 3 );
	}

	/**
	 * Handle save_post hook as backup
	 */
	public function handle_save_post( $post_id, $post, $update ): void {
		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'save_post hook fired: post_id=%d, status=%s, update=%s',
				$post_id,
				$post->post_status,
				$update ? 'true' : 'false'
			)
		);

		// Only process if post is being published
		if ( $post->post_status === 'publish' ) {
			$this->handle_post_publish( 'publish', $update ? 'unknown' : 'new', $post );
		}
	}

		/**
		 * Handle post publish transition.
		 *
		 * @param string   $new_status New post status.
		 * @param string   $old_status Old post status.
		 * @param \WP_Post $post    Post object.
		 */
	public function handle_post_publish( $new_status, $old_status, $post ): void {
		try {
			// Only log when actually transitioning to publish
			if ( 'publish' !== $old_status && 'publish' === $new_status ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'Post published: post_id=%d, post_type=%s',
						$post->ID,
						$post->post_type
					)
				);
			}

			if ( 'publish' === $old_status || 'publish' !== $new_status ) {
					return;
			}

				// Prevent unauthorized users from triggering generation.
			// Check if user has permission to edit this specific post
			if ( ! wp_doing_cron() && ! current_user_can( 'edit_post', $post->ID ) ) {
						\NuclearEngagement\Services\LoggingService::log(
							sprintf(
								'Skipping auto-generation: User not authorized (can_edit=%s, doing_cron=%s)',
								current_user_can( 'edit_post', $post->ID ) ? 'yes' : 'no',
								wp_doing_cron() ? 'yes' : 'no'
							)
						);
						return;
			}

					$allowed_post_types = $this->settings_repository->get( 'generation_post_types', array( 'post' ) );

			if ( ! in_array( $post->post_type, (array) $allowed_post_types, true ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( 'Skipping auto-generation: Post type %s not in allowed types', $post->post_type )
				);
				return;
			}

					$quiz_setting    = $this->settings_repository->get( 'auto_generate_quiz_on_publish', false );
					$summary_setting = $this->settings_repository->get( 'auto_generate_summary_on_publish', false );

					// Settings are stored as "1" string when enabled, need proper conversion
					$gen_quiz    = ( $quiz_setting === '1' || $quiz_setting === 1 || $quiz_setting === true );
					$gen_summary = ( $summary_setting === '1' || $summary_setting === 1 || $summary_setting === true );

			if ( ! $gen_quiz && ! $gen_summary ) {
				return;
			}
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Error in handle_post_publish (part 1): %s', $e->getMessage() )
			);
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			return;
		}

		try {
			if ( $gen_quiz ) {
				$protected     = get_post_meta( $post->ID, 'nuclen_quiz_protected', true );
				$existing_quiz = get_post_meta( $post->ID, Quiz_Service::META_KEY, true );

				// Check if quiz has actual content (not just empty structure)
				$has_quiz_content = false;
				if ( is_array( $existing_quiz ) && isset( $existing_quiz['questions'] ) && is_array( $existing_quiz['questions'] ) ) {
					foreach ( $existing_quiz['questions'] as $question ) {
						if ( ! empty( $question['question'] ) ) {
							$has_quiz_content = true;
							break;
						}
					}
				}

				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'Quiz generation check: post_id=%d, protected=%s, has_existing=%s',
						$post->ID,
						$protected ? 'yes' : 'no',
						$has_quiz_content ? 'yes' : 'no'
					)
				);

				// Skip if protected or if quiz content already exists
				if ( ! $protected && ! $has_quiz_content ) {
					$args = array( $post->ID, 'quiz' );
					if ( ! wp_next_scheduled( AutoGenerationService::START_HOOK, $args ) ) {
						$scheduled = wp_schedule_single_event( time() + 2, AutoGenerationService::START_HOOK, $args );
						if ( $scheduled === false ) {
							\NuclearEngagement\Services\LoggingService::log(
								'Failed to schedule quiz generation for post ' . $post->ID
							);
						} else {
							\NuclearEngagement\Services\LoggingService::log(
								sprintf( 'Scheduled quiz generation for post %d', $post->ID )
							);
						}
					}
				}
			}

			if ( $gen_summary ) {
				$protected        = get_post_meta( $post->ID, Summary_Service::PROTECTED_KEY, true );
				$existing_summary = get_post_meta( $post->ID, Summary_Service::META_KEY, true );

				// Check if summary has actual content (not just empty structure)
				$has_summary_content = false;
				if ( is_array( $existing_summary ) && isset( $existing_summary['summary'] ) ) {
					$has_summary_content = ! empty( trim( $existing_summary['summary'] ) );
				}

				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'Summary generation check: post_id=%d, protected=%s, has_existing=%s',
						$post->ID,
						$protected ? 'yes' : 'no',
						$has_summary_content ? 'yes' : 'no'
					)
				);

				// Skip if protected or if summary content already exists
				if ( ! $protected && ! $has_summary_content ) {
					$args = array( $post->ID, 'summary' );
					if ( ! wp_next_scheduled( AutoGenerationService::START_HOOK, $args ) ) {
						$scheduled = wp_schedule_single_event( time() + 2, AutoGenerationService::START_HOOK, $args );
						if ( $scheduled === false ) {
							\NuclearEngagement\Services\LoggingService::log(
								'Failed to schedule summary generation for post ' . $post->ID
							);
						} else {
							\NuclearEngagement\Services\LoggingService::log(
								sprintf( 'Scheduled summary generation for post %d', $post->ID )
							);
						}
					}
				}
			}
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Error in handle_post_publish (part 2): %s', $e->getMessage() )
			);
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
		}
	}
}
