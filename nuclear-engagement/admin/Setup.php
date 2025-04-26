<?php
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/SetupHandlersTrait.php';

class Setup {

	use SetupHandlersTrait;

	/** @var \NuclearEngagement\Utils */
	private $utils;

	public function __construct() {
		$this->utils = new \NuclearEngagement\Utils();
	}

	public function nuclen_get_utils() {
		return $this->utils;
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

		/* ───── Retrieve & normalise option ───── */
		$raw_setup = get_option( 'nuclear_engagement_setup', array() );

		// Ensure we always have an array with all keys, even if the option was malformed.
		if ( ! is_array( $raw_setup ) ) {
			$raw_setup = array();
		}
		$app_setup = wp_parse_args(
			$raw_setup,
			array(
				'api_key'             => '',
				'connected'           => false,
				'wp_app_pass_created' => false,
				'wp_app_pass_uuid'    => '',
				'plugin_password'     => '',
			)
		);

		$fully_setup = ( ! empty( $app_setup['connected'] ) && ! empty( $app_setup['wp_app_pass_created'] ) );

		/* ───── View-partials directory ───── */
		$views_dir = __DIR__ . '/partials/setup/';

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
