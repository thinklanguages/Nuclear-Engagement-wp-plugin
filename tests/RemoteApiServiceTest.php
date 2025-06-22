<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\SettingsRepository;

namespace NuclearEngagement\Services {
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
class RemoteApiServiceTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['test_api_response'] = null;
        SettingsRepository::_reset_for_tests();
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
        try { $svc->sendPostsToGenerate(['posts'=>[], 'workflow'=>[]]); } catch (ApiException $e) {
            $this->assertSame(400, $e->getCode());
            throw $e;
        }
    }

    public function test_auth_error_sets_code(): void {
        $GLOBALS['test_api_response'] = ['code'=>401,'body'=>json_encode(['error_code'=>'invalid_api_key'])];
        $svc = $this->makeService();
        try {
            $svc->sendPostsToGenerate(['posts'=>[], 'workflow'=>[]]);
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
            $svc->sendPostsToGenerate(['posts'=>[], 'workflow'=>[]]);
        } catch (ApiException $e) {
            $this->assertSame(500, $e->getCode());
            $this->assertSame('oops', $e->getMessage());
            return;
        }
        $this->fail('Exception not thrown');
    }
}
}
