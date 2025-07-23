<?php
/**
 * GenerateController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Controller_Ajax
 */

declare(strict_types=1);
/**
 * File: admin/Controller/Ajax/GenerateController.php

 * Generate Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Requests\GenerateRequest;
use NuclearEngagement\Services\GenerationService;
use NuclearEngagement\Services\BulkGenerationTimeoutHandler;
use NuclearEngagement\Exceptions\ValidationException;
use NuclearEngagement\Exceptions\NuclenException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for content generation
 */
class GenerateController extends BaseController {
	/**
	 * @var GenerationService
	 */
	private GenerationService $service;

	/**
	 * Constructor
	 *
	 * @param GenerationService $service
	 */
	public function __construct( GenerationService $service ) {
		$this->service = $service;
	}

	/**
	 * Handle generation request
	 */
	public function handle(): void {
		// Set extended timeout for bulk generation.
		$original_timeout = BulkGenerationTimeoutHandler::set_extended_timeout();

		try {
			// Sanitized debug logging - only log non-sensitive keys.
			$safe_post_data = array();
			$safe_keys      = array( 'action', 'workflow', 'step', 'batch_size', 'total_items', 'priority', 'source' );
			foreach ( $safe_keys as $key ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens after logging
				if ( isset( $_POST[ $key ] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization happens in next line
					$safe_post_data[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				}
			}

			// Count selected posts.
			$post_count = 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens after logging
			if ( ! empty( $_POST['nuclen_selected_post_ids'] ) && is_array( $_POST['nuclen_selected_post_ids'] ) ) {
				$post_count = count( $_POST['nuclen_selected_post_ids'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens after logging
			} elseif ( ! empty( $_POST['payload'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization handled by stripslashes
				$payload_data = json_decode( stripslashes( wp_unslash( $_POST['payload'] ) ), true );
				if ( isset( $payload_data['postIds'] ) && is_array( $payload_data['postIds'] ) ) {
					$post_count = count( $payload_data['postIds'] );
				}
			}
			$safe_post_data['post_count'] = $post_count;

			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[INFO] Generation request | Data: %s', wp_json_encode( $safe_post_data ) )
			);

			if ( ! $this->verify_request( 'nuclen_admin_ajax_nonce' ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					'[ERROR] Security check failed | Nonce verification failed'
				);
				return;
			}

			// Check if we have the required data (either in payload or directly in POST).
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified right before this check
			if ( empty( $_POST['payload'] ) && empty( $_POST['nuclen_selected_post_ids'] ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					'[ERROR] Invalid request | Missing required data (payload or post_ids)'
				);
				$this->send_error(
					__( 'Missing required data in request', 'nuclear-engagement' ),
					400
				);
				return;
			}

			// Parse request.
			$request = GenerateRequest::from_post( $_POST );

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[INFO] Request parsed | GenID: %s | Posts: %d | Workflow: %s | Priority: %s',
					$request->generationId,
					count( $request->postIds ),
					$request->workflowType,
					$request->priority ?? 'normal'
				)
			);

			// Process generation.
			$response = $this->service->generateContent( $request );

			// Log the response
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[%s] Generation response | GenID: %s | Message: %s | Posts: %d | Batches: %d',
					$response->success ? 'SUCCESS' : 'ERROR',
					$response->generationId ?? 'none',
					$response->message ?? 'none',
					$response->totalPosts ?? 0,
					$response->totalBatches ?? 0
				)
			);

			// Return response.
			wp_send_json_success( $response->toArray() );

		} catch ( ValidationException $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[ERROR] Validation failed | %s', $e->getMessage() )
			);
			if ( $e->get_context() ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[DEBUG] Validation context | %s', wp_json_encode( $e->get_context() ) )
				);
			}
			$this->send_error( $e->get_user_message(), 400 );
		} catch ( NuclenException $e ) {
			// Handle our custom exceptions
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[ERROR] %s | Code: %d',
					$e->getMessage(),
					$e->getCode()
				)
			);
			\NuclearEngagement\Services\LoggingService::log_exception( $e );

			// Determine HTTP status code based on exception type
			$status_code = $e->getCode() ?: 500;
			if ( $status_code < 400 || $status_code > 599 ) {
				$status_code = 500;
			}

			$this->send_error( $e->getUserMessage(), $status_code );
		} catch ( \InvalidArgumentException $e ) {
			// Backwards compatibility for any remaining InvalidArgumentException
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[ERROR] Invalid argument | %s', $e->getMessage() )
			);
			$this->send_error( $e->getMessage(), 400 );
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[CRITICAL] Unexpected error | %s', $e->getMessage() )
			);
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			$this->send_error(
				__( 'An unexpected error occurred. Please check your error logs.', 'nuclear-engagement' ),
				500
			);
		} finally {
			// Always restore original timeout settings
			BulkGenerationTimeoutHandler::restore_timeout( $original_timeout );
		}
	}
}
