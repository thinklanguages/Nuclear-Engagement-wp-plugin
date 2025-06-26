<?php
/**
 * File: modules/toc/includes/Nuclen_TOC_View.php
 *
 * Generates the HTML markup for the front-end TOC.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Core\SettingsRepository;

/**
 * Output builder for the front-end table of contents.
 */
final class Nuclen_TOC_View {
	private const DEFAULT_STICKY_OFFSET_X  = 20;
	private const DEFAULT_STICKY_OFFSET_Y  = 20;
	private const DEFAULT_STICKY_MAX_WIDTH = 300;

	/**
	 * Retrieve the translated TOC title with a fallback.
	 *
	 * @param SettingsRepository $settings Plugin settings repository.
	 * @return string Sanitized title string.
	 */
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
	 * @param array              $atts     Shortcode attributes.
	 * @param SettingsRepository $settings Settings repository.
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

		if ( 'true' === $atts['highlight'] ) {
			$classes[] = 'nuclen-has-highlight';
		}

		$z_index = $settings->get_int( 'toc_z_index', 100 );
		$z_index = max( 1, min( 9999, $z_index ) );

			return array(
				'classes'      => $classes,
				'sticky_attrs' => $sticky_attrs,
				'show_toggle'  => $show_toggle,
				'hidden'       => $hidden,
			);
	}

	/**
	 * Build the toggle button HTML if toggling is enabled.
	 *
	 * @param bool   $show    Whether to show the toggle.
	 * @param bool   $hidden  Whether the content is hidden.
	 * @param array  $atts    Shortcode attributes.
	 * @param string $nav_id  Navigation element ID.
	 * @return string Button markup or empty string.
	 */
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

	/**
	 * Render the nested heading list.
	 *
	 * @param array  $heads     Parsed heading data.
	 * @param string $list_tag  Tag name for list wrapper.
	 * @return string HTML list markup.
	 */
	public function render_headings_list( array $heads, string $list_tag ): string {
			$out = '';
		$stack   = array();
		foreach ( $heads as $h ) {
			$l = $h['level'];
			while ( $stack && end( $stack ) > $l ) {
				$out .= '</li></' . $list_tag . '>';
				array_pop( $stack );
			}
			if ( ! $stack || end( $stack ) < $l ) {
				$out    .= '<' . $list_tag . '>';
				$stack[] = $l;
			} else {
				$out .= '</li>';
			}
			$out .= '<li><a href="#' . esc_attr( $h['id'] ) . '">' . esc_html( $h['text'] ) . '</a>';
		}
		while ( $stack ) {
			$out .= '</li></' . $list_tag . '>';
			array_pop( $stack );
		}
		return $out;
	}

	/**
	 * Build the navigation markup containing the heading list.
	 *
	 * @param array  $heads     Parsed heading data.
	 * @param string $list_tag  Tag name for list wrapper.
	 * @param array  $atts      Shortcode attributes.
	 * @param string $nav_id    Navigation element ID.
	 * @param string $toc_title Title for accessibility label.
	 * @param bool   $hidden    Whether the list starts hidden.
	 * @return string HTML navigation markup.
	 */
	public function build_nav_markup( array $heads, string $list_tag, array $atts, string $nav_id, string $toc_title, bool $hidden ): string {
			$nav = sprintf(
				'<nav id="%s" class="nuclen-toc" aria-label="%s"%s%s>',
				esc_attr( $nav_id ),
				esc_attr( $toc_title ),
				$hidden ? ' style="display:none"' : '',
				'true' === $atts['highlight'] ? ' data-highlight="true"' : ''
			);

		if ( '' !== $atts['title'] ) {
				$nav .= '<strong class="toc-title">' . esc_html( $atts['title'] ) . '</strong>';
		}

			$nav .= $this->render_headings_list( $heads, $list_tag ) . '</nav>';

		return $nav;
	}
}
