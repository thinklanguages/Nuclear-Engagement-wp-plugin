<?php
/**
 * File: admin/SetupHandlersTrait.php
 *
 * Handles all Setup‑form submissions.
 * • Connect app (store Gold Code).
 * • Generate / reset the plugin‑side App Password.
 *
 * Native WordPress Application Password logic has been completely removed.
 */

namespace NuclearEngagement\Admin;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Services\SetupService;

if (!defined('ABSPATH')) {
    exit;
}

trait SetupHandlersTrait {

	/*--------------------------------------------------------------
	 #  STEP 1 – Store the Gold Code
	 --------------------------------------------------------------*/
	public function nuclen_handle_connect_app() {

		if ( ! isset( $_POST['nuclen_connect_app_nonce'], $_POST['nuclen_api_key'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_connect_app_nonce'] ) ), 'nuclen_connect_app_action' )
		) {
			$this->nuclen_redirect_with_error( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions.' );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['nuclen_api_key'] ) );
		if ( '' === $api_key ) {
			$this->nuclen_redirect_with_error( 'Missing Gold Code.' );
		}

               // Validate API key with SaaS
               $setup_service = $this->nuclen_get_setup_service();
               if ( ! $setup_service->validate_api_key( $api_key ) ) {
                       $this->nuclen_redirect_with_error( 'Invalid or unknown Gold Code.' );
               }

		// Store key & mark as connected
		$settings = SettingsRepository::get_instance();
		$settings->set('api_key', $api_key)
		        ->set('connected', true)
		        ->save();

		// Auto‑create the plugin App Password (Step 2)
		$settings = SettingsRepository::get_instance();
		if (!$settings->get_bool('wp_app_pass_created', false)) {
			$this->nuclen_handle_generate_app_password( true );
			return; // that method redirects on success/fail
		}

		$this->nuclen_redirect_with_success( 'Gold Code saved.' );
	}

	/*--------------------------------------------------------------
	 #  STEP 2 – Generate & store plugin App Password
	 --------------------------------------------------------------*/
	public function nuclen_handle_generate_app_password( $bypass_nonce = false ) {

		if ( ! $bypass_nonce ) {
			if ( ! isset( $_POST['nuclen_generate_app_password_nonce'] ) ||
				 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_generate_app_password_nonce'] ) ), 'nuclen_generate_app_password_action' )
			) {
				$this->nuclen_redirect_with_error( 'Invalid nonce.' );
			}
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions.' );
		}

		$settings = SettingsRepository::get_instance();
		if (!$settings->get_bool('connected', false) || empty($settings->get('api_key'))) {
			$this->nuclen_redirect_with_error('Please complete Step 1 first.');
		}
		
		// Get the current app setup data
		$app_setup = get_option('nuclear_engagement_setup', array());

		// ──────────────────────────────────────────────────────────
		//  Generate a new random password & UUID
		// ──────────────────────────────────────────────────────────
		$new_password = wp_generate_password( 32, false, false );
		$uuid         = wp_generate_uuid4();
		$current_user = wp_get_current_user();

		// Get API key from settings
		$api_key = $settings->get('api_key');
		if (empty($api_key)) {
			$this->nuclen_redirect_with_error('API key is missing. Please complete Step 1 first.');
		}

               // Send credentials to SaaS (keep payload identical to old format)
               $setup_service = $this->nuclen_get_setup_service();
               $ok = $setup_service->send_app_password(
                       array(
                               'appApiKey'     => $api_key,
                               'siteUrl'       => get_site_url(),
                               'wpUserLogin'   => $current_user->user_login,
                               'wpAppPassword' => $new_password, // same key, new value
                               'wpAppPassUuid' => $uuid,
                       )
               );
		if ( ! $ok ) {
			$this->nuclen_redirect_with_error( 'Failed to send App Password to the SaaS.' );
		}

		// Update SettingsRepository first
		$settings = SettingsRepository::get_instance();
		$settings->set('wp_app_pass_created', true)
		        ->set('wp_app_pass_uuid', $uuid)
		        ->set('plugin_password', $new_password)
		        ->set('connected', true) // Ensure connected is also set
		        ->save();

		// Then update the legacy options for backward compatibility
		$app_setup['wp_app_pass_created'] = true;
		$app_setup['wp_app_pass_uuid']    = $uuid;
		$app_setup['plugin_password']     = $new_password;
		$app_setup['connected']           = true; // Ensure connected is also set in legacy option
		update_option( 'nuclear_engagement_setup', $app_setup );

		// Clear any caches that might be holding old values
		wp_cache_delete( 'nuclear_engagement_setup', 'options' );

		$this->nuclen_redirect_with_success( 'Setup completed – you are ready to go!' );
	}

	/*--------------------------------------------------------------
	 #  Reset handlers
	 --------------------------------------------------------------*/
	public function nuclen_handle_reset_api_key() {

		if ( ! isset( $_POST['nuclen_reset_api_key_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_reset_api_key_nonce'] ) ), 'nuclen_reset_api_key_action' )
		) {
			$this->nuclen_redirect_with_error( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions.' );
		}

		$settings = SettingsRepository::get_instance();
		$settings->set('api_key', '')
		        ->set('connected', false)
		        ->set('wp_app_pass_created', false)
		        ->set('wp_app_pass_uuid', '')
		        ->set('plugin_password', '')
		        ->save();

		$this->nuclen_redirect_with_success( 'Gold Code reset. Site disconnected.' );
	}

	public function nuclen_handle_reset_wp_app_connection() {

		if ( ! isset( $_POST['nuclen_reset_wp_app_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_reset_wp_app_nonce'] ) ), 'nuclen_reset_wp_app_action' )
		) {
			$this->nuclen_redirect_with_error( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions.' );
		}

               $app_setup = get_option( 'nuclear_engagement_setup', array() );
               $app_setup['wp_app_pass_created'] = false;
               $app_setup['wp_app_pass_uuid']    = '';
               $app_setup['plugin_password']     = '';
               update_option( 'nuclear_engagement_setup', $app_setup );

               $settings = SettingsRepository::get_instance();
               $settings->set( 'wp_app_pass_created', false )
                       ->set( 'wp_app_pass_uuid', '' )
                       ->set( 'plugin_password', '' )
                       ->save();

               $this->nuclen_redirect_with_success( 'App Password revoked.' );
       }


	private function nuclen_redirect_with_error( $msg ) {
		wp_redirect(
			add_query_arg(
				array(
					'page'         => 'nuclear-engagement-setup',
					'nuclen_error' => urlencode( $msg ),
					'_wpnonce'     => wp_create_nonce( 'nuclear-engagement-setup' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function nuclen_redirect_with_success( $msg ) {
		wp_redirect(
			add_query_arg(
				array(
					'page'           => 'nuclear-engagement-setup',
					'nuclen_success' => urlencode( $msg ),
					'_wpnonce'       => wp_create_nonce( 'nuclear-engagement-setup' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
