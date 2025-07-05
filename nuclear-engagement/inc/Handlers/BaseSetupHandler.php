<?php
/**
 * BaseSetupHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Handlers
 */

namespace NuclearEngagement\Handlers;

use NuclearEngagement\Services\SetupService;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Traits\Security\SetupSecurityTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseSetupHandler {
	use SetupSecurityTrait;

	protected SetupService $setup_service;
	protected SettingsRepository $settings_repository;

	public function __construct( SetupService $setup_service, SettingsRepository $settings_repository ) {
		$this->setup_service       = $setup_service;
		$this->settings_repository = $settings_repository;
	}

	protected function create_app_password(): array {
		$new_password = \NuclearEngagement\Utils\SecurityUtils::generate_secure_password( 32, true );
		$uuid         = \NuclearEngagement\Utils\SecurityUtils::generate_uuid();
		$current_user = wp_get_current_user();
		return array( $new_password, $uuid, $current_user );
	}

	protected function send_credentials_to_saas( string $api_key, string $password, string $uuid, $user ): bool {
		return $this->setup_service->send_app_password(
			array(
				'appApiKey'     => $api_key,
				'siteUrl'       => get_site_url(),
				'wpUserLogin'   => $user->user_login,
				'wpAppPassword' => $password,
				'wpAppPassUuid' => $uuid,
			)
		);
	}

	protected function clear_app_password_data(): void {
		$this->settings_repository->set( 'wp_app_pass_created', false )
			->set( 'wp_app_pass_uuid', '' )
			->set( 'plugin_password', '' )  // Clear the plain text password used by SaaS.
			->save();

		$app_setup                        = get_option( 'nuclear_engagement_setup', array() );
		$app_setup['wp_app_pass_created'] = false;
		$app_setup['wp_app_pass_uuid']    = '';
		$app_setup['plugin_password']     = '';  // Clear the plain text password used by SaaS.
		update_option( 'nuclear_engagement_setup', $app_setup );
		wp_cache_delete( 'nuclear_engagement_setup', 'options' );
	}
}
