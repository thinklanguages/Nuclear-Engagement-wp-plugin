<?php
// Mock WordPress functions for AdminNoticeServiceTest

if (!function_exists('add_action')) {
    function add_action(...$args) {
        if (!isset($GLOBALS['ans_actions'])) {
            $GLOBALS['ans_actions'] = [];
        }
        $GLOBALS['ans_actions'][] = $args;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $GLOBALS['test_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        $GLOBALS['test_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return $GLOBALS['test_current_user_id'] ?? 1;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content) {
        return $content;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . $path;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text) {
        return $text;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'test_nonce';
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg = false, $die = true) {
        return 1;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return $str;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        die();
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return $GLOBALS['test_current_user_can'] ?? true;
    }
}