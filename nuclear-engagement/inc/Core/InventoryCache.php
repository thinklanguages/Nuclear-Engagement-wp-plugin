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
    /** Cache key base for inventory data. */
    public const CACHE_KEY = 'nuclen_inventory_data';

    /** Cache group used for inventory. */
    public const CACHE_GROUP = 'nuclen_inventory';

        /** Default cache lifetime. */
        public const CACHE_EXPIRATION = HOUR_IN_SECONDS;

       /** Timestamp key for last clear operation. */
       private const CLEAR_TS_KEY = 'nuclen_inventory_last_clear';

       /** Seconds to debounce consecutive clears. */
       public const CLEAR_DEBOUNCE = 2;

    /**
     * Register hooks to automatically clear the cache when posts change.
     */
    public static function register_hooks(): void {
        $cb = array( self::class, 'clear' );
        foreach ( array(
            'save_post',
            'delete_post',
            'deleted_post',
            'trashed_post',
            'untrashed_post',
            'transition_post_status',
            'clean_post_cache',
        ) as $hook ) {
            add_action( $hook, $cb );
        }
        foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
            add_action( $hook, $cb );
        }
        foreach ( array(
            'create_term',
            'created_term',
            'edit_term',
            'edited_term',
            'delete_term',
            'deleted_term',
            'set_object_terms',
            'added_term_relationship',
            'deleted_term_relationships',
            'edited_terms',
        ) as $hook ) {
            add_action( $hook, $cb );
        }
        add_action( 'switch_blog', $cb );
    }

    /**
     * Get the cache key for the current site.
     */
    private static function get_cache_key(): string {
        return self::CACHE_KEY . '_' . get_current_blog_id();
    }

    /**
     * Get cached inventory data.
     */
    public static function get(): ?array {
                $key    = self::get_cache_key();
                $found  = false;
                $cached = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
                if ( ! $found ) {
                        $cached = get_transient( $key );
                }

                return is_array( $cached ) ? $cached : null;
        }

    /**
     * Store inventory data in cache.
     */
    public static function set( array $data ): void {
                $key = self::get_cache_key();

                wp_cache_set( $key, $data, self::CACHE_GROUP, self::CACHE_EXPIRATION );
                set_transient( $key, $data, self::CACHE_EXPIRATION );
        }

    /**
     * Clear the inventory cache.
     */
        public static function clear(): void {
               $now  = time();
               $last = (int) wp_cache_get( self::CLEAR_TS_KEY, self::CACHE_GROUP );

               if ( $last && ( $now - $last ) < self::CLEAR_DEBOUNCE ) {
                       return;
               }

               wp_cache_set( self::CLEAR_TS_KEY, $now, self::CACHE_GROUP, self::CLEAR_DEBOUNCE * 2 );

               $key = self::get_cache_key();

               wp_cache_delete( $key, self::CACHE_GROUP );
               delete_transient( $key );

               if ( function_exists( 'wp_cache_flush_group' ) ) {
                       wp_cache_flush_group( self::CACHE_GROUP );
               }

               \NuclearEngagement\Services\DashboardDataService::clear_cache();
       }
}
