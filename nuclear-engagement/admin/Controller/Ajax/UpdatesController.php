<?php
/**
 * UpdatesController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Controller_Ajax
 */

declare(strict_types=1);
/**
 * File: admin/Controller/Ajax/UpdatesController.php
 *
 * Updates Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Requests\UpdatesRequest;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Responses\UpdatesResponse;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Utils\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for polling updates
 */
class UpdatesController extends BaseController {

	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $api;

	/**
	 * @var ContentStorageService
	 */
	private ContentStorageService $storage;

	/**
	 * @var Utils
	 */
	private Utils $utils;

	/**
	 * Constructor.
	 *
	 * @param RemoteApiService      $api
	 * @param ContentStorageService $storage
	 */
	public function __construct( RemoteApiService $api, ContentStorageService $storage ) {
		$this->api     = $api;
		$this->storage = $storage;
		$this->utils   = new Utils();
	}

	/**
	 * Handle updates request.
	 */
	public function handle(): void {
		try {
			if ( ! $this->verify_request( 'nuclen_admin_ajax_nonce' ) ) {
				return;
			}

				$request = UpdatesRequest::fromPost( $_POST );

				// Credits-check ping uses a dummy generation_id.
			if ( empty( $request->generationId ) ) {
				$request->generationId = 'gen_' . uniqid( 'auto_', true );
			}

								$data = $this->api->fetch_updates( $request->generationId );
				\NuclearEngagement\Services\LoggingService::log( 'Updates response: ' . wp_json_encode( $data ) );
				\NuclearEngagement\Services\LoggingService::log( 'Results present in response: ' . ( ! empty( $data['results'] ) ? 'YES' : 'NO' ) );

				$response          = new UpdatesResponse();
				$response->success = true;

			if ( isset( $data['processed'] ) ) {
				$response->processed = (int) $data['processed'];
			}
			if ( isset( $data['total'] ) ) {
				$response->total = (int) $data['total'];
			}
			if ( isset( $data['remaining_credits'] ) ) {
				$response->remainingCredits = (int) $data['remaining_credits'];
			}
			if ( isset( $data['message'] ) ) {
				$response->message = $data['message'];
			}

				/* ── Persist & return results ───────────────────────────── */
			if ( ! empty( $data['results'] ) && is_array( $data['results'] ) ) {
					$first         = reset( $data['results'] );
					\NuclearEngagement\Services\LoggingService::log( 'First result data structure: ' . wp_json_encode( $first ) );
					
					// Improve workflow detection logic
					$workflow_type = $this->detectWorkflowType( $data['results'], $first, $request->generationId );
					\NuclearEngagement\Services\LoggingService::log( "Detected workflow type: {$workflow_type}" );

					$statuses = $this->storage->storeResults( $data['results'], $workflow_type );

				if ( array_filter( $statuses, static fn( $s ) => $s !== true ) ) {
								$this->send_error( __( 'Failed to store content.', 'nuclear-engagement' ) );
								return;
				}

					$response->results  = $data['results'];
					$response->workflow = $workflow_type; // NEW → lets JS forward it to /receive-content.
			}

				wp_send_json_success( $response->toArray() );

		} catch ( ApiException $e ) {
			\NuclearEngagement\Services\LoggingService::log( 'Error fetching updates: ' . $e->getMessage() );
			$message = __( 'Failed to fetch updates. Please try again later.', 'nuclear-engagement' );
			$this->send_error( $message, $e->getCode() ?: 500 );
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log( 'Error fetching updates: ' . $e->getMessage() );
			$this->send_error( __( 'An unexpected error occurred.', 'nuclear-engagement' ) );
		}
	}

	/**
	 * Detect workflow type from results data
	 * 
	 * @param array $results Full results array
	 * @param mixed $first First result item
	 * @param string $generationId Generation ID for pattern matching
	 * @return string Detected workflow type
	 */
	private function detectWorkflowType( array $results, $first, string $generationId ): string {
		// Method 1: Check for 'questions' field in any result
		foreach ( $results as $result ) {
			if ( is_array( $result ) && isset( $result['questions'] ) && is_array( $result['questions'] ) ) {
				return 'quiz';
			}
		}
		
		// Method 2: Check for 'summary' or 'content' field indicating summary workflow
		foreach ( $results as $result ) {
			if ( is_array( $result ) && ( isset( $result['summary'] ) || isset( $result['content'] ) ) ) {
				return 'summary';
			}
		}
		
		// Method 3: Pattern matching on generation ID
		if ( strpos( $generationId, 'quiz' ) !== false ) {
			return 'quiz';
		}
		if ( strpos( $generationId, 'summary' ) !== false ) {
			return 'summary';
		}
		
		// Method 4: Check first result structure (fallback to original logic)
		if ( is_array( $first ) && isset( $first['questions'] ) ) {
			return 'quiz';
		}
		
		// Default to summary if unclear
		\NuclearEngagement\Services\LoggingService::log( 'Could not determine workflow type from results, defaulting to summary' );
		return 'summary';
	}
}
