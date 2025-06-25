<?php
declare(strict_types=1);
/**
 * File: admin/Setup.php
 *
 * Renders the Setup page for Nuclear Engagement.
 * Step 1 – enter Gold Code  Step 2 – generate the plugin App Password.
 *
 * Split into smaller view-partials for readability; no logic removed.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Services\SetupService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Setup {

		use SetupHandlersTrait;

		/** @var \NuclearEngagement\Utils */
		private $utils;

		/** @var SetupService */
		private $setup_service;

	public function __construct() {
			$this->utils         = new \NuclearEngagement\Utils();
			$this->setup_service = new SetupService();
	}

	public function nuclen_get_utils() {
			return $this->utils;
	}

	public function nuclen_get_setup_service(): SetupService {
			return $this->setup_service;
	}

	/** Add the Setup submenu page. */
	public function nuclen_add_setup_page() {
		add_submenu_page(
			'nuclear-engagement',
			esc_html__( 'Nuclear Engagement – Setup', 'nuclear-engagement' ),
			esc_html__( 'Setup', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-setup',
			array( $this, 'nuclen_render_setup_page' )
		);
	}

	/** Render the Setup screen. */
	public function nuclen_render_setup_page() {

		/* ───── Notices ───── */
		$nuclen_error   = isset( $_GET['nuclen_error'] )
			? sanitize_text_field( wp_unslash( $_GET['nuclen_error'] ) )
			: '';
		$nuclen_success = isset( $_GET['nuclen_success'] )
			? sanitize_text_field( wp_unslash( $_GET['nuclen_success'] ) )
			: '';
		$nonce          = isset( $_GET['_wpnonce'] ) ? sanitize_key( $_GET['_wpnonce'] ) : '';

		if ( $nuclen_error && wp_verify_nonce( $nonce, 'nuclear-engagement-setup' ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $nuclen_error ) . '</p></div>';
		}
		if ( $nuclen_success && wp_verify_nonce( $nonce, 'nuclear-engagement-setup' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $nuclen_success ) . '</p></div>';
		}

				/* ───── Retrieve settings ───── */
				$settings = \NuclearEngagement\Container::getInstance()->get( 'settings' );
		$app_setup        = array(
			'api_key'             => $settings->get_string( 'api_key', '' ),
			'connected'           => $settings->get_bool( 'connected', false ),
			'wp_app_pass_created' => $settings->get_bool( 'wp_app_pass_created', false ),
			'wp_app_pass_uuid'    => $settings->get_string( 'wp_app_pass_uuid', '' ),
			'plugin_password'     => $settings->get_string( 'plugin_password', '' ),
		);

		$fully_setup = ( $app_setup['connected'] && $app_setup['wp_app_pass_created'] );

               /* ───── View-partials directory ───── */
               $views_dir = NUCLEN_PLUGIN_DIR . 'templates/admin/setup/';

		/* ───── Branding header ───── */
		$this->utils->display_nuclen_page_header();

		/* ───── Main container & partials ───── */
		echo '<div class="wrap nuclen-container">';

		require $views_dir . 'header.php';
		require $views_dir . 'step1.php';
		require $views_dir . 'step2.php';
		require $views_dir . 'credits.php';
		require $views_dir . 'support.php';

		echo '</div><!-- /.wrap -->';
	}
}
