<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\InventoryCache;

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
		// Count actual flushes here: the bootstrap defines wp_cache_delete (which this
		// file cannot override), so the debounce is observed via the group flush that
		// clear() performs exactly once per non-debounced call.
		$GLOBALS['flush_count']++;
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

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id() { return 1; }
}

class InventoryCacheTest extends TestCase {
	protected function setUp(): void {
		// The central bootstrap stubs back wp_cache_* with $wp_cache and *_transient
		// with $wp_transients, so reset those (the test's own guarded stubs above are
		// never installed because the central ones already exist).
		global $wp_cache, $wp_transients, $flush_count;
		$wp_cache      = [];
		$wp_transients = [];
		$flush_count   = 0;
	}

	public function test_clear_is_debounced() {
		// clear() performs more than one group flush (InventoryCache + DashboardDataService),
		// so assert the debounce relatively rather than with hard-coded counts.
		InventoryCache::clear();
		$first = $GLOBALS['flush_count'];
		$this->assertGreaterThan( 0, $first, 'first clear() flushes' );

		// A second immediate clear() is debounced: no additional flush.
		InventoryCache::clear();
		$this->assertSame( $first, $GLOBALS['flush_count'], 'second clear() within the window is debounced' );

		// After the debounce window elapses, clear() flushes again.
		usleep( ( InventoryCache::CLEAR_DEBOUNCE + 1 ) * 1000000 );
		InventoryCache::clear();
		$this->assertGreaterThan( $first, $GLOBALS['flush_count'], 'clear() flushes again after the debounce window' );
	}

	public function test_set_get_and_clear() {
		$data = array( 'foo' => 'bar' );

		InventoryCache::set( $data );

		$key = InventoryCache::CACHE_KEY . '_' . get_current_blog_id();

		$this->assertSame( $data, $GLOBALS['wp_cache'][ InventoryCache::CACHE_GROUP ][ $key ] );
		$this->assertSame( $data, $GLOBALS['wp_transients'][ $key ] );

		$this->assertSame( $data, InventoryCache::get() );

		unset( $GLOBALS['wp_cache'][ InventoryCache::CACHE_GROUP ][ $key ] );
		$this->assertSame( $data, InventoryCache::get(), 'falls back to transient' );

		InventoryCache::clear();

		$this->assertArrayNotHasKey( $key, $GLOBALS['wp_cache'][ InventoryCache::CACHE_GROUP ] ?? array() );
		$this->assertArrayNotHasKey( $key, $GLOBALS['wp_transients'] );
		$this->assertNull( InventoryCache::get() );
	}
}
