<?php
/**
 * RestTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Front
 */

declare(strict_types=1);
/**
 * File: front/traits/RestTrait.php
 *
 * Trait: RestTrait
 *
 * REST-API route registration - now delegates to controller.
 *
 * Host class must implement protected get_container(): \NuclearEngagement\Core\ServiceContainer.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait RestTrait {

	/* ---------- REST route registration ---------- */
	public function nuclen_register_content_endpoint() {
		// Registration is now handled directly in Plugin::nuclen_define_public_hooks().
		// This method is kept for backward compatibility but does nothing.
	}

	/* ---------- Legacy methods for backward compatibility ---------- */
	public function nuclen_receive_content( \WP_REST_Request $request ) {
			$container  = $this->get_container();
			$controller = $container->get( 'content_controller' );
			return $controller->handle( $request );
	}

	public function nuclen_validate_and_store_quiz_data( $post_id, $quiz_data ): bool {
		try {
						$container = $this->get_container();
						$storage   = $container->get( 'content_storage' );
			$storage->storeQuizData( $post_id, $quiz_data );
			return true;
		} catch ( \Throwable $e ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[ERROR] Failed to store quiz data for post %d: %s',
						$post_id,
						$e->getMessage()
					),
					'error'
				);
				\NuclearEngagement\Services\LoggingService::log_exception( $e );
				
				// Fire action hook to allow error recovery or notification
				do_action( 'nuclen_quiz_storage_failed', $post_id, $quiz_data, $e );
				return false;
		}
	}

	public function nuclen_send_posts_to_app_backend( $data_to_send ) {
		try {
						$container = $this->get_container();
						$api       = $container->get( 'remote_api' );
						return $api->send_posts_to_generate( $data_to_send );
		} catch ( \RuntimeException $e ) {
				$post_count = is_array( $data_to_send ) && isset( $data_to_send['posts'] ) ? count( $data_to_send['posts'] ) : 0;
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[ERROR] Failed to send %d posts to backend API: %s',
						$post_count,
						$e->getMessage()
					),
					'error'
				);
				\NuclearEngagement\Services\LoggingService::log_exception( $e );
				
				// Fire action hook to allow error recovery or retry
				do_action( 'nuclen_api_send_failed', $data_to_send, $e );
				return false;
		}
	}
}
