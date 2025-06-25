<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AutoGenerationQueue;
use NuclearEngagement\SettingsRepository;

class DummyRemoteApiService {
    public array $updates = [];
    public $generateResponse = [];
    public array $lastData = [];
    public function send_posts_to_generate(array $data): array {
        $this->lastData = $data;
        return $this->generateResponse;
    }
}

class DummyContentStorageService {
    public array $stored = [];
    public function storeResults(array $results, string $workflowType): void {
        $this->stored[] = [$results, $workflowType];
    }
}

class AutoGenerationQueueTest extends TestCase {
    protected function setUp(): void {
        global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $wp_events;
        $wp_options = $wp_autoload = $wp_posts = $wp_meta = $wp_events = [];
        SettingsRepository::reset_for_tests();
    }

    private function makeQueue(): AutoGenerationQueue {
        $api     = new DummyRemoteApiService();
        $storage = new DummyContentStorageService();
        return new AutoGenerationQueue($api, $storage);
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
}
