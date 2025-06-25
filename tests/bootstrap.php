<?php
// Define ABSPATH to bypass exit calls
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
// Track calls to dbDelta in unit tests
global $dbDelta_called;
$dbDelta_called = false;
// Load shared WordPress function stubs
require_once __DIR__ . '/wp-stubs.php';
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

// Include files for tests
require_once __DIR__ . '/../nuclear-engagement/inc/Core/Defaults.php';
require_once __DIR__ . '/../nuclear-engagement/inc/OptinData.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Core/SettingsRepository.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Core/SettingsSanitizer.php';
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

// Additional stubs used by services
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

