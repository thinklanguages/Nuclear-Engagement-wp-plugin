<?php
/**
 * File: modules/toc/includes/class-nuclen-toc-headings.php
 *
 * Injects unique IDs into post headings for jump links.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use function nuclen_str_contains as _nc;

final class Nuclen_TOC_Headings {
        public function __construct() {
                add_filter( 'the_content', [ $this, 'nuclen_add_heading_ids' ], 99 );
        }

        /** Back-compat wrapper for legacy callback name. */
        public function nuclen_add_heading_ids( string $content ) : string {
                return $this->add_heading_ids( $content );
        }

        /**
         * Inject IDs into headings that lack them.
         */
        public function add_heading_ids( string $content ) : string {
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
}
