<?php
// Define ABSPATH to bypass exit calls
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Define plugin constants
if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
	define( 'NUCLEN_PLUGIN_DIR', dirname(__DIR__) . '/nuclear-engagement/' );
}
if ( ! defined( 'NUCLEN_PLUGIN_URL' ) ) {
	define( 'NUCLEN_PLUGIN_URL', 'http://test.local/wp-content/plugins/nuclear-engagement/' );
}
if ( ! defined( 'NUCLEN_PLUGIN_FILE' ) ) {
	define( 'NUCLEN_PLUGIN_FILE', dirname(__DIR__) . '/nuclear-engagement/nuclear-engagement.php' );
}
if ( ! defined( 'NUCLEN_VERSION' ) ) {
	define( 'NUCLEN_VERSION', '1.0.0' );
}
// NUCLEN_ASSET_VERSION is loaded from constants.php

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
if ( ! defined( 'TB_IN_BYTES' ) ) {
	define( 'TB_IN_BYTES', 1024 * GB_IN_BYTES );
}

// Define Nuclear Engagement constants
if ( ! defined( 'NUCLEN_LOG_FILE_MAX_SIZE' ) ) {
	define( 'NUCLEN_LOG_FILE_MAX_SIZE', MB_IN_BYTES );
}
if ( ! defined( 'NUCLEN_BUFFER_LOGS' ) ) {
	define( 'NUCLEN_BUFFER_LOGS', true );
}
if ( ! defined( 'NUCLEN_API_TIMEOUT' ) ) {
	define( 'NUCLEN_API_TIMEOUT', 30 );
}
if ( ! defined( 'NUCLEN_INITIAL_POLL_DELAY' ) ) {
	define( 'NUCLEN_INITIAL_POLL_DELAY', 15 );
}
if ( ! defined( 'NUCLEN_MAX_POLL_ATTEMPTS' ) ) {
	define( 'NUCLEN_MAX_POLL_ATTEMPTS', 10 );
}
if ( ! defined( 'NUCLEN_ACTIVATION_REDIRECT_TTL' ) ) {
	define( 'NUCLEN_ACTIVATION_REDIRECT_TTL', 30 );
}
if ( ! defined( 'NUCLEN_POLL_RETRY_DELAY' ) ) {
	define( 'NUCLEN_POLL_RETRY_DELAY', MINUTE_IN_SECONDS );
}
if ( ! defined( 'NUCLEN_GENERATION_POLL_DELAY' ) ) {
	define( 'NUCLEN_GENERATION_POLL_DELAY', 30 );
}
if ( ! defined( 'NUCLEN_POST_FETCH_CHUNK' ) ) {
	define( 'NUCLEN_POST_FETCH_CHUNK', 200 );
}
if ( ! defined( 'NUCLEN_SUMMARY_LENGTH_DEFAULT' ) ) {
	define( 'NUCLEN_SUMMARY_LENGTH_DEFAULT', 30 );
}
if ( ! defined( 'NUCLEN_SUMMARY_LENGTH_MIN' ) ) {
	define( 'NUCLEN_SUMMARY_LENGTH_MIN', 20 );
}
if ( ! defined( 'NUCLEN_SUMMARY_LENGTH_MAX' ) ) {
	define( 'NUCLEN_SUMMARY_LENGTH_MAX', 50 );
}
if ( ! defined( 'NUCLEN_SUMMARY_ITEMS_DEFAULT' ) ) {
	define( 'NUCLEN_SUMMARY_ITEMS_DEFAULT', 3 );
}
if ( ! defined( 'NUCLEN_SUMMARY_ITEMS_MIN' ) ) {
	define( 'NUCLEN_SUMMARY_ITEMS_MIN', 3 );
}
if ( ! defined( 'NUCLEN_SUMMARY_ITEMS_MAX' ) ) {
	define( 'NUCLEN_SUMMARY_ITEMS_MAX', 7 );
}
if ( ! defined( 'NUCLEN_TOC_SCROLL_OFFSET_DEFAULT' ) ) {
	define( 'NUCLEN_TOC_SCROLL_OFFSET_DEFAULT', 72 );
}
if ( ! defined( 'NUCLEN_ADMIN_MENU_POSITION' ) ) {
	define( 'NUCLEN_ADMIN_MENU_POSITION', 30 );
}

// Track calls to dbDelta in unit tests
global $dbDelta_called;
$dbDelta_called = false;

