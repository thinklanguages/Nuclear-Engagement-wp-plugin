<?php
namespace NuclearEngagement\Services {
    function update_post_meta($postId, $key, $value) { return false; }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($text) { return trim($text); }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Services\ContentStorageService;
    use NuclearEngagement\Core\SettingsRepository;

    class ContentStorageServiceFailureTest extends TestCase {
        protected function setUp(): void {
            global $wp_options, $wp_autoload, $wp_meta;
            $wp_options = $wp_autoload = $wp_meta = [];
            SettingsRepository::reset_for_tests();
        }

        public function test_store_results_returns_failure_status(): void {
            $settings = SettingsRepository::get_instance();
            $service = new ContentStorageService($settings);
            $data = ['summary' => 'S'];
            $result = $service->storeResults([1 => $data], 'summary');
            $this->assertSame('Failed to update summary data for post 1', $result[1]);
        }
    }
}

