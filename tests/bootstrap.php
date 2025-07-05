<?php
// Define ABSPATH to bypass exit calls
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Define plugin constants
if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
	define( 'NUCLEN_PLUGIN_DIR', dirname(__DIR__) . '/nuclear-engagement/' );
}

// Load composer autoloader
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Load Brain Monkey mock
require_once __DIR__ . '/brain-monkey-mock.php';

// Define WordPress time constants
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
}
if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );
}
if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS );
}

// Define WordPress size constants
if ( ! defined( 'KB_IN_BYTES' ) ) {
	define( 'KB_IN_BYTES', 1024 );
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
}
if ( ! defined( 'GB_IN_BYTES' ) ) {
	define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );
}
// Track calls to dbDelta in unit tests
global $dbDelta_called;
$dbDelta_called = false;
// Load shared WordPress function stubs
require_once __DIR__ . '/wp-stubs.php';

// WP_Mock is already loaded by composer autoloader
// Initialize globals for WP_Mock support
$GLOBALS['wp_mock_callbacks'] = [];
$GLOBALS['wp_mock_values'] = [];
$GLOBALS['wp_mock_values_with_args'] = [];

// Initialize global cache variable
$GLOBALS['wp_cache'] = [];
// Minimal stubs for WordPress functions used in included files
if (!function_exists('add_action')) {
	function add_action(...$args) {}
}
if (!function_exists('absint')) {
	function absint($maybeint) { return abs(intval($maybeint)); }
}
if (!function_exists('plugin_dir_path')) {
	function plugin_dir_path($file) { return dirname($file) . '/'; }
}
if (!function_exists('get_plugin_data')) {
	function get_plugin_data($file) { return ['Version' => '1.0']; }
}
if (!function_exists('register_activation_hook')) {
	function register_activation_hook(...$args) {}
}
if (!function_exists('register_deactivation_hook')) {
	function register_deactivation_hook(...$args) {}
}

// Simple in-memory storage for options and related autoload flags
$GLOBALS['wp_options'] = [];
$GLOBALS['wp_autoload'] = [];
$GLOBALS['update_option_calls'] = [];
$GLOBALS['wp_posts'] = [];
$GLOBALS['wp_meta'] = [];
$GLOBALS['wp_events'] = [];
$GLOBALS['wp_user_meta'] = [];

if (!function_exists('update_option')) {
	function update_option($name, $value, $autoload = 'yes') {
		global $update_option_calls;
		$update_option_calls[$name] = ($update_option_calls[$name] ?? 0) + 1;
		$GLOBALS['wp_options'][$name] = $value;
		$GLOBALS['wp_autoload'][$name] = $autoload;
		return true;
	}
}

if (!function_exists('get_option')) {
	function get_option($name, $default = false) {
		return $GLOBALS['wp_options'][$name] ?? $default;
	}
}

if (!function_exists('delete_option')) {
	function delete_option($name) {
		unset($GLOBALS['wp_options'][$name], $GLOBALS['wp_autoload'][$name]);
		return true;
	}
}

if (!function_exists('update_user_meta')) {
	function update_user_meta($user_id, $meta_key, $meta_value) {
		$GLOBALS['wp_user_meta'][$user_id][$meta_key] = $meta_value;
		return true;
	}
}

if (!function_exists('get_user_meta')) {
	function get_user_meta($user_id, $meta_key, $single = false) {
		return $GLOBALS['wp_user_meta'][$user_id][$meta_key] ?? '';
	}
}

// Include files for tests
require_once __DIR__ . '/../nuclear-engagement/inc/Core/Defaults.php';
require_once __DIR__ . '/../nuclear-engagement/inc/OptinData.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Core/SettingsRepository.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Core/SettingsSanitizer.php';
require_once __DIR__ . '/../nuclear-engagement/front/traits/AssetsTrait.php';
require_once __DIR__ . '/../nuclear-engagement/front/traits/RestTrait.php';
require_once __DIR__ . '/../nuclear-engagement/front/traits/ShortcodesTrait.php';