// Initialize WordPress database globals
global $wpdb;
if (!isset($wpdb)) {
	$wpdb = new class {
		public $prefix = 'wp_';
		public $base_prefix = 'wp_';
		public $tables = [];
		public $queries = [];
		public $last_error = '';
		public $insert_id = 0;
		public $num_rows = 0;
		public $rows_affected = 0;
		
		public function prepare($query, ...$args) {
			return vsprintf(str_replace(['%s', '%d', '%f'], ['\'%s\'', '%d', '%f'], $query), $args);
		}
		
		public function query($query) {
			$this->queries[] = $query;
			return true;
		}
		
		public function get_results($query, $output = OBJECT) {
			$this->queries[] = $query;
			return [];
		}
		
		public function get_var($query) {
			$this->queries[] = $query;
			return null;
		}
		
		public function get_row($query, $output = OBJECT, $offset = 0) {
			$this->queries[] = $query;
			return null;
		}
		
		public function insert($table, $data, $format = null) {
			$this->queries[] = "INSERT INTO $table";
			$this->insert_id = rand(1, 1000);
			return true;
		}
		
		public function update($table, $data, $where, $format = null, $where_format = null) {
			$this->queries[] = "UPDATE $table";
			$this->rows_affected = 1;
			return 1;
		}
		
		public function delete($table, $where, $where_format = null) {
			$this->queries[] = "DELETE FROM $table";
			$this->rows_affected = 1;
			return 1;
		}
		
		public function get_charset_collate() {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}
		
		public function esc_like($text) {
			return addcslashes($text, '_%\\');
		}
		
		public function _escape($data) {
			return addslashes($data);
		}
	};
}

// Define database result type constants
if (!defined('OBJECT')) {
	define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
	define('ARRAY_N', 'ARRAY_N');
}

// Database functions
if (!function_exists('dbDelta')) {
	function dbDelta($queries = '', $execute = true) {
		global $dbDelta_called;
		$dbDelta_called = true;
		return ['Table created'];
	}
}

if (!function_exists('require_wp_db')) {
	function require_wp_db() {
		// Mock implementation
	}
}
// Load shared WordPress function stubs
require_once __DIR__ . '/wp-stubs.php';

// WP_Mock is already loaded by composer autoloader
// Initialize globals for WP_Mock support
$GLOBALS['wp_mock_callbacks'] = [];
$GLOBALS['wp_mock_values'] = [];
$GLOBALS['wp_mock_values_with_args'] = [];

// Initialize global cache variable
$GLOBALS['wp_cache'] = [];
// Initialize global hooks storage
$GLOBALS['wp_actions'] = [];
$GLOBALS['wp_filters'] = [];
$GLOBALS['wp_current_filter'] = [];

// Minimal stubs for WordPress functions used in included files
if (!function_exists('add_action')) {
	function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
		global $wp_filters;
		if (!isset($wp_filters[$tag])) {
			$wp_filters[$tag] = [];
		}
		if (!isset($wp_filters[$tag][$priority])) {
			$wp_filters[$tag][$priority] = [];
		}
		$wp_filters[$tag][$priority][] = [
			'function' => $function_to_add,
			'accepted_args' => $accepted_args
		];
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
		return add_action($tag, $function_to_add, $priority, $accepted_args);
	}
}

if (!function_exists('remove_action')) {
	function remove_action($tag, $function_to_remove, $priority = 10) {
		global $wp_filters;
		if (isset($wp_filters[$tag][$priority])) {
			foreach ($wp_filters[$tag][$priority] as $key => $hook) {
				if ($hook['function'] === $function_to_remove) {
					unset($wp_filters[$tag][$priority][$key]);
					return true;
				}
			}
		}
		return false;
	}
}

if (!function_exists('remove_filter')) {
	function remove_filter($tag, $function_to_remove, $priority = 10) {
		return remove_action($tag, $function_to_remove, $priority);
	}
}

if (!function_exists('do_action')) {
	function do_action($tag, ...$args) {
		global $wp_filters, $wp_actions, $wp_current_filter;
		
		if (!isset($wp_actions[$tag])) {
			$wp_actions[$tag] = 1;
		} else {
			++$wp_actions[$tag];
		}
		
		$wp_current_filter[] = $tag;
		
		if (isset($wp_filters[$tag])) {
			ksort($wp_filters[$tag]);
			foreach ($wp_filters[$tag] as $priority => $hooks) {
				foreach ($hooks as $hook) {
					call_user_func_array($hook['function'], array_slice($args, 0, $hook['accepted_args']));
				}
			}
		}
		
		array_pop($wp_current_filter);
	}
}

if (!function_exists('apply_filters')) {
	function apply_filters($tag, $value, ...$args) {
		global $wp_filters, $wp_current_filter;
		
		$wp_current_filter[] = $tag;
		
		if (isset($wp_filters[$tag])) {
			ksort($wp_filters[$tag]);
			foreach ($wp_filters[$tag] as $priority => $hooks) {
				foreach ($hooks as $hook) {
					$value = call_user_func_array($hook['function'], array_merge([$value], array_slice($args, 0, $hook['accepted_args'] - 1)));
				}
			}
		}
		
		array_pop($wp_current_filter);
		
		return $value;
	}
}

if (!function_exists('has_action')) {
	function has_action($tag, $function_to_check = false) {
		global $wp_filters;
		
		if (!isset($wp_filters[$tag])) {
			return false;
		}
		
		if ($function_to_check === false) {
			return true;
		}
		
		foreach ($wp_filters[$tag] as $priority => $hooks) {
			foreach ($hooks as $hook) {
				if ($hook['function'] === $function_to_check) {
					return $priority;
				}
			}
		}
		
		return false;
	}
}

if (!function_exists('has_filter')) {
	function has_filter($tag, $function_to_check = false) {
		return has_action($tag, $function_to_check);
	}
}

