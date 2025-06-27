<?php
/**
 * File: modules/toc/includes/Nuclen_TOC_Render.php
 *
 * Public-facing shortcode handler for the TOC module.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

use NuclearEngagement\Core\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

	/**
	 * Handle the [nuclear_engagement_toc] shortcode output.
	 */
final class Nuclen_TOC_Render {
	/**
	 * Assets manager instance.
	 *
	 * @var Nuclen_TOC_Assets
	 */
private Nuclen_TOC_Assets $assets;

/** Heading extractor instance. */
	private HeadingExtractor $extractor;

	/**
	 * View helper instance.
	 *
	 * @var Nuclen_TOC_View
	 */
	private Nuclen_TOC_View $view;

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/** Class constructor. */
public function __construct( SettingsRepository $settings ) {
$this->assets   = new Nuclen_TOC_Assets();
$this->view     = new Nuclen_TOC_View();
$this->settings = $settings;
	$this->extractor = new HeadingExtractor();

		add_shortcode( 'nuclear_engagement_toc', array( $this, 'nuclen_toc_shortcode' ) );

		// i18n for strings inside this class.
		add_action(
			'init',
			static function () {
				load_plugin_textdomain(
					'nuclen-toc-shortcode',
					false,
					dirname( plugin_basename( NUCLEN_TOC_DIR ) ) . '/languages'
				);
			}
		);
	}

	/**
	 * Sanitize and sort heading levels.
	 *
	 * @param array|string $heading_levels Provided heading levels.
	 * @return array Sanitized heading levels array.
	 */
	private function validate_heading_levels( $heading_levels ): array {
		if ( ! is_array( $heading_levels ) ) {
			if ( is_string( $heading_levels ) ) {
				$heading_levels = explode( ',', $heading_levels );
			} else {
				return range( 2, 6 );
			}
		}
		$heading_levels = array_map( 'intval', $heading_levels );
		$heading_levels = array_filter(
			$heading_levels,
			static function ( $level ) {
				return $level >= 2 && $level <= 6;
			}
		);
		if ( empty( $heading_levels ) ) {
			return range( 2, 6 );
		}
		$heading_levels = array_unique( $heading_levels );
		sort( $heading_levels );
		return array_values( $heading_levels );
	}

	/**
	 * Sanitize shortcode attributes.
	 *
	 * @param array $atts Raw shortcode attributes.
	 * @return array Validated attributes.
	 */
	private function validate_shortcode_atts( array $atts ): array {
		$valid_lists    = array( 'ul', 'ol' );
		$valid_booleans = array( 'true', 'false' );
		$valid_themes   = array( 'light', 'dark', 'auto' );

		if ( isset( $atts['list'] ) && ! in_array( strtolower( $atts['list'] ), $valid_lists, true ) ) {
			$atts['list'] = 'ul';
		}
		foreach ( array( 'toggle', 'collapsed', 'smooth', 'highlight' ) as $bool_attr ) {
			if ( isset( $atts[ $bool_attr ] ) && ! in_array( strtolower( $atts[ $bool_attr ] ), $valid_booleans, true ) ) {
				$atts[ $bool_attr ] = 'true';
			}
		}
		if ( isset( $atts['offset'] ) ) {
			$atts['offset'] = max( 0, min( 500, intval( $atts['offset'] ) ) );
		}
		if ( isset( $atts['theme'] ) && ! in_array( strtolower( $atts['theme'] ), $valid_themes, true ) ) {
			$atts['theme'] = 'light';
		}
		if ( isset( $atts['title'] ) ) {
			$atts['title'] = sanitize_text_field( $atts['title'] );
		}
		if ( isset( $atts['show_text'] ) ) {
			$atts['show_text'] = sanitize_text_field( $atts['show_text'] );
		}
		if ( isset( $atts['hide_text'] ) ) {
			$atts['hide_text'] = sanitize_text_field( $atts['hide_text'] );
		}
		return $atts;
	}

