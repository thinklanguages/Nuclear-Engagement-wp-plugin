<?php
declare(strict_types=1);
/**
 * File: admin/trait-admin-autogenerate.php
 *
 * Auto-generation on publish - now uses services.
 *
 * Host class must implement `nuclen_get_settings_repository()` and
 * protected `get_container(): \NuclearEngagement\Container`.
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_AutoGenerate {

	/*
	──────────────────────────────────────────────────────────
		Register the WP-Cron action (called from Admin::__construct())
	──────────────────────────────────────────────────────────*/
	public function nuclen_register_autogen_cron_hook() {
		add_action(
			'nuclen_poll_generation',
			array( $this, 'nuclen_cron_poll_generation' ),
			10,
			4 // generation_id, workflow_type, post_id, attempt
		);
	}

	/*
	──────────────────────────────────────────────────────────
		Hook: post transitions to "publish"
	──────────────────────────────────────────────────────────*/
	public function nuclen_auto_generate_on_publish( $new_status, $old_status, $post ) {
		// Only when we enter publish
		if ( $old_status === 'publish' || $new_status !== 'publish' ) {
			return;
		}

		// Get settings from repository
		$settings_repo      = $this->nuclen_get_settings_repository();
		$allowed_post_types = $settings_repo->get( 'generation_post_types', array( 'post' ) );
		if ( ! in_array( $post->post_type, (array) $allowed_post_types, true ) ) {
			return;
		}

		$gen_quiz    = (bool) $settings_repo->get( 'auto_generate_quiz_on_publish', false );
		$gen_summary = (bool) $settings_repo->get( 'auto_generate_summary_on_publish', false );
		if ( ! $gen_quiz && ! $gen_summary ) {
			return;
		}

		// Auto-generate quiz
		if ( $gen_quiz ) {
			// Skip if quiz is protected
			$protected = get_post_meta( $post->ID, 'nuclen_quiz_protected', true );
			if ( ! $protected ) {
				$this->nuclen_generate_single( $post->ID, 'quiz' );
			}
		}

		// Auto-generate summary
		if ( $gen_summary ) {
			// Skip if summary is protected
			$protected = get_post_meta( $post->ID, 'nuclen_summary_protected', true );
			if ( ! $protected ) {
				$this->nuclen_generate_single( $post->ID, 'summary' );
			}
		}
	}

	/*
	──────────────────────────────────────────────────────────
		Send post to SaaS & schedule polling - now uses service
	──────────────────────────────────────────────────────────*/
	private function nuclen_generate_single( $post_id, $workflow_type ) {
		try {
			$container = $this->get_container();
			$service   = $container->get( 'generation_service' );
			$service->generateSingle( $post_id, $workflow_type );
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log( 'Auto-generation error: ' . $e->getMessage() );
		}
	}

	/*
	──────────────────────────────────────────────────────────
		Cron callback: poll SaaS /updates - now uses services
	──────────────────────────────────────────────────────────*/
	public function nuclen_cron_poll_generation( $generation_id, $workflow_type, $post_id, $attempt ) {
		$max_attempts = \NuclearEngagement\Services\AutoGenerationService::MAX_ATTEMPTS;
		$retry_delay  = \NuclearEngagement\Services\AutoGenerationService::RETRY_DELAY; // 1 minute between retries

		try {
			// Check if auto-generation is enabled for this post type.
			$settings_repo       = $this->nuclen_get_settings_repository();
			$connected           = $settings_repo->get( 'connected', false );
			$wp_app_pass_created = $settings_repo->get( 'wp_app_pass_created', false );
			if ( ! $connected || ! $wp_app_pass_created ) {
				return;
			}

			$container = $this->get_container();
			$api       = $container->get( 'remote_api' );
			$storage   = $container->get( 'content_storage' );

						$data = $api->fetch_updates( $generation_id );

			// Check if we have results
			if ( ! empty( $data['results'] ) && is_array( $data['results'] ) ) {
				$storage->storeResults( $data['results'], $workflow_type );
				\NuclearEngagement\Services\LoggingService::log(
					"Poll success for post {$post_id} ({$workflow_type}), generation {$generation_id}"
				);
				return;
			}

			// Check if still processing
			if ( isset( $data['success'] ) && $data['success'] === true ) {
				// Still processing, continue polling
				\NuclearEngagement\Services\LoggingService::log(
					"Still processing post {$post_id} ({$workflow_type}), attempt {$attempt}/{$max_attempts}"
				);
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				"Polling error for post {$post_id} ({$workflow_type}): " . $e->getMessage()
			);
		}

		// Schedule next poll if not at max attempts
                if ( $attempt < $max_attempts ) {
                        $scheduled = wp_schedule_single_event(
                                time() + $retry_delay,
                                'nuclen_poll_generation',
                                array( $generation_id, $workflow_type, $post_id, $attempt + 1 )
                        );
                        if ( false === $scheduled ) {
                                \NuclearEngagement\Services\LoggingService::log(
                                        'Failed to schedule event nuclen_poll_generation for generation ' . $generation_id
                                );
                        }
                } else {
			\NuclearEngagement\Services\LoggingService::log(
				"Polling aborted after {$max_attempts} attempts for post {$post_id} ({$workflow_type})"
			);
		}
	}
}
