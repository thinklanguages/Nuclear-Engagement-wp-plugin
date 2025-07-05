<?php
namespace NuclearEngagement\Services {
	if (!function_exists('NuclearEngagement\Services\add_action')) {
		function add_action(...$args) {
			$GLOBALS['ls_actions'][] = $args;
		}
	}
	function file_put_contents($file, $data, $flags = 0) {
		$GLOBALS['ls_puts'][] = $file;
		return \file_put_contents($file, $data, $flags);
	}
	function register_shutdown_function($cb) {
		$GLOBALS['ls_shutdown'][] = $cb;
	}
	function error_log($msg) {
		$GLOBALS['ls_errors'][] = $msg;
	}
	function rename($from, $to) {
		if (!empty($GLOBALS['ls_rename_fail'])) {
			return false;
		}
		return \rename($from, $to);
	}
	if (!function_exists('apply_filters')) {
		function apply_filters($hook, $value) {
			if ($hook === 'nuclen_enable_log_buffer' && isset($GLOBALS['ls_filter_buffer'])) {
				return $GLOBALS['ls_filter_buffer'];
			}
			return $value;
		}
	}
	if (!function_exists('esc_html')) {
		function esc_html($text) { return $text; }
	}

	class AdminNoticeService {
		public array $messages = [];
		public function add(string $msg): void {
			$this->messages[] = $msg;
			add_action('admin_notices', [$this, 'render']);
		}
		public function render(): void {}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\LoggingService;
	use NuclearEngagement\Services\AdminNoticeService;
	class LoggingServiceTest extends TestCase {
		private static string $plugin_dir;
		private LoggingService $logger;

		public static function setUpBeforeClass(): void {
			self::$plugin_dir = sys_get_temp_dir() . '/ls_plugin_' . uniqid();
			if (!defined('NUCLEN_PLUGIN_DIR')) {
				define('NUCLEN_PLUGIN_DIR', self::$plugin_dir . '/');
			}
		}
		protected function setUp(): void {
			$GLOBALS['ls_actions'] = [];
			$GLOBALS['ls_errors'] = [];
			$GLOBALS['ls_puts'] = [];
			$GLOBALS['ls_shutdown'] = [];
			$GLOBALS['test_upload_basedir'] = sys_get_temp_dir() . '/ls_' . uniqid();
			mkdir($GLOBALS['test_upload_basedir']);
			$GLOBALS['ls_filter_buffer'] = false;
			$GLOBALS['ls_rename_fail'] = false;
			if (!is_dir(self::$plugin_dir)) {
				mkdir(self::$plugin_dir, 0777, true);
			}

			$this->logger = new LoggingService(new AdminNoticeService());
		}

		protected function tearDown(): void {
			$this->logger->flush();
			foreach ($GLOBALS['ls_shutdown'] as $cb) {
				$cb();
			}
			unset($GLOBALS['ls_filter_buffer']);
			unset($GLOBALS['ls_rename_fail']);
			$base = $GLOBALS['test_upload_basedir'];
			foreach (glob("$base/*") as $file) {
				@unlink($file);
			}
			@rmdir($base);
			if (is_dir(self::$plugin_dir)) {
				array_map('unlink', glob(self::$plugin_dir . '/*'));
				rmdir(self::$plugin_dir);
			}
		}

		public function test_unwritable_directory_triggers_fallback(): void {
			chmod($GLOBALS['test_upload_basedir'], 0555);
			$this->logger->log('test message');
			$this->assertSame(['test message'], $GLOBALS['ls_errors']);
			$this->assertSame('admin_notices', $GLOBALS['ls_actions'][0][0]);
		}

		public function test_logs_message_to_file_when_writable(): void {
			$this->logger->log('hello world');
			$info = $this->logger->get_log_file_info();
			$this->assertFileExists($info['path']);
			$contents = file_get_contents($info['path']);
			$this->assertStringContainsString('hello world', $contents);
		}

		public function test_debug_logs_only_when_constant_defined(): void {
			$this->logger->debug('no constant');
			$info = $this->logger->get_log_file_info();
			$this->assertFileDoesNotExist($info['path']);

			if (!defined('WP_DEBUG')) {
				define('WP_DEBUG', true);
			}
			$this->logger->debug('debug message');
			$this->assertFileExists($info['path']);
			$contents = file_get_contents($info['path']);
			$this->assertStringContainsString('[DEBUG] debug message', $contents);
		}

		public function test_logs_strip_html_and_truncate_long_message(): void {
			$long = '<p>' . str_repeat('a', 1005) . '</p>';
			$this->logger->log($long);
			$info = $this->logger->get_log_file_info();
			$this->assertFileExists($info['path']);
			$contents = file_get_contents($info['path']);
			$expected = str_repeat('a', 1000) . '...';
			$this->assertStringContainsString($expected, $contents);
			$this->assertStringNotContainsString('<p>', $contents);
		}

		public function test_buffered_logging_single_write(): void {
			$GLOBALS['ls_filter_buffer'] = true;
			for ($i = 0; $i < 5; $i++) {
				$this->logger->log("msg $i");
			}
			$info = $this->logger->get_log_file_info();
			$this->assertFileDoesNotExist($info['path']);

			$this->logger->flush();

			$this->assertFileExists($info['path']);
			$this->assertCount(1, $GLOBALS['ls_puts']);
			$contents = file_get_contents($info['path']);
			$this->assertStringContainsString('msg 4', $contents);
		}

		public function test_rotation_failure_triggers_fallback(): void {
			if (!defined('NUCLEN_LOG_FILE_MAX_SIZE')) {
				define('NUCLEN_LOG_FILE_MAX_SIZE', 1);
			}
			$info = $this->logger->get_log_file_info();
			if (!file_exists($info['dir'])) {
				mkdir($info['dir'], 0777, true);
			}
			file_put_contents($info['path'], 'aa');
			$GLOBALS['ls_rename_fail'] = true;

			$this->logger->log('rotate');

			$this->assertNotEmpty($GLOBALS['ls_errors']);
			$this->assertStringContainsString('Failed to rotate log file', $GLOBALS['ls_errors'][0]);
		}

		public function test_upload_dir_error_returns_fallback_info(): void {
			$GLOBALS['test_upload_error'] = 'fail';
			$info = $this->logger->get_log_file_info();
			$expected_dir = rtrim(NUCLEN_PLUGIN_DIR, '/') . '/logs';
			$this->assertSame($expected_dir, $info['dir']);
			$this->assertSame($expected_dir . '/log.txt', $info['path']);
			$this->assertSame('', $info['url']);
			$this->assertSame('admin_notices', $GLOBALS['ls_actions'][0][0]);
			$this->assertNotEmpty($GLOBALS['ls_errors']);
			unset($GLOBALS['test_upload_error']);
		}

		public function test_log_exception_without_debug(): void {
			$e = new \Exception('oops');
			$this->logger->log_exception($e);
			$info = $this->logger->get_log_file_info();
			$this->assertFileExists($info['path']);
			$contents = file_get_contents($info['path']);
			$this->assertStringContainsString('oops in', $contents);
			$this->assertStringNotContainsString('Stack trace:', $contents);
		}

		public function test_log_exception_with_debug(): void {
			if (!defined('WP_DEBUG')) {
				define('WP_DEBUG', true);
			}
			$e = new \Exception('boom');
			$this->logger->log_exception($e);
			$info = $this->logger->get_log_file_info();
			$this->assertFileExists($info['path']);
			$contents = file_get_contents($info['path']);
			$this->assertStringContainsString('boom in', $contents);
			$this->assertStringContainsString('Stack trace:', $contents);
		}
	}
}
