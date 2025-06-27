<?php
namespace NuclearEngagement\Admin\Setup;

use NuclearEngagement\Services\SetupService;
use NuclearEngagement\Core\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConnectHandler {
	private SetupService $setup_service;
	private SettingsRepository $settings_repository;

	public function __construct( SetupService $setup_service, SettingsRepository $settings_repository ) {
		$this->setup_service	   = $setup_service;
		$this->settings_repository = $settings_repository;
	}

	public function handle_connect_app(): void {
		if ( ! isset( $_POST['nuclen_connect_app_nonce'], $_POST['nuclen_api_key'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_connect_app_nonce'] ) ), 'nuclen_connect_app_action' ) ) {
			$this->redirect_with_error( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_error( 'Insufficient permissions.' );
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
		if ( ! isset( $_POST['nuclen_reset_api_key_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_reset_api_key_nonce'] ) ), 'nuclen_reset_api_key_action' ) ) {
			$this->redirect_with_error( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_error( 'Insufficient permissions.' );
		}

		$this->settings_repository->set( 'api_key', '' )
			->set( 'connected', false )
			->set( 'wp_app_pass_created', false )
			->set( 'wp_app_pass_uuid', '' )
			->set( 'plugin_password', '' )
			->save();

		$this->redirect_with_success( 'Gold Code reset. Site disconnected.' );
	}

	protected function get_app_password_handler(): AppPasswordHandler {
		return new AppPasswordHandler( $this->setup_service, $this->settings_repository );
	}

	protected function redirect_with_error( $msg ): void {
		wp_redirect(
			add_query_arg(
				array(
					'page'		   => 'nuclear-engagement-setup',
					'nuclen_error' => urlencode( $msg ),
					'_wpnonce'	   => wp_create_nonce( 'nuclear-engagement-setup' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	protected function redirect_with_success( $msg ): void {
		wp_redirect(
			add_query_arg(
				array(
					'page'			 => 'nuclear-engagement-setup',
					'nuclen_success' => urlencode( $msg ),
					'_wpnonce'		 => wp_create_nonce( 'nuclear-engagement-setup' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
