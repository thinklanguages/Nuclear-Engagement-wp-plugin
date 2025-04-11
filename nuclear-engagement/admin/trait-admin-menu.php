<?php
/**
 * File: admin/trait-admin-menu.php
 * Implementation of changes required by WordPress.org guidelines:
 * - Internationalize menu labels.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use NuclearEngagement\Admin\Settings;

trait Admin_Menu {

	/**
	 * Add the plugin admin menu & submenus.
	 */
	public function nuclen_add_admin_menu() {
		add_menu_page(
			esc_html__( 'Nuclear Engagement', 'nuclear-engagement' ),
			esc_html__( 'Nuclear Engagement', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement',
			array( $this, 'nuclen_display_dashboard' ),
			'dashicons-airplane',
			30
		);

		add_submenu_page(
			'nuclear-engagement',
			esc_html__( 'Nuclear Engagement - Dashboard', 'nuclear-engagement' ),
			esc_html__( 'Dashboard', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement',
			array( $this, 'nuclen_display_dashboard' )
		);

		add_submenu_page(
			'nuclear-engagement',
			esc_html__( 'Nuclear Engagement - Generate Content', 'nuclear-engagement' ),
			esc_html__( 'Generate', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-generate',
			array( $this, 'nuclen_display_generate_page' )
		);

		$settings = new Settings();
		add_submenu_page(
			'nuclear-engagement',
			esc_html__( 'Nuclear Engagement Settings', 'nuclear-engagement' ),
			esc_html__( 'Settings', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-settings',
			array( $settings, 'nuclen_display_settings_page' )
		);
	}

	/**
	 * Display the main Dashboard page.
	 */
	public function nuclen_display_dashboard() {
		include plugin_dir_path( __FILE__ ) . 'Dashboard.php';
	}

	/**
	 * Display the "Generate" page.
	 */
	public function nuclen_display_generate_page() {
		$app_setup = get_option( 'nuclear_engagement_setup', array( 'connected' => false ) );
		if ( ! $app_setup['connected'] ) {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'Please complete the plugin setup before generating content. Go to the Setup page.', 'nuclear-engagement' )
				. '</p></div>';
			return;
		}

		include plugin_dir_path( __FILE__ ) . 'partials/nuclen-admin-generate.php';
	}

	/**
	 * Helper to render quiz stats table (example).
	 */
	private function nuclen_render_dashboard_stats_table( $data ) {
		if ( empty( $data ) ) {
			return '<p>' . esc_html__( 'No items found.', 'nuclear-engagement' ) . '</p>';
		}

		$html  = '<table class="nuclen-stats-table">';
		$html .= '<tr><th></th><th>' . esc_html__( 'With', 'nuclear-engagement' ) . '</th><th>' . esc_html__( 'Without', 'nuclear-engagement' ) . '</th></tr>';
		foreach ( $data as $name => $counts ) {
			$total = $counts['with'] + $counts['without'];
			if ( $total > 0 ) {
				$html .= '<tr>';
				$html .= '<td>' . esc_html( $name ) . '</td>';
				$html .= '<td>' . (int) $counts['with'] . '</td>';
				$html .= '<td>' . (int) $counts['without'] . '</td>';
				$html .= '</tr>';
			}
		}
		$html .= '</table>';
		return $html;
	}
}
