<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\InventoryCache;

// ----------------------------------------------
// WordPress cache stubs
// ----------------------------------------------

$GLOBALS['wp_cache'] = [];
$GLOBALS['flush_count'] = 0;
$GLOBALS['transients'] = [];

if ( ! function_exists( 'wp_cache_get' ) ) {
    function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
        $found = isset( $GLOBALS['wp_cache'][ $group ][ $key ] );
        return $GLOBALS['wp_cache'][ $group ][ $key ] ?? false;
    }
}
if ( ! function_exists( 'wp_cache_set' ) ) {
    function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
        $GLOBALS['wp_cache'][ $group ][ $key ] = $value;
    }
}
if ( ! function_exists( 'wp_cache_delete' ) ) {
    function wp_cache_delete( $key, $group = '' ) {
        $GLOBALS['flush_count']++;
        unset( $GLOBALS['wp_cache'][ $group ][ $key ] );
    }
}
if ( ! function_exists( 'wp_cache_flush_group' ) ) {
    function wp_cache_flush_group( $group ) {
        unset( $GLOBALS['wp_cache'][ $group ] );
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        return $GLOBALS['transients'][ $key ] ?? false;
    }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $ttl = 0 ) {
        $GLOBALS['transients'][ $key ] = $value;
    }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['transients'][ $key ] );
    }
}

class InventoryCacheTest extends TestCase {
    protected function setUp(): void {
        global $wp_cache, $flush_count, $transients;
        $wp_cache   = [];
        $flush_count = 0;
        $transients = [];
    }

    public function test_clear_is_debounced() {
        InventoryCache::clear();
        $this->assertSame( 1, $GLOBALS['flush_count'] );

        InventoryCache::clear();
        $this->assertSame( 1, $GLOBALS['flush_count'] );

        usleep( ( InventoryCache::CLEAR_DEBOUNCE + 1 ) * 1000000 );
        InventoryCache::clear();
        $this->assertSame( 2, $GLOBALS['flush_count'] );
    }
}