	/**
	 * Merge defaults with shortcode attributes and settings.
	 *
	 * @param array              $atts     Shortcode attributes.
	 * @param SettingsRepository $settings Settings API wrapper.
	 * @return array Prepared attributes.
	 */
	private function prepare_shortcode_attributes( array $atts, SettingsRepository $settings ): array {
		$heading_levels = $settings->get_array( 'toc_heading_levels', range( 2, 6 ) );
		$heading_levels = $this->validate_heading_levels( $heading_levels );

		$defaults = array(
			'heading_levels' => $heading_levels,
			'list'           => 'ul',
			'title'          => '',
			'toggle'         => 'true',
			'collapsed'      => 'false',
			'smooth'         => 'true',
			'highlight'      => 'true',
			'offset'         => Nuclen_TOC_Assets::DEFAULT_SCROLL_OFFSET,
			'theme'          => 'light',
			'show_text'      => __( 'Show table of contents', 'nuclear-engagement' ),
			'hide_text'      => __( 'Hide table of contents', 'nuclear-engagement' ),
		);

		$atts = shortcode_atts( $defaults, array_intersect_key( $atts, $defaults ), 'nuclear_engagement_toc' );
		$atts = $this->validate_shortcode_atts( $atts );

		if ( isset( $atts['heading_levels'] ) ) {
				$atts['heading_levels'] = $this->validate_heading_levels( $atts['heading_levels'] );
		}
		if ( ! isset( $atts['sticky'] ) ) {
				$atts['sticky'] = $settings->get_bool( 'toc_sticky' );
		}
		return $atts;
	}

	/**
	 * Shortcode callback for rendering the table of contents.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Generated HTML markup.
	 */
	public function nuclen_toc_shortcode( array $atts ): string {
			$post = get_post( get_the_ID() );
		if ( null === $post ) {
				return '';
		}

			$settings = $this->settings;
			$atts     = $this->prepare_shortcode_attributes( $atts, $settings );

			$list  = ( strtolower( $atts['list'] ) === 'ol' ) ? 'ol' : 'ul';
$heads = $this->extractor->extract( $post->post_content, $atts['heading_levels'], $post->ID );
		if ( ! $heads ) {
			return '';
		}

			$this->assets->enqueue( $atts );

			$toc_title = $this->view->get_toc_title( $settings );
		if ( empty( $atts['title'] ) ) {
			$atts['title'] = $toc_title;
		}

			$nav_id  = esc_attr( wp_unique_id( 'nuclen-toc-' ) );
			$wrapper = $this->view->build_wrapper_props( $atts, $settings );
			$classes = $wrapper['classes'];
			$sticky  = $wrapper['sticky_attrs'];
			$show    = $wrapper['show_toggle'];
			$hidden  = $wrapper['hidden'];

			$theme = $settings->get_string( 'theme', 'bright' );
			$out   = '<div class="nuclen-root" data-theme="' . esc_attr( $theme ) . '">';
			$out  .= sprintf(
				'<section id="%s-wrapper" class="%s"%s>',
				esc_attr( $nav_id ),
				esc_attr( implode( ' ', $classes ) ),
				$sticky
			);

		if ( ! empty( $atts['sticky'] ) ) {
			$out .= '<div class="nuclen-toc-content">';
		}

		$out .= $this->view->build_toggle_button( $show, $hidden, $atts, $nav_id );
		$out .= $this->view->build_nav_markup( $heads, $list, $atts, $nav_id, $toc_title, $hidden );

		if ( ! empty( $atts['sticky'] ) ) {
			$out .= '</div>';
		}

		$out .= '</section></div>';

		if ( ! wp_script_is( 'nuclen-toc-front', 'enqueued' ) ) {
			wp_enqueue_script( 'nuclen-toc-front' );
		}

		return $out;
	}
}
