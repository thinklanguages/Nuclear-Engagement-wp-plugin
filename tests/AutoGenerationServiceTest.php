<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AutoGenerationService;
use NuclearEngagement\SettingsRepository;

class DummyRemoteApiService {
    public array $updates = [];
    public function sendPostsToGenerate(array $data): array { return []; }
    public function fetchUpdates(string $id): array { return $this->updates[$id] ?? []; }
}

class DummyContentStorageService {
    public array $stored = [];
    public function storeResults(array $results, string $workflowType): void {
        $this->stored[] = [$results, $workflowType];
    }
}

class AutoGenerationServiceTest extends TestCase {
    protected function setUp(): void {
        global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $wp_events;
        $wp_options = $wp_autoload = $wp_posts = $wp_meta = $wp_events = [];
        SettingsRepository::_reset_for_tests();
    }

    private function makeService(?DummyRemoteApiService $api = null): AutoGenerationService {
        $settings = SettingsRepository::get_instance();
        $api = $api ?: new DummyRemoteApiService();
        $storage = new DummyContentStorageService();
        return new AutoGenerationService($settings, $api, $storage);
    }

    public function test_generate_single_sets_autoload_no(): void {
        global $wp_autoload, $wp_posts;
        $wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
        $service = $this->makeService();
        $service->generate_single(1, 'quiz');
        $this->assertSame('no', $wp_autoload['nuclen_active_generations']);
    }

    public function test_poll_generation_removes_entry_after_success(): void {
        global $wp_options;
        $id = 'gen123';
        $wp_options['nuclen_active_generations'] = [ $id => ['foo'=>'bar'] ];
        $api = new DummyRemoteApiService();
        $api->updates[$id] = ['results' => ['1'=>['ok']]];
        $service = $this->makeService($api);
        $service->poll_generation($id, 'quiz', 1, 1);
        $this->assertArrayNotHasKey($id, $wp_options['nuclen_active_generations'] ?? []);
    }

    public function test_poll_generation_removes_entry_after_final_failure(): void {
        global $wp_options;
        $id = 'gen999';
        $wp_options['nuclen_active_generations'] = [ $id => ['foo'=>'bar'] ];
        $api = new DummyRemoteApiService();
        $service = $this->makeService($api);
        $service->poll_generation($id, 'quiz', 1, AutoGenerationService::MAX_ATTEMPTS);
        $this->assertArrayNotHasKey($id, $wp_options['nuclen_active_generations'] ?? []);
    }
}
