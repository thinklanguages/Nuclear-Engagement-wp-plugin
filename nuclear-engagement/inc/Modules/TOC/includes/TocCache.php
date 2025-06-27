<?php
/**
 * Cache utilities for the TOC module.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

use function NuclearEngagement\nuclen_settings_array;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles cache operations and parse timing.
 */
final class TocCache {

    public const GROUP = 'nuclen_toc';
    public const TTL   = 6 * HOUR_IN_SECONDS;

    /** Last parse duration in milliseconds. */
    private static int $last_ms = 0;

    /**
     * Get the duration of the last parse.
     */
    public static function get_last_parse_ms(): int {
        return self::$last_ms;
    }

    /**
     * Set the duration of the last parse.
     *
     * @param int $ms Duration in milliseconds.
     */
    public static function set_last_parse_ms( int $ms ): void {
        self::$last_ms = $ms;
    }

    /**
     * Clear cached headings for a post.
     *
     * @param int $post_id Post ID.
     */
    public static function clear_for_post( int $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        $levels = nuclen_settings_array( 'toc_heading_levels', range( 2, 6 ) );
        $levels = array_unique( array_map( 'intval', $levels ) );
        sort( $levels );

        $key       = md5( $post->post_content ) . '_' . implode( '', $levels );
        $transient = 'nuclen_toc_' . $key;

        wp_cache_delete( $key, self::GROUP );
        delete_transient( $transient );
        delete_post_meta( $post_id, Nuclen_TOC_Headings::META_KEY );
    }
}
