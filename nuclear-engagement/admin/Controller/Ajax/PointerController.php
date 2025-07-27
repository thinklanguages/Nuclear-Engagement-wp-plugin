<?php
/**
 * PointerController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Controller_Ajax
 */

declare(strict_types=1);
/**
 * File: admin/Controller/Ajax/PointerController.php

 * Pointer Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Services\PointerService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for admin pointers
 */
class PointerController extends BaseController {
	/**
	 * The pointer service instance.
	 * 
	 * @var PointerService
	 */
	private PointerService $service;

	/**
	 * Constructor.
	 *
	 * @param PointerService $service The pointer service instance.
	 */
	public function __construct( PointerService $service ) {
		$this->service = $service;
	}

	/**
	 * Dismiss a pointer
	 */
	public function dismiss(): void {
		try {
			if ( ! $this->verify_request( 'nuclen_dismiss_pointer_nonce', 'nonce' ) ) {
				return;
			}

			$pointer_id = isset( $_POST['pointer'] ) ? sanitize_text_field( wp_unslash( $_POST['pointer'] ) ) : '';
			$user_id    = get_current_user_id();

			$this->service->dismissPointer( $pointer_id, $user_id );

			wp_send_json_success( array( 'message' => __( 'Pointer dismissed.', 'nuclear-engagement' ) ) );

		} catch ( \InvalidArgumentException $e ) {
			$this->send_error( $e->getMessage() );
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			$this->send_error( __( 'An error occurred', 'nuclear-engagement' ) );
		}
	}
}
