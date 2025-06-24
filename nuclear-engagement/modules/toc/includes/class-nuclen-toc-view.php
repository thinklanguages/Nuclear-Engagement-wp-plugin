<?php
declare(strict_types=1);
/**
 * File: modules/toc/includes/class-nuclen-toc-view.php
 *
 * Generates the HTML markup for the front-end TOC.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\SettingsRepository;

final class Nuclen_TOC_View {
	private const DEFAULT_STICKY_OFFSET_X  = 20;
	private const DEFAULT_STICKY_OFFSET_Y  = 20;
	private const DEFAULT_STICKY_MAX_WIDTH = 300;

	/** Retrieve the translated TOC title with a fallback. */
	public function get_toc_title( SettingsRepository $settings ): string {
		$title = $settings->get_string( 'toc_title' );
		if ( empty( $title ) ) {
			return esc_html__( 'Table of Contents', 'nuclen-toc-shortcode' );
		}
		return esc_html( $title );
	}

	/**
	 * Build wrapper classes and sticky attributes.
	 *
	 * @return array{classes:array,sticky_attrs:string,show_toggle:bool,hidden:bool}
	 */
	public function build_wrapper_props( array $atts, SettingsRepository $settings ): array {
		$classes = array( 'nuclen-toc-wrapper' );

		if ( in_array( $atts['theme'], array( 'dark', 'auto' ), true ) ) {
			$classes[] = 'nuclen-toc-' . $atts['theme'];
		}

		$show_toggle = $settings->get_bool( 'toc_show_toggle' );
		$hidden      = $show_toggle && ! $settings->get_bool( 'toc_show_content' );

		if ( $show_toggle ) {
			$classes[] = 'nuclen-toc-has-toggle';
			if ( $hidden ) {
				$classes[] = 'nuclen-toc-collapsed';
			}
		}

		$sticky_attrs = '';
		if ( ! empty( $atts['sticky'] ) ) {
			$classes[] = 'nuclen-toc-sticky';

			$sticky_offset_x  = $settings->get_int( 'toc_sticky_offset_x', self::DEFAULT_STICKY_OFFSET_X );
			$sticky_offset_y  = $settings->get_int( 'toc_sticky_offset_y', self::DEFAULT_STICKY_OFFSET_Y );
			$sticky_max_width = $settings->get_int( 'toc_sticky_max_width', self::DEFAULT_STICKY_MAX_WIDTH );

			$sticky_offset_x  = max( 0, min( 1000, $sticky_offset_x ) );
			$sticky_offset_y  = max( 0, min( 1000, $sticky_offset_y ) );
			$sticky_max_width = min( 1000, max( 100, $sticky_max_width ) );

			$sticky_attrs = sprintf(
				' data-offset-x="%d" data-offset-y="%d" data-max-width="%d" data-show-content="%s" data-heading-levels="%s"',
				$sticky_offset_x,
				$sticky_offset_y,
				$sticky_max_width,
				$settings->get_bool( 'toc_show_content' ) ? 'true' : 'false',
				esc_attr( implode( ',', $atts['heading_levels'] ) )
			);
		}

		if ( $atts['highlight'] === 'true' ) {
			$classes[] = 'nuclen-has-highlight';
		}

		$z_index = $settings->get_int( 'toc_z_index', 100 );
		$z_index = max( 1, min( 9999, $z_index ) );
		( $z_index );

		return array(
			'classes'      => $classes,
			'sticky_attrs' => $sticky_attrs,
			'show_toggle'  => $show_toggle,
			'hidden'       => $hidden,
		);
	}

	/** Build the toggle button HTML if toggling is enabled. */
	public function build_toggle_button( bool $show, bool $hidden, array $atts, string $nav_id ): string {
		if ( ! $show ) {
			return '';
		}
		$toggle_text = $hidden ? $atts['show_text'] : $atts['hide_text'];
		return sprintf(
			'<button type="button" class="nuclen-toc-toggle" aria-expanded="%s" aria-controls="%s">%s</button>',
			$hidden ? 'false' : 'true',
			esc_attr( $nav_id ),
			esc_html( $toggle_text )
		);
	}

	/** Render the nested heading list. */
	public function render_headings_list( array $heads, string $list ): string {
		$out   = '';
		$stack = array();
		foreach ( $heads as $h ) {
			$l = $h['level'];
			while ( $stack && end( $stack ) > $l ) {
				$out .= '</li></' . $list . '>';
				array_pop( $stack );
			}
			if ( ! $stack || end( $stack ) < $l ) {
				$out    .= '<' . $list . '>';
				$stack[] = $l;
			} else {
				$out .= '</li>';
			}
			$out .= '<li><a href="#' . esc_attr( $h['id'] ) . '">' . esc_html( $h['text'] ) . '</a>';
		}
		while ( $stack ) {
			$out .= '</li></' . $list . '>';
			array_pop( $stack );
		}
		return $out;
	}

	/**
	 * Build the navigation markup containing the heading list.
	 */
	public function build_nav_markup( array $heads, string $list, array $atts, string $nav_id, string $toc_title, bool $hidden ): string {
		$nav = sprintf(
			'<nav id="%s" class="nuclen-toc" aria-label="%s"%s%s>',
			esc_attr( $nav_id ),
			esc_attr__( $toc_title, 'nuclen-toc-shortcode' ),
			$hidden ? ' style="display:none"' : '',
			$atts['highlight'] === 'true' ? ' data-highlight="true"' : ''
		);

		if ( $atts['title'] !== '' ) {
			$nav .= '<strong class="toc-title">' . esc_html__( $atts['title'], 'nuclen-toc-shortcode' ) . '</strong>';
		}

		$nav .= $this->render_headings_list( $heads, $list ) . '</nav>';

		return $nav;
	}
}
