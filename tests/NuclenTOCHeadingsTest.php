<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Modules\TOC\Nuclen_TOC_Headings;

// ------------------------------------------------------
// Constants and WordPress function stubs
// ------------------------------------------------------
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }
if (!defined('NUCLEN_TOC_SCROLL_OFFSET_DEFAULT')) { define('NUCLEN_TOC_SCROLL_OFFSET_DEFAULT', 72); }
if (!defined('NUCLEN_TOC_DIR')) { define('NUCLEN_TOC_DIR', dirname(__DIR__) . '/nuclear-engagement/inc/Modules/TOC/'); }
if (!defined('NUCLEN_TOC_URL')) { define('NUCLEN_TOC_URL', 'http://example.com/'); }

$GLOBALS['wp_cache'] = $GLOBALS['transients'] = [];

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') { return $GLOBALS['wp_cache'][$group][$key] ?? false; }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $value, $group = '', $ttl = 0) { $GLOBALS['wp_cache'][$group][$key] = $value; }
}
if (!function_exists('get_transient')) {
    function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $ttl = 0) { $GLOBALS['transients'][$key] = $value; }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($text) { $text = strtolower(trim($text)); $text = preg_replace('/[^a-z0-9_-]+/','-',$text); return trim($text,'-'); }
}
if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($text) { $text = strtolower(trim($text)); $text = preg_replace('/[^a-z0-9_-]+/','-',$text); return trim($text,'-'); }
}
if (!function_exists('esc_attr')) { function esc_attr($t){ return htmlspecialchars($t, ENT_QUOTES); } }
if (!function_exists('__')) { function __($t, $d = null) { return $t; } }
if (!function_exists('apply_filters')) { function apply_filters($hook, $value) { return $value; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($t){ return strip_tags($t); } }
if (!function_exists('get_the_ID')) { function get_the_ID() { return $GLOBALS['current_post_id'] ?? 0; } }

namespace NuclearEngagement\Modules\TOC {
    if (!function_exists('apply_filters')) {
        function apply_filters($hook, $value) {
            if ($hook === 'nuclen_toc_enable_heading_ids' && array_key_exists('toc_enable_heading_ids', $GLOBALS)) {
                return $GLOBALS['toc_enable_heading_ids'];
            }
            return \apply_filters($hook, $value);
        }
    }
}

namespace {
    require_once NUCLEN_TOC_DIR . 'includes/polyfills.php';
    require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-utils.php';
    require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-headings.php';

    class NuclenTOCHeadingsTest extends TestCase {
        protected function setUp(): void {
            $GLOBALS['toc_enable_heading_ids'] = true;
            $GLOBALS['wp_cache'] = [];
            $GLOBALS['transients'] = [];
        }

        public function test_injects_unique_ids(): void {
            $headings = new \NuclearEngagement\Modules\TOC\Nuclen_TOC_Headings();
            $html = '<h2>Intro</h2><h2>Intro</h2><h2 id="custom">X</h2>';
            $out = $headings->add_heading_ids($html);
            $this->assertStringContainsString('<h2 id="intro">Intro</h2>', $out);
            $this->assertStringContainsString('<h2 id="intro-2">Intro</h2>', $out);
            $this->assertStringContainsString('<h2 id="custom">X</h2>', $out);
        }

        public function test_filter_disables_injection(): void {
            $GLOBALS['toc_enable_heading_ids'] = false;
            $headings = new \NuclearEngagement\Modules\TOC\Nuclen_TOC_Headings();
            $html = '<h2>Intro</h2>';
            $this->assertSame($html, $headings->add_heading_ids($html));
        }
    }
}
