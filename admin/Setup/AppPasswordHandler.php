<?php
declare(strict_types=1);

namespace NuclearEngagement\Admin\Setup;

use NuclearEngagement\Services\SetupService;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Security\TokenManager;
use NuclearEngagement\Handlers\BaseSetupHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AppPasswordHandler extends BaseSetupHandler {
	private TokenManager $token_manager;

	public function __construct( SetupService $setup_service, SettingsRepository $settings_repository, TokenManager $token_manager ) {
		parent::__construct( $setup_service, $settings_repository );
		$this->token_manager = $token_manager;
	}

	public function generate_app_password( bool $bypass_nonce = false ): void {
		$this->validate_generate_nonce( $bypass_nonce );

		if ( ! $this->settings_repository->get_bool( 'connected', false ) || empty( $this->settings_repository->get( 'api_key' ) ) ) {
			$this->redirect_with_error( 'Please complete Step 1 first.' );
		}

		list( $new_password, $uuid, $current_user ) = $this->create_app_password();
		$api_key = $this->settings_repository->get( 'api_key' );
		if ( empty( $api_key ) ) {
			$this->redirect_with_error( 'API key is missing. Please complete Step 1 first.' );
		}

		if ( ! $this->send_credentials_to_saas( $api_key, $new_password, $uuid, $current_user ) ) {
			$this->redirect_with_error( 'Failed to send App Password to the SaaS.' );
		}

		$this->persist_app_password_securely( $new_password, $uuid );

		$this->redirect_with_success( 'Setup completed – you are ready to go!' );
	}

	public function reset_wp_app_connection(): void {
		$this->validate_nonce_and_permissions( 'nuclen_reset_wp_app_nonce', 'nuclen_reset_wp_app_action' );
		$this->clear_app_password_data();
		$this->redirect_with_success( 'App Password revoked.' );
	}

	protected function validate_generate_nonce( bool $bypass ): void {
		if ( ! $bypass ) {
			$this->validate_nonce_and_permissions( 'nuclen_generate_app_password_nonce', 'nuclen_generate_app_password_action' );
		} else {
			if ( ! current_user_can( 'manage_options' ) ) {
				$this->redirect_with_error( 'Insufficient permissions.' );
			}
		}
	}

	protected function persist_app_password_securely( string $password, string $uuid ): void {
		// CRITICAL: DO NOT CHANGE THIS PASSWORD STORAGE MECHANISM!
		// The SaaS backend uses this plain password for authentication.
		// Any changes to this storage method will break SaaS connectivity.
		// This password must remain accessible in plain text for the SaaS to function.
		
		$this->settings_repository->set( 'wp_app_pass_created', true )
			->set( 'wp_app_pass_uuid', $uuid )
			->set( 'plugin_password', $password )  // REQUIRED: SaaS needs plain text access
			->set( 'connected', true )
			->save();

		$app_setup = get_option( 'nuclear_engagement_setup', array() );
		$app_setup['wp_app_pass_created'] = true;
		$app_setup['wp_app_pass_uuid'] = $uuid;
		$app_setup['plugin_password'] = $password;  // REQUIRED: SaaS needs plain text access
		$app_setup['connected'] = true;
		update_option( 'nuclear_engagement_setup', $app_setup );
		wp_cache_delete( 'nuclear_engagement_setup', 'options' );
	}
}