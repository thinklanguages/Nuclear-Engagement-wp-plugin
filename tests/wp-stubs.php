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
		return $nonce === 'valid_nonce';
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

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        return $GLOBALS['wp_cache'][$group][$key] ?? false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        $GLOBALS['wp_cache'][$group][$key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        unset($GLOBALS['wp_cache'][$group][$key]);
        return true;
    }
}

if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag) {
        return strpos($content, '[' . $tag) !== false;
    }
}

if (!function_exists('has_block')) {
    function has_block($block_name, $post = null) {
        return false;
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data) {
        if (is_serialized($data)) {
            return @unserialize($data);
        }
        return $data;
    }
}

if (!function_exists('is_serialized')) {
    function is_serialized($data, $strict = true) {
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' === $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        if ($strict) {
            $lastc = substr($data, -1);
            if (';' !== $lastc && '}' !== $lastc) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace = strpos($data, '}');
            if (false === $semicolon && false === $brace) {
                return false;
            }
            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }
            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];
        switch ($token) {
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (false === strpos($data, '"')) {
                    return false;
                }
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';
                return (bool) preg_match("/^{$token}:[0-9.E+-]+;$end/", $data);
        }
        return false;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return $GLOBALS['is_admin'] ?? false;
    }
}

if (!function_exists('is_singular')) {
    function is_singular($post_types = '') {
        return $GLOBALS['is_singular'] ?? true;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        return 1;
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $json_params;
        private $headers = [];
        public $method;
        public $route;
        
        public function __construct($method = '', $route = '') {
            $this->method = $method;
            $this->route = $route;
        }
        
        public function set_body_params($params) {
            $this->json_params = $params;
        }
        
        public function get_json_params() {
            return $this->json_params;
        }
        
        public function get_header($header) {
            return $this->headers[$header] ?? null;
        }
        
        public function set_header($header, $value) {
            $this->headers[$header] = $value;
        }
        
        public function get_route() {
            return $this->route;
        }
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        unset($GLOBALS['wp_transients'][$transient]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return $GLOBALS['wp_transients'][$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        $GLOBALS['wp_transients'][$transient] = $value;
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return $GLOBALS['current_user_id'] ?? 1;
    }
}

if (!function_exists('wp_get_upload_dir')) {
    function wp_get_upload_dir() {
        return wp_upload_dir();
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html, $allowed_protocols = array()) {
        return strip_tags($string);
    }
}

if (!function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0) {
        if (!isset($GLOBALS['wp_cache'][$group][$key])) {
            $GLOBALS['wp_cache'][$group][$key] = $data;
            return true;
        }
        return false;
    }
}

if (!function_exists('maybe_serialize')) {
    function maybe_serialize($data) {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }
        return $data;
    }
}

if (!function_exists('wp_slash')) {
    function wp_slash($value) {
        if (is_string($value)) {
            return addslashes($value);
        }
        if (is_array($value)) {
            return array_map('wp_slash', $value);
        }
        return $value;
    }
}

