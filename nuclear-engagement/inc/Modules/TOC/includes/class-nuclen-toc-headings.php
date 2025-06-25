<?php
/**
 * Injects unique IDs into post headings for jump links.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds unique IDs to headings within post content.
 */
final class Nuclen_TOC_Headings {
        /**
         * Hook into content filters.
         */
    public function __construct() {
            add_filter( 'the_content', array( $this, 'nuclen_add_heading_ids' ), 99 );
    }

        /**
         * Back-compat wrapper for legacy callback name.
         *
         * @param string $content Post content to filter.
         * @return string Filtered content.
         */
    public function nuclen_add_heading_ids( string $content ): string {
            return $this->add_heading_ids( $content );
    }

        /**
         * Inject IDs into headings that lack them.
         *
         * @param string $content HTML content to modify.
         * @return string Modified HTML content.
         */
    public function add_heading_ids( string $content ): string {
        if ( ! apply_filters( 'nuclen_toc_enable_heading_ids', true ) ) {
                return $content;
        }
        if ( ! nuclen_str_contains( $content, '<h' ) ) {
            return $content; }

        foreach ( Nuclen_TOC_Utils::extract( $content, range( 1, 6 ) ) as $h ) {
            $pat         = sprintf(
                '/(<%1$s\b(?![^>]*\bid=)[^>]*>)(%2$s)(<\/%1$s>)/is',
                $h['tag'],
                preg_quote( $h['inner'], '/' )
            );
                $rep     = sprintf(
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
