<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AutoGenerationScheduler;
use NuclearEngagement\SettingsRepository;

class DummyRemoteApiService {
    public array $updates = [];
    public $generateResponse = [];
    public array $lastData = [];
    public function send_posts_to_generate(array $data): array { return []; }
    public function fetch_updates(string $id): array { return $this->updates[$id] ?? []; }
}

class DummyContentStorageService {
    public array $stored = [];
    public function storeResults(array $results, string $workflowType): void { $this->stored[] = [$results, $workflowType]; }
}

class AutoGenerationSchedulerTest extends TestCase {
    protected function setUp(): void {
        global $wp_options;
        $wp_options = [];
        SettingsRepository::reset_for_tests();
    }

    private function makeScheduler(?DummyRemoteApiService $api = null): AutoGenerationScheduler {
        $settings = SettingsRepository::get_instance();
        $api      = $api ?: new DummyRemoteApiService();
        $storage  = new DummyContentStorageService();
        $poller   = new \NuclearEngagement\Services\GenerationPoller($settings, $api, $storage);
        return new AutoGenerationScheduler($poller);
    }

    public function test_poll_generation_success_clears_entry(): void {
        global $wp_options;
        $id = 'gen123';
        $wp_options['nuclen_active_generations'] = [ $id => ['foo'=>'bar'] ];
        $api = new DummyRemoteApiService();
        $api->updates[$id] = ['results' => ['1'=>['ok']]];
        $scheduler = $this->makeScheduler($api);
        $scheduler->poll_generation($id, 'quiz', [1], 1);
        $this->assertArrayNotHasKey($id, $wp_options['nuclen_active_generations'] ?? []);
    }
}
