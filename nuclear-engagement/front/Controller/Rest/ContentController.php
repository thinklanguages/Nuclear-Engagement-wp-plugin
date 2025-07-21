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
