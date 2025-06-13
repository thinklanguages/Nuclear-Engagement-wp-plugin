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
               /* List of attributes with limited allowed values. The first
                * value in each array acts as the default fallback. */
               $rules = [
                       'list'      => [ 'ul', 'ol' ],
                       'toggle'    => [ 'true', 'false' ],
                       'collapsed' => [ 'true', 'false' ],
                       'smooth'    => [ 'true', 'false' ],
                       'highlight' => [ 'true', 'false' ],
                       'theme'     => [ 'light', 'dark', 'auto' ],
               ];

               /* Validate enumerated attributes. */
               foreach ( $rules as $attr => $allowed ) {
                       if ( isset( $atts[ $attr ] ) && ! in_array( strtolower( $atts[ $attr ] ), $allowed, true ) ) {
                               $atts[ $attr ] = $allowed[0];
                       }
               }

               /* Offset validation (ensure within 0-500). */
               if ( isset( $atts['offset'] ) ) {
                       $atts['offset'] = max( 0, min( 500, intval( $atts['offset'] ) ) );
               }

               /* Text sanitisation. */
               foreach ( [ 'title', 'show_text', 'hide_text' ] as $t ) {
                       if ( isset( $atts[ $t ] ) ) {
                               $atts[ $t ] = sanitize_text_field( $atts[ $t ] );
                       }
               }

               return $atts;
       }

	/* ───────────────── shortcode handler ─────────────────────── */
	public function nuclen_toc_shortcode( array $atts ) : string {
		global $post;
		if ( empty( $post ) ) { return ''; }

		// Get settings repository instance
		$settings = SettingsRepository::get_instance();
		
		// Get heading levels from settings with proper type safety
		$heading_levels = $settings->get_array('toc_heading_levels', range(2, 6));
		
		// Validate heading levels
		$heading_levels = $this->validate_heading_levels( $heading_levels );

		$whitelist = [
			'heading_levels' => $heading_levels,
			'list'      => 'ul',
			'title'     => '', // Will be populated from settings
			'toggle'    => 'true',  'collapsed' => 'false',
			'smooth'    => 'true',  'highlight' => 'true',
			'offset'    => 72,      'theme'     => 'light',
			'show_text' => __( 'Show table of contents', 'nuclear-engagement' ),
			'hide_text' => __( 'Hide table of contents', 'nuclear-engagement' ),
		];

		// Merge and validate attributes
		$atts = shortcode_atts( $whitelist, array_intersect_key( $atts, $whitelist ), 'nuclear_engagement_toc' );
		$atts = $this->validate_shortcode_atts( $atts );

		// Override heading_levels if provided in shortcode
		if ( isset( $atts['heading_levels'] ) ) {
			$atts['heading_levels'] = $this->validate_heading_levels( $atts['heading_levels'] );
		}

		// Get list style (already validated)
		$list = ( strtolower( $atts['list'] ) === 'ol' ) ? 'ol' : 'ul';

		// Get headings using the specified heading levels
		$heads = Nuclen_TOC_Utils::extract($post->post_content, $atts['heading_levels']);
		if ( ! $heads ) { 
			// No headings found, don't enqueue any assets
			return ''; 
		}

		// Get sticky setting if not explicitly set in shortcode
		if ( ! isset( $atts['sticky'] ) ) {
			$atts['sticky'] = $settings->get_bool('toc_sticky');
		}

		// Only enqueue assets if we have valid headings to display
		$this->enqueue_assets( $atts );

		// Get TOC title from settings with fallback
		$toc_title = $settings->get_string('toc_title');
		if (empty($toc_title)) {
			$toc_title = esc_html__('Table of Contents', 'nuclen-toc-shortcode');
		} else {
			$toc_title = esc_html($toc_title);
		}
		
		// Set the title from settings if not explicitly set in shortcode
		if (empty($atts['title'])) {
			$atts['title'] = $toc_title;
		}

		/* ---------- build HTML ---------- */
		$nav_id = esc_attr( wp_unique_id( 'nuclen-toc-' ) );
		
		// Initialize wrapper classes
		$wrapper_classes = array( 'nuclen-toc-wrapper' );
		
		// Add theme class if needed
		if ( in_array( $atts['theme'], array( 'dark', 'auto' ), true ) ) {
			$wrapper_classes[] = 'nuclen-toc-' . $atts['theme'];
		}
		
		/**
		 * Determine TOC visibility and toggle behavior
		 * - If toggle is enabled, respect the show/hide setting
		 * - If toggle is disabled, always show the content
		 */
		$show_toggle = $settings->get_bool('toc_show_toggle');
		$is_collapsed = $show_toggle && !$settings->get_bool('toc_show_content');
		$hidden = $is_collapsed; // For backward compatibility
		
		// Add toggle class if enabled
		if ( $show_toggle ) {
			$wrapper_classes[] = 'nuclen-toc-has-toggle';
			if ( $is_collapsed ) {
				$wrapper_classes[] = 'nuclen-toc-collapsed';
			}
		}
		
		// Add sticky class and data attributes if enabled
		$sticky_attrs = '';
		if ( ! empty( $atts['sticky'] ) ) {
			$wrapper_classes[] = 'nuclen-toc-sticky';
			
			// Get sticky offset values from settings with proper type safety
			$sticky_offset_x = $settings->get_int('toc_sticky_offset_x', self::DEFAULT_STICKY_OFFSET_X);
			$sticky_offset_y = $settings->get_int('toc_sticky_offset_y', self::DEFAULT_STICKY_OFFSET_Y);
			$sticky_max_width = $settings->get_int('toc_sticky_max_width', self::DEFAULT_STICKY_MAX_WIDTH);

			// Ensure values are within valid ranges
			$sticky_offset_x = max(0, min(1000, $sticky_offset_x));
			$sticky_offset_y = max(0, min(1000, $sticky_offset_y));
			$sticky_max_width = min(1000, max(100, $sticky_max_width));

			// Add data attributes for JavaScript
			$sticky_attrs = sprintf(
				' data-offset-x="%d" data-offset-y="%d" data-max-width="%d" data-show-content="%s" data-heading-levels="%s"',
				$sticky_offset_x,
				$sticky_offset_y,
				$sticky_max_width,
				$settings->get_bool('toc_show_content') ? 'true' : 'false',
				esc_attr( implode( ',', $atts['heading_levels'] ) )
			);
		}
		
		// Add highlight class if enabled
		if ( $atts['highlight'] === 'true' ) {
			$wrapper_classes[] = 'nuclen-has-highlight';
		}
		
		// Get z-index from settings with proper fallback
		$z_index = $settings->get_int('toc_z_index', 100);
		$z_index = max(1, min(9999, $z_index));

		// Build the output
		$out = '<section id="' . esc_attr( $nav_id ) . '-wrapper" class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"' . $sticky_attrs . '>';
		
		// Add the TOC content wrapper if sticky is enabled
		if ( ! empty( $atts['sticky'] ) ) {
			$out .= '<div class="nuclen-toc-content">';
		}

		// Build the toggle button if enabled in settings
		$toggle_button = '';
		if ( $show_toggle ) {
			$toggle_text = $hidden ? $atts['show_text'] : $atts['hide_text'];
			$toggle_button = sprintf(
				'<button type="button" class="nuclen-toc-toggle" aria-expanded="%s" aria-controls="%s">%s</button>',
				$hidden ? 'false' : 'true',
				esc_attr( $nav_id ),
				esc_html( $toggle_text )
			);
		}

		$out .= $toggle_button;

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

		// Close the content wrapper if sticky is enabled
		if ( ! empty( $atts['sticky'] ) ) {
			$out .= '</div>'; // Close .nuclen-toc-content
		}

		$out .= '</section>';

		// Enqueue the necessary scripts
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