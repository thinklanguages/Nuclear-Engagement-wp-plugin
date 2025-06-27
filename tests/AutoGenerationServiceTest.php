<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AutoGenerationService;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Modules\Summary\Summary_Service;

class ServiceDummyRemoteApiService {
	public array $updates = [];
	public $generateResponse = [];
	public array $lastData = [];
	public function send_posts_to_generate(array $data): array {
		if ($this->generateResponse instanceof \Exception) {
			throw $this->generateResponse;
		}
		$this->lastData = $data;
		return $this->generateResponse;
	}
	public function fetch_updates(string $id): array { return $this->updates[$id] ?? []; }
}

class ServiceDummyContentStorageService {
	public array $stored = [];
	public function storeResults(array $results, string $workflowType): array {
		$this->stored[] = [$results, $workflowType];
		return array_fill_keys(array_keys($results), true);
	}
}

class Service_WPDB {
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
			if (!empty($GLOBALS['wp_meta'][$id]['nuclen_quiz_protected']) || !empty($GLOBALS['wp_meta'][$id][Summary_Service::PROTECTED_KEY])) {
				continue;
			}
			$rows[] = (object) [ 'ID' => $p->ID, 'post_title' => $p->post_title, 'post_content' => $p->post_content ];
		}
		return $rows;
	}
}

class AutoGenerationServiceTest extends TestCase {
	protected function setUp(): void {
		global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $wp_events, $wpdb;
		$wp_options = $wp_autoload = $wp_posts = $wp_meta = $wp_events = [];
		$wpdb = new Service_WPDB();
		SettingsRepository::reset_for_tests();
	}

	private function makeService(?ServiceDummyRemoteApiService $api = null): AutoGenerationService {
		$settings = SettingsRepository::get_instance();
		$api      = $api ?: new ServiceDummyRemoteApiService();
		$storage  = new ServiceDummyContentStorageService();

		$poller    = new \NuclearEngagement\Services\GenerationPoller($settings, $api, $storage);
		$scheduler = new \NuclearEngagement\Services\AutoGenerationScheduler($poller);
		$queue     = new \NuclearEngagement\Services\AutoGenerationQueue($api, $storage, new \NuclearEngagement\Services\PostDataFetcher());
		$handler   = new \NuclearEngagement\Services\PublishGenerationHandler($settings);

		return new AutoGenerationService($settings, $queue, $scheduler, $handler);
	}

	public function test_generate_single_sets_autoload_no(): void {
		global $wp_autoload, $wp_posts;
		$wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
		$service = $this->makeService();
		$service->generate_single(1, 'quiz');
		$this->assertSame('no', $wp_autoload['nuclen_autogen_queue']);
	}

	public function test_generate_single_does_not_schedule_on_error(): void {
		global $wp_posts, $wp_events, $wp_options;
		$wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
		$api = new ServiceDummyRemoteApiService();
		$api->generateResponse = new ApiException('nope');
		$service = $this->makeService($api);
		$service->generate_single(1, 'quiz');
		$service->process_queue();
		$this->assertEmpty($wp_events);
		$this->assertEmpty($wp_options['nuclen_active_generations'] ?? []);
		$this->assertArrayNotHasKey('nuclen_autogen_queue', $wp_options);
	}

	public function test_process_queue_handles_runtime_exception(): void {
		global $wp_posts, $wp_events, $wp_options;
		$wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
		$api = new ServiceDummyRemoteApiService();
		$api->generateResponse = new \RuntimeException('missing key');
		$service = $this->makeService($api);
		$service->generate_single(1, 'quiz');
		$service->process_queue();
		$this->assertEmpty($wp_events);
		$this->assertEmpty($wp_options['nuclen_active_generations'] ?? []);
		$this->assertArrayNotHasKey('nuclen_autogen_queue', $wp_options);
	}

	public function test_poll_generation_removes_entry_after_success(): void {
		global $wp_options;
		$id = 'gen123';
		$wp_options['nuclen_active_generations'] = [ $id => ['foo'=>'bar'] ];
		$api = new ServiceDummyRemoteApiService();
		$api->updates[$id] = ['results' => ['1'=>['ok']]];
		$service = $this->makeService($api);
		$service->poll_generation($id, 'quiz', [1], 1);
		$this->assertArrayNotHasKey($id, $wp_options['nuclen_active_generations'] ?? []);
	}

	public function test_poll_generation_removes_entry_after_final_failure(): void {
		global $wp_options;
		$id = 'gen999';
		$wp_options['nuclen_active_generations'] = [ $id => ['foo'=>'bar'] ];
		$api = new ServiceDummyRemoteApiService();
		$service = $this->makeService($api);
		$service->poll_generation($id, 'quiz', [1], NUCLEN_MAX_POLL_ATTEMPTS);
		$this->assertArrayNotHasKey($id, $wp_options['nuclen_active_generations'] ?? []);
	}

	public function test_poll_generation_schedules_with_increasing_delay(): void {
		global $wp_events;
		$api = new ServiceDummyRemoteApiService();
		$api->updates['gid'] = ['success' => true];
		$service = $this->makeService($api);
		$start = time();
		$service->poll_generation('gid', 'quiz', [1], 2);
		$this->assertNotEmpty($wp_events);
		$event = $wp_events[0];
		$delay = $event['timestamp'] - $start;
		$this->assertGreaterThanOrEqual(NUCLEN_POLL_RETRY_DELAY * 2, $delay);
		$this->assertSame('gid', $event['args'][0]);
		$this->assertSame(3, $event['args'][3]);
	}

	public function test_handle_post_publish_queues_generation(): void {
		global $wp_posts, $wp_events, $wp_meta, $wp_options;

		$post = (object) [
			'ID' => 5,
			'post_title' => 'T',
			'post_content' => 'C',
			'post_type' => 'post',
		];
		$wp_posts[5] = $post;
		$wp_meta[5] = [];

		$settings = SettingsRepository::get_instance();
		$settings->set_bool('auto_generate_quiz_on_publish', true)
				 ->set_array('generation_post_types', ['post'])
				 ->save();

		$service = $this->makeService();
		$service->handle_post_publish('publish', 'draft', $post);

		$this->assertCount(1, $wp_events);
		$event = $wp_events[0];
		$this->assertSame('nuclen_start_generation', $event['hook']);
		$this->assertSame([5, 'quiz'], $event['args']);
	}

	public function test_process_queue_skips_protected_posts(): void {
		global $wp_posts, $wp_meta, $wp_events;

		$wp_posts[1] = (object) [ 'ID' => 1, 'post_title' => 'A', 'post_content' => 'C1' ];
		$wp_posts[2] = (object) [ 'ID' => 2, 'post_title' => 'B', 'post_content' => 'C2' ];
		$wp_meta[1]  = [ 'nuclen_quiz_protected' => 1 ];

		$api     = new ServiceDummyRemoteApiService();
		$service = $this->makeService( $api );

		$service->generate_single( 1, 'quiz' );
		$service->generate_single( 2, 'quiz' );
		$service->process_queue();

		$this->assertCount( 1, $api->lastData['posts'] );
		$this->assertSame( 2, $api->lastData['posts'][0]['id'] );
		$this->assertCount( 1, $wp_events );
	}
}
