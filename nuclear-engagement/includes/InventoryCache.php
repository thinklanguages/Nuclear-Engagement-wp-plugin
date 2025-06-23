<?php
declare(strict_types=1);
/**
 * File: includes/InventoryCache.php
 *
 * Cache handler for dashboard post inventory data.
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class InventoryCache {
    /** Cache key for inventory data. */
    public const CACHE_KEY = 'nuclen_inventory_data';

    /** Cache group used for inventory. */
    public const CACHE_GROUP = 'nuclen_inventory';

    /** Default cache lifetime. */
    public const CACHE_EXPIRATION = HOUR_IN_SECONDS;

    /**
     * Register hooks to automatically clear the cache when posts change.
     */
    public static function register_hooks(): void {
        $cb = [ self::class, 'clear' ];
        foreach ( [ 'save_post', 'delete_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'transition_post_status' ] as $hook ) {
            add_action( $hook, $cb );
        }
        foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
            add_action( $hook, $cb );
        }
        foreach ( [
            'create_term',
            'created_term',
            'edit_term',
            'edited_term',
            'delete_term',
            'deleted_term',
            'set_object_terms',
        ] as $hook ) {
            add_action( $hook, $cb );
        }
        add_action( 'switch_blog', $cb );
    }

    /**
     * Get cached inventory data.
     */
    public static function get(): ?array {
        $cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );
        return is_array( $cached ) ? $cached : null;
    }

    /**
     * Store inventory data in cache.
     */
    public static function set( array $data ): void {
        wp_cache_set( self::CACHE_KEY, $data, self::CACHE_GROUP, self::CACHE_EXPIRATION );
    }

    /**
     * Clear the inventory cache.
     */
    public static function clear(): void {
        wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( self::CACHE_GROUP );
        } else {
            wp_cache_flush();
        }
    }
}
