<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\SettingsRepository;

namespace NuclearEngagement\Services {
    function update_post_meta($postId, $key, $val) {
        $GLOBALS['wp_meta'][$postId][$key] = $val;
        return true;
    }
}

namespace {
    class ContentStorageServiceTest extends TestCase {
        protected function setUp(): void {
            global $wp_meta, $wp_options, $wp_autoload;
            $wp_meta = $wp_options = $wp_autoload = [];
            SettingsRepository::reset_for_tests();
        }

        public function test_store_quiz_data_saves_formatted_meta(): void {
            $settings = SettingsRepository::get_instance();
            $service = new ContentStorageService($settings);
            $data = [
                'questions' => [
                    ['question' => 'Q1', 'answers' => ['A'], 'explanation' => 'E'],
                ],
                'date' => '2025-05-01',
            ];
            $service->storeQuizData(1, $data);
            $expected = [
                'questions' => [
                    ['question' => 'Q1', 'answers' => ['A'], 'explanation' => 'E'],
                ],
                'date' => '2025-05-01',
            ];
            $this->assertSame($expected, $GLOBALS['wp_meta'][1]['nuclen-quiz-data']);
        }

        public function test_store_quiz_data_throws_on_invalid_questions(): void {
            $settings = SettingsRepository::get_instance();
            $service = new ContentStorageService($settings);
            $this->expectException(InvalidArgumentException::class);
            $service->storeQuizData(2, []);
        }
    }
}
