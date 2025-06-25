<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\SettingsRepository;

namespace NuclearEngagement\Services {
    function update_post_meta($postId, $key, $value) {
        $GLOBALS['wp_meta'][$postId][$key] = $value;
        return true;
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($text) { return trim($text); }
    }
}

namespace {
    class ContentStorageServiceTest extends TestCase {
        protected function setUp(): void {
            global $wp_options, $wp_autoload, $wp_meta;
            $wp_options = $wp_autoload = $wp_meta = [];
            NuclearEngagement\SettingsRepository::reset_for_tests();
        }

        public function test_store_quiz_data_filters_and_limits_answers(): void {
            $settings = NuclearEngagement\SettingsRepository::get_instance();
            $settings->set_int('answers_per_question', 2)->save();
            $service = new NuclearEngagement\Services\ContentStorageService($settings);
            $data = [
                'questions' => [
                    [
                        'question' => 'Q1',
                        'answers' => ['A1','A2','A3',''],
                        'explanation' => 'E1',
                    ],
                    [
                        'question' => '',
                        'answers' => ['A'],
                    ],
                    [
                        'question' => 'Q2',
                        'answers' => ['A1'],
                    ],
                ],
                'date' => '2025-01-01',
            ];
            $service->storeQuizData(1, $data);
            $expected = [
                'questions' => [
                    [
                        'question' => 'Q1',
                        'answers' => ['A1','A2'],
                        'explanation' => 'E1',
                    ],
                    [
                        'question' => 'Q2',
                        'answers' => ['A1'],
                        'explanation' => '',
                    ],
                ],
                'date' => '2025-01-01',
            ];
            $this->assertSame($expected, $GLOBALS['wp_meta'][1]['nuclen-quiz-data']);
        }

        public function test_store_quiz_data_throws_when_no_valid_question(): void {
            $settings = NuclearEngagement\SettingsRepository::get_instance();
            $service = new NuclearEngagement\Services\ContentStorageService($settings);
            $data = [
                'questions' => [
                    [ 'question' => '', 'answers' => [''] ],
                    [ 'question' => ' ', 'answers' => [] ],
                ],
            ];
            $this->expectException(\InvalidArgumentException::class);
            $service->storeQuizData(1, $data);
        }

        public function test_store_quiz_data_sanitizes_and_adds_date(): void {
            $settings = NuclearEngagement\SettingsRepository::get_instance();
            $service  = new NuclearEngagement\Services\ContentStorageService($settings);
            $data = [
                'questions' => [
                    [
                        'question'    => '  Q1  ',
                        'answers'     => [' A1 ', ''],
                        'explanation' => '  E1 ',
                    ],
                ],
            ];
            $service->storeQuizData(5, $data);
            $saved = $GLOBALS['wp_meta'][5]['nuclen-quiz-data'];
            $this->assertSame('Q1', $saved['questions'][0]['question']);
            $this->assertSame(['A1'], $saved['questions'][0]['answers']);
            $this->assertSame('E1', $saved['questions'][0]['explanation']);
            $this->assertArrayHasKey('date', $saved);
            $this->assertNotEmpty($saved['date']);
        }
    }
}
