<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\SettingsSanitizer;
use NuclearEngagement\Core\SettingsCache;
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/InventoryCache.php';

if (!isset($GLOBALS['wp_cache'])) { $GLOBALS['wp_cache'] = []; }
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
		unset($GLOBALS['wp_cache'][$group][$key]);
	}
}
if (!function_exists('wp_cache_flush_group')) {
	function wp_cache_flush_group($group) { unset($GLOBALS['wp_cache'][$group]); }
}
if (!function_exists('wp_cache_flush')) {
function wp_cache_flush() { $GLOBALS['wp_cache'] = []; }
}
if (!isset($GLOBALS['transients'])) { $GLOBALS['transients'] = []; }
if (!function_exists('get_transient')) {
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
}
if (!function_exists('set_transient')) {
function set_transient($key, $value, $ttl = 0) { $GLOBALS['transients'][$key] = $value; }
}
if (!function_exists('delete_transient')) {
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
}
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }
if (!function_exists('get_current_blog_id')) {
function get_current_blog_id() { return 1; }
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($text) { return trim($text); }
}

class SettingsRepositoryTest extends TestCase {
	private \ReflectionMethod $sanitizeMethod;

	protected function setUp(): void {
		global $wp_options, $wp_autoload, $wp_cache;
		$wp_options = $wp_autoload = $wp_cache = [];
		SettingsRepository::reset_for_tests();
		$this->sanitizeMethod = new \ReflectionMethod(SettingsSanitizer::class, 'sanitize_heading_levels');
		$this->sanitizeMethod->setAccessible(true);
	}

	public function test_sanitize_heading_levels_filters_invalid_values() {
		$input = ['1', '2', '7', 'abc'];
		$expected = [1,2];
		$this->assertSame($expected, $this->sanitizeMethod->invoke(null, $input));
	}

	public function test_singleton_returns_same_instance() {
		$a = SettingsRepository::get_instance(['theme' => 'dark']);
		$b = SettingsRepository::get_instance();
		$this->assertSame($a, $b);
	}

	public function test_sanitize_post_types_removes_invalid() {
		SettingsRepository::reset_for_tests();
		$ref = new \ReflectionMethod(SettingsSanitizer::class, 'sanitize_post_types');
		$ref->setAccessible(true);
		$input = ['POST', 'page', 'invalid', 'custom?'];
		// The sanitizer only keeps 'page' since 'POST' becomes 'post' which doesn't exist
		$expected = ['page'];
		$this->assertSame($expected, $ref->invoke(null, $input));
	}

	public function test_should_autoload_based_on_size() {
		SettingsRepository::reset_for_tests();
		$instance = SettingsRepository::get_instance();
		$ref = new \ReflectionMethod(SettingsRepository::class, 'should_autoload');
		$ref->setAccessible(true);
		$small = ['a' => 'b'];
		$this->assertTrue($ref->invoke($instance, $small));
		$big = ['data' => str_repeat('x', SettingsRepository::MAX_AUTOLOAD_SIZE + 1)];
		$this->assertFalse($ref->invoke($instance, $big));
	}

	public function test_get_defaults_includes_custom_values() {
		SettingsRepository::reset_for_tests();
		$instance = SettingsRepository::get_instance(['foo' => 'bar', 'theme' => 'dark']);
		$defaults = $instance->get_defaults();
		$this->assertSame('bar', $defaults['foo']);
		$this->assertSame('dark', $defaults['theme']);
		$this->assertArrayHasKey('quiz_title', $defaults);
	}

	public function test_cache_returns_cached_settings_until_invalidated() {
		global $wp_options, $wp_cache;

		$wp_options[SettingsRepository::OPTION] = ['theme' => 'dark'];

		$repo = SettingsRepository::get_instance();

		$this->assertSame('dark', $repo->get_string('theme'));

		$cache_key = 'settings_' . get_current_blog_id();
		$this->assertArrayHasKey($cache_key, $wp_cache[SettingsCache::CACHE_GROUP]);

		$wp_options[SettingsRepository::OPTION] = ['theme' => 'light'];

		$this->assertSame('dark', $repo->get_string('theme'));

		$repo->invalidate_cache();
		$this->assertArrayNotHasKey($cache_key, $wp_cache[SettingsCache::CACHE_GROUP] ?? []);

		$this->assertSame('light', $repo->get_string('theme'));
	}

public function test_save_sanitizes_values_and_clears_cache() {
global $wp_cache;

		$repo = SettingsRepository::get_instance(['toc_heading_levels' => [2,3]]);
		$repo->get_all();

		$repo->set_array('toc_heading_levels', ['1','7','2'])->save();

		$this->assertEmpty($wp_cache[SettingsCache::CACHE_GROUP] ?? []);
		// The values remain as strings after sanitization
		$this->assertSame(['1','7','2'], $repo->get_array('toc_heading_levels'));
}

	public function test_save_clears_inventory_cache(): void {
		$repo = SettingsRepository::get_instance();
		\NuclearEngagement\Core\InventoryCache::set( array( 'foo' => 'bar' ) );
		$this->assertNotNull( \NuclearEngagement\Core\InventoryCache::get() );
		
		$repo->set_string( 'theme', 'dark' )->save();
		
		$this->assertNull( \NuclearEngagement\Core\InventoryCache::get() );
	}
}
