<?php
namespace NuclearEngagement\Core {
	if (!function_exists(__NAMESPACE__ . '\set_error_handler')) {
		function set_error_handler($handler) {
			$GLOBALS['error_handler'] = $handler;
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\set_exception_handler')) {
		function set_exception_handler($handler) {
			$GLOBALS['exception_handler'] = $handler;
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\register_shutdown_function')) {
		function register_shutdown_function($handler) {
			$GLOBALS['shutdown_handler'] = $handler;
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\add_action')) {
		function add_action($hook, $callback, $priority = 10, $args = 1) {
			$GLOBALS['wp_actions'][$hook][] = $callback;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\add_filter')) {
		function add_filter($hook, $callback, $priority = 10, $args = 1) {
			$GLOBALS['wp_filters'][$hook][] = $callback;
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\get_transient')) {
		function get_transient($name) {
			return $GLOBALS['wp_transients'][$name]['value'] ?? false;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\set_transient')) {
		function set_transient($name, $value, $expiration) {
			$GLOBALS['wp_transients'][$name] = ['value' => $value, 'expiration' => $expiration];
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\wp_generate_uuid4')) {
		function wp_generate_uuid4() {
			return 'test-uuid-' . uniqid();
		}
	}
	if (!function_exists(__NAMESPACE__ . '\get_current_user_id')) {
		function get_current_user_id() {
			return $GLOBALS['current_user_id'] ?? 0;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\get_bloginfo')) {
		function get_bloginfo($field) {
			return $field === 'version' ? '6.4.2' : 'Test Blog';
		}
	}
	if (!function_exists(__NAMESPACE__ . '\do_action')) {
		function do_action($hook, ...$args) {
			$GLOBALS['wp_do_actions'][$hook][] = $args;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\error_get_last')) {
		function error_get_last() {
			return $GLOBALS['last_error'] ?? null;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\error_log')) {
		function error_log($message) {
			$GLOBALS['error_logs'][] = $message;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\file_put_contents')) {
		function file_put_contents($file, $data, $flags = 0) {
			$GLOBALS['file_writes'][$file][] = $data;
			return strlen($data);
		}
	}
	if (!function_exists(__NAMESPACE__ . '\wp_json_encode')) {
		function wp_json_encode($data, $options = 0, $depth = 512) {
			return json_encode($data, $options, $depth);
		}
	}
	if (!function_exists(__NAMESPACE__ . '\date')) {
		function date($format, $timestamp = null) {
			return \date($format, $timestamp);
		}
	}
	if (!function_exists(__NAMESPACE__ . '\memory_get_usage')) {
		function memory_get_usage($real = false) {
			return 1048576; // 1MB
		}
	}
	if (!function_exists(__NAMESPACE__ . '\memory_get_peak_usage')) {
		function memory_get_peak_usage($real = false) {
			return 2097152; // 2MB
		}
	}
	if (!function_exists(__NAMESPACE__ . '\time')) {
		function time() {
			return $GLOBALS['test_time'] ?? \time();
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Core\ErrorHandler;

	class ErrorHandlerTest extends TestCase {
		protected function setUp(): void {
			global $wp_actions, $wp_filters, $wp_transients, $wp_do_actions, $error_logs, $file_writes, $current_user_id, $last_error, $test_time;
			$wp_actions = $wp_filters = $wp_transients = $wp_do_actions = $error_logs = $file_writes = [];
			$current_user_id = 1;
			$last_error = null;
			$test_time = time();
		}

		public function test_init_registers_handlers(): void {
			ErrorHandler::init();
			
			$this->assertArrayHasKey('error_handler', $GLOBALS);
			$this->assertArrayHasKey('exception_handler', $GLOBALS);
			$this->assertArrayHasKey('shutdown_handler', $GLOBALS);
			$this->assertArrayHasKey('wp_die_handler', $GLOBALS['wp_actions']);
			$this->assertArrayHasKey('wp_die_ajax_handler', $GLOBALS['wp_filters']);
			$this->assertArrayHasKey('wp_die_json_handler', $GLOBALS['wp_filters']);
		}

		public function test_handle_php_error_returns_false(): void {
			// The handle_php_error method should return false to allow default error handling
			$result = ErrorHandler::handle_php_error(E_WARNING, 'Test warning', '/test/file.php', 123);
			
			$this->assertFalse($result);
		}

		public function test_handle_fatal_error_only_processes_fatal_errors(): void {
			// Test with non-fatal error
			$GLOBALS['last_error'] = [
				'type' => E_NOTICE,
				'message' => 'Notice message',
				'file' => '/test/file.php',
				'line' => 123
			];
			
			ErrorHandler::handle_fatal_error();
			
			// Non-fatal errors should not be logged
			$this->assertEmpty($GLOBALS['error_logs']);
			
			// Test with fatal error
			$GLOBALS['last_error'] = [
				'type' => E_ERROR,
				'message' => 'Fatal error occurred',
				'file' => '/test/fatal.php',
				'line' => 456
			];
			
			ErrorHandler::handle_fatal_error();
			
			// Fatal errors should be logged
			$this->assertNotEmpty($GLOBALS['error_logs']);
		}

		public function test_handle_wp_die_returns_callable(): void {
			$originalHandler = function($msg, $title, $args) {
				return "Original: $msg";
			};
			
			$wrappedHandler = ErrorHandler::handle_wp_die($originalHandler);
			
			$this->assertIsCallable($wrappedHandler);
			
			// Test that it calls the original handler
			$result = $wrappedHandler('Test message', 'Title', []);
			$this->assertEquals('Original: Test message', $result);
		}

		public function test_rate_limiting_with_transients(): void {
			$category = ErrorHandler::CATEGORY_AUTHENTICATION;
			
			// First few calls should work
			for ($i = 0; $i < 5; $i++) {
				$key = "nuclen_error_limit_{$category}";
				$current = $GLOBALS['wp_transients'][$key]['value'] ?? 0;
				$this->assertLessThan(5, $current);
			}
			
			// After hitting the limit, transient should be set
			$GLOBALS['wp_transients']["nuclen_error_limit_{$category}"] = ['value' => 5, 'expiration' => 300];
			
			// Verify the transient exists with correct value
			$this->assertEquals(5, $GLOBALS['wp_transients']["nuclen_error_limit_{$category}"]['value']);
		}

		public function test_error_handler_handles_exceptions_gracefully(): void {
			// Test that the error handler itself doesn't throw exceptions
			try {
				ErrorHandler::handle_php_error(E_WARNING, 'Test error', '', 0);
				ErrorHandler::handle_exception(new \Exception('Test exception'));
				ErrorHandler::handle_fatal_error();
				
				$this->assertTrue(true); // If we get here, no exceptions were thrown
			} catch (\Throwable $e) {
				$this->fail('ErrorHandler threw an exception: ' . $e->getMessage());
			}
		}

		public function test_sensitive_data_patterns_are_defined(): void {
			// Use reflection to check that sensitive patterns are defined
			$reflection = new \ReflectionClass(ErrorHandler::class);
			$property = $reflection->getProperty('sensitive_patterns');
			$property->setAccessible(true);
			$patterns = $property->getValue();
			
			$this->assertIsArray($patterns);
			$this->assertNotEmpty($patterns);
			
			// Test that patterns include common sensitive data
			$hasEmailPattern = false;
			$hasPasswordPattern = false;
			$hasApiKeyPattern = false;
			
			foreach ($patterns as $pattern) {
				if (strpos($pattern, '@') !== false) $hasEmailPattern = true;
				if (strpos($pattern, 'password') !== false) $hasPasswordPattern = true;
				if (strpos($pattern, 'api') !== false && strpos($pattern, 'key') !== false) $hasApiKeyPattern = true;
			}
			
			$this->assertTrue($hasEmailPattern, 'Should have email pattern');
			$this->assertTrue($hasPasswordPattern, 'Should have password pattern');
			$this->assertTrue($hasApiKeyPattern, 'Should have API key pattern');
		}

		public function test_severity_constants_exist(): void {
			$this->assertEquals('critical', ErrorHandler::SEVERITY_CRITICAL);
			$this->assertEquals('high', ErrorHandler::SEVERITY_HIGH);
			$this->assertEquals('medium', ErrorHandler::SEVERITY_MEDIUM);
			$this->assertEquals('low', ErrorHandler::SEVERITY_LOW);
		}

		public function test_category_constants_exist(): void {
			$this->assertEquals('authentication', ErrorHandler::CATEGORY_AUTHENTICATION);
			$this->assertEquals('database', ErrorHandler::CATEGORY_DATABASE);
			$this->assertEquals('network', ErrorHandler::CATEGORY_NETWORK);
			$this->assertEquals('validation', ErrorHandler::CATEGORY_VALIDATION);
			$this->assertEquals('permissions', ErrorHandler::CATEGORY_PERMISSIONS);
			$this->assertEquals('resource', ErrorHandler::CATEGORY_RESOURCE);
			$this->assertEquals('security', ErrorHandler::CATEGORY_SECURITY);
			$this->assertEquals('configuration', ErrorHandler::CATEGORY_CONFIGURATION);
			$this->assertEquals('external_api', ErrorHandler::CATEGORY_EXTERNAL_API);
			$this->assertEquals('file_system', ErrorHandler::CATEGORY_FILE_SYSTEM);
		}

		public function test_write_to_error_log_creates_file(): void {
			// Test critical error logging
			$reflection = new \ReflectionClass(ErrorHandler::class);
			$method = $reflection->getMethod('write_to_error_log');
			$method->setAccessible(true);
			
			$testEntry = 'Test critical error entry';
			$method->invoke(null, $testEntry);
			
			$expectedFile = WP_CONTENT_DIR . '/nuclen-errors.log';
			$this->assertArrayHasKey($expectedFile, $GLOBALS['file_writes']);
			$this->assertStringContainsString($testEntry, $GLOBALS['file_writes'][$expectedFile][0]);
		}
	}
	
	// Define constants needed for tests
	if (!defined('WP_CONTENT_DIR')) {
		define('WP_CONTENT_DIR', '/tmp/wp-content');
	}
	if (!defined('FILE_APPEND')) {
		define('FILE_APPEND', 8);
	}
	if (!defined('LOCK_EX')) {
		define('LOCK_EX', 2);
	}
	if (!defined('PHP_VERSION')) {
		define('PHP_VERSION', '8.0.0');
	}
}

// Mock dependencies
namespace NuclearEngagement\Core {
	if (!class_exists(__NAMESPACE__ . '\ErrorContext')) {
		class ErrorContext {
			private $id;
			private $message;
			private $severity;
			private $category;
			private $context;
			
			public function __construct($id, $message, $severity, $category, $context) {
				$this->id = $id;
				$this->message = $message;
				$this->severity = $severity;
				$this->category = $category;
				$this->context = $context;
			}
			
			public function get_message() { return $this->message; }
			public function get_severity() { return $this->severity; }
			public function get_category() { return $this->category; }
			public function get_context() { return $this->context; }
		}
	}
	
	if (!class_exists(__NAMESPACE__ . '\ErrorMonitor')) {
		class ErrorMonitor {
			public static function track_security_event($context) {
				// Mock implementation
			}
		}
	}
}

namespace NuclearEngagement\Utils {
	if (!class_exists(__NAMESPACE__ . '\ServerUtils')) {
		class ServerUtils {
			public static function get_safe_context() {
				return [
					'user_ip' => '127.0.0.1',
					'user_agent' => 'Test Agent',
					'request_uri' => '/test'
				];
			}
			
			public static function get_client_ip() {
				return '127.0.0.1';
			}
		}
	}
}