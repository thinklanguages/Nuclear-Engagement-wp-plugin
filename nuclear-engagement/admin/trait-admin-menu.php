<?php
/**
 * File: admin/trait-admin-menu.php
 *
 * Adds the Nuclear Engagement admin menu and hides the “Generate” page
 * until **both** setup steps are finished (API key + WP App Password).
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
	 * Register top‑level menu and sub‑pages.
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
			esc_html__( 'Nuclear Engagement – Dashboard', 'nuclear-engagement' ),
			esc_html__( 'Dashboard', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement',
			array( $this, 'nuclen_display_dashboard' )
		);

		add_submenu_page(
			'nuclear-engagement',
			esc_html__( 'Nuclear Engagement – Generate Content', 'nuclear-engagement' ),
			esc_html__( 'Generate', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-generate',
			array( $this, 'nuclen_display_generate_page' )
		);

		$settings = new Settings();
		add_submenu_page(
			'nuclear-engagement',
			esc_html__( 'Nuclear Engagement – Settings', 'nuclear-engagement' ),
			esc_html__( 'Settings', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-settings',
			array( $settings, 'nuclen_display_settings_page' )
		);
	}

	/** Dashboard page callback */
	public function nuclen_display_dashboard() {
		include plugin_dir_path( __FILE__ ) . 'Dashboard.php';
	}

	/**
	 * “Generate” page callback.
	 *
	 * Shows only an admin notice until **both** setup steps are done.
	 */
	public function nuclen_display_generate_page() {
		$app_setup = get_option(
			'nuclear_engagement_setup',
			array(
				'connected'           => false,
				'wp_app_pass_created' => false,
			)
		);

		// Block access unless the API key **and** WP App Password are present.
		if ( empty( $app_setup['connected'] ) || empty( $app_setup['wp_app_pass_created'] ) ) {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__(
					'Please finish the plugin setup (Step 1: API key and Step 2: WP App Password) before generating content. Go to the Setup page to complete the configuration.',
					'nuclear-engagement'
				)
				. '</p></div>';
			return;
		}

		include plugin_dir_path( __FILE__ ) . 'partials/nuclen-admin-generate.php';
	}

	/**
	 * Helper: build a small “with / without” stats table.
	 *
	 * @param array $data Stats array.
	 * @return string HTML table.
	 */
	private function nuclen_render_dashboard_stats_table( $data ) {
		if ( empty( $data ) ) {
			return '<p>' . esc_html__( 'No items found.', 'nuclear-engagement' ) . '</p>';
		}

		$html  = '<table class="nuclen-stats-table">';
		$html .= '<tr><th></th><th>' . esc_html__( 'With', 'nuclear-engagement' ) . '</th><th>' . esc_html__( 'Without', 'nuclear-engagement' ) . '</th></tr>';

		foreach ( $data as $name => $counts ) {
			$with = isset($counts['with']) ? (int)$counts['with'] : 0;
			$without = isset($counts['without']) ? (int)$counts['without'] : 0;
			$total = $with + $without;
			if ( $total > 0 ) {
				$html .= '<tr>';
				$html .= '<td>' . esc_html( $name ) . '</td>';
				$html .= '<td>' . $with . '</td>';
				$html .= '<td>' . $without . '</td>';
				$html .= '</tr>';
			}
		}

		$html .= '</table>';
		return $html;
	}
}
