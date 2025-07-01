<?php
if (!function_exists('wp_upload_dir')) {
	function wp_upload_dir() {
		return [
			'basedir' => $GLOBALS['test_upload_basedir'] ?? sys_get_temp_dir(),
			'baseurl'  => $GLOBALS['test_upload_baseurl'] ?? 'http://example.com/uploads',
			'error'    => $GLOBALS['test_upload_error'] ?? '',
		];
	}
}
if (!function_exists('wp_mkdir_p')) {
	function wp_mkdir_p($dir) {
		if (!empty($GLOBALS['test_wp_mkdir_p_failure'])) {
			return false;
		}
		return mkdir($dir, 0777, true);
	}
}
if (!function_exists('wp_verify_nonce')) {
	function wp_verify_nonce($nonce, $action) {
		if (array_key_exists('test_verify_nonce', $GLOBALS)) {
			return (bool) $GLOBALS['test_verify_nonce'];
		}
		return $nonce === 'valid';
	}
}
if (!function_exists('wp_remote_post')) {
	function wp_remote_post(string $url, array $args = []) {
		return $GLOBALS['test_http_response'] ?? null;
	}
}
if (!function_exists('wp_remote_retrieve_response_code')) {
	function wp_remote_retrieve_response_code($res) {
		return is_array($res) ? ($res['code'] ?? 0) : 0;
	}
}
if (!function_exists('wp_remote_retrieve_body')) {
	function wp_remote_retrieve_body($res) {
		return is_array($res) ? ($res['body'] ?? '') : '';
	}
}

if (!function_exists('locate_template')) {
        function locate_template($names, $load = false, $require_once = true, $args = []) {
                return '';
        }
}

if (!function_exists('load_template')) {
       function load_template($file, $require_once = true, $args = []) {
               if (is_array($args)) {
                       extract($args, EXTR_SKIP);
               }
               if ($require_once) {
                       require $file;
               } else {
                       require $file;
               }
       }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        return true;
    }
}

