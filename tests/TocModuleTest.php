<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Container;

// ------------------------------------------------------
// WordPress function stubs
// ------------------------------------------------------
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'NUCLEN_TOC_SCROLL_OFFSET_DEFAULT' ) ) {
    define( 'NUCLEN_TOC_SCROLL_OFFSET_DEFAULT', 72 );
}
if ( ! defined( 'NUCLEN_TOC_DIR' ) ) {
    define( 'NUCLEN_TOC_DIR', dirname( __DIR__ ) . '/nuclear-engagement/inc/Modules/TOC/' );
}
if ( ! defined( 'NUCLEN_TOC_URL' ) ) {
    define( 'NUCLEN_TOC_URL', 'http://example.com/' );
}

$GLOBALS['wp_cache'] = [];
$GLOBALS['transients'] = [];

if ( ! function_exists( 'wp_cache_get' ) ) {
    function wp_cache_get( $key, $group = '' ) {
        return $GLOBALS['wp_cache'][ $group ][ $key ] ?? false;
    }
}
if ( ! function_exists( 'wp_cache_set' ) ) {
    function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
        $GLOBALS['wp_cache'][ $group ][ $key ] = $value;
    }
}
if ( ! function_exists( 'wp_cache_delete' ) ) {
    function wp_cache_delete( $key, $group = '' ) {
        unset( $GLOBALS['wp_cache'][ $group ][ $key ] );
    }
}
if ( ! function_exists( 'wp_cache_flush_group' ) ) {
    function wp_cache_flush_group( $group ) {
        unset( $GLOBALS['wp_cache'][ $group ] );
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        return $GLOBALS['transients'][ $key ] ?? false;
    }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $ttl = 0 ) {
        $GLOBALS['transients'][ $key ] = $value;
    }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['transients'][ $key ] );
    }
}
if ( ! function_exists( 'wp_list_pluck' ) ) {
    function wp_list_pluck( array $list, string $field ) {
        $out = [];
        foreach ( $list as $item ) {
            if ( is_array( $item ) && isset( $item[ $field ] ) ) {
                $out[] = $item[ $field ];
            } elseif ( is_object( $item ) && isset( $item->$field ) ) {
                $out[] = $item->$field;
            }
        }
        return $out;
    }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($text) {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9_-]+/', '-', $text);
        return trim($text, '-');
    }
}
if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($text) {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9_-]+/', '-', $text);
        return trim($text, '-');
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return trim($text);
    }
}
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES);
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES);
    }
}
if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) { return esc_html($text); }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = null) { return esc_attr($text); }
}
if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) { return $value; }
}
if (!function_exists('add_filter')) {
    function add_filter(...$args) {}
}
if ( ! function_exists( 'add_shortcode' ) ) {
    function add_shortcode( ...$args ) {}
}
if ( ! function_exists( 'shortcode_atts' ) ) {
    function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
        $out = $pairs;
        foreach ( $pairs as $name => $default ) {
            if ( isset( $atts[ $name ] ) ) {
                $out[ $name ] = $atts[ $name ];
            }
        }
        foreach ( $atts as $name => $value ) {
            if ( ! isset( $out[ $name ] ) ) {
                $out[ $name ] = $value;
            }
        }
        return $out;
    }
}
if ( ! function_exists( 'wp_unique_id' ) ) {
    function wp_unique_id( $prefix = '' ) {
        static $i = 1;
        return $prefix . $i++;
    }
}
if ( ! function_exists( 'wp_script_is' ) ) {
    function wp_script_is( $handle, $list = 'enqueued' ) { return false; }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle ) {}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle ) {}
}
if ( ! function_exists( 'wp_add_inline_style' ) ) {
    function wp_add_inline_style( $handle, $css ) {}
}
if ( ! function_exists( 'wp_register_style' ) ) {
    function wp_register_style( $handle, $src, $deps = [], $ver = '' ) {}
}
if ( ! function_exists( 'wp_register_script' ) ) {
    function wp_register_script( $handle, $src, $deps = [], $ver = '', $in_footer = false ) {}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( $handle, $object_name, $l10n ) {}
}
if ( ! function_exists( 'is_singular' ) ) {
    function is_singular() { return true; }
}

// ------------------------------------------------------
// Load plugin classes
// ------------------------------------------------------
require_once NUCLEN_TOC_DIR . 'includes/polyfills.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-utils.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-view.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-assets.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-headings.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-render.php';
require_once dirname(__DIR__) . '/nuclear-engagement/includes/SettingsCache.php';
require_once dirname(__DIR__) . '/nuclear-engagement/includes/SettingsRepository.php';
require_once dirname(__DIR__) . '/nuclear-engagement/includes/Defaults.php';
require_once dirname(__DIR__) . '/nuclear-engagement/includes/Container.php';

class TocModuleTest extends TestCase {
    protected function setUp(): void {
        global $wp_posts, $wp_cache, $transients;
        $wp_posts   = [];
        $wp_cache   = [];
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
        $c = Container::getInstance();
        $c->register('settings', static function () use ($settings) {
            return $settings;
        });
        return $settings;
    }

    public function test_heading_ids_are_injected() {
        $this->registerSettings();
        $headings = new \NuclearEngagement\Modules\TOC\Nuclen_TOC_Headings();
        $html = '<h2>Intro</h2><h2>Intro</h2><h3 class="no-toc">Skip</h3><h2 id="existing">X</h2>';
        $result = $headings->add_heading_ids($html);
        $this->assertStringContainsString('<h2 id="intro">Intro</h2>', $result);
        $this->assertStringContainsString('<h2 id="intro-2">Intro</h2>', $result);
        $this->assertStringContainsString('<h3 class="no-toc">Skip</h3>', $result);
        $this->assertStringContainsString('<h2 id="existing">X</h2>', $result);
    }

    public function test_shortcode_outputs_expected_markup() {
        global $post;
        $post = (object)[
            'ID' => 1,
            'post_content' => '<h2>One</h2><h3>Sub</h3>',
        ];
        $this->registerSettings();
        $render = new \NuclearEngagement\Modules\TOC\Nuclen_TOC_Render();
        $out = $render->nuclen_toc_shortcode([]);
        $this->assertStringContainsString('<nav id="', $out);
        $this->assertStringContainsString('<a href="#one">One</a>', $out);
        $this->assertStringContainsString('<a href="#sub">Sub</a>', $out);
        $this->assertStringContainsString('class="nuclen-toc', $out);
    }

    public function test_cache_is_cleared_for_post() {
        global $wp_posts, $wp_cache, $transients;

        $wp_posts[1] = (object) [
            'ID'           => 1,
            'post_content' => '<h2>One</h2>',
        ];

        $this->registerSettings();

        \NuclearEngagement\Modules\TOC\Nuclen_TOC_Utils::extract( $wp_posts[1]->post_content, [2, 3] );
        $key = md5( $wp_posts[1]->post_content ) . '_23';

        $this->assertArrayHasKey( $key, $wp_cache['nuclen_toc'] );
        $this->assertArrayHasKey( 'nuclen_toc_' . $key, $transients );

        \NuclearEngagement\Modules\TOC\Nuclen_TOC_Utils::clear_cache_for_post( 1 );

        $this->assertArrayNotHasKey( $key, $wp_cache['nuclen_toc'] );
        $this->assertArrayNotHasKey( 'nuclen_toc_' . $key, $transients );
    }
}
