<?php
/**
 * SecureSetupHandlersTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Traits
 */

declare(strict_types=1);

namespace NuclearEngagement\Admin\Traits;

use NuclearEngagement\Traits\Security\SetupSecurityTrait;
use NuclearEngagement\Security\TokenManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SecureSetupHandlersTrait {
	use SetupSecurityTrait;

	private function get_token_manager(): TokenManager {
		return new TokenManager( $this->nuclen_get_settings_repository() );
	}

	public function nuclen_handle_connect_app(): void {
		$this->validate_nonce_and_permissions( 'nuclen_connect_app_nonce', 'nuclen_connect_app_action' );

		if ( ! isset( $_POST['nuclen_api_key'] ) ) {
			$this->redirect_with_error( 'Missing API key field.' );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['nuclen_api_key'] ) );
		if ( '' === $api_key ) {
			$this->redirect_with_error( 'Missing Gold Code.' );
		}

		$setup_service = $this->nuclen_get_setup_service();
		if ( ! $setup_service->validate_api_key( $api_key ) ) {
			$this->redirect_with_error( 'Invalid or unknown Gold Code.' );
		}

		$settings = $this->nuclen_get_settings_repository();
		$settings->set( 'api_key', $api_key )
			->set( 'connected', true )
			->save();

		if ( ! $settings->get_bool( 'wp_app_pass_created', false ) ) {
			$this->nuclen_handle_generate_app_password( true );
			return;
		}

		$this->redirect_with_success( 'Gold Code saved.' );
	}

	public function nuclen_handle_generate_app_password( bool $bypass_nonce = false ): void {
		$this->validate_generate_nonce( $bypass_nonce );

		$settings = $this->nuclen_get_settings_repository();
		if ( ! $settings->get_bool( 'connected', false ) || empty( $settings->get( 'api_key' ) ) ) {
			$this->redirect_with_error( 'Please complete Step 1 first.' );
		}

		list( $new_password, $uuid, $current_user ) = $this->create_app_password();
		$api_key                                    = $settings->get( 'api_key' );
		if ( empty( $api_key ) ) {
			$this->redirect_with_error( 'API key is missing. Please complete Step 1 first.' );
		}

		if ( ! $this->send_credentials_to_saas( $api_key, $new_password, $uuid, $current_user ) ) {
			$this->redirect_with_error( 'Failed to send App Password to the SaaS.' );
		}

		$this->persist_app_password_securely( $new_password, $uuid );

		$this->redirect_with_success( 'Setup completed – you are ready to go!' );
	}

	public function nuclen_handle_reset_api_key(): void {
		$this->validate_nonce_and_permissions( 'nuclen_reset_api_key_nonce', 'nuclen_reset_api_key_action' );

		$settings = $this->nuclen_get_settings_repository();
		$settings->set( 'api_key', '' )
			->set( 'connected', false )
			->set( 'wp_app_pass_created', false )
			->set( 'wp_app_pass_uuid', '' )
			->set( 'plugin_token_hash', '' )
			->save();

		$this->redirect_with_success( 'Gold Code reset. Site disconnected.' );
	}

	public function nuclen_handle_reset_wp_app_connection(): void {
		$this->validate_nonce_and_permissions( 'nuclen_reset_wp_app_nonce', 'nuclen_reset_wp_app_action' );

		$settings = $this->nuclen_get_settings_repository();
		$settings->set( 'wp_app_pass_created', false )
			->set( 'wp_app_pass_uuid', '' )
			->set( 'plugin_token_hash', '' )
			->save();

		$app_setup                        = get_option( 'nuclear_engagement_setup', array() );
		$app_setup['wp_app_pass_created'] = false;
		$app_setup['wp_app_pass_uuid']    = '';
		$app_setup['plugin_token_hash']   = '';
		update_option( 'nuclear_engagement_setup', $app_setup );
		wp_cache_delete( 'nuclear_engagement_setup', 'options' );

		$this->redirect_with_success( 'App Password revoked.' );
	}

	private function validate_generate_nonce( bool $bypass ): void {
		if ( ! $bypass ) {
			$this->validate_nonce_and_permissions( 'nuclen_generate_app_password_nonce', 'nuclen_generate_app_password_action' );
		} elseif ( ! current_user_can( 'manage_options' ) ) {
				$this->redirect_with_error( 'Insufficient permissions.' );
		}
	}

	private function create_app_password(): array {
		$new_password = wp_generate_password( 32, true, true );
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

	private function persist_app_password_securely( string $password, string $uuid ): void {
		// CRITICAL: DO NOT CHANGE THIS PASSWORD STORAGE MECHANISM!
		// The SaaS backend uses this plain password for authentication.
		// Any changes to this storage method will break SaaS connectivity.
		// This password must remain accessible in plain text for the SaaS to function.

		$settings = $this->nuclen_get_settings_repository();
		$settings->set( 'wp_app_pass_created', true )
			->set( 'wp_app_pass_uuid', $uuid )
			->set( 'plugin_password', $password )  // REQUIRED: SaaS needs plain text access.
			->set( 'connected', true )
			->save();

		$app_setup                        = get_option( 'nuclear_engagement_setup', array() );
		$app_setup['wp_app_pass_created'] = true;
		$app_setup['wp_app_pass_uuid']    = $uuid;
		$app_setup['plugin_password']     = $password;  // REQUIRED: SaaS needs plain text access.
		$app_setup['connected']           = true;
		update_option( 'nuclear_engagement_setup', $app_setup );
		wp_cache_delete( 'nuclear_engagement_setup', 'options' );
	}

	abstract protected function nuclen_get_setup_service();
	abstract protected function nuclen_get_settings_repository();
}
