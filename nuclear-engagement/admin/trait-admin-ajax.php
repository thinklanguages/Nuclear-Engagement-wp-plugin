<?php
/**
 * File: admin/trait-admin-ajax.php
 *
 * Handles AJAX callbacks - now delegates to controllers.
 *
 * Host class must provide protected get_container(): \NuclearEngagement\Container.
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Admin_Ajax {

    /**
     * Fetch updates from the remote app - delegates to controller
     */
    public function nuclen_fetch_app_updates() {
        $container = $this->get_container();
        $controller = $container->get('updates_controller');
        $controller->handle();
    }

    /**
     * AJAX to get a list of posts for bulk generation - delegates to controller
     */
    public function nuclen_get_posts_count() {
        $container = $this->get_container();
        $controller = $container->get('posts_count_controller');
        $controller->handle();
    }

    /**
     * AJAX to start generation - delegates to controller
     */
    public function nuclen_handle_trigger_generation() {
        $container = $this->get_container();
        $controller = $container->get('generate_controller');
        $controller->handle();
    }
}
