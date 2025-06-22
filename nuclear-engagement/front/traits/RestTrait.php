<?php
/**
 * File: front/traits/RestTrait.php
 *
 * Trait: RestTrait
 *
 * REST-API route registration - now delegates to controller.
 *
 * Host class must implement protected get_container(): \NuclearEngagement\Container.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

if (!defined('ABSPATH')) {
    exit;
}

trait RestTrait {

    /* ---------- REST route registration ---------- */
    public function nuclen_register_content_endpoint() {
        // Registration is now handled directly in Plugin::nuclen_define_public_hooks()
        // This method is kept for backward compatibility but does nothing
    }

    /* ---------- Legacy methods for backward compatibility ---------- */
        public function nuclen_receive_content( \WP_REST_Request $request ) {
                $container = $this->get_container();
                $controller = $container->get('content_controller');
                return $controller->handle($request);
        }

    public function nuclen_validate_and_store_quiz_data( $post_id, $quiz_data ) : bool {
        try {
                        $container = $this->get_container();
                        $storage = $container->get('content_storage');
            $storage->storeQuizData($post_id, $quiz_data);
            return true;
        } catch (\Exception $e) {
\NuclearEngagement\Services\LoggingService::log("Failed storing quiz-data for {$post_id}: " . $e->getMessage());
            return false;
        }
    }

    public function nuclen_send_posts_to_app_backend( $data_to_send ) {
        try {
                        $container = $this->get_container();
                        $api = $container->get('remote_api');
            return $api->sendPostsToGenerate($data_to_send);
        } catch (\RuntimeException $e) {
\NuclearEngagement\Services\LoggingService::log('Error sending data: ' . $e->getMessage());
            return false;
        }
    }
}
