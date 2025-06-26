<?php
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

			if ( ! $this->verifyRequest( 'nuclen_admin_ajax_nonce' ) ) {
				return;
			}

			if ( empty( $_POST['payload'] ) ) {
				$this->sendError(
					__( 'Missing payload in request', 'nuclear-engagement' ),
					400
				);
				return;
			}

			// Parse request
			$request = GenerateRequest::fromPost( $_POST );

			// Process generation
			$response = $this->service->generateContent( $request );

			// Return response
			wp_send_json_success( $response->toArray() );

		} catch ( \InvalidArgumentException $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				'Nuclear Engagement validation error: ' . $e->getMessage()
			);
			$this->sendError( $e->getMessage(), 400 );
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			$this->sendError(
				__( 'An unexpected error occurred. Please check your error logs.', 'nuclear-engagement' ),
				500
			);
		}
	}
}
