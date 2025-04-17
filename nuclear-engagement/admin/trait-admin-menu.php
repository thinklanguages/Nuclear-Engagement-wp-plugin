<?php
namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Admin\Settings;

/**
 * Trait: Admin_Menu
 * The free plugin's admin menu now only has:
 *  - Dashboard
 *  - Settings
 * (The “Generate” and “Setup” pages moved to Pro.)
 */
trait Admin_Menu {

	public function nuclen_add_admin_menu() {
		// Main top-level menu
		add_menu_page(
			__( 'Nuclear Engagement', 'nuclear-engagement' ),
			__( 'Nuclear Engagement', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement',
			[ $this, 'nuclen_display_dashboard' ],
			'dashicons-airplane',
			30
		);

		// Dashboard submenu
		add_submenu_page(
			'nuclear-engagement',
			__( 'Nuclear Engagement - Dashboard', 'nuclear-engagement' ),
			__( 'Dashboard', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement',
			[ $this, 'nuclen_display_dashboard' ]
		);

		// Settings submenu
		$settings = new Settings();
		add_submenu_page(
			'nuclear-engagement',
			__( 'Nuclear Engagement Settings', 'nuclear-engagement' ),
			__( 'Settings', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-settings',
			[ $settings, 'nuclen_display_settings_page' ]
		);
	}

	public function nuclen_display_dashboard() {
		require_once plugin_dir_path( __FILE__ ) . 'Dashboard.php';
	}

	/**
	 * Helper method to render the small stats tables on the Dashboard.
	 *
	 * @param array $data A keyed array of e.g. [ 'Draft' => ['with' => 3, 'without' => 5], ... ]
	 * @return string HTML table
	 */
	public function nuclen_render_dashboard_stats_table( $data ) {
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