if (!function_exists('did_action')) {
	function did_action($tag) {
		global $wp_actions;
		return isset($wp_actions[$tag]) ? $wp_actions[$tag] : 0;
	}
}

if (!function_exists('current_filter')) {
	function current_filter() {
		global $wp_current_filter;
		return end($wp_current_filter);
	}
}

if (!function_exists('doing_filter')) {
	function doing_filter($filter = null) {
		global $wp_current_filter;
		
		if (null === $filter) {
			return !empty($wp_current_filter);
		}
		
		return in_array($filter, $wp_current_filter);
	}
}

if (!function_exists('remove_all_actions')) {
	function remove_all_actions($tag, $priority = false) {
		global $wp_filters;
		
		if (false === $priority) {
			unset($wp_filters[$tag]);
		} elseif (isset($wp_filters[$tag][$priority])) {
			unset($wp_filters[$tag][$priority]);
		}
		
		return true;
	}
}

if (!function_exists('remove_all_filters')) {
	function remove_all_filters($tag, $priority = false) {
		return remove_all_actions($tag, $priority);
	}
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

// Include exception classes
require_once __DIR__ . '/../nuclear-engagement/inc/Exceptions/BaseException.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Exceptions/ApiException.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Exceptions/NuclenException.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Services/ApiException.php';

// Include constants file (if not already loaded by autoloader)
if (file_exists(__DIR__ . '/../nuclear-engagement/inc/Core/constants.php')) {
    require_once __DIR__ . '/../nuclear-engagement/inc/Core/constants.php';
}

// Include admin classes and dependencies
$required_files = [
    // Core dependencies first
    '/../nuclear-engagement/inc/Utils/Utils.php',
    '/../nuclear-engagement/inc/Core/SettingsRepository.php',
    '/../nuclear-engagement/inc/Core/SettingsSanitizer.php',
    '/../nuclear-engagement/inc/Core/ServiceContainer.php',
    '/../nuclear-engagement/inc/Core/InventoryCache.php',
    '/../nuclear-engagement/inc/Core/AssetVersions.php',
    '/../nuclear-engagement/inc/Utils/SecurityUtils.php',
    '/../nuclear-engagement/inc/Utils/ValidationUtils.php',
    '/../nuclear-engagement/inc/Utils/CacheUtils.php',
    '/../nuclear-engagement/inc/Utils/DatabaseUtils.php',
    '/../nuclear-engagement/inc/Security/TokenManager.php',
    '/../nuclear-engagement/inc/Services/SetupService.php',
    '/../nuclear-engagement/inc/Services/Remote/RemoteRequest.php',
    '/../nuclear-engagement/admin/Setup/ConnectHandler.php',
    '/../nuclear-engagement/admin/Setup/AppPasswordHandler.php',
    // Then admin classes
    '/../nuclear-engagement/admin/Setup.php',
    '/../nuclear-engagement/admin/Settings.php', 
    '/../nuclear-engagement/admin/Admin.php',
];

foreach ($required_files as $file) {
    $filepath = __DIR__ . $file;
    if (file_exists($filepath)) {
        require_once $filepath;
    }
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
	function wp_schedule_single_event($timestamp, $hook, $args = []) {
		$GLOBALS['wp_events'][] = compact('timestamp', 'hook', 'args');
		return true;
	}
}

if (!function_exists('wp_schedule_event')) {
	function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
		$GLOBALS['wp_events'][] = compact('timestamp', 'recurrence', 'hook', 'args');
		return true;
	}
}

if (!function_exists('wp_unschedule_event')) {
	function wp_unschedule_event($timestamp, $hook, $args = []) {
		// Mock implementation
		return true;
	}
}

if (!function_exists('wp_clear_scheduled_hook')) {
	function wp_clear_scheduled_hook($hook, $args = []) {
		// Track cleared hooks for tests
		if (!isset($GLOBALS['cleared_hooks'])) {
			$GLOBALS['cleared_hooks'] = [];
		}
		$GLOBALS['cleared_hooks'][] = $hook;
		return true;
	}
}

if (!function_exists('wp_unschedule_hook')) {
	function wp_unschedule_hook($hook) {
		// Mock implementation
		return true;
	}
}

if (!function_exists('wp_get_scheduled_event')) {
	function wp_get_scheduled_event($hook, $args = [], $timestamp = null) {
		// Mock implementation
		return false;
	}
}

