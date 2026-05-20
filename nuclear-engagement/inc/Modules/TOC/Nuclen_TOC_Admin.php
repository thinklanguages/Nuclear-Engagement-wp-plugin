<?php
/**
 * Settings→Nuclen TOC — live shortcode generator.
 *
 * @package    NuclearEngagement
 * @subpackage TOC
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

use NuclearEngagement\Core\AssetVersions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page for generating TOC shortcodes.
 *
 * @package NuclearEngagement\TOC
 */
final class Nuclen_TOC_Admin {

	/**
	 * Hook suffix for the options page.
	 *
	 * @var string|false
	 */
	private string|false $hook = '';

	/**
	 * Register admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Register the settings page.
	 *
	 * @return void
	 */
	public function menu(): void {
		$this->hook = add_options_page(
			__( 'Nuclen TOC', 'nuclear-engagement' ),
			__( 'Nuclen TOC', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement',
			array( $this, 'page' )
		);
	}

	/**
	 * Enqueue scripts and styles for the admin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}

		$css_p = NUCLEN_TOC_DIR . 'assets/css/nuclen-toc-admin.css';
		$js_p  = NUCLEN_TOC_DIR . 'assets/js/nuclen-toc-admin.js';

		$css_v = AssetVersions::get( 'toc_admin_css' );
		$js_v  = AssetVersions::get( 'toc_admin_js' );

		wp_register_style(
			'nuclen-toc-admin',
			NUCLEN_TOC_URL . 'assets/css/nuclen-toc-admin.css',
			array(),
			$css_v
		);

		wp_register_script(
			'nuclen-toc-admin',
			NUCLEN_TOC_URL . 'assets/js/nuclen-toc-admin.js',
			array(),
			$js_v,
			true
		);
		wp_script_add_data( 'nuclen-toc-admin', 'type', 'module' );

		wp_localize_script(
			'nuclen-toc-admin',
			'nuclenTocAdmin',
			array(
				'copy' => __( 'Copy', 'nuclear-engagement' ),
				'done' => __( 'Copied!', 'nuclear-engagement' ),
			)
		);

		wp_enqueue_style( 'nuclen-toc-admin' );
		wp_enqueue_script( 'nuclen-toc-admin' );
	}

	/**
	 * Render the settings page markup.
	 *
	 * @return void
	 */
	public function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' .
			esc_html__( 'Nuclen TOC Shortcode Generator', 'nuclear-engagement' ) . '</h1>';

		echo '<table class="form-table"><tbody>';
		$this->select_row( 'nuclen-min', __( 'Minimum heading level', 'nuclear-engagement' ), 2 );
		$this->select_row( 'nuclen-max', __( 'Maximum heading level', 'nuclear-engagement' ), 6 );

		echo '<tr><th>' . esc_html__( 'List type', 'nuclear-engagement' ) .
			'</th><td><select id="nuclen-list"><option value="ul">' .
			esc_html__( 'Unordered', 'nuclear-engagement' ) . '</option><option value="ol">' .
			esc_html__( 'Ordered', 'nuclear-engagement' ) . '</option></select></td></tr>';

		echo '<tr><th><label for="nuclen-title">' . esc_html__( 'Title', 'nuclear-engagement' ) .
			'</label></th><td><input id="nuclen-title" class="regular-text" value="' .
			esc_attr__( 'Table of Contents', 'nuclear-engagement' ) . '"></td></tr>';

		$this->checkbox_row( 'nuclen-tog', __( 'Collapsible', 'nuclear-engagement' ), true );
		$this->checkbox_row( 'nuclen-col', __( 'Start collapsed', 'nuclear-engagement' ), false );
		$this->checkbox_row( 'nuclen-smo', __( 'Smooth scroll', 'nuclear-engagement' ), true );
		$this->checkbox_row( 'nuclen-hil', __( 'Highlight current section', 'nuclear-engagement' ), true );

		echo '<tr><th><label for="nuclen-off">' .
			esc_html__( 'Scroll offset (px)', 'nuclear-engagement' ) .
			'</label></th><td><input id="nuclen-off" type="number" min="0" value="' .
			esc_attr( Nuclen_TOC_Assets::DEFAULT_SCROLL_OFFSET ) . '" style="width:80px"></td></tr>';

		echo '</tbody></table><p><strong>' .
			esc_html__( 'Generated shortcode:', 'nuclear-engagement' ) .
			'</strong> <code id="nuclen-shortcode-preview"></code> ' .
			'<button class="button" id="nuclen-copy">' .
			esc_html__( 'Copy', 'nuclear-engagement' ) .
			'</button></p></div>';
	}

	/**
	 * Output a select row for heading levels.
	 *
	 * @param string $id    DOM id for the select element.
	 * @param string $label Row label.
	 * @param int    $def   Default selected heading level.
	 * @return void
	 */
	private function select_row( string $id, string $label, int $def ): void {
		echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) .
			'</label></th><td><select id="' . esc_attr( $id ) . '">';

		for ( $i = 1; $i <= 6; $i++ ) {
			echo '<option value="' . esc_attr( $i ) . '"' . selected( $def, $i, false ) . '>H' .
				esc_html( $i ) . '</option>';
		}

		echo '</select></td></tr>';
	}

	/**
	 * Output a checkbox row.
	 *
	 * @param string $id      DOM id for the checkbox.
	 * @param string $label   Row label.
	 * @param bool   $checked Whether the checkbox should be checked.
	 * @return void
	 */
	private function checkbox_row( string $id, string $label, bool $checked ): void {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" id="' .
			esc_attr( $id ) . '"' . checked( $checked, true, false ) . '> ' .
			esc_html( $label ) . '</label></td></tr>';
	}
}
