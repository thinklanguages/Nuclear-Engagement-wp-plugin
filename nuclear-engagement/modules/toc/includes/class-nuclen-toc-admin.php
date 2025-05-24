<?php
/**
 * File: modules/toc/includes/class-nuclen-toc-admin.php
 *
 * Settings→Nuclen TOC  — live shortcode generator.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Nuclen_TOC_Admin {

	private string $hook = '';

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	/* ───────────────── menu item ─────────────────────────────── */
	public function menu() : void {
		$this->hook = add_options_page(
			__( 'Nuclen TOC', 'nuclen-toc-shortcode' ),
			__( 'Nuclen TOC', 'nuclen-toc-shortcode' ),
			'manage_options',
			'nuclen-toc-shortcode',
			[ $this, 'page' ]
		);
	}

	/* ───────────────── admin assets ──────────────────────────── */
	public function assets( string $hook ) : void {
		if ( $hook !== $this->hook ) { return; }

		$css_p = NUCLEN_TOC_DIR . 'assets/css/nuclen-toc-admin.css';
		$js_p  = NUCLEN_TOC_DIR . 'assets/js/nuclen-toc-admin.js';

		$css_v = file_exists( $css_p ) ? filemtime( $css_p ) : NUCLEN_ASSET_VERSION;
		$js_v  = file_exists( $js_p )  ? filemtime( $js_p )  : NUCLEN_ASSET_VERSION;

		wp_register_style(
			'nuclen-toc-admin',
			NUCLEN_TOC_URL . 'assets/css/nuclen-toc-admin.css',
			[],
			$css_v
		);
		wp_register_script(
			'nuclen-toc-admin',
			NUCLEN_TOC_URL . 'assets/js/nuclen-toc-admin.js',
			[],
			$js_v,
			true
		);

		wp_localize_script( 'nuclen-toc-admin', 'nuclenTocAdmin', [
			'copy' => __( 'Copy',   'nuclen-toc-shortcode' ),
			'done' => __( 'Copied!', 'nuclen-toc-shortcode' ),
		] );

		wp_enqueue_style(  'nuclen-toc-admin' );
		wp_enqueue_script( 'nuclen-toc-admin' );
	}

	/* ───────────────── page HTML ─────────────────────────────── */
	public function page() : void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		echo '<div class="wrap"><h1>' .
		     esc_html__( 'Nuclen TOC Shortcode Generator', 'nuclen-toc-shortcode' ) . '</h1>';
		echo '<table class="form-table"><tbody>';

		$this->select_row( 'nuclen-min', __( 'Minimum heading level', 'nuclen-toc-shortcode' ), 2 );
		$this->select_row( 'nuclen-max', __( 'Maximum heading level', 'nuclen-toc-shortcode' ), 6 );

		echo '<tr><th>' . esc_html__( 'List type', 'nuclen-toc-shortcode' ) .
		     '</th><td><select id="nuclen-list"><option value="ul">' .
		     esc_html__( 'Unordered', 'nuclen-toc-shortcode' ) . '</option><option value="ol">' .
		     esc_html__( 'Ordered', 'nuclen-toc-shortcode' ) . '</option></select></td></tr>';

		echo '<tr><th><label for="nuclen-title">' . esc_html__( 'Title', 'nuclen-toc-shortcode' ) .
		     '</label></th><td><input id="nuclen-title" class="regular-text" value="' .
		     esc_attr__( 'Table of Contents', 'nuclen-toc-shortcode' ) . '"></td></tr>';

		$this->checkbox_row( 'nuclen-tog', __( 'Collapsible',               'nuclen-toc-shortcode' ), true  );
		$this->checkbox_row( 'nuclen-col', __( 'Start collapsed',           'nuclen-toc-shortcode' ), false );
		$this->checkbox_row( 'nuclen-smo', __( 'Smooth scroll',             'nuclen-toc-shortcode' ), true  );
		$this->checkbox_row( 'nuclen-hil', __( 'Highlight current section', 'nuclen-toc-shortcode' ), true  );

		echo '<tr><th><label for="nuclen-off">' .
		     esc_html__( 'Scroll offset (px)', 'nuclen-toc-shortcode' ) .
		     '</label></th><td><input id="nuclen-off" type="number" min="0" value="72" style="width:80px"></td></tr>';

		echo '</tbody></table><p><strong>' .
		     esc_html__( 'Generated shortcode:', 'nuclen-toc-shortcode' ) .
		     '</strong> <code id="nuclen-shortcode-preview"></code> ' .
		     '<button class="button" id="nuclen-copy">' .
		     esc_html__( 'Copy', 'nuclen-toc-shortcode' ) .
		     '</button></p></div>';
	}

	/* helpers */
	private function select_row( string $id, string $label, int $def ) : void {
		echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) .
		     '</label></th><td><select id="' . esc_attr( $id ) . '">';
		for ( $i = 1; $i <= 6; $i++ ) {
			echo '<option value="' . $i . '"' . selected( $def, $i, false ) . '>H' . $i . '</option>';
		}
		echo '</select></td></tr>';
	}
	private function checkbox_row( string $id, string $label, bool $checked ) : void {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" id="' .
		     esc_attr( $id ) . '"' . checked( $checked, true, false ) . '> ' .
		     esc_html( $label ) . '</label></td></tr>';
	}
}

