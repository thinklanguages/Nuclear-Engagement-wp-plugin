<?php
/**
 * Cache helpers for TOC operations.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

use NuclearEngagement\Helpers\SettingsFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TocCache {

	private const CACHE_GROUP = 'nuclen_toc';

	/** Last parse duration in milliseconds. */
	private static int $last_parse_ms = 0;

	/**
	 * Record the parse time.
	 */
	public static function set_last_parse_ms( int $ms ): void {
		self::$last_parse_ms = $ms;
	}

	/**
	 * Retrieve the duration of the last heading parse.
	 */
	public static function get_last_parse_ms(): int {
		return self::$last_parse_ms;
	}

	/**
	 * Clear cached headings for a post.
	 */
	public static function clear_cache_for_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$levels = SettingsFunctions::get_array( 'toc_heading_levels', range( 2, 6 ) );
		$levels = array_unique( array_map( 'intval', $levels ) );
		sort( $levels );

		// Use CacheUtils for secure key generation
		$key_components = array(
			'toc',
			hash( 'sha256', $post->post_content ),
			implode( '', $levels ),
		);
		$key = \NuclearEngagement\Utils\CacheUtils::generate_key( $key_components );
		$transient = 'nuclen_toc_' . substr( $key, 0, 40 ); // Limit transient key length

		wp_cache_delete( $key, self::CACHE_GROUP );
		delete_transient( $transient );
		delete_post_meta( $post_id, Nuclen_TOC_Headings::META_KEY );
	}
}
