<?php
/**
 * File: modules/toc/includes/class-nuclen-toc-render.php
 *
 * Public-facing shortcode handler for the TOC module.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

use NuclearEngagement\SettingsRepository;

final class Nuclen_TOC_Render {
        private Nuclen_TOC_Assets $assets;
        private Nuclen_TOC_View   $view;

        public function __construct() {
                $this->assets = new Nuclen_TOC_Assets();
                $this->view   = new Nuclen_TOC_View();

                add_shortcode( 'nuclear_engagement_toc', [ $this, 'nuclen_toc_shortcode' ] );

                // i18n for strings inside this class
                add_action( 'init', static function () {
                        load_plugin_textdomain(
                                'nuclen-toc-shortcode',
                                false,
                                dirname( plugin_basename( NUCLEN_TOC_DIR ) ) . '/languages'
                        );
                } );
        }

        private function validate_heading_levels( $heading_levels ) : array {
                if ( ! is_array( $heading_levels ) ) {
                        if ( is_string( $heading_levels ) ) {
                                $heading_levels = explode( ',', $heading_levels );
                        } else {
                                return range( 2, 6 );
                        }
                }
                $heading_levels = array_map( 'intval', $heading_levels );
                $heading_levels = array_filter( $heading_levels, static function ( $level ) {
                        return $level >= 2 && $level <= 6;
                } );
                if ( empty( $heading_levels ) ) {
                        return range( 2, 6 );
                }
                $heading_levels = array_unique( $heading_levels );
                sort( $heading_levels );
                return array_values( $heading_levels );
        }

        private function validate_shortcode_atts( array $atts ) : array {
                $valid_lists    = [ 'ul', 'ol' ];
                $valid_booleans = [ 'true', 'false' ];
                $valid_themes   = [ 'light', 'dark', 'auto' ];

                if ( isset( $atts['list'] ) && ! in_array( strtolower( $atts['list'] ), $valid_lists, true ) ) {
                        $atts['list'] = 'ul';
                }
                foreach ( [ 'toggle', 'collapsed', 'smooth', 'highlight' ] as $bool_attr ) {
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

        public function nuclen_toc_shortcode( array $atts ) : string {
                global $post;
                if ( empty( $post ) ) {
                        return '';
                }

                $settings = SettingsRepository::get_instance();
                $atts     = $this->prepare_shortcode_attributes( $atts, $settings );

                $list  = ( strtolower( $atts['list'] ) === 'ol' ) ? 'ol' : 'ul';
                $heads = Nuclen_TOC_Utils::extract( $post->post_content, $atts['heading_levels'] );
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

                $out = sprintf(
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

                $out .= '</section>';

                if ( ! wp_script_is( 'nuclen-toc-front', 'enqueued' ) ) {
                        wp_enqueue_script( 'nuclen-toc-front' );
                }

                return $out;
        }
}