if (!function_exists('wp_get_schedules')) {
	function wp_get_schedules() {
		return [
			'hourly' => ['interval' => HOUR_IN_SECONDS, 'display' => 'Once Hourly'],
			'twicedaily' => ['interval' => 12 * HOUR_IN_SECONDS, 'display' => 'Twice Daily'],
			'daily' => ['interval' => DAY_IN_SECONDS, 'display' => 'Once Daily'],
			'weekly' => ['interval' => WEEK_IN_SECONDS, 'display' => 'Once Weekly']
		];
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

// Add missing WordPress classes
if (!class_exists('WP_User')) {
	class WP_User {
		public $ID;
		public $user_login;
		public $user_email;
		public $roles = [];
		public $caps = [];
		public $data;
		
		public function __construct($id = 0, $name = '', $site_id = '') {
			$this->ID = $id;
			$this->user_login = $name;
			$this->data = (object) [
				'ID' => $id,
				'user_login' => $name,
				'user_email' => $name . '@example.com'
			];
		}
		
		public function has_cap($cap) { return in_array($cap, $this->caps); }
		public function add_cap($cap) { $this->caps[] = $cap; }
		public function remove_cap($cap) { $this->caps = array_diff($this->caps, [$cap]); }
		public function add_role($role) { $this->roles[] = $role; }
		public function remove_role($role) { $this->roles = array_diff($this->roles, [$role]); }
		public function set_role($role) { $this->roles = [$role]; }
	}
}

if (!class_exists('WP_Role')) {
	class WP_Role {
		public $name;
		public $capabilities = [];
		
		public function __construct($role, $capabilities = []) {
			$this->name = $role;
			$this->capabilities = $capabilities;
		}
		
		public function add_cap($cap, $grant = true) {
			$this->capabilities[$cap] = $grant;
		}
		
		public function remove_cap($cap) {
			unset($this->capabilities[$cap]);
		}
		
		public function has_cap($cap) {
			return isset($this->capabilities[$cap]) && $this->capabilities[$cap];
		}
	}
}

if (!class_exists('WP_Roles')) {
	class WP_Roles {
		public $roles = [];
		public $role_objects = [];
		
		public function __construct() {
			$this->roles = [
				'administrator' => ['name' => 'Administrator', 'capabilities' => ['manage_options' => true]],
				'editor' => ['name' => 'Editor', 'capabilities' => ['edit_posts' => true]],
				'subscriber' => ['name' => 'Subscriber', 'capabilities' => ['read' => true]]
			];
		}
		
		public function add_role($role, $display_name, $capabilities = []) {
			$this->roles[$role] = ['name' => $display_name, 'capabilities' => $capabilities];
			$this->role_objects[$role] = new WP_Role($role, $capabilities);
			return $this->role_objects[$role];
		}
		
		public function remove_role($role) {
			unset($this->roles[$role], $this->role_objects[$role]);
		}
		
		public function get_role($role) {
			if (!isset($this->role_objects[$role]) && isset($this->roles[$role])) {
				$this->role_objects[$role] = new WP_Role($role, $this->roles[$role]['capabilities']);
			}
			return $this->role_objects[$role] ?? null;
		}
		
		public function role_exists($role) {
			return isset($this->roles[$role]);
		}
	}
}

// Initialize global WP_Roles instance
if (!isset($GLOBALS['wp_roles'])) {
	$GLOBALS['wp_roles'] = new WP_Roles();
}

// Add missing WordPress functions
if (!function_exists('get_userdata')) {
	function get_userdata($user_id) {
		return new WP_User($user_id, 'user' . $user_id);
	}
}

if (!function_exists('username_exists')) {
	function username_exists($username) {
		return false; // For testing, assume no existing users
	}
}

if (!function_exists('email_exists')) {
	function email_exists($email) {
		return false; // For testing, assume no existing emails
	}
}

if (!function_exists('wp_insert_user')) {
	function wp_insert_user($userdata) {
		static $user_id = 1;
		$user = new WP_User($user_id++, $userdata['user_login'] ?? 'testuser');
		$GLOBALS['wp_users'][$user->ID] = $user;
		return $user->ID;
	}
}

if (!function_exists('wp_update_user')) {
	function wp_update_user($userdata) {
		$user_id = $userdata['ID'] ?? 1;
		if (isset($GLOBALS['wp_users'][$user_id])) {
			// Update existing user
			return $user_id;
		}
		return new WP_Error('invalid_user_id', 'Invalid user ID.');
	}
}

if (!function_exists('wp_delete_user')) {
	function wp_delete_user($user_id, $reassign = null) {
		unset($GLOBALS['wp_users'][$user_id]);
		return true;
	}
}

if (!function_exists('get_role')) {
	function get_role($role) {
		global $wp_roles;
		return $wp_roles->get_role($role);
	}
}

if (!function_exists('add_role')) {
	function add_role($role, $display_name, $capabilities = []) {
		global $wp_roles;
		return $wp_roles->add_role($role, $display_name, $capabilities);
	}
}

if (!function_exists('remove_role')) {
	function remove_role($role) {
		global $wp_roles;
		$wp_roles->remove_role($role);
	}
}

if (!function_exists('wp_roles')) {
	function wp_roles() {
		global $wp_roles;
		return $wp_roles;
	}
}

// Add missing WordPress functions needed by tests
if (!function_exists('wp_enqueue_style')) {
	function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
		global $wp_styles;
		if (!isset($wp_styles)) {
			$wp_styles = [];
		}
		$wp_styles[] = compact('handle', 'src', 'deps', 'ver', 'media');
	}
}

if (!function_exists('wp_enqueue_script')) {
	function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
		global $wp_scripts;
		if (!isset($wp_scripts)) {
			$wp_scripts = [];
		}
		$wp_scripts[] = compact('handle', 'src', 'deps', 'ver', 'in_footer');
	}
}

