<?php
/**
 * PostsCountController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Controller_Ajax
 */

declare(strict_types=1);
/**
 * File: admin/Controller/Ajax/PostsCountController.php

 * Posts Count Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Requests\PostsCountRequest;
use NuclearEngagement\Services\PostsQueryService;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for getting posts count
 */
class PostsCountController extends BaseController {
	/**
	 * @var PostsQueryService
	 */
	private PostsQueryService $service;

	/**
	 * Constructor
	 *
	 * @param PostsQueryService $service
	 */
	public function __construct( PostsQueryService $service ) {
		$this->service = $service;
	}

	/**
	 * Handle posts count request
	 */
	public function handle(): void {
		try {
			if ( ! $this->verify_request( 'nuclen_admin_ajax_nonce' ) ) {
				return;
			}

			// Parse request.
			$request = PostsCountRequest::fromPost( $_POST );

			// Validate post type against allowed post types.
			$settings           = get_option( 'nuclear_engagement_settings', array() );
			$allowed_post_types = $settings['generation_post_types'] ?? array( 'post' );

			// Debug logging (commented out temporarily to isolate 500 error).
			// LoggingService::log( 'PostsCountController: Allowed post types: ' . implode( ', ', $allowed_post_types ) );
			// LoggingService::log( 'PostsCountController: Requested post type: ' . $request->postType );

			if ( ! empty( $request->postType ) && ! in_array( $request->postType, $allowed_post_types, true ) ) {
				$this->send_error( 'Selected post type is not allowed for generation.' );
				return;
			}

			// Get posts without caching for real-time accuracy in bulk generation.
			// Caching is disabled here because users need to see immediate, accurate post counts
			// when changing filter criteria during post selection. Cached results could show
			// outdated counts that don't reflect the current filter selections.
			$result = $this->service->get_posts_count( $request, false );

			wp_send_json_success( $result );

		} catch ( \Throwable $e ) {
			LoggingService::log_exception( $e );
			$this->send_error( $e->getMessage() );
		}
	}
}
