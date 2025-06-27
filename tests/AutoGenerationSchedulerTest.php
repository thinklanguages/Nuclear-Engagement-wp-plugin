<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AutoGenerationScheduler;
use NuclearEngagement\Core\SettingsRepository;

class SchedulerDummyRemoteApiService {
	public array $updates = [];
	public $generateResponse = [];
	public array $lastData = [];
	public function send_posts_to_generate(array $data): array { return []; }
	public function fetch_updates(string $id): array { return $this->updates[$id] ?? []; }
}

class SchedulerDummyContentStorageService {
	public array $stored = [];
	public function storeResults(array $results, string $workflowType): array { $this->stored[] = [$results, $workflowType]; return array_fill_keys(array_keys($results), true); }
}

class AutoGenerationSchedulerTest extends TestCase {
	protected function setUp(): void {
		global $wp_options;
		$wp_options = [];
		SettingsRepository::reset_for_tests();
	}

	private function makeScheduler(?SchedulerDummyRemoteApiService $api = null): AutoGenerationScheduler {
		$settings = SettingsRepository::get_instance();
		$api      = $api ?: new SchedulerDummyRemoteApiService();
		$storage  = new SchedulerDummyContentStorageService();
		$poller   = new \NuclearEngagement\Services\GenerationPoller($settings, $api, $storage);
		return new AutoGenerationScheduler($poller);
	}

	public function test_poll_generation_success_clears_entry(): void {
		global $wp_options;
		$id = 'gen123';
		$wp_options['nuclen_active_generations'] = [ $id => ['foo'=>'bar'] ];
		$api = new SchedulerDummyRemoteApiService();
		$api->updates[$id] = ['results' => ['1'=>['ok']]];
		$scheduler = $this->makeScheduler($api);
		$scheduler->poll_generation($id, 'quiz', [1], 1);
		$this->assertArrayNotHasKey($id, $wp_options['nuclen_active_generations'] ?? []);
	}
}
