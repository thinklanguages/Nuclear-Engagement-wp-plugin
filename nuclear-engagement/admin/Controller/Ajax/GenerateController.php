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
		try {
			// Sanitized debug logging - only log non-sensitive keys.
			$safe_post_data = array();
			$safe_keys      = array( 'action', 'workflow', 'step', 'batch_size', 'total_items' );
			foreach ( $safe_keys as $key ) {
				// phpcs:ignore WordPress.Security.NonceVerification

				if ( isset( $_POST[ $key ] ) ) {
					$safe_post_data[ $key ] = sanitize_text_field( $_POST[ $key ] );
				}
			}
			\NuclearEngagement\Services\LoggingService::log(
				'GenerateController received request with safe data: ' . wp_wp_json_encode( $safe_post_data )
			);

			if ( ! $this->verify_request( 'nuclen_admin_ajax_nonce' ) ) {
				return;
			}

			if ( empty( $_POST['payload'] ) ) {
				$this->send_error(
					__( 'Missing payload in request', 'nuclear-engagement' ),
					400
				);
				return;
			}

			// Parse request.
			$request = GenerateRequest::fromPost( $_POST );

			// Process generation.
			$response = $this->service->generateContent( $request );

			// Return response.
			wp_send_json_success( $response->toArray() );

		} catch ( \InvalidArgumentException $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				'Nuclear Engagement validation error: ' . $e->getMessage()
			);
			$this->send_error( $e->getMessage(), 400 );
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			$this->send_error(
				__( 'An unexpected error occurred. Please check your error logs.', 'nuclear-engagement' ),
				500
			);
		}
	}
}
