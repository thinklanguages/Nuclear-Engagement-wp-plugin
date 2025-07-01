<?php
declare(strict_types=1);
// File: admin/partials/nuclen-admin-settings.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Settings-page wrapper – loads each tab from its own partial for readability.
 *
 * @package NuclearEngagement\Admin
 */

// Display the admin header
$utils = new \NuclearEngagement\Utils\Utils();
$utils->display_nuclen_page_header();
?>
<div class="wrap nuclen-container">
	<h1><?php esc_html_e( 'Nuclear Engagement Settings', 'nuclear-engagement' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( 'nuclen_settings_nonce', 'nuclen_settings_nonce_field' ); ?>

		<!-- ───── Tabs nav ───── -->
		<div class="nav-tab-wrapper">
			<a id="placement-tab"   href="#placement"  class="nav-tab nav-tab-active"><?php esc_html_e( 'Placement', 'nuclear-engagement' ); ?></a>
			<a id="theme-tab"       href="#theme"      class="nav-tab"><?php esc_html_e( 'Theme', 'nuclear-engagement' ); ?></a>
			<a id="display-tab"     href="#display"    class="nav-tab"><?php esc_html_e( 'Display', 'nuclear-engagement' ); ?></a>
			<a id="optin-tab"       href="#optin"      class="nav-tab"><?php esc_html_e( 'Opt-In', 'nuclear-engagement' ); ?></a>
						<a id="generation-tab"  href="#generation" class="nav-tab"><?php esc_html_e( 'Generation', 'nuclear-engagement' ); ?></a>
						<a id="uninstall-tab"   href="#uninstall"  class="nav-tab"><?php esc_html_e( 'Uninstall', 'nuclear-engagement' ); ?></a>
		</div>

		<?php
		/* Load tab partials. */
		$tabs_dir = plugin_dir_path( __FILE__ ) . 'settings/';
		require $tabs_dir . 'placement.php';
		require $tabs_dir . 'theme.php';
		require $tabs_dir . 'display.php';
		require $tabs_dir . 'optin.php';
				require $tabs_dir . 'generation.php';
				require $tabs_dir . 'uninstall.php';
		?>

		<!-- ───── Save button ───── -->
		<div>
			<?php
			submit_button(
				esc_html__( 'Save Settings', 'nuclear-engagement' ),
				'primary nuclen-button nuclen-button-primary',
				'nuclen_save_settings'
			);
			?>
		</div>
	</form>
</div>
