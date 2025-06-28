<?php
namespace NuclearEngagement\Admin\Setup;

use NuclearEngagement\Services\SetupService;
use NuclearEngagement\Core\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AppPasswordHandler {
	private SetupService $setup_service;
	private SettingsRepository $settings_repository;

	public function __construct( SetupService $setup_service, SettingsRepository $settings_repository ) {
		$this->setup_service	   = $setup_service;
		$this->settings_repository = $settings_repository;
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
			$this->redirect_with_error( 'Failed to send App Password to the SaaS.' );
		}

		$this->persist_app_password( $new_password, $uuid );

		$this->redirect_with_success( 'Setup completed – you are ready to go!' );
	}

	public function reset_wp_app_connection(): void {
		if ( ! isset( $_POST['nuclen_reset_wp_app_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_reset_wp_app_nonce'] ) ), 'nuclen_reset_wp_app_action' ) ) {
			$this->redirect_with_error( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_error( 'Insufficient permissions.' );
		}

		$app_setup = get_option( 'nuclear_engagement_setup', array() );
		$app_setup['wp_app_pass_created'] = false;
		$app_setup['wp_app_pass_uuid'] = '';
		$app_setup['plugin_password'] = '';
		update_option( 'nuclear_engagement_setup', $app_setup );

		$this->settings_repository->set( 'wp_app_pass_created', false )
			->set( 'wp_app_pass_uuid', '' )
			->set( 'plugin_password', '' )
			->save();

		$this->redirect_with_success( 'App Password revoked.' );
	}

	protected function validate_generate_nonce( bool $bypass ): void {
		if ( ! $bypass ) {
			if ( ! isset( $_POST['nuclen_generate_app_password_nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_generate_app_password_nonce'] ) ), 'nuclen_generate_app_password_action' ) ) {
				$this->redirect_with_error( 'Invalid nonce.' );
			}
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_error( 'Insufficient permissions.' );
		}
	}

	protected function create_app_password(): array {
		// Security fix: Enable special characters for stronger password entropy
		// This significantly increases security against brute force attacks
		$new_password = wp_generate_password( 32, true, true );
		$uuid = wp_generate_uuid4();
		$current_user = wp_get_current_user();
		return array( $new_password, $uuid, $current_user );
	}

	protected function send_credentials_to_saas( string $api_key, string $password, string $uuid, $user ): bool {
		return $this->setup_service->send_app_password(
			array(
				'appApiKey'		=> $api_key,
				'siteUrl'		=> get_site_url(),
				'wpUserLogin'	=> $user->user_login,
				'wpAppPassword' => $password,
				'wpAppPassUuid' => $uuid,
			)
		);
	}

	protected function persist_app_password( string $password, string $uuid ): void {
		$this->settings_repository->set( 'wp_app_pass_created', true )
			->set( 'wp_app_pass_uuid', $uuid )
			->set( 'plugin_password', $password )
			->set( 'connected', true )
			->save();

		$app_setup = get_option( 'nuclear_engagement_setup', array() );
		$app_setup['wp_app_pass_created'] = true;
		$app_setup['wp_app_pass_uuid'] = $uuid;
		$app_setup['plugin_password'] = $password;
		$app_setup['connected'] = true;
		update_option( 'nuclear_engagement_setup', $app_setup );
		wp_cache_delete( 'nuclear_engagement_setup', 'options' );
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
