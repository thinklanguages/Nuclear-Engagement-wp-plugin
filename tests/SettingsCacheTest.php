<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\SettingsCache;
use NuclearEngagement\Core\SettingsRepository;

// ------------------------------------------------------
// WordPress cache stubs
// ------------------------------------------------------
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!isset($GLOBALS['wp_cache'])) {
    $GLOBALS['wp_cache'] = [];
}
$GLOBALS['delete_calls'] = 0;
$GLOBALS['flush_group_calls'] = 0;
$GLOBALS['flush_calls'] = 0;

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null) {
        $found = isset($GLOBALS['wp_cache'][$group][$key]);
        return $GLOBALS['wp_cache'][$group][$key] ?? false;
    }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $value, $group = '', $ttl = 0) {
        $GLOBALS['wp_cache'][$group][$key] = $value;
    }
}
if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        $GLOBALS['delete_calls']++;
        unset($GLOBALS['wp_cache'][$group][$key]);
    }
}
if (!function_exists('wp_cache_flush_group')) {
    function wp_cache_flush_group($group) {
        $GLOBALS['flush_group_calls']++;
        unset($GLOBALS['wp_cache'][$group]);
    }
}
if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        $GLOBALS['flush_calls']++;
        $GLOBALS['wp_cache'] = [];
    }
}
if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() { return 1; }
}
if (!function_exists('add_action')) {
    function add_action(...$args) {}
}

require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsCache.php';

class SettingsCacheTest extends TestCase {
    protected function setUp(): void {
        global $wp_cache, $delete_calls, $flush_group_calls, $flush_calls;
        $wp_cache = [];
        $delete_calls = $flush_group_calls = $flush_calls = 0;
    }

    public function test_get_returns_null_when_cache_missing(): void {
        $cache = new SettingsCache();
        $this->assertNull($cache->get());
    }

    public function test_set_and_get(): void {
        $cache = new SettingsCache();
        $data = ['foo' => 'bar'];
        $cache->set($data);
        $this->assertSame($data, $cache->get());
    }

    public function test_invalidate_cache_deletes_and_flushes(): void {
        $cache = new SettingsCache();
        $cache->set(['a' => 'b']);
        $cache->invalidate_cache();
        $key = $cache->get_cache_key();
        $this->assertArrayNotHasKey($key, $GLOBALS['wp_cache'][SettingsCache::CACHE_GROUP] ?? []);
        $this->assertSame(1, $GLOBALS['delete_calls']);
        $this->assertSame(1, $GLOBALS['flush_group_calls']);
    }

    public function test_maybe_invalidate_cache_only_for_plugin_option(): void {
        $cache = new SettingsCache();
        $cache->set(['a' => 'b']);
        $cache->maybe_invalidate_cache('other_option');
        $this->assertArrayHasKey($cache->get_cache_key(), $GLOBALS['wp_cache'][SettingsCache::CACHE_GROUP]);
        $cache->maybe_invalidate_cache(SettingsRepository::OPTION);
        $this->assertArrayNotHasKey($cache->get_cache_key(), $GLOBALS['wp_cache'][SettingsCache::CACHE_GROUP] ?? []);
    }
}
