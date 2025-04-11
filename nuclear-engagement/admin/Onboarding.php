<?php
/**
 * File: admin/Onboarding.php
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Utils;

class Onboarding {

	public function nuclen_register_hooks() {
		// Enqueue pointers (on certain pages), handle AJAX for dismissal
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_nuclen_onboarding_pointers' ) );
		add_action( 'wp_ajax_nuclen_dismiss_pointer', array( $this, 'nuclen_ajax_dismiss_pointer' ) );
	}

	/**
	 * Enqueue pointer scripts/styles if on the correct pages; localize pointer data.
	 */
	public function enqueue_nuclen_onboarding_pointers( $hook_suffix ) {
		$target_pages = array(
			'toplevel_page_nuclear-engagement',
			'nuclear-engagement_page_nuclear-engagement-generate',
			'nuclear-engagement_page_nuclear-engagement-settings',
			'nuclear-engagement_page_nuclear-engagement-setup',
			'post.php',
			// 'post-new.php'
		);

		if ( ! in_array( $hook_suffix, $target_pages, true ) ) {
			return;
		}

		// Enqueue WP pointer
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		$pointers_by_page = array(
			'toplevel_page_nuclear-engagement' => array(
				array(
					'id'       => 'nuclen_dashboard_step1',
					'target'   => '#overview-tab',
					'title'    => esc_html__( 'Post Inventory, at a Glance', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Keep track of which posts lack quizzes or summaries. Looks like many may need an upgrade!', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_dashboard_step2',
					'target'   => '#post-type-tab',
					'title'    => esc_html__( 'Custom Post Types', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Not only posts, but also pages and other custom types are supported.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_dashboard_step3',
					'target'   => 'li.toplevel_page_nuclear-engagement ul.wp-submenu a[href="admin.php?page=nuclear-engagement-generate"]',
					'title'    => esc_html__( 'Generate Engaging Content', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Open the Generate page to create or update your content at scale.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'left',
						'align' => 'center',
					),
				),
			),
			'nuclear-engagement_page_nuclear-engagement-generate' => array(
				array(
					'id'       => 'nuclen_generate_step1',
					'target'   => '#nuclen_generate_workflow',
					'title'    => esc_html__( 'Bulk Upgrade', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Generate content for all selected posts in one go.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_generate_step2',
					'target'   => '#nuclen-filters-form .form-table',
					'title'    => esc_html__( 'Refine Your Selection', 'nuclear-engagement' ),
					'content'  => esc_html__( 'You can filter down to specific authors, categories, or post types.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_generate_step3',
					'target'   => 'li.toplevel_page_nuclear-engagement ul.wp-submenu a[href="admin.php?page=nuclear-engagement-settings"]',
					'title'    => esc_html__( 'Customize Behavior', 'nuclear-engagement' ),
					'content'  => esc_html__( 'You can finuclen-tune how and where new content is displayed under Settings.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'left',
						'align' => 'center',
					),
				),
			),
			'nuclear-engagement_page_nuclear-engagement-settings' => array(
				array(
					'id'       => 'nuclen_settings_step1',
					'target'   => '#placement-tab',
					'title'    => esc_html__( 'Display Sections as You Prefer', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Use shortcodes or automatically insert quiz/summary before/after post content.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_settings_step2',
					'target'   => '#optin-tab',
					'title'    => esc_html__( 'Generate Leads', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Enable an email opt-in form at the end of the quiz, hooking into your marketing tools.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_settings_step3',
					'target'   => 'li.toplevel_page_nuclear-engagement ul.wp-submenu a[href="admin.php?page=nuclear-engagement-setup"]',
					'title'    => esc_html__( 'Easy Setup', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Authorize your site to generate content with NE in two steps.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'left',
						'align' => 'center',
					),
				),
			),
			'nuclear-engagement_page_nuclear-engagement-setup' => array(
				array(
					'id'       => 'nuclen_setup_step1',
					'target'   => '#nuclen-setup-step-1',
					'title'    => esc_html__( 'Enter Your Gold Code', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Paste your API key to connect your site with the NE service.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_setup_step2',
					'target'   => '#nuclen-setup-step-2',
					'title'    => esc_html__( 'One More Click', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Allow NE to push generated content directly into your site.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_setup_step3',
					'target'   => 'li.toplevel_page_nuclear-engagement ul.wp-submenu li.wp-first-item',
					'title'    => esc_html__( 'Check Your Dashboard', 'nuclear-engagement' ),
					'content'  => esc_html__( 'See which posts need a quiz or summary the most.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'left',
						'align' => 'center',
					),
				),
			),
			'post.php'                         => array(
				array(
					'id'       => 'nuclen_postedit_step1',
					'target'   => '#nuclen-quiz-data-meta-box',
					'title'    => esc_html__( 'Generate from Editor', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Create or edit quiz content for this single post.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'top',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_postedit_step2',
					'target'   => '#nuclen-generate-quiz-single',
					'title'    => esc_html__( 'Onuclen-Click Generation', 'nuclear-engagement' ),
					'content'  => esc_html__( 'Immediately fetch new quiz data for this post.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'right',
						'align' => 'center',
					),
				),
				array(
					'id'       => 'nuclen_postedit_step3',
					'target'   => '#show-settings-link',
					'title'    => esc_html__( 'Hide Metaboxes', 'nuclear-engagement' ),
					'content'  => esc_html__( 'You can hide plugin sections here if your editor is cluttered.', 'nuclear-engagement' ),
					'position' => array(
						'edge'  => 'bottom',
						'align' => 'center',
					),
				),
			),
		);

		// Pull the pointers for this page
		$pointers_for_this_page = isset( $pointers_by_page[ $hook_suffix ] ) ? $pointers_by_page[ $hook_suffix ] : array();
		if ( empty( $pointers_for_this_page ) ) {
			return;
		}

		// Filter out dismissed pointers via user meta
		$current_user_id = get_current_user_id();
		$undismissed     = array();
		foreach ( $pointers_for_this_page as $ptr ) {
			$dismissed = get_user_meta( $current_user_id, 'nuclen_pointer_dismissed_' . $ptr['id'], true );
			if ( ! $dismissed ) {
				$undismissed[] = $ptr;
			}
		}

		if ( empty( $undismissed ) ) {
			return; // All dismissed
		}

		// Localize them for JS, including a pointer nonce
		wp_localize_script(
			'nuclen-admin',
			'nePointerData',
			array(
				'pointers' => array_values( $undismissed ),
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'nuclen_dismiss_pointer_nonce' ),
			)
		);
	}

	/**
	 * AJAX callback to dismiss a pointer by saving user meta
	 */
	public function nuclen_ajax_dismiss_pointer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No permission', 'nuclear-engagement' ) ) );
		}

		// Verify nonce
		check_ajax_referer( 'nuclen_dismiss_pointer_nonce', 'nonce' );

		$pointerId = isset( $_POST['pointer'] ) ? sanitize_text_field( wp_unslash( $_POST['pointer'] ) ) : '';
		if ( ! $pointerId ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No pointer ID.', 'nuclear-engagement' ) ) );
		}

		$current_user_id = get_current_user_id();
		update_user_meta( $current_user_id, 'nuclen_pointer_dismissed_' . $pointerId, true );

		wp_send_json_success( array( 'message' => esc_html__( 'Pointer dismissed via user meta.', 'nuclear-engagement' ) ) );
	}
}
