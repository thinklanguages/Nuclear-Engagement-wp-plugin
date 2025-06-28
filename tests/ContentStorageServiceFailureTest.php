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
	use NuclearEngagement\Modules\Summary\Summary_Service;

	class ContentStorageServiceFailureTest extends TestCase {
		protected function setUp(): void {
			global $wp_options, $wp_autoload, $wp_meta;
			$wp_options = $wp_autoload = $wp_meta = [];
			SettingsRepository::reset_for_tests();
		}

		public function test_store_results_succeeds_when_meta_matches(): void {
			$settings = SettingsRepository::get_instance();
			$service  = new ContentStorageService($settings);
			$data     = ['summary' => 'S', 'date' => '2025-01-01'];
			$GLOBALS['wp_meta'][1][Summary_Service::META_KEY] = $data;
			$result   = $service->storeResults([1 => $data], 'summary');
			$this->assertTrue($result[1]);
		}

		public function test_store_results_returns_failure_status_for_mismatch(): void {
			$settings = SettingsRepository::get_instance();
			$service  = new ContentStorageService($settings);
			$data     = ['summary' => 'S', 'date' => '2025-01-01'];
			$GLOBALS['wp_meta'][2][Summary_Service::META_KEY] = ['summary' => 'X', 'date' => '2025-01-01'];
			$result   = $service->storeResults([2 => $data], 'summary');
			$this->assertSame('Failed to update summary data for post 2', $result[2]);
		}
	}
}
