<?php
namespace NuclearEngagement\Services {
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Admin\Controller\Ajax\PointerController;
	use NuclearEngagement\Services\PointerService;
	if (!function_exists('check_ajax_referer')) {
		function check_ajax_referer($a, $f, $d = false) { return true; }
	}
	if (!function_exists('current_user_can')) {
		function current_user_can($c) { return true; }
	}
	if (!function_exists('wp_send_json_success')) {
		function wp_send_json_success($d) { $GLOBALS['json_response'] = ['success', $d]; }
	}
	if (!function_exists('wp_send_json_error')) {
		function wp_send_json_error($d, $c = 0) { $GLOBALS['json_response'] = ['error', $d, $c]; }
	}
	if (!function_exists('status_header')) {
		function status_header($c) { $GLOBALS['status_header'] = $c; }
	}
	if (!function_exists('sanitize_text_field')) {
		function sanitize_text_field($t) { return is_string($t) ? trim($t) : ''; }
	}
	if (!function_exists('wp_unslash')) {
		function wp_unslash($d) { return $d; }
	}
	if (!function_exists('get_current_user_id')) {
		function get_current_user_id() { return 5; }
	}
	if (!function_exists('__')) {
		function __($t, $d = null) { return $t; }
	}

	require_once dirname(__DIR__) . '/nuclear-engagement/admin/Controller/Ajax/BaseController.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/admin/Controller/Ajax/PointerController.php';

	class DummyPointerService extends PointerService {
		public array $args = [];
		public ?\Exception $throw = null;

		public function dismissPointer(string $pointerId, int $userId): void {
			$this->args[] = [$pointerId, $userId];
			if ($this->throw) {
				throw $this->throw;
			}
		}
	}

	class PointerControllerTest extends TestCase {
		protected function setUp(): void {
			$_POST = [];
			$GLOBALS['json_response'] = null;
			$GLOBALS['status_header'] = null;
			\NuclearEngagement\Services\LoggingService::$exceptions = [];
		}

		public function test_dismiss_success(): void {
			$service = new DummyPointerService();
			$controller = new PointerController($service);
			$_POST = ['pointer' => 'abc', 'nonce' => 'n'];
			$controller->dismiss();
			$this->assertSame([['abc', 5]], $service->args);
			$this->assertSame(['success', ['message' => 'Pointer dismissed.']], $GLOBALS['json_response']);
		}

		public function test_invalid_input_returns_error(): void {
			$service = new DummyPointerService();
			$service->throw = new \InvalidArgumentException('bad');
			$controller = new PointerController($service);
			$_POST = ['pointer' => '', 'nonce' => 'n'];
			$controller->dismiss();
			$this->assertSame([['', 5]], $service->args);
			$this->assertSame(500, $GLOBALS['status_header']);
			$this->assertSame(['error', ['message' => 'bad'], 500], $GLOBALS['json_response']);
		}

		public function test_exception_logs_and_returns_generic_error(): void {
			$service = new DummyPointerService();
			$service->throw = new \Exception('fail');
			$controller = new PointerController($service);
			$_POST = ['pointer' => 'abc', 'nonce' => 'n'];
			$controller->dismiss();
			$this->assertSame('fail', \NuclearEngagement\Services\LoggingService::$exceptions[0]);
			$this->assertSame(['error', ['message' => 'An error occurred'], 500], $GLOBALS['json_response']);
		}
	}
}