if (!function_exists('plugin_dir_url')) {
	function plugin_dir_url($file) {
		return 'http://test.local/wp-content/plugins/' . basename(dirname($file)) . '/';
	}
}

if (!function_exists('plugins_url')) {
	function plugins_url($path = '', $plugin = '') {
		return 'http://test.local/wp-content/plugins/' . $path;
	}
}

if (!function_exists('admin_url')) {
	function admin_url($path = '', $scheme = 'admin') {
		return 'http://test.local/wp-admin/' . $path;
	}
}

if (!function_exists('wp_nonce_url')) {
	function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce') {
		return $actionurl . (strpos($actionurl, '?') !== false ? '&' : '?') . $name . '=test_nonce';
	}
}

if (!function_exists('check_admin_referer')) {
	function check_admin_referer($action = -1, $name = '_wpnonce') {
		return true; // For tests, always pass nonce check
	}
}

if (!function_exists('wp_verify_nonce')) {
	function wp_verify_nonce($nonce, $action = -1) {
		return true; // For tests, always pass nonce verification
	}
}

if (!function_exists('home_url')) {
	function home_url($path = '', $scheme = null) {
		return 'http://test.local/' . ltrim($path, '/');
	}
}

if (!function_exists('site_url')) {
	function site_url($path = '', $scheme = null) {
		return 'http://test.local/' . ltrim($path, '/');
	}
}

if (!function_exists('wp_create_nonce')) {
	function wp_create_nonce($action = -1) {
		return 'test_nonce_' . md5($action);
	}
}

if (!function_exists('wp_generate_password')) {
	function wp_generate_password($length = 12, $include_standard_special_chars = true, $extra_special_chars = false) {
		return 'test_password_' . $length;
	}
}

if (!function_exists('wp_hash_password')) {
	function wp_hash_password($password) {
		return password_hash($password, PASSWORD_DEFAULT);
	}
}

if (!function_exists('wp_set_password')) {
	function wp_set_password($password, $user_id) {
		// Mock implementation for tests
		return true;
	}
}

if (!function_exists('wp_create_user')) {
	function wp_create_user($username, $password, $email = '') {
		static $user_id = 100;
		$user = new WP_User($user_id++, $username);
		$user->user_email = $email;
		$GLOBALS['wp_users'][$user->ID] = $user;
		return $user->ID;
	}
}

if (!function_exists('is_user_logged_in')) {
	function is_user_logged_in() {
		return false; // For tests, assume not logged in unless specified
	}
}

if (!function_exists('get_current_user_id')) {
	function get_current_user_id() {
		return 0; // For tests, assume no current user unless specified
	}
}

if (!function_exists('wp_get_current_user')) {
	function wp_get_current_user() {
		return new WP_User(0); // For tests, return empty user unless specified
	}
}

// Add missing database and WordPress functions
if (!function_exists('get_transient')) {
	function get_transient($transient) {
		global $wp_transients;
		if (!isset($wp_transients)) {
			$wp_transients = [];
		}
		return isset($wp_transients[$transient]) ? $wp_transients[$transient] : false;
	}
}

if (!function_exists('set_transient')) {
	function set_transient($transient, $value, $expiration = 0) {
		global $wp_transients;
		if (!isset($wp_transients)) {
			$wp_transients = [];
		}
		$wp_transients[$transient] = $value;
		return true;
	}
}

if (!function_exists('delete_transient')) {
	function delete_transient($transient) {
		global $wp_transients;
		if (!isset($wp_transients)) {
			$wp_transients = [];
		}
		unset($wp_transients[$transient]);
		return true;
	}
}

if (!function_exists('wp_cache_get')) {
	function wp_cache_get($key, $group = '', $force = false, &$found = null) {
		global $wp_cache;
		$found = isset($wp_cache[$group][$key]);
		return $found ? $wp_cache[$group][$key] : false;
	}
}

if (!function_exists('wp_cache_set')) {
	function wp_cache_set($key, $data, $group = '', $expire = 0) {
		global $wp_cache;
		if (!isset($wp_cache[$group])) {
			$wp_cache[$group] = [];
		}
		$wp_cache[$group][$key] = $data;
		return true;
	}
}

if (!function_exists('wp_cache_delete')) {
	function wp_cache_delete($key, $group = '') {
		global $wp_cache;
		if (isset($wp_cache[$group][$key])) {
			unset($wp_cache[$group][$key]);
			return true;
		}
		return false;
	}
}

if (!function_exists('wp_cache_flush')) {
	function wp_cache_flush() {
		global $wp_cache;
		$wp_cache = [];
		return true;
	}
}

if (!function_exists('wp_remote_get')) {
	function wp_remote_get($url, $args = []) {
		return ['response' => ['code' => 200], 'body' => 'mock response'];
	}
}

if (!function_exists('wp_remote_post')) {
	function wp_remote_post($url, $args = []) {
		return ['response' => ['code' => 200], 'body' => 'mock response'];
	}
}

if (!function_exists('wp_remote_retrieve_response_code')) {
	function wp_remote_retrieve_response_code($response) {
		return isset($response['response']['code']) ? $response['response']['code'] : 0;
	}
}

