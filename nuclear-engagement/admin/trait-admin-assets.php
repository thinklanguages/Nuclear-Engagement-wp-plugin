<?php
/**
 * File: admin/trait-admin-assets.php
 *
 * Handles enqueueing of Admin CSS/JS for Nuclear Engagement
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


trait Admin_Assets {

        /**
         * Enqueue base admin CSS on our plugin pages only.
         *
         * @param string $hook Current admin page hook suffix.
         */
        public function wp_enqueue_styles( $hook ) {
                $allowed_hooks = array(
                        'post.php',
                        'post-new.php',
                        'toplevel_page_nuclear-engagement',
                        'nuclear-engagement_page_nuclear-engagement-generate',
                        'nuclear-engagement_page_nuclear-engagement-settings',
                        'nuclear-engagement_page_nuclear-engagement-setup',
                );

                if ( ! in_array( $hook, $allowed_hooks, true ) ) {
                        return;
                }

                wp_enqueue_style(
                        $this->nuclen_get_plugin_name(),
                        plugin_dir_url( __FILE__ ) . 'css/nuclen-admin.css',
                        array(),
                        filemtime( plugin_dir_path( __FILE__ ) . 'css/nuclen-admin.css' ),
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

		// Enqueue WP pointer (CSS + script) so our pointer logic works
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		// Enqueue your single admin bundle
		// NOTE: We add 'wp-pointer' & 'jquery' as dependencies
		wp_enqueue_script(
			'nuclen-admin',
			plugin_dir_url( __DIR__ ) . 'admin/js/nuclen-admin.js',
			array( 'wp-pointer', 'jquery' ),
			NUCLEN_ASSET_VERSION,
			true
		);

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
				plugin_dir_url( __FILE__ ) . 'css/nuclen-admin-dashboard.css?v=' . NUCLEN_ASSET_VERSION,
				array(),
				$this->nuclen_get_version(),
				'all'
			);
		}
	}
}
