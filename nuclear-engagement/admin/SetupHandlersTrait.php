<?php
declare(strict_types=1);
/**
 * File: admin/SetupHandlersTrait.php
 *
 * Handles all Setup‑form submissions.
 * • Connect app (store Gold Code).
 * • Generate / reset the plugin‑side App Password.
 *
 * Native WordPress Application Password logic has been completely removed.
 *
 * Host class must expose `nuclen_get_setup_service()` and
 * `nuclen_get_settings_repository()`.
 */

namespace NuclearEngagement\Admin;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SetupHandlersTrait {

	/*
	--------------------------------------------------------------
	#  STEP 1 – Store the Gold Code
	--------------------------------------------------------------*/
	public function nuclen_handle_connect_app(): void {

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
		$settings = $this->nuclen_get_settings_repository();
		$settings->set( 'api_key', $api_key )
				->set( 'connected', true )
				->save();

		// Auto‑create the plugin App Password (Step 2)
		$settings = $this->nuclen_get_settings_repository();
		if ( ! $settings->get_bool( 'wp_app_pass_created', false ) ) {
			$this->nuclen_handle_generate_app_password( true );
			return; // that method redirects on success/fail
		}

		$this->nuclen_redirect_with_success( 'Gold Code saved.' );
	}

	/*
	--------------------------------------------------------------
	#  STEP 2 – Generate & store plugin App Password
	--------------------------------------------------------------*/
	   public function nuclen_handle_generate_app_password( $bypass_nonce = false ): void {

$this->validate_generate_nonce( $bypass_nonce );

$settings = $this->nuclen_get_settings_repository();
if ( ! $settings->get_bool( 'connected', false ) || empty( $settings->get( 'api_key' ) ) ) {
$this->nuclen_redirect_with_error( 'Please complete Step 1 first.' );
}

list( $new_password, $uuid, $current_user ) = $this->create_app_password();
$api_key                                  = $settings->get( 'api_key' );
if ( empty( $api_key ) ) {
$this->nuclen_redirect_with_error( 'API key is missing. Please complete Step 1 first.' );
}

if ( ! $this->send_credentials_to_saas( $api_key, $new_password, $uuid, $current_user ) ) {
$this->nuclen_redirect_with_error( 'Failed to send App Password to the SaaS.' );
}

$this->persist_app_password( $new_password, $uuid );

$this->nuclen_redirect_with_success( 'Setup completed – you are ready to go!' );
}

	/*
	--------------------------------------------------------------
	#  Reset handlers
	--------------------------------------------------------------*/
	public function nuclen_handle_reset_api_key(): void {

		if ( ! isset( $_POST['nuclen_reset_api_key_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_reset_api_key_nonce'] ) ), 'nuclen_reset_api_key_action' )
		) {
			$this->nuclen_redirect_with_error( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions.' );
		}

		$settings = $this->nuclen_get_settings_repository();
		$settings->set( 'api_key', '' )
				->set( 'connected', false )
				->set( 'wp_app_pass_created', false )
				->set( 'wp_app_pass_uuid', '' )
				->set( 'plugin_password', '' )
				->save();

		$this->nuclen_redirect_with_success( 'Gold Code reset. Site disconnected.' );
	}

	public function nuclen_handle_reset_wp_app_connection(): void {

		if ( ! isset( $_POST['nuclen_reset_wp_app_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_reset_wp_app_nonce'] ) ), 'nuclen_reset_wp_app_action' )
		) {
			$this->nuclen_redirect_with_error( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions.' );
		}

				$app_setup                        = get_option( 'nuclear_engagement_setup', array() );
				$app_setup['wp_app_pass_created'] = false;
				$app_setup['wp_app_pass_uuid']    = '';
				$app_setup['plugin_password']     = '';
				update_option( 'nuclear_engagement_setup', $app_setup );

		$settings = $this->nuclen_get_settings_repository();
		$settings->set( 'wp_app_pass_created', false )
						->set( 'wp_app_pass_uuid', '' )
						->set( 'plugin_password', '' )
						->save();

$this->nuclen_redirect_with_success( 'App Password revoked.' );
}

	private function validate_generate_nonce( $bypass ): void {
	if ( ! $bypass ) {
	if ( ! isset( $_POST['nuclen_generate_app_password_nonce'] ) ||
	! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_generate_app_password_nonce'] ) ), 'nuclen_generate_app_password_action' )
	) {
	$this->nuclen_redirect_with_error( 'Invalid nonce.' );
	}
	}
	if ( ! current_user_can( 'manage_options' ) ) {
	$this->nuclen_redirect_with_error( 'Insufficient permissions.' );
	}
	}
		
		private function create_app_password(): array {
		$new_password = wp_generate_password( 32, false, false );
		$uuid         = wp_generate_uuid4();
		$current_user = wp_get_current_user();
		return array( $new_password, $uuid, $current_user );
		}
		
		private function send_credentials_to_saas( string $api_key, string $password, string $uuid, $user ): bool {
		$setup_service = $this->nuclen_get_setup_service();
		return $setup_service->send_app_password(
		array(
		'appApiKey'     => $api_key,
		'siteUrl'       => get_site_url(),
		'wpUserLogin'   => $user->user_login,
		'wpAppPassword' => $password,
		'wpAppPassUuid' => $uuid,
		)
		);
		}
		
		private function persist_app_password( string $password, string $uuid ): void {
		$settings = $this->nuclen_get_settings_repository();
		$settings->set( 'wp_app_pass_created', true )
		->set( 'wp_app_pass_uuid', $uuid )
		->set( 'plugin_password', $password )
		->set( 'connected', true )
		->save();
		
		$app_setup                        = get_option( 'nuclear_engagement_setup', array() );
		$app_setup['wp_app_pass_created'] = true;
		$app_setup['wp_app_pass_uuid']    = $uuid;
		$app_setup['plugin_password']     = $password;
		$app_setup['connected']           = true;
		update_option( 'nuclear_engagement_setup', $app_setup );
		wp_cache_delete( 'nuclear_engagement_setup', 'options' );
		}


	private function nuclen_redirect_with_error( $msg ): void {
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

	private function nuclen_redirect_with_success( $msg ): void {
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
