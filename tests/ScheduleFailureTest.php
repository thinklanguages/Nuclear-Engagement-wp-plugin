<?php
namespace NuclearEngagement\Services {
	function wp_schedule_single_event($timestamp, $hook, $args) {
		return false;
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\AutoGenerationService;
	use NuclearEngagement\Services\GenerationPoller;
	use NuclearEngagement\Core\SettingsRepository;
	use NuclearEngagement\Modules\Summary\Summary_Service;
	class ScheduleFailDummyRemoteApiService {
		public array $updates = [];
		public $generateResponse = [];
		public array $lastData = [];
		public function send_posts_to_generate(array $data): array {
			$this->lastData = $data;
			return $this->generateResponse;
		}
		public function fetch_updates(string $id): array {
			return $this->updates[$id] ?? [];
		}
	}

	class ScheduleFailDummyContentStorageService {
		public array $stored = [];
		public function storeResults(array $results, string $type): array {
			$this->stored[] = [$results, $type];
			return array_fill_keys(array_keys($results), true);
		}
	}

	class ScheduleFail_WPDB {
		public $posts = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public array $args = [];
		public function prepare($sql, ...$args) { $this->args = $args; return $sql; }
		public function get_results($sql) {
			$ids = array_slice($this->args, 2);
			$rows = [];
			foreach ($ids as $id) {
				if (!isset($GLOBALS['wp_posts'][$id])) { continue; }
				$p = $GLOBALS['wp_posts'][$id];
				if ($p->post_status !== 'publish') { continue; }
				if (!empty($GLOBALS['wp_meta'][$id]['nuclen_quiz_protected']) || !empty($GLOBALS['wp_meta'][$id][Summary_Service::PROTECTED_KEY])) { continue; }
				$rows[] = (object) [ 'ID' => $p->ID, 'post_title' => $p->post_title, 'post_content' => $p->post_content ];
			}
			return $rows;
		}
	}

	class ScheduleFailureTest extends TestCase {
		protected function setUp(): void {
			global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $wp_events, $wpdb;
			$wp_options = $wp_autoload = $wp_posts = $wp_meta = $wp_events = [];
			$wpdb = new ScheduleFail_WPDB();
			\NuclearEngagement\Services\LoggingService::$logs = [];
			\NuclearEngagement\Services\LoggingService::$notices = [];
			SettingsRepository::reset_for_tests();
		}

		private function makeService(?ScheduleFailDummyRemoteApiService $api = null): AutoGenerationService {
			$settings = SettingsRepository::get_instance();
			$api      = $api ?: new ScheduleFailDummyRemoteApiService();
			$storage  = new ScheduleFailDummyContentStorageService();
			$poller    = new GenerationPoller($settings, $api, $storage);
			$scheduler = new \NuclearEngagement\Services\AutoGenerationScheduler($poller);
			$batch_processor = new \NuclearEngagement\Services\BulkGenerationBatchProcessor($settings);
			$generation_service = new \NuclearEngagement\Services\GenerationService($settings, $api, $storage, new \NuclearEngagement\Services\PostDataFetcher(), $batch_processor);
			$handler   = new \NuclearEngagement\Services\PublishGenerationHandler($settings);
			return new AutoGenerationService($settings, $generation_service, $scheduler, $handler);
		}

		public function test_queue_post_failure_notifies_admin(): void {
			global $wp_posts, $wp_events;
			$wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
			$service = $this->makeService();
			$service->generate_single(1, 'quiz');
			$this->assertEmpty($wp_events);
			$this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$notices);
		}

		public function test_batch_processing_failure_notifies_admin(): void {
			global $wp_posts;
			$wp_posts[2] = (object)[ 'ID' => 2, 'post_title' => 'B', 'post_content' => 'C2', 'post_status' => 'publish' ];
			$api = new ScheduleFailDummyRemoteApiService();
			// Force a failure by returning an error
			$api->generateResponse = new \Exception('API Error');
			$service = $this->makeService($api);
			try {
				$service->generate_single(2, 'quiz');
			} catch (\Exception $e) {
				// Expected
			}
			$this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$logs);
		}

		public function test_poller_failure_notifies_admin(): void {
			$settings = SettingsRepository::get_instance();
			$api      = new ScheduleFailDummyRemoteApiService();
			$storage  = new ScheduleFailDummyContentStorageService();
			$poller   = new GenerationPoller($settings, $api, $storage);
			$poller->poll_generation('gid', 'quiz', [1], 1);
			$this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$notices);
		}
	}
}
