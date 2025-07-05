<?php
/**
 * Generate unique slugs for TOC headings.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug creation helper.
 */
final class SlugGenerator {

	/**
	 * Track generated IDs within a single post.
	 *
	 * @var array<string,bool>
	 */
	private static array $ids_in_post = array();

	/**
	 * Prime internal slug tracking with existing IDs.
	 *
	 * @param array $ids IDs already present in the post.
	 */
	public static function prime( array $ids ): void {
		foreach ( $ids as $id ) {
			if ( is_string( $id ) && '' !== $id ) {
				self::$ids_in_post[ $id ] = true;
			}
		}
	}

	/**
	 * Reset internal slug tracking.
	 */
	public static function reset(): void {
		self::$ids_in_post = array();
	}

	/**
	 * Generate a unique slug from heading text.
	 *
	 * @param string $text Heading text.
	 * @return string Unique slug.
	 */
	public static function generate( string $text ): string {
		$base = sanitize_title( $text );
		$id   = $base;
		$n    = 2;

		while ( isset( self::$ids_in_post[ $id ] ) ) {
			$id = $base . '-' . ( $n++ );
		}

		self::$ids_in_post[ $id ] = true;
		return $id;
	}
}
