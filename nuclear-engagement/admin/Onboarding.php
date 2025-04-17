<?php
namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Onboarding: We remove references to "Generate" or "Setup" pages
 * that are now part of the Pro plugin.
 */
class Onboarding {

	public function nuclen_register_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_nuclen_onboarding_pointers' ] );
		add_action( 'wp_ajax_nuclen_dismiss_pointer', [ $this, 'nuclen_ajax_dismiss_pointer' ] );
	}

	public function enqueue_nuclen_onboarding_pointers( $hook_suffix ) {
		// We only keep pointers relevant to the free plugin (Dashboard, Settings, editing a post).
		$target_pages = [
			'toplevel_page_nuclear-engagement',
			'nuclear-engagement_page_nuclear-engagement-settings',
			'post.php',
		];
		if ( ! in_array( $hook_suffix, $target_pages, true ) ) {
			return;
		}

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		// Define only the pointers used in free version
		$pointers_for_this_page = [];
		switch ( $hook_suffix ) {
			case 'toplevel_page_nuclear-engagement':
				$pointers_for_this_page = [
					[
						'id'       => 'nuclen_dashboard_step1',
						'target'   => '#overview-tab',
						'title'    => __( 'Post Inventory', 'nuclear-engagement' ),
						'content'  => __( 'Check how many posts have quizzes or summaries.', 'nuclear-engagement' ),
						'position' => [ 'edge' => 'top', 'align' => 'center' ],
					],
					[
						'id'       => 'nuclen_dashboard_step2',
						'target'   => '#post-type-tab',
						'title'    => __( 'Multiple Post Types', 'nuclear-engagement' ),
						'content'  => __( 'We support posts, pages, or custom types for quizzes/summaries.', 'nuclear-engagement' ),
						'position' => [ 'edge' => 'top', 'align' => 'center' ],
					],
				];
				break;

			case 'nuclear-engagement_page_nuclear-engagement-settings':
				$pointers_for_this_page = [
					[
						'id'       => 'nuclen_settings_step1',
						'target'   => '#placement-tab',
						'title'    => __( 'Quiz & Summary Placement', 'nuclear-engagement' ),
						'content'  => __( 'Choose how to insert them: manual shortcodes or automatically.', 'nuclear-engagement' ),
						'position' => [ 'edge' => 'top', 'align' => 'center' ],
					],
				];
				break;

			case 'post.php':
				$pointers_for_this_page = [
					[
						'id'       => 'nuclen_postedit_step1',
						'target'   => '#nuclen-quiz-data-meta-box',
						'title'    => __( 'Create a Quiz', 'nuclear-engagement' ),
						'content'  => __( 'Manually add or edit quiz data for this post.', 'nuclear-engagement' ),
						'position' => [ 'edge' => 'top', 'align' => 'center' ],
					],
					[
						'id'       => 'nuclen_postedit_step2',
						'target'   => '#nuclen-summary-data-meta-box',
						'title'    => __( 'Add a Summary', 'nuclear-engagement' ),
						'content'  => __( 'Manually create or edit a summary for this post.', 'nuclear-engagement' ),
						'position' => [ 'edge' => 'top', 'align' => 'center' ],
					],
				];
				break;
		}

		if ( empty( $pointers_for_this_page ) ) {
			return;
		}

		// Filter out dismissed pointers
		$current_user_id = get_current_user_id();
		$undismissed     = [];
		foreach ( $pointers_for_this_page as $ptr ) {
			$dismissed = get_user_meta( $current_user_id, 'nuclen_pointer_dismissed_' . $ptr['id'], true );
			if ( ! $dismissed ) {
				$undismissed[] = $ptr;
			}
		}
		if ( empty( $undismissed ) ) {
			return;
		}

		// Localize to JS
		wp_localize_script(
			'nuclen-admin',
			'nePointerData',
			[
				'pointers' => $undismissed,
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'nuclen_dismiss_pointer_nonce' ),
			]
		);
	}

	public function nuclen_ajax_dismiss_pointer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'No permission', 'nuclear-engagement' ) ] );
		}
		check_ajax_referer( 'nuclen_dismiss_pointer_nonce', 'nonce' );

		$pointerId = isset( $_POST['pointer'] ) ? sanitize_text_field( wp_unslash( $_POST['pointer'] ) ) : '';
		if ( ! $pointerId ) {
			wp_send_json_error( [ 'message' => __( 'No pointer ID.', 'nuclear-engagement' ) ] );
		}

		update_user_meta( get_current_user_id(), 'nuclen_pointer_dismissed_' . $pointerId, true );
		wp_send_json_success( [ 'message' => __( 'Pointer dismissed.', 'nuclear-engagement' ) ] );
	}
}
