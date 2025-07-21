<?php
/**
 * GenerationPoller.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/GenerationPoller.php
 *
 * Polls the remote API for generation results.
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Services\ApiException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GenerationPoller {
	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings_repository;

	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $remote_api;

	/**
	 * @var ContentStorageService
	 */
	private ContentStorageService $content_storage;

	public function __construct(
		SettingsRepository $settings_repository,
		RemoteApiService $remote_api,
		ContentStorageService $content_storage
	) {
		$this->settings_repository = $settings_repository;
		$this->remote_api          = $remote_api;
		$this->content_storage     = $content_storage;
	}

	/**
	 * Register WordPress hook for polling events.
	 */
	public function register_hooks(): void {
		add_action(
			'nuclen_poll_generation',
			array( $this, 'poll_generation' ),
			10,
			4 // generation_id, workflow_type, post_ids, attempt.
		);
	}

	/**
	 * Poll for generation updates.
	 *
	 * @param string $generation_id Generation ID
	 * @param string $workflow_type  Type of workflow (quiz/summary)
	 * @param array  $post_ids      List of post IDs in this batch
	 * @param int    $attempt       Current attempt number
	 */
	public function poll_generation( string $generation_id, string $workflow_type, array $post_ids, int $attempt ): void {
			$max_attempts = NUCLEN_MAX_POLL_ATTEMPTS;
			$retry_delay  = NUCLEN_POLL_RETRY_DELAY * $attempt;

		try {
			$connected      = $this->settings_repository->get( 'connected', false );
			$wp_app_created = $this->settings_repository->get( 'wp_app_pass_created', false );
			if ( ! $connected || ! $wp_app_created ) {
				return;
			}

			// fetch_updates() checks a short cache to avoid redundant requests.
			$data = $this->remote_api->fetch_updates( $generation_id );

			if ( ! empty( $data['results'] ) && is_array( $data['results'] ) ) {
					// Filter out summary statistics from results
					$post_results = array_filter( 
						$data['results'], 
						function( $key ) {
							// Only keep numeric post IDs, filter out summary keys
							return is_numeric( $key ) && ! in_array( $key, ['success_count', 'fail_count', 'processed_count'], true );
						},
						ARRAY_FILTER_USE_KEY
					);

					if ( ! empty( $post_results ) ) {
						$statuses = $this->content_storage->storeResults( $post_results, $workflow_type );
				if ( array_filter( $statuses, static fn( $s ) => $s !== true ) ) {
								\NuclearEngagement\Services\LoggingService::notify_admin(
									sprintf( 'Failed to store results for generation %s', $generation_id )
								);
				} else {
									\NuclearEngagement\Services\LoggingService::log(
										"Poll success for generation {$generation_id}"
									);

					// Mark generation as complete in centralized queue
					$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
					if ( $container->has( 'centralized_polling_queue' ) ) {
						$queue = $container->get( 'centralized_polling_queue' );
						$queue->mark_generation_complete( $generation_id );
					}
				}
						return;
					}
			}

			if ( isset( $data['success'] ) && $data['success'] === true ) {
								\NuclearEngagement\Services\LoggingService::log(
									"Still processing generation {$generation_id}, attempt {$attempt}/{$max_attempts}"
								);
			}
		} catch ( ApiException $e ) {
								\NuclearEngagement\Services\LoggingService::log(
									"Polling error for generation {$generation_id}: " . $e->getMessage()
								);
			if ( $attempt >= $max_attempts ) {
				return;
			}
		} catch ( \Throwable $e ) {
								\NuclearEngagement\Services\LoggingService::log(
									"Polling error for generation {$generation_id}: " . $e->getMessage()
								);
			if ( $attempt >= $max_attempts ) {
				return;
			}
		}

		if ( $attempt < $max_attempts ) {
			// Use centralized queue if available
			$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
			if ( $container->has( 'centralized_polling_queue' ) ) {
				$queue = $container->get( 'centralized_polling_queue' );
				// Re-add to queue with updated attempt count
				$priority = $workflow_type === 'quiz' ? 3 : 5; // Higher priority for quizzes
				$queue->add_to_queue( $generation_id, $workflow_type, $post_ids, $priority );
			} else {
				// Fallback to old method
				$event_args = array( $generation_id, $workflow_type, $post_ids, $attempt + 1 );
				$scheduled  = wp_schedule_single_event(
					time() + $retry_delay,
					'nuclen_poll_generation',
					$event_args
				);
				if ( $scheduled === false ) {
					\NuclearEngagement\Services\LoggingService::log(
						'Failed to schedule event nuclen_poll_generation for generation ' . $generation_id
					);
					\NuclearEngagement\Services\LoggingService::notify_admin(
						sprintf(
							__( 'Failed to schedule event nuclen_poll_generation for generation %s', 'nuclear-engagement' ),
							$generation_id
						)
					);
				}
			}
		} else {
			\NuclearEngagement\Services\LoggingService::log(
				"Polling aborted after {$max_attempts} attempts for generation {$generation_id}"
			);
		}
	}
}
