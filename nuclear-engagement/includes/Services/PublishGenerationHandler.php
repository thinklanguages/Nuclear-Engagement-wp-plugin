<?php
declare(strict_types=1);
/**
 * File: includes/Services/PublishGenerationHandler.php
 *
 * Handles auto-generation triggers on post publish.
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PublishGenerationHandler {
	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings_repository;

	public function __construct( SettingsRepository $settings_repository ) {
		$this->settings_repository = $settings_repository;
	}

	/**
	 * Register the publish transition hook.
	 */
	public function register_hooks(): void {
		add_action( 'transition_post_status', array( $this, 'handle_post_publish' ), 10, 3 );
	}

	/**
	 * Handle post publish transition.
	 *
	 * @param string   $new_status New post status
	 * @param string   $old_status Old post status
	 * @param \WP_Post $post       Post object
	 */
	public function handle_post_publish( $new_status, $old_status, $post ): void {
		if ( $old_status === 'publish' || $new_status !== 'publish' ) {
			return;
		}

		// Prevent unauthorized users from triggering generation
		if ( ! wp_doing_cron() && ! current_user_can( 'publish_post', $post->ID ) ) {
			return;
		}

		$allowed_post_types = $this->settings_repository->get( 'generation_post_types', array( 'post' ) );
		if ( ! in_array( $post->post_type, (array) $allowed_post_types, true ) ) {
			return;
		}

		$gen_quiz    = (bool) $this->settings_repository->get( 'auto_generate_quiz_on_publish', false );
		$gen_summary = (bool) $this->settings_repository->get( 'auto_generate_summary_on_publish', false );

		if ( ! $gen_quiz && ! $gen_summary ) {
			return;
		}

		if ( $gen_quiz ) {
			$protected = get_post_meta( $post->ID, 'nuclen_quiz_protected', true );
			if ( ! $protected ) {
				$args = array( $post->ID, 'quiz' );
				if ( ! wp_next_scheduled( AutoGenerationService::START_HOOK, $args ) ) {
					wp_schedule_single_event( time(), AutoGenerationService::START_HOOK, $args );
				}
			}
		}

		if ( $gen_summary ) {
			$protected = get_post_meta( $post->ID, 'nuclen_summary_protected', true );
			if ( ! $protected ) {
				$args = array( $post->ID, 'summary' );
				if ( ! wp_next_scheduled( AutoGenerationService::START_HOOK, $args ) ) {
					wp_schedule_single_event( time(), AutoGenerationService::START_HOOK, $args );
				}
			}
		}
	}
}
