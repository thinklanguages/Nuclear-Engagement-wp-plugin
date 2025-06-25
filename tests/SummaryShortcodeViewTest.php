<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Front\SummaryShortcode;
use NuclearEngagement\Front\SummaryView;
use NuclearEngagement\SettingsRepository;

namespace NuclearEngagement\Front {
    function get_the_ID() { return $GLOBALS['current_post_id'] ?? 0; }
}

namespace {
    if (!function_exists('esc_html')) {
        function esc_html($t) { return $t; }
    }
    if (!function_exists('esc_html__')) {
        function esc_html__($t, $d = null) { return $t; }
    }
    if (!function_exists('__')) {
        function __($t, $d = null) { return $t; }
    }
    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($html) { return $html; }
    }

    class DummyFront {
        public int $calls = 0;
        public function nuclen_force_enqueue_assets(): void { $this->calls++; }
    }

    class SummaryShortcodeViewTest extends TestCase {
        protected function setUp(): void {
            global $wp_options, $wp_meta;
            $wp_options = $wp_meta = [];
            SettingsRepository::reset_for_tests();
        }

        private function makeShortcode(DummyFront $front): SummaryShortcode {
            $settings = SettingsRepository::get_instance();
            return new SummaryShortcode($settings, $front);
        }

        public function test_render_outputs_markup_with_valid_data(): void {
            global $wp_meta, $current_post_id;
            $current_post_id = 1;
            $wp_meta[1]['nuclen-summary-data'] = ['summary' => '<p>Hi</p>'];

            $settings = SettingsRepository::get_instance();
            $settings->set_string('summary_title', 'Facts')->set_bool('show_attribution', true)->save();

            $front = new DummyFront();
            $sc = $this->makeShortcode($front);
            $html = $sc->render();

            $this->assertStringContainsString('id="nuclen-summary-container"', $html);
            $this->assertStringContainsString('<h2 id="nuclen-summary-title" class="nuclen-fg">Facts</h2>', $html);
            $this->assertStringContainsString('class="nuclen-attribution"', $html);
            $this->assertSame(1, $front->calls);
        }

        public function test_render_returns_empty_string_when_data_invalid(): void {
            global $wp_meta, $current_post_id;
            $current_post_id = 2;
            $wp_meta[2]['nuclen-summary-data'] = ['summary' => ''];

            $front = new DummyFront();
            $sc = $this->makeShortcode($front);
            $this->assertSame('', $sc->render());
        }

        public function test_summary_view_outputs_markup(): void {
            $view = new SummaryView();
            $data = ['summary' => '<p>Ok</p>'];
            $settings = ['summary_title' => 'Title'];
            $html = $view->container($data, $settings);
            $this->assertStringContainsString('<section id="nuclen-summary-container"', $html);
            $this->assertStringContainsString('Title', $html);
            $this->assertStringContainsString('<p>Ok</p>', $html);

            $this->assertNotEmpty($view->attribution(true));
            $this->assertSame('', $view->attribution(false));
        }
    }
}
