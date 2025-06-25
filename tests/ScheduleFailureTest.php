<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AutoGenerationService;
use NuclearEngagement\Services\GenerationPoller;
use NuclearEngagement\SettingsRepository;

namespace NuclearEngagement\Services {
    if (!class_exists('NuclearEngagement\\Services\\LoggingService')) {
        class LoggingService {
            public static array $logs = [];
            public static array $notices = [];
            public static function log(string $msg): void { self::$logs[] = $msg; }
            public static function notify_admin(string $msg): void { self::$notices[] = $msg; }
        }
    }
    function wp_schedule_single_event($timestamp, $hook, $args) {
        return false;
    }
}

namespace {
    class DummyRemoteApiService {
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

    class DummyContentStorageService {
        public array $stored = [];
        public function storeResults(array $results, string $type): void {
            $this->stored[] = [$results, $type];
        }
    }

    class ScheduleFailureTest extends TestCase {
        protected function setUp(): void {
            global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $wp_events;
            $wp_options = $wp_autoload = $wp_posts = $wp_meta = $wp_events = [];
            \NuclearEngagement\Services\LoggingService::$logs = [];
            \NuclearEngagement\Services\LoggingService::$notices = [];
            SettingsRepository::reset_for_tests();
        }

        private function makeService(?DummyRemoteApiService $api = null): AutoGenerationService {
            $settings = SettingsRepository::get_instance();
            $api      = $api ?: new DummyRemoteApiService();
            $storage  = new DummyContentStorageService();
            $poller    = new GenerationPoller($settings, $api, $storage);
            $scheduler = new \NuclearEngagement\Services\AutoGenerationScheduler($poller);
            $queue     = new \NuclearEngagement\Services\AutoGenerationQueue($api, $storage);
            $handler   = new \NuclearEngagement\Services\PublishGenerationHandler($settings);
            return new AutoGenerationService($settings, $queue, $scheduler, $handler);
        }

        public function test_queue_post_failure_notifies_admin(): void {
            global $wp_posts, $wp_events;
            $wp_posts[1] = (object)[ 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C' ];
            $service = $this->makeService();
            $service->generate_single(1, 'quiz');
            $this->assertEmpty($wp_events);
            $this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$notices);
        }

        public function test_process_queue_failure_notifies_admin(): void {
            global $wp_posts;
            $wp_posts[2] = (object)[ 'ID' => 2, 'post_title' => 'B', 'post_content' => 'C2' ];
            update_option('nuclen_autogen_queue', ['quiz' => [2]], 'no');
            $service = $this->makeService();
            $service->process_queue();
            $this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$notices);
        }

        public function test_poller_failure_notifies_admin(): void {
            $settings = SettingsRepository::get_instance();
            $api      = new DummyRemoteApiService();
            $storage  = new DummyContentStorageService();
            $poller   = new GenerationPoller($settings, $api, $storage);
            $poller->poll_generation('gid', 'quiz', [1], 1);
            $this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$notices);
        }
    }
}
