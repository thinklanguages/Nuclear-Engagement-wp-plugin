<?php
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
use NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for polling updates
 */
class UpdatesController {

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
	 * @param RemoteApiService     $api
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
			check_ajax_referer( 'nuclen_admin_ajax_nonce', 'security' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Not allowed' ) );
			}

			$request = UpdatesRequest::fromPost( $_POST );

			// Credits-check ping uses a dummy generation_id.
			if ( empty( $request->generationId ) ) {
				$request->generationId = 'gen_' . uniqid( 'auto_', true );
			}

			$data = $this->api->fetchUpdates( $request->generationId );
			$this->utils->nuclen_log( 'Updates response: ' . wp_json_encode( $data ) );

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
				$workflowType  = isset( $first['questions'] ) ? 'quiz' : 'summary';

				$this->storage->storeResults( $data['results'], $workflowType );

				$response->results  = $data['results'];
				$response->workflow = $workflowType; // NEW → lets JS forward it to /receive-content
			}

			wp_send_json_success( $response->toArray() );

		} catch ( \Exception $e ) {
			$this->utils->nuclen_log( 'Error fetching updates: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
