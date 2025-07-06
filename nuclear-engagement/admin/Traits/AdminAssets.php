<?php
/**
 * AdminAssets.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Traits
 */

declare(strict_types=1);
/**
 * File: admin/Traits/AdminAssets.php
 *
 * Handles enqueueing of Admin CSS/JS for Nuclear Engagement
 */

namespace NuclearEngagement\Admin\Traits;

use NuclearEngagement\Core\AssetVersions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


trait AdminAssets {

	/**
	 * Register the admin script for later use.
	 */
	public function nuclen_register_admin_scripts() {
		// First register the logger module.
		wp_register_script(
			'nuclen-logger',
			NUCLEN_PLUGIN_URL . 'logger-CjYDh3vN.js',
			array(),
			defined( 'NUCLEN_ASSET_VERSION' ) ? NUCLEN_ASSET_VERSION : NUCLEN_PLUGIN_VERSION,
			true
		);

		// Then register the admin script with logger as dependency.
		wp_register_script(
			'nuclen-admin',
			NUCLEN_PLUGIN_URL . 'admin/js/nuclen-admin.js',
			array( 'nuclen-logger' ),
			defined( 'NUCLEN_ASSET_VERSION' ) ? NUCLEN_ASSET_VERSION : NUCLEN_PLUGIN_VERSION,
			true
		);

		// Add filter to make both scripts modules.
		add_filter(
			'script_loader_tag',
			function ( $tag, $handle ) {
				if ( in_array( $handle, array( 'nuclen-logger', 'nuclen-admin' ), true ) ) {
					return str_replace( '<script ', '<script type="module" ', $tag );
				}
				return $tag;
			},
			10,
			2
		);
	}

	/**
	 * Enqueue base admin CSS for all plugin admin screens.
	 */
	public function wp_enqueue_styles() {
		wp_enqueue_style(
			$this->nuclen_get_plugin_name(),
			NUCLEN_PLUGIN_URL . 'admin/css/nuclen-admin.css',
			array(),
			defined( 'NUCLEN_ASSET_VERSION' ) ? NUCLEN_ASSET_VERSION : NUCLEN_PLUGIN_VERSION,
			'all'
		);
	}

	/**
	 * Enqueue "nuclen-admin.js" for our NE admin pages & post editor.
	 * Optimized for better performance.
	 *
	 * @param string $hook Current admin page hook suffix
	 */
	public function wp_enqueue_scripts( $hook ) {
		// Early return for non-relevant pages
		if ( ! $this->should_load_assets( $hook ) ) {
			return;
		}

		// Use conditional loading to reduce memory usage
		if ( $this->is_post_editor_page( $hook ) ) {
			$this->maybe_enqueue_editor_assets();
		} else {
			$this->enqueue_admin_assets();
		}
	}

	/**
	 * Check if assets should be loaded for the current page.
	 *
	 * @param string $hook Current page hook.
	 * @return bool Whether to load assets.
	 */
	private function should_load_assets( string $hook ): bool {
		$allowed_hooks = array(
			'post.php',
			'post-new.php',
			'toplevel_page_nuclear-engagement',
			'nuclear-engagement_page_nuclear-engagement-generate',
			'nuclear-engagement_page_nuclear-engagement-settings',
			'nuclear-engagement_page_nuclear-engagement-setup',
		);

		return in_array( $hook, $allowed_hooks, true );
	}

	/**
	 * Check if current page is post editor.
	 *
	 * @param string $hook Current page hook.
	 * @return bool Whether current page is post editor.
	 */
	private function is_post_editor_page( string $hook ): bool {
		return in_array( $hook, array( 'post.php', 'post-new.php' ), true );
	}

	/**
	 * Maybe enqueue assets for post editor pages.
	 * Only load if post type is supported.
	 */
	private function maybe_enqueue_editor_assets(): void {
		if ( ! $this->is_supported_post_type() ) {
			return;
		}

		$this->enqueue_admin_assets();
	}

	/**
	 * Check if current post type is supported.
	 * Uses transient caching to reduce database calls.
	 *
	 * @return bool Whether current post type is supported.
	 */
	private function is_supported_post_type(): bool {
		// Use transient for caching settings
		$allowed_post_types = get_transient( 'nuclen_allowed_post_types' );
		
		if ( false === $allowed_post_types ) {
			$settings = get_option( 'nuclen_settings', array() );
			$allowed_post_types = isset( $settings['generation_post_types'] ) ? 
				$settings['generation_post_types'] : array( 'post' );
			
			// Cache for 10 minutes
			set_transient( 'nuclen_allowed_post_types', $allowed_post_types, 10 * MINUTE_IN_SECONDS );
		}

		$post_type = $this->get_current_post_type();
		return in_array( $post_type, $allowed_post_types, true );
	}

