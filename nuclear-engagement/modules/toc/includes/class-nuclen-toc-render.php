<?php
/**
 * File: modules/toc/includes/class-nuclen-toc-render.php
 *
 * Public-facing logic: shortcode, heading-ID filter, asset loading.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

use function nuclen_str_contains as _nc;
use NuclearEngagement\SettingsRepository;

final class Nuclen_TOC_Render {

	private const DEFAULT_STICKY_OFFSET_X = 20;
	private const DEFAULT_STICKY_OFFSET_Y = 20;
	private const DEFAULT_STICKY_MAX_WIDTH = 300;

	private bool $assets_registered = false;
	private int  $scroll_offset     = 72;

	/* ───────────────── bootstrap ─────────────────────────────── */
	public function __construct() {
		add_shortcode( 'nuclear_engagement_toc', [ $this, 'nuclen_toc_shortcode' ] );
		add_filter( 'the_content',               [ $this, 'nuclen_add_heading_ids' ], 99 );

		// i18n for strings inside this class
                add_action( 'init', static function () {
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

	/**
	 * Validate and sanitize heading levels array
	 *
	 * @param mixed $heading_levels Raw heading levels input
	 * @return array Validated array of integers between 2-6
	 */
	private function validate_heading_levels( $heading_levels ) : array {
		// If not an array, try to convert it
		if ( ! is_array( $heading_levels ) ) {
			// If it's a string like "2,3,4", split it
			if ( is_string( $heading_levels ) ) {
				$heading_levels = explode( ',', $heading_levels );
			} else {
				// Default fallback
				return range( 2, 6 );
			}
		}

		// Ensure all values are integers
		$heading_levels = array_map( 'intval', $heading_levels );
		
		// Filter to only valid heading levels (2-6)
		$heading_levels = array_filter( $heading_levels, function( $level ) {
			return $level >= 2 && $level <= 6;
		} );

		// If no valid levels after filtering, use defaults
		if ( empty( $heading_levels ) ) {
			return range( 2, 6 );
		}

		// Ensure unique, sorted values
		$heading_levels = array_unique( $heading_levels );
		sort( $heading_levels );
		
		return array_values( $heading_levels );
	}

	/**
	 * Validate and sanitize shortcode attributes
	 *
	 * @param array $atts Raw shortcode attributes
	 * @return array Validated attributes
	 */
        private function validate_shortcode_atts( array $atts ) : array {
		// Define valid values for each attribute
		$valid_lists = [ 'ul', 'ol' ];
		$valid_booleans = [ 'true', 'false' ];
		$valid_themes = [ 'light', 'dark', 'auto' ];

		// List type validation
		if ( isset( $atts['list'] ) && ! in_array( strtolower( $atts['list'] ), $valid_lists, true ) ) {
			$atts['list'] = 'ul';
		}

		// Boolean validations
		foreach ( [ 'toggle', 'collapsed', 'smooth', 'highlight' ] as $bool_attr ) {
			if ( isset( $atts[ $bool_attr ] ) && ! in_array( strtolower( $atts[ $bool_attr ] ), $valid_booleans, true ) ) {
				$atts[ $bool_attr ] = 'true';
			}
		}

		// Offset validation (ensure it's a positive integer)
		if ( isset( $atts['offset'] ) ) {
			$atts['offset'] = max( 0, min( 500, intval( $atts['offset'] ) ) );
		}

		// Theme validation
		if ( isset( $atts['theme'] ) && ! in_array( strtolower( $atts['theme'] ), $valid_themes, true ) ) {
			$atts['theme'] = 'light';
		}

		// Title sanitization
		if ( isset( $atts['title'] ) ) {
			$atts['title'] = sanitize_text_field( $atts['title'] );
		}

		// Show/hide text sanitization
		if ( isset( $atts['show_text'] ) ) {
			$atts['show_text'] = sanitize_text_field( $atts['show_text'] );
		}
		if ( isset( $atts['hide_text'] ) ) {
			$atts['hide_text'] = sanitize_text_field( $atts['hide_text'] );
		}

                return $atts;
        }

        /**
         * Prepare and sanitize shortcode attributes using plugin settings.
         *
         * @param array             $atts     Raw shortcode attributes.
         * @param SettingsRepository $settings Settings repository instance.
         * @return array Sanitized attributes.
         */
        private function prepare_shortcode_attributes( array $atts, SettingsRepository $settings ) : array {
                $heading_levels = $settings->get_array( 'toc_heading_levels', range( 2, 6 ) );
                $heading_levels = $this->validate_heading_levels( $heading_levels );

                $defaults = [
                        'heading_levels' => $heading_levels,
                        'list'      => 'ul',
                        'title'     => '',
                        'toggle'    => 'true',  'collapsed' => 'false',
                        'smooth'    => 'true',  'highlight' => 'true',
                        'offset'    => 72,      'theme'     => 'light',
                        'show_text' => __( 'Show table of contents', 'nuclear-engagement' ),
                        'hide_text' => __( 'Hide table of contents', 'nuclear-engagement' ),
                ];

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
         * Get the translated TOC title with a fallback.
         *
         * @param SettingsRepository $settings Settings repository.
         * @return string Sanitized title.
         */
        private function get_toc_title( SettingsRepository $settings ) : string {
                $title = $settings->get_string( 'toc_title' );
                if ( empty( $title ) ) {
                        return esc_html__( 'Table of Contents', 'nuclen-toc-shortcode' );
                }
                return esc_html( $title );
        }

        /**
         * Build wrapper classes and sticky attributes for the TOC container.
         *
         * @param array             $atts     Shortcode attributes.
         * @param SettingsRepository $settings Settings repository instance.
         * @return array {
         *     @type array  $classes      Array of wrapper classes.
         *     @type string $sticky_attrs Sticky data attributes string.
         *     @type bool   $show_toggle  Whether toggle is enabled.
         *     @type bool   $hidden       Initial hidden state.
         * }
         */
        private function build_wrapper_props( array $atts, SettingsRepository $settings ) : array {
                $classes = [ 'nuclen-toc-wrapper' ];

                if ( in_array( $atts['theme'], [ 'dark', 'auto' ], true ) ) {
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

                        $sticky_offset_x = $settings->get_int( 'toc_sticky_offset_x', self::DEFAULT_STICKY_OFFSET_X );
                        $sticky_offset_y = $settings->get_int( 'toc_sticky_offset_y', self::DEFAULT_STICKY_OFFSET_Y );
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

                // z-index not currently output but preserved for future use.
                $z_index = $settings->get_int( 'toc_z_index', 100 );
                $z_index = max( 1, min( 9999, $z_index ) );
                ( $z_index );

                return [
                        'classes'      => $classes,
                        'sticky_attrs' => $sticky_attrs,
                        'show_toggle'  => $show_toggle,
                        'hidden'       => $hidden,
                ];
        }

        /**
         * Build the toggle button HTML if toggling is enabled.
         */
        private function build_toggle_button( bool $show, bool $hidden, array $atts, string $nav_id ) : string {
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
         */
        private function render_headings_list( array $heads, string $list ) : string {
                $out   = '';
                $stack = [];
                foreach ( $heads as $h ) {
                        $l = $h['level'];
                        while ( $stack && end( $stack ) > $l ) {
                                $out .= '</li></' . $list . '>';
                                array_pop( $stack );
                        }
                        if ( ! $stack || end( $stack ) < $l ) {
                                $out .= '<' . $list . '>';
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

	/* ───────────────── shortcode handler ─────────────────────── */
        public function nuclen_toc_shortcode( array $atts ) : string {
                global $post;
                if ( empty( $post ) ) { return ''; }

                $settings = SettingsRepository::get_instance();
                $atts     = $this->prepare_shortcode_attributes( $atts, $settings );

                $list  = ( strtolower( $atts['list'] ) === 'ol' ) ? 'ol' : 'ul';
                $heads = Nuclen_TOC_Utils::extract( $post->post_content, $atts['heading_levels'] );
                if ( ! $heads ) {
                        return '';
                }

                $this->enqueue_assets( $atts );

                $toc_title = $this->get_toc_title( $settings );
                if ( empty( $atts['title'] ) ) {
                        $atts['title'] = $toc_title;
                }

                $nav_id = esc_attr( wp_unique_id( 'nuclen-toc-' ) );

                $wrapper = $this->build_wrapper_props( $atts, $settings );
                $classes = $wrapper['classes'];
                $sticky  = $wrapper['sticky_attrs'];
                $show    = $wrapper['show_toggle'];
                $hidden  = $wrapper['hidden'];

                $out = '<section id="' . esc_attr( $nav_id ) . '-wrapper" class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $sticky . '>';

                if ( ! empty( $atts['sticky'] ) ) {
                        $out .= '<div class="nuclen-toc-content">';
                }

                $out .= $this->build_toggle_button( $show, $hidden, $atts, $nav_id );

                $out .= '<nav id="' . $nav_id . '" class="nuclen-toc" aria-label="' .
                        esc_attr__( $toc_title, 'nuclen-toc-shortcode' ) . '"' .
                        ( $hidden ? ' style="display:none"' : '' ) .
                        ( $atts['highlight'] === 'true' ? ' data-highlight="true"' : '' ) . '>';

                if ( $atts['title'] !== '' ) {
                        $out .= '<strong class="toc-title">' . esc_html__( $atts['title'], 'nuclen-toc-shortcode' ) . '</strong>';
                }

                $out .= $this->render_headings_list( $heads, $list );

                if ( ! empty( $atts['sticky'] ) ) {
                        $out .= '</div>';
                }

                $out .= '</section>';

                if ( ! wp_script_is( 'nuclen-toc-front', 'enqueued' ) ) {
                        wp_enqueue_script( 'nuclen-toc-front' );
                }

                return $out;
        }

	/* ───────────────── heading-ID injector ───────────────────── */
	public function add_heading_ids( string $content ) : string {
		// Allow developers to bypass if they want to manage IDs themselves.
		if ( ! apply_filters( 'nuclen_toc_enable_heading_ids', true ) ) {
			return $content;
		}

		if ( ! _nc( $content, '<h' ) ) { return $content; }

		foreach ( Nuclen_TOC_Utils::extract( $content, range( 1, 6 ) ) as $h ) {
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
		$off = max( 0, min( 500, (int) $a['offset'] ) );
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
			'show' => __( 'Show', 'nuclen-toc-shortcode' ),
		] );
	}
}