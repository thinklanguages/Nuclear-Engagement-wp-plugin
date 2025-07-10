<?php
/**
 * AdminAjax.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Traits
 */

declare(strict_types=1);
/**
 * File: admin/Traits/AdminAjax.php
 *
 * Handles AJAX callbacks - now delegates to controllers.
 *
 * Host class must provide protected get_container(): \NuclearEngagement\Core\ServiceContainer.
 */

namespace NuclearEngagement\Admin\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AdminAjax {

	/**
	 * Fetch updates from the remote app - delegates to controller
	 */
	public function nuclen_fetch_app_updates() {
		$container  = $this->get_container();
		$controller = $container->get( 'updates_controller' );
		$controller->handle();
	}

	/**
	 * AJAX to get a list of posts for bulk generation - delegates to controller
	 */
	public function nuclen_get_posts_count() {
		$container  = $this->get_container();
		$controller = $container->get( 'posts_count_controller' );
		$controller->handle();
	}

	/**
	 * AJAX to start generation - delegates to controller
	 */
	public function nuclen_handle_trigger_generation() {
		$container  = $this->get_container();
		$controller = $container->get( 'generate_controller' );
		$controller->handle();
	}

	/**
	 * AJAX to load editor assets on demand
	 */
	public function nuclen_load_editor_assets() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'nuclen_load_assets' ) ) {
			wp_die( 'Security check failed' );
		}

		// Load the admin assets
		$this->enqueue_admin_assets();
		
		wp_send_json_success();
	}
}
