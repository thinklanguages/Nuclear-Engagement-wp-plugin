<?php
namespace NuclearEngagement\Services\Remote {
    function wp_remote_post(string $url, array $args = []) {
        $GLOBALS['rr_called'] = ['url' => $url, 'args' => $args];
        return null;
    }
    function wp_json_encode($data) { return json_encode($data); }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Services\Remote\RemoteRequest;

    if (!defined('NUCLEN_PLUGIN_VERSION')) { define('NUCLEN_PLUGIN_VERSION', '1.0'); }
    if (!defined('NUCLEN_API_TIMEOUT')) { define('NUCLEN_API_TIMEOUT', 30); }

    class RemoteRequestTest extends TestCase {
        protected function setUp(): void {
            unset($GLOBALS['rr_called']);
        }

        public function test_post_sends_json_request_with_headers(): void {
            $req = new RemoteRequest();
            $req->post('/path', ['data' => 1], 'key');

            $this->assertArrayHasKey('rr_called', $GLOBALS);
            $called = $GLOBALS['rr_called'];
            $this->assertSame('https://app.nuclearengagement.com/api/path', $called['url']);
            $this->assertSame(json_encode(['data' => 1]), $called['args']['body']);
            $this->assertSame('application/json', $called['args']['headers']['Content-Type']);
            $this->assertSame('key', $called['args']['headers']['X-API-Key']);
            $this->assertStringContainsString(NUCLEN_PLUGIN_VERSION, $called['args']['user-agent']);
        }
    }
}
