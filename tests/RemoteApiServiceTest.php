<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\SettingsRepository;

namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static array $notices = [];
        public static function log(string $msg): void { self::$logs[] = $msg; }
        public static function debug(string $msg): void { self::$logs[] = $msg; }
        public static function notify_admin(string $msg): void { self::$notices[] = $msg; }
    }
    function wp_remote_post(string $url, array $args = []) {
        return $GLOBALS['test_api_response'];
    }
    function wp_remote_retrieve_response_code($res) {
        return $res['code'];
    }
    function wp_remote_retrieve_body($res) {
        return $res['body'];
    }
    function get_site_url() { return 'http://example.com'; }
}

namespace {
if (!function_exists('__')) {
    function __($t, $d = null) { return $t; }
}
class RemoteApiServiceTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['test_api_response'] = null;
        SettingsRepository::reset_for_tests();
        $settings = SettingsRepository::get_instance();
        $settings->set_string('api_key', 'key')->save();
    }

    private function makeService(): RemoteApiService {
        return new RemoteApiService(SettingsRepository::get_instance());
    }

    public function test_parses_json_message(): void {
        $GLOBALS['test_api_response'] = ['code'=>400,'body'=>json_encode(['message'=>'bad'])];
        $svc = $this->makeService();
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('bad');
        try { $svc->send_posts_to_generate(['posts'=>[], 'workflow'=>[]]); } catch (ApiException $e) {
            $this->assertSame(400, $e->getCode());
            throw $e;
        }
    }

    public function test_auth_error_sets_code(): void {
        $GLOBALS['test_api_response'] = ['code'=>401,'body'=>json_encode(['error_code'=>'invalid_api_key'])];
        $svc = $this->makeService();
        try {
            $svc->send_posts_to_generate(['posts'=>[], 'workflow'=>[]]);
        } catch (ApiException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertSame('invalid_api_key', $e->getErrorCode());
            $this->assertStringContainsString('Invalid API key', $e->getMessage());
            return;
        }
        $this->fail('Exception not thrown');
    }

    public function test_server_error_parses_error_field(): void {
        $GLOBALS['test_api_response'] = ['code'=>500,'body'=>json_encode(['error'=>'oops'])];
        $svc = $this->makeService();
        try {
            $svc->send_posts_to_generate(['posts'=>[], 'workflow'=>[]]);
        } catch (ApiException $e) {
            $this->assertSame(500, $e->getCode());
            $this->assertSame('oops', $e->getMessage());
            return;
        }
        $this->fail('Exception not thrown');
    }

    public function test_send_posts_wp_error_notifies(): void {
        $GLOBALS['test_api_response'] = new \WP_Error('nope', 'bad');
        \NuclearEngagement\Services\LoggingService::$notices = [];
        $svc = $this->makeService();
        $this->expectException(ApiException::class);
        try {
            $svc->send_posts_to_generate(['posts'=>[], 'workflow'=>[]]);
        } catch (ApiException $e) {
            $this->assertSame(['Failed to contact the Nuclear Engagement API.'], \NuclearEngagement\Services\LoggingService::$notices);
            throw $e;
        }
    }

    public function test_fetch_updates_wp_error_notifies(): void {
        $GLOBALS['test_api_response'] = new \WP_Error('fail', 'oops');
        \NuclearEngagement\Services\LoggingService::$notices = [];
        $svc = $this->makeService();
        $this->expectException(ApiException::class);
        try {
            $svc->fetch_updates('id');
        } catch (ApiException $e) {
            $this->assertSame(['Failed to contact the Nuclear Engagement API.'], \NuclearEngagement\Services\LoggingService::$notices);
            throw $e;
        }
    }
}
}
