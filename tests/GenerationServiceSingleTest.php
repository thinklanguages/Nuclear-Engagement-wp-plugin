<?php
namespace NuclearEngagement\Services {
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\GenerationService;
	use NuclearEngagement\Core\SettingsRepository;
	use NuclearEngagement\Services\ApiException;
	class DummyGenApi {
		public ?\Exception $exception = null;
		public array $response = [];
		public function send_posts_to_generate(array $data): array {
			if ($this->exception) {
				throw $this->exception;
			}
			return $this->response;
		}
		public function fetch_updates(string $id): array { return []; }
	}

	class DummyStorageGenerationSingle {
		public array $stored = [];
		public function storeResults(array $r, string $t): array {
			$this->stored[] = [$r, $t];
			return array_fill_keys(array_keys($r), true);
		}
	}

	class GenerationServiceSingleTest extends TestCase {
		protected function setUp(): void {
			global $wp_posts, $wp_events, $wp_options, $wp_meta, $wp_autoload;
			$wp_posts = $wp_events = $wp_options = $wp_meta = $wp_autoload = [];
			SettingsRepository::reset_for_tests();
		}

		private function makeService(?DummyGenApi $api = null, ?DummyStorageGenerationSingle $store = null): GenerationService {
			$settings = SettingsRepository::get_instance();
			$api = $api ?: new DummyGenApi();
			$store = $store ?: new DummyStorageGenerationSingle();
			return new GenerationService($settings, $api, $store);
		}

		public function test_generate_single_schedules_poll_when_no_results(): void {
			global $wp_posts, $wp_events;
			$wp_posts[1] = (object)[
				'ID' => 1,
				'post_title' => 'T',
				'post_content' => 'C',
				'post_type' => 'post',
				'post_status' => 'publish',
			];
			$api = new DummyGenApi();
			$api->response = [];
			$store = new DummyStorageGenerationSingle();
			$service = $this->makeService($api, $store);
			$service->generateSingle(1, 'quiz');
			$this->assertEmpty($store->stored);
			$this->assertCount(1, $wp_events);
			$event = $wp_events[0];
			$this->assertSame('nuclen_poll_generation', $event['hook']);
			$this->assertSame('quiz', $event['args'][1]);
			$this->assertSame(1, $event['args'][2]);
			$this->assertSame(1, $event['args'][3]);
			$this->assertStringStartsWith('auto_1_', $event['args'][0]);
		}

		public function test_generate_single_rethrows_api_errors(): void {
			global $wp_posts, $wp_events;
			$wp_posts[2] = (object)[
				'ID' => 2,
				'post_title' => 'T2',
				'post_content' => 'C2',
				'post_type' => 'post',
				'post_status' => 'publish',
			];
			$api = new DummyGenApi();
			$api->exception = new ApiException('fail', 500);
			$service = $this->makeService($api);
			$this->expectException(ApiException::class);
			$this->expectExceptionMessage('fail');
			try {
				$service->generateSingle(2, 'quiz');
			} finally {
				$this->assertEmpty($wp_events);
			}
		}
	}
}
