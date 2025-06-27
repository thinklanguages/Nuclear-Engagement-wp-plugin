<?php
namespace NuclearEngagement\Services {
	class LoggingService {
		public static array $logs = [];
		public static array $notices = [];
		public static function log(string $msg): void { self::$logs[] = $msg; }
		public static function notify_admin(string $msg): void { self::$notices[] = $msg; }
	}
	function wp_json_encode($data) { return json_encode($data); }
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\SetupService;
	use NuclearEngagement\Services\LoggingService;

	if (!defined('NUCLEN_PLUGIN_VERSION')) { define('NUCLEN_PLUGIN_VERSION', '1.0'); }
	if (!defined('NUCLEN_API_TIMEOUT')) { define('NUCLEN_API_TIMEOUT', 30); }

	class SetupServiceTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['test_http_response'] = null;
			LoggingService::$logs = [];
			LoggingService::$notices = [];
		}

		public function test_validate_api_key_success(): void {
			$GLOBALS['test_http_response'] = ['code' => 200];
			$svc = new SetupService();
			$this->assertTrue($svc->validate_api_key('key'));
			$this->assertEmpty(LoggingService::$logs);
			$this->assertEmpty(LoggingService::$notices);
		}

		public function test_validate_api_key_failure_logs_error(): void {
			$GLOBALS['test_http_response'] = new \WP_Error();
			$svc = new SetupService();
			$this->assertFalse($svc->validate_api_key('key'));
			$this->assertSame(['API-key validation error: error'], LoggingService::$logs);
			$this->assertSame(['Failed to validate API key.'], LoggingService::$notices);
		}

		public function test_send_app_password_success(): void {
			$GLOBALS['test_http_response'] = ['code' => 200];
			$svc = new SetupService();
			$data = ['appApiKey' => 'key', 'user' => 'u'];
			$this->assertTrue($svc->send_app_password($data));
			$this->assertEmpty(LoggingService::$logs);
			$this->assertEmpty(LoggingService::$notices);
		}

		public function test_send_app_password_failure_logs_error(): void {
			$GLOBALS['test_http_response'] = new \WP_Error();
			$svc = new SetupService();
			$data = ['appApiKey' => 'key', 'user' => 'u'];
			$this->assertFalse($svc->send_app_password($data));
			$this->assertSame(['Error sending creds: error'], LoggingService::$logs);
			$this->assertSame(['Failed to send WordPress credentials.'], LoggingService::$notices);
		}

		public function test_send_app_password_http_error_logs_error(): void {
			$GLOBALS['test_http_response'] = ['code' => 400, 'body' => 'bad'];
			$svc = new SetupService();
			$data = ['appApiKey' => 'key', 'user' => 'u'];
			$this->assertFalse($svc->send_app_password($data));
			$this->assertSame([
				'Unexpected creds response code: 400, body: bad'
			], LoggingService::$logs);
			$this->assertSame(['Failed to send WordPress credentials.'], LoggingService::$notices);
		}
	}
}
