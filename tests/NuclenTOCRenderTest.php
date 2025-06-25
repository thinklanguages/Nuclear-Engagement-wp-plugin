<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Modules\TOC\Nuclen_TOC_Render;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Container;

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
if (!function_exists('sanitize_title')) { function sanitize_title($t){ $t=strtolower(trim($t)); $t=preg_replace('/[^a-z0-9_-]+/','-',$t); return trim($t,'-'); } }
if (!function_exists('sanitize_html_class')) { function sanitize_html_class($t){ $t=strtolower(trim($t)); $t=preg_replace('/[^a-z0-9_-]+/','-',$t); return trim($t,'-'); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return trim($t); } }
if (!function_exists('esc_html')) { function esc_html($t){ return htmlspecialchars($t,ENT_QUOTES); } }
if (!function_exists('esc_attr')) { function esc_attr($t){ return htmlspecialchars($t,ENT_QUOTES); } }
if (!function_exists('__')) { function __($t,$d=null){ return $t; } }
if (!function_exists('esc_html__')) { function esc_html__($t,$d=null){ return esc_html($t); } }
if (!function_exists('esc_attr__')) { function esc_attr__($t,$d=null){ return esc_attr($t); } }
if (!function_exists('apply_filters')) { function apply_filters($hook,$value){ return $value; } }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('add_shortcode')) { function add_shortcode(...$a) {} }
if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs,$atts,$shortcode='') {
        $out=$pairs; foreach($pairs as $name=>$default){ if(isset($atts[$name])) $out[$name]=$atts[$name]; }
        foreach($atts as $name=>$value){ if(!isset($out[$name])) $out[$name]=$value; }
        return $out;
    }
}
if (!function_exists('wp_unique_id')) { function wp_unique_id($p=''){ static $i=1; return $p.$i++; } }
if (!function_exists('wp_script_is')) { function wp_script_is($h,$l='enqueued'){ return false; } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script($h) {} }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style($h) {} }
if (!function_exists('wp_add_inline_style')) { function wp_add_inline_style($h,$c) {} }
if (!function_exists('wp_register_style')) { function wp_register_style($h,$s,$d=[],$v='') {} }
if (!function_exists('wp_register_script')) { function wp_register_script($h,$s,$d=[],$v='',$f=false) {} }
if (!function_exists('wp_localize_script')) { function wp_localize_script($h,$o,$l) {} }
if (!function_exists('is_singular')) { function is_singular(){ return true; } }
if (!function_exists('get_the_ID')) { function get_the_ID(){ return $GLOBALS['current_post_id'] ?? 0; } }

namespace {
    require_once NUCLEN_TOC_DIR . 'includes/polyfills.php';
    require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-utils.php';
    require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-view.php';
    require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-assets.php';
    require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-headings.php';
    require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-render.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsCache.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsRepository.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Defaults.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Container.php';

    class NuclenTOCRenderTest extends TestCase {
        protected function setUp(): void {
            global $wp_posts, $current_post_id, $wp_cache, $transients;
            $wp_posts = [];
            $current_post_id = 0;
            $wp_cache = [];
            $transients = [];
            SettingsRepository::reset_for_tests();
            Container::getInstance()->reset();
        }

        private function registerSettings(): SettingsRepository {
            $settings = SettingsRepository::get_instance([
                'toc_heading_levels' => [2,3],
                'toc_show_toggle'    => true,
                'toc_show_content'   => true,
            ]);
            Container::getInstance()->register('settings', static function () use ($settings) {
                return $settings;
            });
            return $settings;
        }

        public function test_shortcode_outputs_markup(): void {
            global $wp_posts, $current_post_id;
            $current_post_id = 1;
            $wp_posts[1] = (object)[
                'ID' => 1,
                'post_content' => '<h2>One</h2><h3>Sub</h3>',
            ];
            $this->registerSettings();
            $render = new \NuclearEngagement\Modules\TOC\Nuclen_TOC_Render();
            $out = $render->nuclen_toc_shortcode([]);
            $this->assertStringContainsString('<nav id="', $out);
            $this->assertStringContainsString('<a href="#one">One</a>', $out);
            $this->assertStringContainsString('<a href="#sub">Sub</a>', $out);
        }

        public function test_shortcode_returns_empty_when_no_post(): void {
            $this->registerSettings();
            $render = new \NuclearEngagement\Modules\TOC\Nuclen_TOC_Render();
            $this->assertSame('', $render->nuclen_toc_shortcode([]));
        }
    }
}
