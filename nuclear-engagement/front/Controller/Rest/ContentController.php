<?php
/**
 * ContentController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Front_Controller_Rest
 */

declare(strict_types=1);
/**
	* File: front/Controller/Rest/ContentController.php
	*
	* Content Controller
	*
	* @package NuclearEngagement\Front\Controller\Rest
	*/

namespace NuclearEngagement\Front\Controller\Rest;

use NuclearEngagement\Requests\ContentRequest;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Utils\Utils;
use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Security\ApiUserManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for receiving content.
 *
 * Accepts authentication via the custom header `X-WP-App-Password`
 * using the plugin-generated password, or falls back to a standard
 * admin nonce check when present.
 */
class ContentController {
	/**
	 * @var ContentStorageService
	 */
	private ContentStorageService $storage;

		/**
		 * @var SettingsRepository
		 */
	private SettingsRepository $settings;

	/**
	 * @var Utils
	 */
	private Utils $utils;

	/**
	 * Constructor
	 *
	 * @param ContentStorageService $storage
	 */
	public function __construct( ContentStorageService $storage, SettingsRepository $settings ) {
			$this->storage  = $storage;
			$this->settings = $settings;
			$this->utils    = new Utils();
	}

	/**
	 * Handle content receive request
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ) {
		try {
				// Authentication handled in permissions().

				$data = $request->get_json_params();

			if ( ! is_array( $data ) ) {
				\NuclearEngagement\Services\LoggingService::log( 'Invalid JSON received in REST request' );
				return new \WP_Error(
					'ne_invalid_json',
					__( 'Invalid JSON', 'nuclear-engagement' ),
					array( 'status' => 400 )
				);
			}

				\NuclearEngagement\Services\LoggingService::log(
					'Received content via REST: ' . wp_json_encode(
						array(
							'workflow'      => $data['workflow'] ?? 'unknown',
							'results_count' => is_array( $data['results'] ?? null ) ? count( $data['results'] ) : 0,
						)
					)
				);

			$contentRequest = ContentRequest::fromJson( $data );

						// Store the results.
						$statuses = $this->storage->storeResults( $contentRequest->results, $contentRequest->workflow );
			if ( array_filter( $statuses, static fn( $s ) => $s !== true ) ) {
					return new \WP_Error( 'ne_store_failed', __( 'Failed to store content', 'nuclear-engagement' ), array( 'status' => 500 ) );
			}

			// Check if this is from a single post generation and update task status
			if ( ! empty( $data['generation_id'] ) && count( $contentRequest->results ) === 1 ) {
				$generation_id = sanitize_text_field( $data['generation_id'] );
				
				// Check if this is likely a single post generation task
				$post_ids = array_keys( $contentRequest->results );
				if ( count( $post_ids ) === 1 ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'[ContentController] Single post generation completed - GenID: %s | PostID: %s | Workflow: %s',
							$generation_id,
							$post_ids[0],
							$contentRequest->workflow
						)
					);

					// Get container for services
					$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
					
					// Get current task data first
					$task_data = \NuclearEngagement\Services\TaskTransientManager::get_task_transient( $generation_id );
					if ( $task_data ) {
						// Validate current state
						if ( in_array( $task_data['status'], array( 'completed', 'failed', 'cancelled' ), true ) ) {
							\NuclearEngagement\Services\LoggingService::log(
								sprintf(
									'[ContentController] Task %s already in terminal state: %s, skipping update',
									$generation_id,
									$task_data['status']
								),
								'warning'
							);
							// Continue with normal response flow
						} else {
							// For single post, we need to update the batch data first
							if ( isset( $task_data['batch_jobs'] ) && ! empty( $task_data['batch_jobs'][0]['batch_id'] ) ) {
								$batch_id = $task_data['batch_jobs'][0]['batch_id'];
								$batch_data = \NuclearEngagement\Services\TaskTransientManager::get_batch_transient( $batch_id );
								
								if ( $batch_data ) {
									// Validate batch state
									if ( in_array( $batch_data['status'], array( 'completed', 'failed', 'cancelled' ), true ) ) {
										\NuclearEngagement\Services\LoggingService::log(
											sprintf(
												'[ContentController] Batch %s already in terminal state: %s',
												$batch_id,
												$batch_data['status']
											),
											'warning'
										);
									} else {
										// Update batch with comprehensive data
										$batch_data['status'] = 'completed';
										$batch_data['success_count'] = 1;
										$batch_data['fail_count'] = 0;
										$batch_data['completed_at'] = time();
										$batch_data['updated_at'] = time();
										
										// Store results summary
										$batch_data['results'] = array(
											'success_count' => 1,
											'fail_count' => 0,
											'post_ids' => $post_ids,
											'workflow' => $contentRequest->workflow,
										);
										
										// Save updated batch data
										\NuclearEngagement\Services\TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );
										
										\NuclearEngagement\Services\LoggingService::log(
											sprintf(
												'[ContentController] Updated batch data - BatchID: %s | Success: 1 | UpdatedAt: %s',
												$batch_id,
												date( 'Y-m-d H:i:s', $batch_data['updated_at'] )
											)
										);
									}
								} else {
									\NuclearEngagement\Services\LoggingService::log(
										sprintf(
											'[ContentController] Warning: Batch data not found for batch_id: %s',
											$batch_id
										),
										'warning'
									);
								}
							}
							
							// Update task data with completion info
							$task_data['status'] = 'completed';
							$task_data['completed_at'] = time();
							$task_data['updated_at'] = time();
							$task_data['success_count'] = 1;
							$task_data['fail_count'] = 0;
							$task_data['processed_count'] = 1;
							$task_data['completed_batches'] = 1;
							
							// Calculate duration if started_at exists
							if ( isset( $task_data['started_at'] ) ) {
								$task_data['duration'] = time() - $task_data['started_at'];
							}
							
							// For single post generation, ensure we have the right structure
							if ( isset( $task_data['post_ids'] ) && is_array( $task_data['post_ids'] ) ) {
								$task_data['total_posts'] = count( $task_data['post_ids'] );
							} else {
								$task_data['total_posts'] = 1;
								$task_data['post_ids'] = $post_ids;
							}
							
							// Save updated task data
							\NuclearEngagement\Services\TaskTransientManager::set_task_transient( $generation_id, $task_data, DAY_IN_SECONDS );
							
							// Update task index with proper data
							if ( $container->has( 'task_index_service' ) ) {
								$task_index_service = $container->get( 'task_index_service' );
								$task_index_service->update_task( $generation_id, $task_data );
							}
							
							// Remove from polling queue
							if ( $container->has( 'centralized_polling_queue' ) ) {
								$queue = $container->get( 'centralized_polling_queue' );
								$queue->mark_generation_complete( $generation_id );
							}
							
							// Trigger completion action for other systems
							do_action( 'nuclen_task_completed', $generation_id );
						}
					} else {
						// If no task data exists, create minimal data with proper structure
						$task_data = array(
							'status' => 'completed',
							'created_at' => time(),
							'started_at' => time(),
							'completed_at' => time(),
							'updated_at' => time(),
							'success_count' => 1,
							'fail_count' => 0,
							'processed_count' => 1,
							'total_posts' => 1,
							'post_ids' => $post_ids,
							'workflow_type' => $contentRequest->workflow,
							'completed_batches' => 1,
							'total_batches' => 1,
						);
						
						\NuclearEngagement\Services\TaskTransientManager::set_task_transient( $generation_id, $task_data, DAY_IN_SECONDS );
						
						// Update task index
						if ( $container->has( 'task_index_service' ) ) {
							$task_index_service = $container->get( 'task_index_service' );
							$task_index_service->update_task( $generation_id, $task_data );
						}
					}
				}
			}

			// Get date from first stored item.
			reset( $contentRequest->results );
			$firstPostId          = key( $contentRequest->results );
						$meta_key = $contentRequest->workflow === 'quiz' ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			$stored               = get_post_meta( $firstPostId, $meta_key, true );
			$date                 = is_array( $stored ) && ! empty( $stored['date'] ) ? $stored['date'] : '';

			$message = sprintf(
				__( '%s data received and stored successfully', 'nuclear-engagement' ),
				ucfirst( $contentRequest->workflow )
			);

			return new \WP_REST_Response(
				array(
					'message'   => $message,
					'finalDate' => $date,
				),
				200
			);

		} catch ( \InvalidArgumentException $e ) {
					\NuclearEngagement\Services\LoggingService::log_exception( $e );
					// Use generic error message to avoid exposing internal details
					return new \WP_Error( 'ne_invalid', __( 'Invalid request data provided', 'nuclear-engagement' ), array( 'status' => 400 ) );
		} catch ( \Throwable $e ) {
				\NuclearEngagement\Services\LoggingService::log_exception( $e );
				return new \WP_Error( 'ne_error', __( 'An error occurred', 'nuclear-engagement' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Check permissions with secure API user management.
	 *
	 * Security fix: Use dedicated service account instead of admin impersonation.
	 * This implements proper capability-based authorization and audit trails.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return bool True if authorized, false otherwise.
	 */
	public function permissions( \WP_REST_Request $request ): bool {
		$header_pass = sanitize_text_field( (string) $request->get_header( 'X-WP-App-Password' ) );
		$stored_pass = $this->settings->get_string( 'plugin_password', '' );

		// API Password Authentication.
		if ( ! empty( $stored_pass ) && hash_equals( $stored_pass, $header_pass ) ) {
			// Security fix: Use dedicated API service account instead of admin impersonation.
			if ( 0 === get_current_user_id() ) {
				$service_user = ApiUserManager::get_service_account();

				if ( ! $service_user ) {
					ApiUserManager::log_api_operation(
						'authentication_failed',
						array(
							'reason' => 'service_account_not_found',
							'ip'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
						)
					);
					return false;
				}

				// Set current user to the dedicated API service account.
				wp_set_current_user( $service_user->ID );

				// Log successful API authentication.
				ApiUserManager::log_api_operation(
					'api_authentication_success',
					array(
						'service_user_id' => $service_user->ID,
						'endpoint'        => $request->get_route(),
					)
				);
			}

			// Verify the current user has required API capabilities.
			if ( ! current_user_can( 'manage_nuclear_engagement_content' ) ) {
				ApiUserManager::log_api_operation(
					'authorization_failed',
					array(
						'reason'       => 'insufficient_capabilities',
						'user_id'      => get_current_user_id(),
						'required_cap' => 'manage_nuclear_engagement_content',
					)
				);
				return false;
			}

			return true;
		}

		// Nonce-based Authentication (for admin users).
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( wp_verify_nonce( $nonce, 'wp_rest' ) && current_user_can( 'manage_options' ) ) {
			ApiUserManager::log_api_operation(
				'nonce_authentication_success',
				array(
					'user_id'  => get_current_user_id(),
					'endpoint' => $request->get_route(),
				)
			);
			return true;
		}

		// Log failed authentication attempt.
		ApiUserManager::log_api_operation(
			'authentication_failed',
			array(
				'reason'       => 'invalid_credentials',
				'has_password' => ! empty( $header_pass ),
				'has_nonce'    => ! empty( $nonce ),
				'ip'           => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
			)
		);

		return false;
	}
}