if (!function_exists('wp_remote_retrieve_body')) {
	function wp_remote_retrieve_body($response) {
		return isset($response['body']) ? $response['body'] : '';
	}
}

if (!function_exists('wp_send_json_success')) {
	function wp_send_json_success($data = null, $status_code = null) {
		echo json_encode(['success' => true, 'data' => $data]);
		exit;
	}
}

if (!function_exists('wp_send_json_error')) {
	function wp_send_json_error($data = null, $status_code = null) {
		echo json_encode(['success' => false, 'data' => $data]);
		exit;
	}
}

if (!function_exists('wp_die')) {
	function wp_die($message = '', $title = '', $args = []) {
		throw new WPDieException($message);
	}
}

// Add WPDieException for testing wp_die calls
if (!class_exists('WPDieException')) {
	class WPDieException extends \Exception {}
}

if (!function_exists('__')) {
	function __($text, $domain = 'default') {
		return $text;
	}
}

if (!function_exists('_e')) {
	function _e($text, $domain = 'default') {
		echo $text;
	}
}

if (!function_exists('_n')) {
	function _n($single, $plural, $number, $domain = 'default') {
		return $number == 1 ? $single : $plural;
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__($text, $domain = 'default') {
		return htmlspecialchars($text);
	}
}

if (!function_exists('esc_attr__')) {
	function esc_attr__($text, $domain = 'default') {
		return htmlspecialchars($text, ENT_QUOTES);
	}
}

if (!function_exists('esc_html_e')) {
	function esc_html_e($text, $domain = 'default') {
		echo htmlspecialchars($text);
	}
}

if (!function_exists('esc_attr_e')) {
	function esc_attr_e($text, $domain = 'default') {
		echo htmlspecialchars($text, ENT_QUOTES);
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text) {
		return htmlspecialchars($text);
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text) {
		return htmlspecialchars($text, ENT_QUOTES);
	}
}

if (!function_exists('esc_js')) {
	function esc_js($text) {
		return str_replace(["'", '"', "\n", "\r"], ["\'", '\"', '\n', '\r'], $text);
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url) {
		return filter_var($url, FILTER_SANITIZE_URL);
	}
}

if (!function_exists('esc_url_raw')) {
	function esc_url_raw($url) {
		return filter_var($url, FILTER_SANITIZE_URL);
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses($string, $allowed_html, $allowed_protocols = []) {
		return strip_tags($string); // Simplified for tests
	}
}

if (!function_exists('wp_kses_post')) {
	function wp_kses_post($string) {
		return $string; // Simplified for tests
	}
}

if (!function_exists('doing_ajax')) {
	function doing_ajax() {
		return defined('DOING_AJAX') && DOING_AJAX;
	}
}

if (!function_exists('is_admin')) {
	function is_admin() {
		return defined('WP_ADMIN') && WP_ADMIN;
	}
}

if (!function_exists('wp_doing_ajax')) {
	function wp_doing_ajax() {
		return doing_ajax();
	}
}

if (!function_exists('wp_doing_cron')) {
	function wp_doing_cron() {
		return defined('DOING_CRON') && DOING_CRON;
	}
}

if (!function_exists('wp_upload_dir')) {
	function wp_upload_dir() {
		return [
			'path' => '/tmp/uploads',
			'url' => 'http://test.local/wp-content/uploads',
			'subdir' => '',
			'basedir' => '/tmp/uploads',
			'baseurl' => 'http://test.local/wp-content/uploads',
			'error' => false
		];
	}
}

if (!function_exists('wp_get_upload_dir')) {
	function wp_get_upload_dir() {
		return wp_upload_dir();
	}
}

if (!function_exists('wp_normalize_path')) {
	function wp_normalize_path($path) {
		return str_replace('\\', '/', $path);
	}
}

if (!function_exists('trailingslashit')) {
	function trailingslashit($string) {
		return rtrim($string, '/\\') . '/';
	}
}

if (!function_exists('untrailingslashit')) {
	function untrailingslashit($string) {
		return rtrim($string, '/\\');
	}
}

if (!function_exists('wp_slash')) {
	function wp_slash($value) {
		if (is_array($value)) {
			return array_map('wp_slash', $value);
		}
		return addslashes($value);
	}
}

if (!function_exists('wp_check_password')) {
	function wp_check_password($password, $hash, $user_id = '') {
		return password_verify($password, $hash);
	}
}

if (!function_exists('wp_mail')) {
	function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
		global $wp_mail_sent;
		if (!isset($wp_mail_sent)) {
			$wp_mail_sent = [];
		}
		$wp_mail_sent[] = compact('to', 'subject', 'message', 'headers', 'attachments');
		return true;
	}
}

if (!function_exists('get_bloginfo')) {
	function get_bloginfo($show = '', $filter = 'raw') {
		switch ($show) {
			case 'name':
				return 'Test Site';
			case 'url':
				return 'http://test.local';
			case 'admin_email':
				return 'admin@test.local';
			case 'charset':
				return 'UTF-8';
			case 'version':
				return '6.0';
			default:
				return '';
		}
	}
}

if (!function_exists('get_locale')) {
	function get_locale() {
		return 'en_US';
	}
}

if (!function_exists('load_plugin_textdomain')) {
	function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) {
		return true;
	}
}