// Include admin classes
if (file_exists(__DIR__ . '/../nuclear-engagement/admin/Setup.php')) {
    require_once __DIR__ . '/../nuclear-engagement/admin/Setup.php';
}
if (file_exists(__DIR__ . '/../nuclear-engagement/admin/Settings.php')) {
    require_once __DIR__ . '/../nuclear-engagement/admin/Settings.php';
}
if (file_exists(__DIR__ . '/../nuclear-engagement/inc/Core/ServiceContainer.php')) {
    require_once __DIR__ . '/../nuclear-engagement/inc/Core/ServiceContainer.php';
}
if (file_exists(__DIR__ . '/../nuclear-engagement/inc/Core/InventoryCache.php')) {
    require_once __DIR__ . '/../nuclear-engagement/inc/Core/InventoryCache.php';
}
if (file_exists(__DIR__ . '/../nuclear-engagement/inc/Core/AssetVersions.php')) {
    require_once __DIR__ . '/../nuclear-engagement/inc/Core/AssetVersions.php';
}
if (!function_exists('sanitize_key')) {
	function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_]/', '', $key)); }
}
if (!function_exists('post_type_exists')) {
	function post_type_exists($type) { return in_array($type, ['post','page'], true); }
}
if (!function_exists('wp_parse_args')) {
	function wp_parse_args($args, $defaults = []) {
		if (is_array($args)) {
			return array_merge($defaults, $args);
		}
		parse_str((string) $args, $parsed);
		return array_merge($defaults, $parsed);
	}
}
if (!function_exists('is_multisite')) {
	function is_multisite() {
		return false;
	}
}
if (!function_exists('get_current_blog_id')) {
	function get_current_blog_id() {
		return 1;
	}
}

// get_post will be provided by WP_Mock when needed
if (!function_exists('get_post')) {
	function get_post($id) { return $GLOBALS['wp_posts'][$id] ?? null; }
}
if (!function_exists('get_the_title')) {
	function get_the_title($id) { return $GLOBALS['wp_posts'][$id]->post_title ?? ''; }
}
if (!function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags($text) { return strip_tags($text); }
}
if (!function_exists('get_post_meta')) {
	function get_post_meta($post_id, $key, $single) {
		return $GLOBALS['wp_meta'][$post_id][$key] ?? '';
	}
}
if (!function_exists('current_time')) {
	function current_time($type) { return date('Y-m-d H:i:s'); }
}
if (!function_exists('wp_next_scheduled')) {
	function wp_next_scheduled(...$args) { return false; }
}
if (!function_exists('wp_schedule_single_event')) {
	function wp_schedule_single_event($timestamp, $hook, $args) {
		$GLOBALS['wp_events'][] = compact('timestamp', 'hook', 'args');
	}
}
if (!function_exists('is_wp_error')) {
	function is_wp_error($thing) { return $thing instanceof WP_Error; }
}
if (!class_exists('WP_Error')) {
	class WP_Error {
		public $data;
		public $code;
		public $message;
		public function __construct($code = '', $message = '', $data = null) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}
		public function get_error_message() { return $this->message ?: 'error'; }
	}
}

// Add WP_UnitTestCase mock for integration tests
if (!class_exists('WP_UnitTestCase')) {
	class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
		protected $factory;
		
		public function setUp(): void {
			parent::setUp();
			// Mock factory object
			$this->factory = new class {
				public $post;
				public $user;
				public $term;
				
				public function __construct() {
					$this->post = new class {
						public function create($args = []) {
							static $id = 1;
							$post = (object) array_merge([
								'ID' => $id++,
								'post_title' => 'Test Post',
								'post_content' => 'Test content',
								'post_status' => 'publish',
								'post_type' => 'post'
							], $args);
							$GLOBALS['wp_posts'][$post->ID] = $post;
							return $post->ID;
						}
					};
					
					$this->user = new class {
						public function create($args = []) {
							static $id = 1;
							return $id++;
						}
					};
					
					$this->term = new class {
						public function create($args = []) {
							static $id = 1;
							return $id++;
						}
					};
				}
			};
		}
		
		public function tearDown(): void {
			parent::tearDown();
			// Clear global state
			$GLOBALS['wp_posts'] = [];
			$GLOBALS['wp_meta'] = [];
			$GLOBALS['wp_options'] = [];
			$GLOBALS['wp_autoload'] = [];
			$GLOBALS['wp_events'] = [];
			$GLOBALS['wp_user_meta'] = [];
		}
	}
}

