<?php
declare(strict_types=1);
/**
 * File: admin/Onboarding.php
 *
 * Handles admin onboarding pointers for Nuclear Engagement.
 * Pointer bootstrap JS now lives in a tiny standalone bundle
 * (`admin/js/onboarding-pointers.js`).  The PHP stays clean and
 * loads that bundle only on screens that actually need it.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

use NuclearEngagement\Admin\OnboardingPointers;
use NuclearEngagement\Core\AssetVersions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Onboarding {

	/*
	-------------------------------------------------------------------------
	 *  Public API – called from Plugin::__construct()
	 * ---------------------------------------------------------------------- */

	/** Hook up everything we need (enqueue + AJAX) */
	public function nuclen_register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_nuclen_onboarding_pointers' ) );
		add_action( 'wp_ajax_nuclen_dismiss_pointer', array( $this, 'nuclen_ajax_dismiss_pointer' ) );
	}

	/*
	-------------------------------------------------------------------------
	 *  Enqueue pointer assets on specific admin pages
	 * ---------------------------------------------------------------------- */

	/**
	 * Conditionally enqueue the pointer JS bundle and inject its JSON payload.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_nuclen_onboarding_pointers( $hook_suffix ) {

		/* ───── 1. Which admin pages can show pointers? ───── */
		$target_pages = array(
			'toplevel_page_nuclear-engagement',
			'nuclear-engagement_page_nuclear-engagement-generate',
			'nuclear-engagement_page_nuclear-engagement-settings',
			'nuclear-engagement_page_nuclear-engagement-setup',
			'post.php',
			// 'post-new.php',
		);

		if ( ! in_array( $hook_suffix, $target_pages, true ) ) {
			return; // nothing to do on this screen
		}

				/* ───── 2. Pointer definitions ───── */
				$pointers_by_page = OnboardingPointers::get_pointers();

		$pointers_for_this_page = $pointers_by_page[ $hook_suffix ] ?? array();
		if ( empty( $pointers_for_this_page ) ) {
			return;
		}

		/* ───── 3. Remove already-dismissed pointers ───── */
		$current_user_id = get_current_user_id();
		$undismissed     = array();

		foreach ( $pointers_for_this_page as $ptr ) {
			$dismissed = get_user_meta( $current_user_id, 'nuclen_pointer_dismissed_' . $ptr['id'], true );
			if ( ! $dismissed ) {
				$undismissed[] = $ptr;
			}
		}

		if ( empty( $undismissed ) ) {
			return; // nothing new to show
		}

		/* ───── 4. Enqueue pointer assets ───── */
				wp_enqueue_style( 'wp-pointer' );

				wp_enqueue_script(
					'nuclen-onboarding',
					NUCLEN_PLUGIN_URL . 'admin/js/onboarding-pointers.js',
					array( 'wp-util' ),
					AssetVersions::get( 'onboarding_js' ),
					true
				);

		/* ───── 5. Inject payload via wp_add_inline_script() ───── */
		$payload = array(
			'pointers' => array_values( $undismissed ),
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'nuclen_dismiss_pointer_nonce' ),
		);

		wp_add_inline_script(
			'nuclen-onboarding',
			'window.nePointerData = ' . wp_json_encode(
				$payload,
				JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
			) . ';',
			'before'
		);
	}

	/*
	-------------------------------------------------------------------------
	 *  AJAX: persist pointer dismissal
	 * ---------------------------------------------------------------------- */

	public function nuclen_ajax_dismiss_pointer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No permission', 'nuclear-engagement' ) ) );
		}

		check_ajax_referer( 'nuclen_dismiss_pointer_nonce', 'nonce' );

		$pointer_id = isset( $_POST['pointer'] ) ? sanitize_text_field( wp_unslash( $_POST['pointer'] ) ) : '';
		if ( '' === $pointer_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No pointer ID.', 'nuclear-engagement' ) ) );
		}

		update_user_meta( get_current_user_id(), 'nuclen_pointer_dismissed_' . $pointer_id, true );

		wp_send_json_success( array( 'message' => esc_html__( 'Pointer dismissed.', 'nuclear-engagement' ) ) );
	}
}
