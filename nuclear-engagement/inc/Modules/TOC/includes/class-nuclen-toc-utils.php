<?php
/**
 * File: modules/toc/includes/class-nuclen-toc-utils.php
 *
 * Heavy‑lifting utilities:
 *   ▸ heading extraction with skip‑rules
 *   ▸ slug deduplication
 *   ▸ object‑cache wrapper
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Utility helpers for TOC parsing and slug generation.
 */
final class Nuclen_TOC_Utils {

	private const CACHE_GROUP = 'nuclen_toc';
        private const CACHE_TTL   = 6 * HOUR_IN_SECONDS;          // 6 hours.

        /**
         * Last parse duration in milliseconds.
         *
         * @var int
         */
        private static int $last_parse_ms = 0;

       /**
        * Track generated IDs to ensure uniqueness within a post.
        *
        * @var array<string,bool>
        */
       private static array $ids_in_post = array();

	/**
	 * Parse H1–H6 headings from raw HTML, respecting heading levels array,
	 * .no‑toc class, data‑toc="false", and cache the result.
	 *
	 * @param string $html The HTML content to parse for headings.
	 * @param array  $heading_levels Array of specific heading levels to include (e.g., [2, 4] for H2 and H4 only).
	 *
	 * @return array[] [
	 *     'tag'   => 'h2',
	 *     'level' => 2,
	 *     'text'  => 'Heading text',
	 *     'inner' => 'Heading <em>HTML</em>',
	 *     'id'    => 'slugified-id'
	 * ]
	 */
        public static function extract( string $html, array $heading_levels ): array {
                $t0 = microtime( true );

                // If no specific levels provided, use defaults (2-6).
                if ( empty( $heading_levels ) ) {
                        $heading_levels = range( 2, 6 );
                }

		// Sanitize and validate heading levels.
		$heading_levels = array_filter(
			array_map( 'intval', $heading_levels ),
			function ( $level ) {
				return $level >= 1 && $level <= 6;
			}
		);

		// If still no valid levels, use defaults (2-6).
		if ( empty( $heading_levels ) ) {
			$heading_levels = range( 2, 6 );
		}

		// Sort and make unique.
		sort( $heading_levels );
		$heading_levels = array_unique( $heading_levels );

                // Generate cache key based on content and heading levels.
                $key          = md5( $html ) . '_' . implode( '', $heading_levels );
                $transient    = 'nuclen_toc_' . $key;
                $hit = wp_cache_get( $key, self::CACHE_GROUP );
                if ( false === $hit ) {
                        $hit = get_transient( $transient );
                }

               if ( false !== $hit ) {
                       self::$ids_in_post = array_fill_keys( wp_list_pluck( $hit, 'id' ), true );
                       self::$last_parse_ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
                       return $hit;
               }

               $out               = array();
               self::$ids_in_post = array();

                if ( nuclen_str_contains( $html, '<h' ) ) {
                        libxml_use_internal_errors( true );
                        $dom = new \DOMDocument();
                        $dom->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html, \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD );
                        libxml_clear_errors();

                        $xpath = new \DOMXPath( $dom );
                        $nodes = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );

                        foreach ( $nodes as $node ) {
                                $tag = strtolower( $node->nodeName );
                                $lvl = (int) substr( $tag, 1 );

                                // Skip if not in allowed levels or has skip classes/attributes.
                                if ( ! in_array( $lvl, $heading_levels, true ) ) {
                                        continue;
                                }
                                if ( preg_match( '/\bno-?toc\b/i', $node->getAttribute( 'class' ) ) ) {
                                        continue;
                                }
                                $data_toc = $node->getAttribute( 'data-toc' );
                                if ( '' !== $data_toc && strtolower( $data_toc ) === 'false' ) {
                                        continue;
                                }

                                $inner = self::inner_html( $node );
                                $text  = trim( wp_strip_all_tags( $inner ) );
                                if ( '' === $text ) {
                                        continue;
                                }

                                $id = $node->hasAttribute( 'id' )
                                        ? sanitize_html_class( $node->getAttribute( 'id' ) )
                                        : self::unique_id_from_text( $text );

                                $out[] = array(
                                        'tag'   => $tag,
                                        'level' => $lvl,
                                        'text'  => $text,
                                        'inner' => $inner,
                                        'id'    => $id,
                                );
                        }
                }

                wp_cache_set( $key, $out, self::CACHE_GROUP, self::CACHE_TTL );
                set_transient( $transient, $out, self::CACHE_TTL );
                self::$last_parse_ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
                return $out;
        }

		/*
		 * ----------------------------------------------------------
		 * Helpers
		 * ----------------------------------------------------------
		 */

		/**
		 * Generate a unique slug from heading text.
		 *
		 * @param string $txt Heading text.
		 * @return string Unique slug.
		 */
       private static function unique_id_from_text( string $txt ): string {
               $base = sanitize_title( $txt );
               $id   = $base;
               $n    = 2;

               while ( isset( self::$ids_in_post[ $id ] ) ) {
                       $id = $base . '-' . ( $n++ );
               }

               self::$ids_in_post[ $id ] = true;
               return $id;
       }

        /**
         * Retrieve the inner HTML of a DOM element.
         *
         * @param \DOMElement $el Element to extract HTML from.
         * @return string Inner HTML markup.
         */
        private static function inner_html( \DOMElement $el ): string {
                $html = '';
                foreach ( $el->childNodes as $child ) {
                        $html .= $el->ownerDocument->saveHTML( $child );
                }
                return $html;
        }

        /**
         * Get the duration of the last parse in milliseconds.
         */
        public static function get_last_parse_ms(): int {
                return self::$last_parse_ms;
        }

       /**
        * Clear cached headings for a post.
        *
        * @param int $post_id Post ID to clear cache for.
        */
       public static function clear_cache_for_post( int $post_id ): void {
               $post = get_post( $post_id );
               if ( ! $post ) {
                       return;
               }

               $levels = range( 2, 6 );
               if ( class_exists( '\\NuclearEngagement\\Container' ) ) {
                       try {
                               $settings = \NuclearEngagement\Container::getInstance()->get( 'settings' );
                               $levels   = $settings->get_array( 'toc_heading_levels', $levels );
                       } catch ( \Throwable $e ) {
                               // Use default levels if settings unavailable.
                       }
               }

               $levels = array_unique( array_map( 'intval', $levels ) );
               sort( $levels );

               $key       = md5( $post->post_content ) . '_' . implode( '', $levels );
               $transient = 'nuclen_toc_' . $key;

               wp_cache_delete( $key, self::CACHE_GROUP );
               delete_transient( $transient );
       }

		/**
		 * Tiny wrapper so callers can import just one name.
		 *
		 * @param string $haystack The string to search in.
		 * @param string $needle   The substring to look for.
		 * @return bool Whether the haystack contains the needle.
		 */
	public static function str_contains( string $haystack, string $needle ): bool {
			return nuclen_str_contains( $haystack, $needle );
	}
}
