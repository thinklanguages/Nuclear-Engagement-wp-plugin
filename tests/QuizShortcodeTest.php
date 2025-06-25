<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Front\QuizShortcode;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Container;

if (!function_exists('get_the_ID')) {
    function get_the_ID() { return 1; }
}

class DummyFront extends FrontClass {
    public function __construct(SettingsRepository $settings) {
        parent::__construct('ne', '1.0', $settings, Container::getInstance());
    }
    public function nuclen_force_enqueue_assets(): void {}
}

class QuizShortcodeTest extends TestCase {
    protected function setUp(): void {
        global $wp_meta, $wp_options, $wp_autoload;
        $wp_meta = $wp_options = $wp_autoload = [];
        SettingsRepository::reset_for_tests();
    }

    private function makeShortcode(): QuizShortcode {
        $settings = SettingsRepository::get_instance();
        $front    = new DummyFront($settings);
        return new QuizShortcode($settings, $front);
    }

    public function test_render_returns_html_with_valid_meta(): void {
        global $wp_meta;
        $wp_meta[1]['nuclen-quiz-data'] = [
            'questions' => [
                ['question' => 'Q1?', 'answers' => ['A', 'B'], 'explanation' => 'E'],
            ],
        ];
        $shortcode = $this->makeShortcode();
        $out = $shortcode->render();
        $this->assertStringContainsString('nuclen-root', $out);
        $this->assertStringContainsString('nuclen-quiz-container', $out);
    }

    public function test_render_returns_empty_string_with_invalid_meta(): void {
        global $wp_meta;
        $wp_meta[1]['nuclen-quiz-data'] = ['questions' => []];
        $shortcode = $this->makeShortcode();
        $this->assertSame('', $shortcode->render());
    }
}