if (!function_exists('get_user_by')) {
	function get_user_by($field, $value) {
		if ($field === 'login' && $value === 'nuclen_service_account') {
			return new WP_User(999, 'nuclen_service_account');
		}
		return false;
	}
}

if (!function_exists('wp_clear_auth_cookie')) {
	function wp_clear_auth_cookie() {
		// Mock implementation
	}
}

if (!function_exists('wp_set_current_user')) {
	function wp_set_current_user($id, $name = '') {
		global $current_user;
		$current_user = new WP_User($id, $name);
		return $current_user;
	}
}

if (!function_exists('wp_set_auth_cookie')) {
	function wp_set_auth_cookie($user_id, $remember = false, $secure = '', $token = '') {
		// Mock implementation
	}
}

if (!function_exists('get_site_url')) {
	function get_site_url($blog_id = null, $path = '', $scheme = null) {
		return 'http://test.local' . '/' . ltrim($path, '/');
	}
}

if (!function_exists('wp_parse_url')) {
	function wp_parse_url($url, $component = -1) {
		return parse_url($url, $component);
	}
}

if (!function_exists('wp_get_referer')) {
	function wp_get_referer() {
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
	}
}

if (!function_exists('wp_get_raw_referer')) {
	function wp_get_raw_referer() {
		return wp_get_referer();
	}
}

if (!function_exists('wp_validate_redirect')) {
	function wp_validate_redirect($location, $default = '') {
		return $location ?: $default;
	}
}

if (!function_exists('wp_safe_redirect')) {
	function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress') {
		// Mock implementation
		return true;
	}
}

if (!function_exists('wp_redirect')) {
	function wp_redirect($location, $status = 302, $x_redirect_by = 'WordPress') {
		// Mock implementation
		return true;
	}
}

if (!function_exists('wp_sanitize_redirect')) {
	function wp_sanitize_redirect($location) {
		return $location;
	}
}

if (!function_exists('wp_is_mobile')) {
	function wp_is_mobile() {
		return false;
	}
}

if (!function_exists('wp_mkdir_p')) {
	function wp_mkdir_p($target) {
		return is_dir($target) || @mkdir($target, 0777, true);
	}
}

if (!function_exists('wp_unique_filename')) {
	function wp_unique_filename($dir, $filename, $unique_filename_callback = null) {
		return $filename;
	}
}

if (!function_exists('wp_check_filetype')) {
	function wp_check_filetype($filename, $mimes = null) {
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		return [
			'ext' => $ext,
			'type' => 'application/octet-stream'
		];
	}
}

if (!function_exists('wp_check_filetype_and_ext')) {
	function wp_check_filetype_and_ext($file, $filename, $mimes = null) {
		$filetype = wp_check_filetype($filename, $mimes);
		return [
			'ext' => $filetype['ext'],
			'type' => $filetype['type'],
			'proper_filename' => false
		];
	}
}

if (!function_exists('wp_basename')) {
	function wp_basename($path, $suffix = '') {
		return basename($path, $suffix);
	}
}

if (!function_exists('size_format')) {
	function size_format($bytes, $decimals = 0) {
		$quant = [
			'TB' => TB_IN_BYTES,
			'GB' => GB_IN_BYTES,
			'MB' => MB_IN_BYTES,
			'KB' => KB_IN_BYTES,
			'B' => 1,
		];
		
		foreach ($quant as $unit => $mag) {
			if ($bytes >= $mag) {
				return number_format($bytes / $mag, $decimals) . ' ' . $unit;
			}
		}
		
		return number_format($bytes, $decimals) . ' B';
	}
}

if (!function_exists('wp_max_upload_size')) {
	function wp_max_upload_size() {
		return 8 * MB_IN_BYTES;
	}
}

if (!function_exists('wp_is_stream')) {
	function wp_is_stream($path) {
		return strpos($path, '://') !== false;
	}
}

if (!function_exists('wp_tempnam')) {
	function wp_tempnam($filename = '', $dir = '') {
		if (empty($dir)) {
			$dir = sys_get_temp_dir();
		}
		return tempnam($dir, $filename);
	}
}

if (!function_exists('wp_handle_upload')) {
	function wp_handle_upload(&$file, $overrides = false, $time = null) {
		return [
			'file' => '/tmp/uploads/test.jpg',
			'url' => 'http://test.local/wp-content/uploads/test.jpg',
			'type' => 'image/jpeg'
		];
	}
}

if (!function_exists('wp_handle_sideload')) {
	function wp_handle_sideload(&$file, $overrides = false, $time = null) {
		return wp_handle_upload($file, $overrides, $time);
	}
}

if (!function_exists('wp_get_attachment_url')) {
	function wp_get_attachment_url($attachment_id = 0) {
		return 'http://test.local/wp-content/uploads/attachment-' . $attachment_id . '.jpg';
	}
}

