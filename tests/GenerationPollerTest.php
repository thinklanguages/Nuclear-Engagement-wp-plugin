<?php
namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static array $notices = [];
        public static function log(string $msg): void { self::$logs[] = $msg; }
        public static function notify_admin(string $msg): void { self::$notices[] = $msg; }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Services\GenerationPoller;
    use NuclearEngagement\Core\SettingsRepository;

    class DummyRemoteApiService {
        public array $updates = [];
        public function fetch_updates(string $id): array {
            return $this->updates[$id] ?? [];
        }
    }

    class DummyContentStorageService {
        public array $calls = [];
        public function storeResults(array $results, string $workflow): array {
            $this->calls[] = [$results, $workflow];
            return array_fill_keys(array_keys($results), true);
        }
    }

    class GenerationPollerTest extends TestCase {
        protected function setUp(): void {
            global $wp_options, $wp_events;
            $wp_options = $wp_events = [];
            \NuclearEngagement\Services\LoggingService::$logs = [];
            \NuclearEngagement\Services\LoggingService::$notices = [];
            SettingsRepository::reset_for_tests();
        }

        private function makePoller(?DummyRemoteApiService $api = null, ?DummyContentStorageService $store = null): GenerationPoller {
            $settings = SettingsRepository::get_instance();
            $settings->set_bool('connected', true)
                     ->set_bool('wp_app_pass_created', true)
                     ->save();
            $api = $api ?: new DummyRemoteApiService();
            $store = $store ?: new DummyContentStorageService();
            return new GenerationPoller($settings, $api, $store);
        }

        public function test_cleanup_generation_removes_ids(): void {
            global $wp_options;
            $wp_options['nuclen_active_generations'] = [
                'a' => ['x'],
                'b' => ['y'],
            ];
            $poller = $this->makePoller();
            $ref = new \ReflectionMethod(GenerationPoller::class, 'cleanup_generation');
            $ref->setAccessible(true);
            $ref->invoke($poller, 'a');
            $this->assertArrayNotHasKey('a', $wp_options['nuclen_active_generations']);
            $this->assertArrayHasKey('b', $wp_options['nuclen_active_generations']);
            $ref->invoke($poller, 'b');
            $this->assertArrayNotHasKey('nuclen_active_generations', $wp_options);
        }

        public function test_poll_generation_stores_results_and_clears_option(): void {
            global $wp_options, $wp_events;
            $id = 'gid1';
            $wp_options['nuclen_active_generations'] = [$id => ['foo']];
            $api = new DummyRemoteApiService();
            $api->updates[$id] = ['results' => ['1' => ['ok']]];
            $store = new DummyContentStorageService();
            $poller = $this->makePoller($api, $store);
            $poller->poll_generation($id, 'quiz', [1], 1);
            $this->assertCount(1, $store->calls);
            $this->assertArrayNotHasKey($id, $wp_options['nuclen_active_generations'] ?? []);
            $this->assertEmpty($wp_events);
        }

        public function test_poll_generation_exceeds_max_attempts_removes_id(): void {
            global $wp_options, $wp_events;
            $id = 'gid2';
            $wp_options['nuclen_active_generations'] = [$id => ['foo']];
            $poller = $this->makePoller();
            $poller->poll_generation($id, 'quiz', [1], NUCLEN_MAX_POLL_ATTEMPTS + 1);
            $this->assertArrayNotHasKey($id, $wp_options['nuclen_active_generations'] ?? []);
            $this->assertEmpty($wp_events);
        }
    }
}
