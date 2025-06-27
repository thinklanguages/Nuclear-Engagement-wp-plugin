<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AutoGenerationQueue;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Modules\Summary\Summary_Service;

class QueueDummyRemoteApiService {
	public array $updates = [];
	public $generateResponse = [];
	public array $lastData = [];
	public function send_posts_to_generate(array $data): array {
		$this->lastData = $data;
		return $this->generateResponse;
	}
}

class QueueDummyContentStorageService {
	public array $stored = [];
	public function storeResults(array $results, string $workflowType): array {
		$this->stored[] = [$results, $workflowType];
		return array_fill_keys(array_keys($results), true);
	}
}

class Queue_WPDB {
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public array $args = [];
	public function prepare($sql, ...$args) {
		$this->args = $args;
		return $sql;
	}
	public function get_results($sql) {
		$ids = array_slice($this->args, 2);
		$rows = [];
		foreach ($ids as $id) {
			if (!isset($GLOBALS['wp_posts'][$id])) {
				continue;
			}
			$p = $GLOBALS['wp_posts'][$id];
			if ($p->post_status !== 'publish') {
				continue;
			}
			if (!empty($GLOBALS['wp_meta'][$id]['nuclen_quiz_protected']) || !empty($GLOBALS['wp_meta'][$id][Summary_Service::PROTECTED_KEY])) {
				continue;
			}
			$rows[] = (object) [
				'ID' => $p->ID,
				'post_title' => $p->post_title,
				'post_content' => $p->post_content,
			];
		}
		return $rows;
	}
}

class AutoGenerationQueueTest extends TestCase {
	private QueueDummyRemoteApiService $api;
	protected function setUp(): void {
		global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $wp_events, $wpdb;
		$wp_options = $wp_autoload = $wp_posts = $wp_meta = $wp_events = [];
		$wpdb = new Queue_WPDB();
		SettingsRepository::reset_for_tests();
	}

	private function makeQueue(): AutoGenerationQueue {
		$this->api = new QueueDummyRemoteApiService();
		$storage   = new QueueDummyContentStorageService();
		return new AutoGenerationQueue($this->api, $storage, new \NuclearEngagement\Services\PostDataFetcher());
	}

	public function test_queue_post_sets_autoload_no(): void {
		$q = $this->makeQueue();
		$q->queue_post(1, 'quiz');
		$this->assertSame('no', $GLOBALS['wp_autoload']['nuclen_autogen_queue']);
	}

	public function test_process_queue_schedules_event(): void {
		global $wp_posts, $wp_events;
		$wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
		$q = $this->makeQueue();
		$q->queue_post(1, 'quiz');
		$q->process_queue();
		$this->assertNotEmpty($wp_events);
	}

	public function test_process_queue_updates_option_once(): void {
		global $wp_posts, $update_option_calls;

		for ($i = 1; $i <= 6; $i++) {
			$wp_posts[$i] = (object) [ 'ID' => $i, 'post_title' => 'T' . $i, 'post_content' => 'C' . $i ];
		}

		$q = $this->makeQueue();
		for ($i = 1; $i <= 6; $i++) {
			$q->queue_post($i, 'quiz');
		}

		$update_option_calls = [];
		$q->process_queue();

		$this->assertSame(1, $update_option_calls['nuclen_active_generations'] ?? 0);
	}

	public function test_process_queue_sends_unprotected_posts(): void {
		global $wp_posts, $wp_meta;

		$wp_posts[1] = (object) [ 'ID' => 1, 'post_title' => 'A', 'post_content' => '<b>C1</b>', 'post_status' => 'publish' ];
		$wp_posts[2] = (object) [ 'ID' => 2, 'post_title' => 'B', 'post_content' => '<i>C2</i>', 'post_status' => 'publish' ];
		$wp_meta[1]  = [ 'nuclen_quiz_protected' => 1 ];

		$q = $this->makeQueue();
		$q->queue_post(1, 'quiz');
		$q->queue_post(2, 'quiz');
		$q->process_queue();

		$this->assertSame([
			['id' => 2, 'title' => 'B', 'content' => 'C2'],
		], $this->api->lastData['posts']);
	}
}
