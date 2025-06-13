<?php
// Define ABSPATH to bypass exit calls
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
// Minimal stubs for WordPress functions used in included files
if (!function_exists('add_action')) {
    function add_action(...$args) {}
}
if (!function_exists('absint')) {
    function absint($maybeint) { return abs(intval($maybeint)); }
}

// Include files for tests
require_once __DIR__ . '/../nuclear-engagement/includes/Defaults.php';
require_once __DIR__ . '/../nuclear-engagement/includes/OptinData.php';
require_once __DIR__ . '/../nuclear-engagement/includes/SettingsRepository.php';
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

