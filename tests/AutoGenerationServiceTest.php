<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AutoGenerationService;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Services\ApiException;

class DummyRemoteApiService {
    public array $updates = [];
    public $generateResponse = [];
    public function sendPostsToGenerate(array $data): array {
        if ($this->generateResponse instanceof \Exception) {
            throw $this->generateResponse;
        }
        return $this->generateResponse;
    }
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
        SettingsRepository::reset_for_tests();
    }

    private function makeService(?DummyRemoteApiService $api = null): AutoGenerationService {
        $settings = SettingsRepository::get_instance();
        $api      = $api ?: new DummyRemoteApiService();
        $storage  = new DummyContentStorageService();

        $poller  = new \NuclearEngagement\Services\GenerationPoller($settings, $api, $storage);
        $handler = new \NuclearEngagement\Services\PublishGenerationHandler($settings);

        return new AutoGenerationService($settings, $api, $storage, $poller, $handler);
    }

    public function test_generate_single_sets_autoload_no(): void {
        global $wp_autoload, $wp_posts;
        $wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
        $service = $this->makeService();
        $service->generate_single(1, 'quiz');
        $this->assertSame('no', $wp_autoload['nuclen_active_generations']);
    }

    public function test_generate_single_does_not_schedule_on_error(): void {
        global $wp_posts, $wp_events, $wp_options;
        $wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
        $api = new DummyRemoteApiService();
        $api->generateResponse = new ApiException('nope');
        $service = $this->makeService($api);
        $service->generate_single(1, 'quiz');
        $this->assertEmpty($wp_events);
        $this->assertEmpty($wp_options['nuclen_active_generations'] ?? []);
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
}