	/**
	 * Get current post type efficiently.
	 *
	 * @return string Current post type.
	 */
	private function get_current_post_type(): string {
		global $post;
		
		if ( $post && $post->post_type ) {
			return $post->post_type;
		}

		if ( isset( $_GET['post_type'] ) ) {
			return sanitize_key( $_GET['post_type'] );
		}

		$post_type = get_post_type();
		return $post_type ?: 'post';
	}

	/**
	 * Enqueue admin assets with optimizations.
	 */
	private function enqueue_admin_assets(): void {
		// Register and enqueue scripts efficiently
		$this->register_and_enqueue_logger();
		$this->register_and_enqueue_admin_script();
		$this->localize_admin_scripts();
	}

	/**
	 * Register and enqueue logger script with optimizations.
	 */
	private function register_and_enqueue_logger(): void {
		wp_enqueue_script(
			'nuclen-logger',
			NUCLEN_PLUGIN_URL . 'logger-CjYDh3vN.js',
			array(),
			AssetVersions::get( 'admin_js' ),
			array(
				'strategy' => 'defer',
				'in_footer' => true,
			)
		);

		// Make it a module
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_logger' ), 10, 2 );
	}

	/**
	 * Register and enqueue admin script with optimizations.
	 */
	private function register_and_enqueue_admin_script(): void {
		wp_enqueue_script(
			'nuclen-admin',
			NUCLEN_PLUGIN_URL . 'admin/js/nuclen-admin.js',
			array( 'nuclen-logger' ),
			AssetVersions::get( 'admin_js' ),
			array(
				'strategy' => 'defer',
				'in_footer' => true,
			)
		);

		// Make it a module
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_admin' ), 10, 2 );
	}

	/**
	 * Localize admin scripts efficiently.
	 */
	private function localize_admin_scripts(): void {
		// Cache nonces to avoid multiple calls
		static $admin_vars = null;
		
		if ( null === $admin_vars ) {
			$admin_vars = array(
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'security'             => wp_create_nonce( 'nuclen_admin_ajax_nonce' ),
				'rest_nonce'           => wp_create_nonce( 'wp_rest' ),
				'rest_receive_content' => rest_url( 'nuclear-engagement/v1/receive-content' ),
			);
		}

		wp_localize_script( 'nuclen-admin', 'nuclenAdminVars', $admin_vars );
		
		// Enqueue generate page scripts if needed
		$this->nuclen_enqueue_generate_page_scripts( $this->get_current_hook() );
	}

	/**
	 * Get current admin hook safely.
	 *
	 * @return string Current hook.
	 */
	private function get_current_hook(): string {
		global $hook_suffix;
		return $hook_suffix ?: '';
	}

	/**
	 * Add module type to logger script.
	 *
	 * @param string $tag Script tag.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public function add_module_type_to_logger( string $tag, string $handle ): string {
		if ( 'nuclen-logger' === $handle ) {
			return str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Add module type to admin script.
	 *
	 * @param string $tag Script tag.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public function add_module_type_to_admin( string $tag, string $handle ): string {
		if ( 'nuclen-admin' === $handle ) {
			return str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Enqueue scripts for Generate page and post editor.
	 * Optimized with caching and conditional loading.
	 *
	 * @param string $hook Current page hook.
	 */
	public function nuclen_enqueue_generate_page_scripts( $hook ) {
		$generate_pages = array(
			'nuclear-engagement_page_nuclear-engagement-generate',
			'post.php',
			'post-new.php'
		);

		if ( ! in_array( $hook, $generate_pages, true ) ) {
			return;
		}

		// Cache ajax config to avoid duplicate calls
		static $ajax_config = null;
		
		if ( null === $ajax_config ) {
			$ajax_config = array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'fetch_action' => 'nuclen_fetch_app_updates',
				'nonce'        => wp_create_nonce( 'nuclen_admin_ajax_nonce' ),
			);
		}

		wp_localize_script( 'nuclen-admin', 'nuclenAjax', $ajax_config );
	}

	/**
	 * Enqueue CSS only on the Dashboard page, if you want.
	 */
	public function nuclen_enqueue_dashboard_styles( $hook ) {
		if ( $hook === 'toplevel_page_nuclear-engagement' ) {
			wp_enqueue_style(
				$this->nuclen_get_plugin_name() . '-dashboard',
				NUCLEN_PLUGIN_URL . 'admin/css/nuclen-admin-dashboard.css',
				array(),
				AssetVersions::get( 'admin_dashboard' ),
				'all'
			);
		}
	}
}
