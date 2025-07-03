<?php
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
	// First register the logger module
	wp_register_script(
	'nuclen-logger',
	NUCLEN_PLUGIN_URL . 'logger-CX9s0JXb.js',
	array(),
	AssetVersions::get( 'admin_js' ),
	true
	);
	
	// Then register the admin script with logger as dependency
	wp_register_script(
	'nuclen-admin',
	NUCLEN_PLUGIN_URL . 'admin/js/nuclen-admin.js',
	array( 'nuclen-logger' ),
	AssetVersions::get( 'admin_js' ),
	true
	);
	
	// Add filter to make both scripts modules
	add_filter( 'script_loader_tag', function( $tag, $handle ) {
		if ( in_array( $handle, array( 'nuclen-logger', 'nuclen-admin' ), true ) ) {
			return str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}, 10, 2 );
	}

	/**
	 * Enqueue base admin CSS for all plugin admin screens.
	 */
	public function wp_enqueue_styles() {
	wp_enqueue_style(
	$this->nuclen_get_plugin_name(),
	NUCLEN_PLUGIN_URL . 'admin/css/nuclen-admin.css',
	array(),
	AssetVersions::get( 'admin_css' ),
	'all'
	);
	}

	/**
	 * Enqueue "nuclen-admin.js" for our NE admin pages & post editor.
	 *
	 * @param string $hook Current admin page hook suffix
	 */
	public function wp_enqueue_scripts( $hook ) {
		// The pages we want our plugin JS to load on:
		$allowed_hooks = array(
			'post.php',
			'post-new.php',
			'toplevel_page_nuclear-engagement',
			'nuclear-engagement_page_nuclear-engagement-generate',
			'nuclear-engagement_page_nuclear-engagement-settings',
			'nuclear-engagement_page_nuclear-engagement-setup',
		);

		// Only enqueue on our plugin pages & post editor
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		// For post editor pages, check if the post type is supported
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			// Use static variable to prevent multiple get_option calls
			static $allowed_post_types = null;
			
			if ( $allowed_post_types === null ) {
				$settings = get_option( 'nuclen_settings', array() );
				$allowed_post_types = isset( $settings['generation_post_types'] ) ? $settings['generation_post_types'] : array( 'post' );
			}
			
			global $post;
			$post_type = $post ? $post->post_type : get_post_type();
			if ( ! $post_type && isset( $_GET['post_type'] ) ) {
				$post_type = sanitize_key( $_GET['post_type'] );
			}
			if ( ! $post_type ) {
				$post_type = 'post';
			}
			
			if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
				return;
			}
		}

				// Enqueue the logger module first
				wp_enqueue_script(
				'nuclen-logger',
				NUCLEN_PLUGIN_URL . 'logger-CjYDh3vN.js',
				array(),
				AssetVersions::get( 'admin_js' ),
				true
				);
				// Add module type and defer loading
				add_filter( 'script_loader_tag', function( $tag, $handle ) {
					if ( 'nuclen-logger' === $handle ) {
						return str_replace( '<script ', '<script type="module" defer ', $tag );
					}
					return $tag;
				}, 10, 2 );
				
				
				// Enqueue the admin bundle. Onboarding handles pointer styles
				// separately, so no wp-pointer or jQuery dependencies here.
	wp_enqueue_script(
	'nuclen-admin',
	NUCLEN_PLUGIN_URL . 'admin/js/nuclen-admin.js',
	array( 'nuclen-logger' ),
	AssetVersions::get( 'admin_js' ),
	true
	);
	// Add module type and defer loading
	add_filter( 'script_loader_tag', function( $tag, $handle ) {
		if ( 'nuclen-admin' === $handle ) {
			return str_replace( '<script ', '<script type="module" defer ', $tag );
		}
		return $tag;
	}, 10, 2 );
	
		// Provide two objects:
		// 1) "security" => wp_create_nonce('nuclen_admin_ajax_nonce') for your admin-ajax calls
		// 2) "rest_nonce" => wp_create_nonce('wp_rest') for your custom REST route
		// 3) "rest_receive_content" => REST endpoint URL
		wp_localize_script(
			'nuclen-admin',
			'nuclenAdminVars',
			array(
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'security'             => wp_create_nonce( 'nuclen_admin_ajax_nonce' ),
				'rest_nonce'           => wp_create_nonce( 'wp_rest' ), // For X-WP-Nonce header
				'rest_receive_content' => rest_url( 'nuclear-engagement/v1/receive-content' ),
			)
		);

		// This ensures nuclenAjax is available on both the Generate page & single-post editor
		$this->nuclen_enqueue_generate_page_scripts( $hook );
	}

	/**
	 * Optionally enqueue scripts just for the Generate page, if needed.
	 * We also extend this to post.php/post-new.php so that single-post generation
	 * does not fail due to missing nuclenAjax config.
	 */
	public function nuclen_enqueue_generate_page_scripts( $hook ) {
		if (
			$hook === 'nuclear-engagement_page_nuclear-engagement-generate'
			|| $hook === 'post.php'
			|| $hook === 'post-new.php'
		) {
			wp_localize_script(
				'nuclen-admin',
				'nuclenAjax',
				array(
					'ajax_url'     => admin_url( 'admin-ajax.php' ),
					'fetch_action' => 'nuclen_fetch_app_updates',
					// same exact nonce to match check_ajax_referer('nuclen_admin_ajax_nonce','security') for admin-ajax
					'nonce'        => wp_create_nonce( 'nuclen_admin_ajax_nonce' ),
				)
			);
		}
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
