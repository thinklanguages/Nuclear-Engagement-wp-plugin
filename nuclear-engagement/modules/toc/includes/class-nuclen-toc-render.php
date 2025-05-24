<?php
/**
 * File: modules/toc/includes/class-nuclen-toc-render.php
 *
 * Public-facing logic: shortcode, heading-ID filter, asset loading.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

use function nuclen_str_contains as _nc;

final class Nuclen_TOC_Render {

	private bool $assets_registered = false;
	private int  $scroll_offset     = 72;

	/* ───────────────── bootstrap ─────────────────────────────── */
	public function __construct() {
		add_shortcode( 'nuclear_engagement_toc', [ $this, 'nuclen_toc_shortcode' ] );
		add_filter( 'the_content',               [ $this, 'nuclen_add_heading_ids' ], 99 );

		// i18n for strings inside this class
		add_action( 'plugins_loaded', static function () {
			load_plugin_textdomain(
				'nuclen-toc-shortcode',
				false,
				dirname( plugin_basename( NUCLEN_TOC_DIR ) ) . '/languages'
			);
		} );
	}

	/**
	 * Back-compat wrapper for the legacy filter callback name.
	 *
	 * @param string $content Post content.
	 * @return string         Content with injected IDs.
	 */
	public function nuclen_add_heading_ids( string $content ) : string {
		return $this->add_heading_ids( $content );
	}

	/* ───────────────── shortcode handler ─────────────────────── */
	public function nuclen_toc_shortcode( array $atts ) : string {
		global $post;
		if ( empty( $post ) ) { return ''; }

		$whitelist = [
			'min_level' => 2, 'max_level' => 6, 'list' => 'ul',
			'title'     => '', // Will be populated from settings
			'toggle'    => 'true',  'collapsed' => 'false',
			'smooth'    => 'true',  'highlight' => 'true',
			'offset'    => 72,      'theme'     => 'light',
		];
		$atts = shortcode_atts( $whitelist, array_intersect_key( $atts, $whitelist ), 'nuclear_engagement_toc' );

		$min  = max( 1, min( 6, (int) $atts['min_level'] ) );
		$max  = max( $min, min( 6, (int) $atts['max_level'] ) );
		$list = ( strtolower( $atts['list'] ) === 'ol' ) ? 'ol' : 'ul';

		$heads = Nuclen_TOC_Utils::extract( $post->post_content, $min, $max );
		if ( ! $heads ) { 
			// No headings found, don't enqueue any assets
			return ''; 
		}

		// Only enqueue assets if we have valid headings to display
		$this->enqueue_assets( $atts );

		$ne_settings = get_option( 'nuclear_engagement_settings', array() );
		$toc_title   = isset( $ne_settings['toc_title'] ) && $ne_settings['toc_title'] !== ''
			? esc_html( $ne_settings['toc_title'] )
			: esc_html__( 'Table of Contents', 'nuclen-toc-shortcode' );
		
		// Set the title from settings if not explicitly set in shortcode
		if ( empty( $atts['title'] ) ) {
			$atts['title'] = $toc_title;
		}
		/* ---------- build HTML ---------- */
		$nav_id = esc_attr( wp_unique_id( 'nuclen-toc-' ) );
		$hidden = ( $atts['toggle'] === 'true' && $atts['collapsed'] === 'true' );
		$theme  = in_array( $atts['theme'], [ 'dark', 'auto' ], true ) ? ' nuclen-toc-' . $atts['theme'] : '';

		$out  = '<section class="nuclen-toc-wrapper' . $theme .
		        ( $atts['highlight'] === 'true' ? ' nuclen-has-highlight' : '' ) . '">';

		if ( $atts['toggle'] === 'true' ) {
			$exp = $hidden ? 'false' : 'true';
			$lbl = $hidden ? __( 'Show table of contents', 'nuclen-toc-shortcode' ) : __( 'Hide', 'nuclen-toc-shortcode' );
			$out .= '<button type="button" class="nuclen-toc-toggle" aria-controls="' . $nav_id .
			        '" aria-expanded="' . esc_attr( $exp ) . '">' . esc_html( $lbl ) . '</button>';
		}

		$out .= '<nav id="' . $nav_id . '" class="nuclen-toc" aria-label="' .
		        esc_attr__( $toc_title, 'nuclen-toc-shortcode' ) . '"' .
		        ( $hidden ? ' style="display:none"' : '' ) .
		        ( $atts['highlight'] === 'true' ? ' data-highlight="true"' : '' ) . '>';

		if ( $atts['title'] !== '' ) {
			$out .= '<strong class="toc-title">' . esc_html__( $atts['title'], 'nuclen-toc-shortcode' ) . '</strong>';
		}

		/* nested list */
		$stack = [];
		foreach ( $heads as $h ) {
			$l = $h['level'];
			while ( $stack && end( $stack ) > $l ) { $out .= '</li></' . $list . '>'; array_pop( $stack ); }
			if ( ! $stack || end( $stack ) < $l ) { $out .= '<' . $list . '>'; $stack[] = $l; }
			else { $out .= '</li>'; }

			$out .= '<li><a href="#' . esc_attr( $h['id'] ) . '">' . esc_html( $h['text'] ) . '</a>';
		}
		while ( $stack ) { $out .= '</li></' . $list . '>'; array_pop( $stack ); }

		return $out . '</nav></section>';
	}

	/* ───────────────── heading-ID injector ───────────────────── */
	public function add_heading_ids( string $content ) : string {
		// Allow developers to bypass if they want to manage IDs themselves.
		if ( ! apply_filters( 'nuclen_toc_enable_heading_ids', true ) ) {
			return $content;
		}

		if ( ! _nc( $content, '<h' ) ) { return $content; }

		foreach ( Nuclen_TOC_Utils::extract( $content, 1, 6 ) as $h ) {
			$pat = sprintf(
				'/(<%1$s\b(?![^>]*\bid=)[^>]*>)(%2$s)(<\/%1$s>)/is',
				$h['tag'],
				preg_quote( $h['inner'], '/' )
			);
			$rep = sprintf(
				'<%1$s id="%2$s">%3$s</%1$s>',
				$h['tag'],
				esc_attr( $h['id'] ),
				$h['inner']
			);

			$content = preg_replace( $pat, $rep, $content, 1 );
		}
		return $content;
	}

	/* ───────────────── assets ─────────────────────────────────── */
	private function enqueue_assets( array $a ) : void {
		// Only proceed if we're on a singular post/page where the TOC is being displayed
		if ( ! is_singular() ) {
			return;
		}

		// Register assets if not already registered
		if ( ! $this->assets_registered ) {
			$this->register_assets();
		}

		// Enqueue the CSS
		wp_enqueue_style( 'nuclen-toc-front' );

		/* JS only when interactive features are on */
		if ( $a['toggle'] === 'true' || $a['highlight'] === 'true' ) {
			wp_enqueue_script( 'nuclen-toc-front' );
		}

		/* runtime CSS tweaks */
		$off = max( 0, (int) $a['offset'] );
		if ( $off !== $this->scroll_offset ) {
			wp_add_inline_style( 'nuclen-toc-front', ':root{--nuclen-toc-offset:' . $off . 'px}' );
			$this->scroll_offset = $off;
		}
		if ( $a['smooth'] === 'true' ) {
			wp_add_inline_style( 'nuclen-toc-front', 'html{scroll-behavior:smooth}' );
		}
	}

	private function register_assets() : void {
		if ( $this->assets_registered ) { 
			return; 
		}
		$this->assets_registered = true;

		$css_p = NUCLEN_TOC_DIR . 'assets/css/nuclen-toc-front.css';
		$js_p  = NUCLEN_TOC_DIR . 'assets/js/nuclen-toc-front.js';

		$css_v = file_exists( $css_p ) ? filemtime( $css_p ) : NUCLEN_ASSET_VERSION;
		$js_v  = file_exists( $js_p )  ? filemtime( $js_p )  : NUCLEN_ASSET_VERSION;

		// Register the CSS with a high priority to ensure it loads after theme styles
		wp_register_style(
			'nuclen-toc-front',
			NUCLEN_TOC_URL . 'assets/css/nuclen-toc-front.css',
			[],
			$css_v
		);

		// Register the script in the footer
		wp_register_script(
			'nuclen-toc-front',
			NUCLEN_TOC_URL . 'assets/js/nuclen-toc-front.js',
			[],
			$js_v,
			true // Load in footer
		);

		// Localize the script with translated strings
		wp_localize_script( 'nuclen-toc-front', 'nuclenTocL10n', [
			'hide' => __( 'Hide', 'nuclen-toc-shortcode' ),
			'show' => __( 'Show table of contents', 'nuclen-toc-shortcode' ),
		] );
	}
}

