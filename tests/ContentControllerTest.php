<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Front\Controller\Rest\ContentController;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Services\ContentStorageService;

namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static function log(string $msg): void {
            self::$logs[] = $msg;
        }
    }
}

namespace {
    class DummyRequest {
        public function get_json_params() {
            return null;
        }
    }

    class ContentControllerTest extends TestCase {
        protected function setUp(): void {
            global $wp_options, $wp_autoload, $wp_posts, $wp_meta;
            $wp_options = $wp_autoload = $wp_posts = $wp_meta = [];
            SettingsRepository::reset_for_tests();
            \NuclearEngagement\Services\LoggingService::$logs = [];
        }

        public function test_handle_invalid_json_returns_error(): void {
            $settings = SettingsRepository::get_instance();
            $storage = new ContentStorageService($settings);
            $controller = new ContentController($storage, $settings);
            $req = new DummyRequest();

            $res = $controller->handle($req);

            $this->assertInstanceOf(WP_Error::class, $res);
            $this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$logs);
        }
    }
}