if (!function_exists('wp_get_attachment_image_src')) {
	function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail', $icon = false) {
		return [
			'http://test.local/wp-content/uploads/attachment-' . $attachment_id . '.jpg',
			150,
			150,
			false
		];
	}
}

if (!function_exists('wp_get_attachment_metadata')) {
	function wp_get_attachment_metadata($attachment_id, $unfiltered = false) {
		return [
			'width' => 1920,
			'height' => 1080,
			'file' => '2024/01/image.jpg',
			'sizes' => []
		];
	}
}

if (!function_exists('wp_update_attachment_metadata')) {
	function wp_update_attachment_metadata($attachment_id, $data) {
		return true;
	}
}

if (!function_exists('map_deep')) {
	function map_deep($value, $callback) {
		if (is_array($value)) {
			foreach ($value as $index => $item) {
				$value[$index] = map_deep($item, $callback);
			}
		} elseif (is_object($value)) {
			$object_vars = get_object_vars($value);
			foreach ($object_vars as $property_name => $property_value) {
				$value->$property_name = map_deep($property_value, $callback);
			}
		} else {
			$value = call_user_func($callback, $value);
		}
		
		return $value;
	}
}

if (!function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags($string, $remove_breaks = false) {
		$string = strip_tags($string);
		
		if ($remove_breaks) {
			$string = preg_replace('/[\r\n\t ]+/', ' ', $string);
		}
		
		return trim($string);
	}
}

if (!function_exists('wp_salt')) {
	function wp_salt($scheme = 'auth') {
		return 'test_salt_' . $scheme . '_1234567890abcdef';
	}
}

if (!function_exists('wp_rand')) {
	function wp_rand($min = 0, $max = 0) {
		if ($max == 0) {
			$max = $min > 0 ? $min : PHP_INT_MAX;
			$min = 0;
		}
		return rand($min, $max);
	}
}

if (!function_exists('wp_generate_uuid4')) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
}

if (!function_exists('wp_localize_script')) {
	function wp_localize_script($handle, $object_name, $l10n) {
		global $wp_localized_scripts;
		if (!isset($wp_localized_scripts)) {
			$wp_localized_scripts = [];
		}
		$wp_localized_scripts[$object_name] = $l10n;
		return true;
	}
}

if (!function_exists('rest_url')) {
	function rest_url($path = '', $scheme = 'rest') {
		return 'http://test.local/wp-json/' . ltrim($path, '/');
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
		$field = '<input type="hidden" name="' . esc_attr($name) . '" value="test_nonce" />';
		if ($echo) {
			echo $field;
		}
		return $field;
	}
}

if (!function_exists('submit_button')) {
	function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {
		if (!is_array($type)) {
			$type = explode(' ', $type);
		}
		
		$button_shorthand = array('primary', 'small', 'large');
		$classes = array('button');
		
		foreach ($type as $t) {
			if ('secondary' === $t || 'button-secondary' === $t) {
				continue;
			}
			$classes[] = in_array($t, $button_shorthand) ? 'button-' . $t : $t;
		}
		
		$text = $text ? $text : __('Save Changes');
		$classes = implode(' ', array_unique($classes));
		
		$button = '<input type="submit" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="' . $classes . '" value="' . esc_attr($text) . '"';
		
		if (!empty($other_attributes)) {
			if (is_array($other_attributes)) {
				foreach ($other_attributes as $attribute => $value) {
					$button .= ' ' . $attribute . '="' . esc_attr($value) . '"';
				}
			} else {
				$button .= ' ' . $other_attributes;
			}
		}
		
		$button .= ' />';
		
		if ($wrap) {
			$button = '<p class="submit">' . $button . '</p>';
		}
		
		echo $button;
	}
}

if (!function_exists('settings_fields')) {
	function settings_fields($option_group) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '" />';
		echo '<input type="hidden" name="action" value="update" />';
		wp_nonce_field("$option_group-options");
	}
}

if (!function_exists('do_settings_sections')) {
	function do_settings_sections($page) {
		// Mock implementation for tests
		echo '<div class="settings-sections">' . $page . '</div>';
	}
}

if (!function_exists('add_settings_section')) {
	function add_settings_section($id, $title, $callback, $page) {
		global $wp_settings_sections;
		if (!isset($wp_settings_sections)) {
			$wp_settings_sections = [];
		}
		$wp_settings_sections[$page][$id] = array(
			'id' => $id,
			'title' => $title,
			'callback' => $callback
		);
	}
}

if (!function_exists('add_settings_field')) {
	function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array()) {
		global $wp_settings_fields;
		if (!isset($wp_settings_fields)) {
			$wp_settings_fields = [];
		}
		$wp_settings_fields[$page][$section][$id] = array(
			'id' => $id,
			'title' => $title,
			'callback' => $callback,
			'args' => $args
		);
	}
}

if (!function_exists('register_setting')) {
	function register_setting($option_group, $option_name, $args = array()) {
		global $wp_registered_settings;
		if (!isset($wp_registered_settings)) {
			$wp_registered_settings = [];
		}
		$wp_registered_settings[$option_name] = array(
			'group' => $option_group,
			'name' => $option_name,
			'args' => $args
		);
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

