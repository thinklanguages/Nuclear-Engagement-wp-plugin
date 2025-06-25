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

class Q_WPDB {
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public array $args = [];
    public function prepare($sql, ...$args) { $this->args = $args; return $sql; }
    public function get_results($sql) {
        $ids = array_slice($this->args, 2);
        $rows = [];
        foreach ($ids as $id) {
            if (!isset($GLOBALS['wp_posts'][$id])) { continue; }
            $meta = $GLOBALS['wp_meta'][$id] ?? [];
            if (!empty($meta['nuclen_quiz_protected']) || !empty($meta['nuclen_summary_protected'])) {
                continue;
            }
            $p = $GLOBALS['wp_posts'][$id];
            $rows[] = (object)[
                'ID' => $p->ID,
                'post_title' => $p->post_title,
                'post_content' => $p->post_content,
            ];
        }
        return $rows;
    }
}

class AutoGenerationQueueTest extends TestCase {
    protected function setUp(): void {
        global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $wp_events, $wpdb;
        $wp_options = $wp_autoload = $wp_posts = $wp_meta = $wp_events = [];
        $wpdb = new Q_WPDB();
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

    public function test_process_queue_sends_unprotected_posts(): void {
        global $wp_posts, $wp_meta;

        $wp_posts[1] = (object) [ 'ID' => 1, 'post_title' => 'A', 'post_content' => '<b>C1</b>' ];
        $wp_posts[2] = (object) [ 'ID' => 2, 'post_title' => 'B', 'post_content' => '<i>C2</i>' ];
        $wp_meta[1]  = [ 'nuclen_quiz_protected' => 1 ];

        $api = new DummyRemoteApiService();
        $q = new AutoGenerationQueue($api, new DummyContentStorageService());
        $q->queue_post(1, 'quiz');
        $q->queue_post(2, 'quiz');
        $q->process_queue();

        $this->assertCount(1, $api->lastData['posts']);
        $sent = $api->lastData['posts'][0];
        $this->assertSame(2, $sent['id']);
        $this->assertSame('B', $sent['title']);
        $this->assertSame('C2', $sent['content']);
    }
}
