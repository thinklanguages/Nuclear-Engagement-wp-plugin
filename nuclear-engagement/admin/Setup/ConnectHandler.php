<?php
/**
 * ConnectHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Setup
 */

namespace NuclearEngagement\Admin\Setup;

use NuclearEngagement\Services\SetupService;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Security\TokenManager;
use NuclearEngagement\Handlers\BaseSetupHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConnectHandler extends BaseSetupHandler {

	public function handle_connect_app(): void {
		$this->validate_nonce_and_permissions( 'nuclen_connect_app_nonce', 'nuclen_connect_app_action' );

		if ( ! isset( $_POST['nuclen_api_key'] ) ) {
			$this->redirect_with_error( 'Missing API key field.' );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['nuclen_api_key'] ) );
		if ( '' === $api_key ) {
			$this->redirect_with_error( 'Missing Gold Code.' );
		}

		if ( ! $this->setup_service->validate_api_key( $api_key ) ) {
			$this->redirect_with_error( 'Invalid or unknown Gold Code.' );
		}

		$this->settings_repository->set( 'api_key', $api_key )
			->set( 'connected', true )
			->save();

		if ( ! $this->settings_repository->get_bool( 'wp_app_pass_created', false ) ) {
			$this->get_app_password_handler()->generate_app_password( true );
			return;
		}

		$this->redirect_with_success( 'Gold Code saved.' );
	}

	public function handle_reset_api_key(): void {
		$this->validate_nonce_and_permissions( 'nuclen_reset_api_key_nonce', 'nuclen_reset_api_key_action' );

		$this->settings_repository->set( 'api_key', '' )
			->set( 'connected', false )
			->set( 'wp_app_pass_created', false )
			->set( 'wp_app_pass_uuid', '' )
			->set( 'plugin_password', '' )
			->save();

		$this->redirect_with_success( 'Gold Code reset. Site disconnected.' );
	}

	protected function get_app_password_handler(): AppPasswordHandler {
		$token_manager = new TokenManager( $this->settings_repository );
		return new AppPasswordHandler( $this->setup_service, $this->settings_repository, $token_manager );
	}
}
