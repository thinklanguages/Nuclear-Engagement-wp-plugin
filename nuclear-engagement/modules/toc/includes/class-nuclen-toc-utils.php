<?php
/**
 * File: modules/toc/includes/class-nuclen-toc-utils.php
 *
 * Heavy‑lifting utilities:
 *   ▸ heading extraction with skip‑rules
 *   ▸ slug deduplication
 *   ▸ object‑cache wrapper
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

use function nuclen_str_contains as _nc;

final class Nuclen_TOC_Utils {

	private const CACHE_GROUP = 'nuclen_toc';
	private const CACHE_TTL   = 21600;          // 6 hours

	private static array $ids_in_post = [];

	/**
	 * Parse H1–H6 headings from raw HTML, respecting heading levels array,
	 * .no‑toc class, data‑toc="false", and cache the result.
	 *
	 * @param string $html The HTML content to parse for headings.
	 * @param array $heading_levels Array of specific heading levels to include (e.g., [2, 4] for H2 and H4 only).
	 *
	 * @return array[] [
	 *     'tag'   => 'h2',
	 *     'level' => 2,
	 *     'text'  => 'Heading text',
	 *     'inner' => 'Heading <em>HTML</em>',
	 *     'id'    => 'slugified-id'
	 * ]
	 */
	public static function extract( string $html, array $heading_levels ) : array {
		// If no specific levels provided, use defaults (2-6)
		if ( empty( $heading_levels ) ) {
			$heading_levels = range( 2, 6 );
		}

		// Sanitize and validate heading levels
		$heading_levels = array_filter( array_map( 'intval', $heading_levels ), function( $level ) {
			return $level >= 1 && $level <= 6;
		} );

		// If still no valid levels, use defaults (2-6)
		if ( empty( $heading_levels ) ) {
			$heading_levels = range( 2, 6 );
		}

		// Sort and make unique
		sort( $heading_levels );
		$heading_levels = array_unique( $heading_levels );

		// Generate cache key based on content and heading levels
		$key = md5( $html ) . '_' . implode( '', $heading_levels );
		$hit = wp_cache_get( $key, self::CACHE_GROUP );

		if ( $hit !== false ) {
			self::$ids_in_post = wp_list_pluck( $hit, 'id' );
			return $hit;
		}

		self::$ids_in_post = $out = [];

		if ( preg_match_all( '/<(h[1-6])([^>]*)>(.*?)<\/\1>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				$tag  = strtolower( $row[1] );
				$lvl  = (int) substr( $tag, 1 );

				// Skip if not in allowed levels or has skip classes/attributes
				if ( ! in_array( $lvl, $heading_levels, true ) ) { continue; }
				if ( preg_match( '/\bno-?toc\b/i', $row[2] ) ) { continue; }
				if ( preg_match( '/data-toc\s*=\s*["\']?false/i', $row[2] ) ) { continue; }

				// Process the heading
				$inner = $row[3];
				$text  = wp_strip_all_tags( $inner );
				if ( $text === '' ) { continue; }

				// Get or generate ID
				$id = ( preg_match( '/\bid=["\']([^"\']+)["\']/', $row[2], $id_m ) )
					? sanitize_html_class( $id_m[1] )
					: self::unique_id_from_text( $text );

				$out[] = [
					'tag'   => $tag,
					'level' => $lvl,
					'text'  => $text,
					'inner' => $inner,
					'id'    => $id,
				];
			}
		}

		wp_cache_set( $key, $out, self::CACHE_GROUP, self::CACHE_TTL );
		return $out;
	}

	/* ---------------------------------------------------------- */
	/*  Helpers                                                   */
	/* ---------------------------------------------------------- */

	private static function unique_id_from_text( string $txt ) : string {
		$base = sanitize_title( $txt );
		$id   = $base; $n = 2;
		while ( in_array( $id, self::$ids_in_post, true ) ) {
			$id = $base . '-' . $n ++;
		}
		self::$ids_in_post[] = $id;
		return $id;
	}

	/** tiny wrapper so callers can import just one name */
	public static function str_contains( string $haystack, string $needle ) : bool {
		return _nc( $haystack, $needle );
	}
}
