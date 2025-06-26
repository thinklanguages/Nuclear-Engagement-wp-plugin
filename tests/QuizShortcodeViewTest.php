<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Front\QuizShortcode;
use NuclearEngagement\Front\QuizView;
use NuclearEngagement\Core\SettingsRepository;

namespace NuclearEngagement\Front {
    function get_the_ID() { return $GLOBALS['current_post_id'] ?? 0; }
    function maybe_unserialize($data) { return is_string($data) ? unserialize($data) : $data; }
}

namespace {
    if (!function_exists('esc_html')) { function esc_html($t) { return $t; } }
    if (!function_exists('esc_html__')) { function esc_html__($t,$d=null){ return $t; } }
    if (!function_exists('__')) { function __($t,$d=null){ return $t; } }
    if (!function_exists('wp_kses_post')) { function wp_kses_post($html){ return $html; } }
    if (!function_exists('shortcode_unautop')) { function shortcode_unautop($html){ return $html; } }
    if (!function_exists('esc_attr')) { function esc_attr($t){ return $t; } }

    class DummyFront {
        public int $calls = 0;
        public function nuclen_force_enqueue_assets(): void { $this->calls++; }
    }

    class QuizShortcodeViewTest extends TestCase {
        protected function setUp(): void {
            global $wp_options, $wp_meta;
            $wp_options = $wp_meta = [];
            SettingsRepository::reset_for_tests();
        }

        private function makeShortcode(DummyFront $front): QuizShortcode {
            $settings = SettingsRepository::get_instance();
            return new QuizShortcode($settings, $front);
        }

        public function test_render_outputs_markup_with_valid_data(): void {
            global $wp_meta, $current_post_id;
            $current_post_id = 3;
            $wp_meta[3]['nuclen-quiz-data'] = [
                'questions' => [ [ 'question' => 'Q', 'answers' => ['A'] ] ],
            ];

            $settings = SettingsRepository::get_instance();
            $settings->set_string('quiz_title', 'Title')
                     ->set_bool('show_attribution', true)
                     ->set_string('custom_quiz_html_before', '<p>Start</p>')
                     ->save();

            $front = new DummyFront();
            $sc = $this->makeShortcode($front);
            $html = $sc->render();

            $this->assertStringContainsString('id="nuclen-quiz-container"', $html);
            $this->assertStringContainsString('id="nuclen-quiz-title"', $html);
            $this->assertStringContainsString('class="nuclen-attribution"', $html);
            $this->assertSame(1, $front->calls);
        }

        public function test_render_returns_empty_string_when_data_invalid(): void {
            global $wp_meta, $current_post_id;
            $current_post_id = 4;
            $wp_meta[4]['nuclen-quiz-data'] = [ 'questions' => [ [ 'question' => '', 'answers' => [] ] ] ];

            $front = new DummyFront();
            $sc = $this->makeShortcode($front);
            $this->assertSame('', $sc->render());
        }

        public function test_quiz_view_outputs_markup(): void {
            $view = new QuizView();
            $settings = [
                'quiz_title'       => 'T',
                'html_before'      => '<em>Hi</em>',
                'show_attribution' => false,
            ];
            $html = $view->container($settings);
            $this->assertStringContainsString('<section id="nuclen-quiz-container"', $html);
            $this->assertStringContainsString('nuclen-quiz-progress-bar', $html);
            $this->assertNotEmpty($view->attribution(true));
            $this->assertSame('', $view->attribution(false));
        }
    }
}
