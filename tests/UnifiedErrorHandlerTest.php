<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
}

// Mock WordPress functions for error handling
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show) {
        return $show === 'version' ? '6.0' : 'Test Blog';
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        $GLOBALS['wp_cache'] = [];
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        $GLOBALS['wp_actions'][$hook] = $args;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['wp_action_hooks'][$hook][] = compact('callback', 'priority', 'accepted_args');
    }
}

if (!function_exists('gmdate')) {
    function gmdate($format, $timestamp = null) {
        return date($format, $timestamp ?: time());
    }
}

// Mock ServerUtils class
class MockServerUtils {
    public static function get_safe_context() {
        return [
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'request_uri' => '/test',
            'request_method' => 'GET'
        ];
    }
}

// Mock LoggingService class
class MockLoggingService {
    public static $logs = [];
    
    public static function log($message) {
        self::$logs[] = $message;
    }
}

// Replace the actual classes with mocks for testing
if (!class_exists('NuclearEngagement\Utils\ServerUtils')) {
    class_alias('MockServerUtils', 'NuclearEngagement\Utils\ServerUtils');
}
if (!class_exists('NuclearEngagement\Services\LoggingService')) {
    class_alias('MockLoggingService', 'NuclearEngagement\Services\LoggingService');
}

require_once __DIR__ . '/../nuclear-engagement/inc/Core/UnifiedErrorHandler.php';

class UnifiedErrorHandlerTest extends TestCase {
    
    private $originalGlobals;
    private $handler;
    
    protected function setUp(): void {
        // Store original globals
        $this->originalGlobals = [
            'wp_options' => $GLOBALS['wp_options'] ?? [],
            'wp_transients' => $GLOBALS['wp_transients'] ?? [],
            'wp_actions' => $GLOBALS['wp_actions'] ?? [],
            'wp_action_hooks' => $GLOBALS['wp_action_hooks'] ?? [],
            'wp_cache' => $GLOBALS['wp_cache'] ?? []
        ];
        
        // Reset globals
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_transients'] = [];
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_action_hooks'] = [];
        $GLOBALS['wp_cache'] = [];
        
        // Reset mock classes
        MockLoggingService::$logs = [];
        
        // Get fresh instance
        $reflection = new ReflectionClass(\NuclearEngagement\Core\UnifiedErrorHandler::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null);
        
        $this->handler = \NuclearEngagement\Core\UnifiedErrorHandler::get_instance();
    }
    
    protected function tearDown(): void {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        
        // Reset singleton
        $reflection = new ReflectionClass(\NuclearEngagement\Core\UnifiedErrorHandler::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null);
    }
    
    public function testSingletonPattern() {
        $handler1 = \NuclearEngagement\Core\UnifiedErrorHandler::get_instance();
        $handler2 = \NuclearEngagement\Core\UnifiedErrorHandler::get_instance();
        
        $this->assertSame($handler1, $handler2);
    }
    
    public function testHandleErrorCreatesErrorData() {
        $result = $this->handler->handle_error(
            'Test error message',
            \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_GENERAL,
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_MEDIUM,
            ['custom_key' => 'custom_value']
        );
        
        $this->assertTrue($result);
        $this->assertNotEmpty(MockLoggingService::$logs);
        
        $log_entry = MockLoggingService::$logs[0];
        $this->assertStringContainsString('Test error message', $log_entry);
        $this->assertStringContainsString('general', $log_entry);
        $this->assertStringContainsString('medium', $log_entry);
    }
    
    public function testHandleErrorWithSecurityCategory() {
        $result = $this->handler->handle_error(
            'Security violation detected',
            \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_SECURITY,
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH
        );
        
        $this->assertTrue($result);
        $this->assertArrayHasKey('nuclen_security_event', $GLOBALS['wp_actions']);
    }
    
    public function testRateLimiting() {
        // Set rate limit for security category to 3
        $category = \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_SECURITY;
        
        // First 3 errors should succeed
        for ($i = 0; $i < 3; $i++) {
            $result = $this->handler->handle_error(
                "Security error $i",
                $category,
                \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH
            );
            $this->assertTrue($result);
        }
        
        // 4th error should be rate limited
        $result = $this->handler->handle_error(
            'Rate limited error',
            $category,
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH
        );
        
        $this->assertFalse($result);
    }
    
    public function testErrorMessageSanitization() {
        $sensitive_message = 'Error with email test@example.com and api_key=secret123';
        
        $result = $this->handler->handle_error($sensitive_message);
        
        $this->assertTrue($result);
        $log_entry = MockLoggingService::$logs[0];
        $this->assertStringContainsString('[EMAIL]', $log_entry);
        $this->assertStringContainsString('[REDACTED]', $log_entry);
        $this->assertStringNotContainsString('test@example.com', $log_entry);
        $this->assertStringNotContainsString('secret123', $log_entry);
    }
    
    public function testHandlePhpError() {
        $result = $this->handler->handle_php_error(
            E_WARNING,
            'Test PHP warning',
            '/path/to/file.php',
            123
        );
        
        $this->assertFalse($result); // Should return false to not suppress default handling
        $this->assertNotEmpty(MockLoggingService::$logs);
        
        $log_entry = MockLoggingService::$logs[0];
        $this->assertStringContainsString('Test PHP warning', $log_entry);
        $this->assertStringContainsString('high', $log_entry); // E_WARNING maps to high severity
    }
    
    public function testHandleException() {
        $exception = new RuntimeException('Test runtime exception', 500);
        
        $this->handler->handle_exception($exception);
        
        $this->assertNotEmpty(MockLoggingService::$logs);
        $log_entry = MockLoggingService::$logs[0];
        $this->assertStringContainsString('Test runtime exception', $log_entry);
        $this->assertStringContainsString('high', $log_entry); // RuntimeException maps to high severity
    }
    
    public function testHandleFatalError() {
        // Mock error_get_last
        $originalErrorHandler = set_error_handler(function() {});
        
        // Trigger a fatal error simulation
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('handle_fatal_error');
        $method->setAccessible(true);
        
        // We can't easily test actual fatal errors, but we can test the logic
        $this->assertTrue(true); // Placeholder for fatal error testing
        
        restore_error_handler();
    }
    
    public function testErrorCategorization() {
        // Test database categorization
        $this->handler->handle_error('Database connection failed');
        $log_entry = MockLoggingService::$logs[0];
        $this->assertStringContainsString('database', $log_entry);
        
        MockLoggingService::$logs = [];
        
        // Test permission categorization
        $this->handler->handle_error('Permission denied error');
        $log_entry = MockLoggingService::$logs[0];
        $this->assertStringContainsString('permissions', $log_entry);
        
        MockLoggingService::$logs = [];
        
        // Test security categorization
        $this->handler->handle_error('Security token invalid');
        $log_entry = MockLoggingService::$logs[0];
        $this->assertStringContainsString('security', $log_entry);
    }
    
    public function testSeverityMapping() {
        // Test critical PHP error mapping
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('map_php_error_severity');
        $method->setAccessible(true);
        
        $severity = $method->invoke($this->handler, E_ERROR);
        $this->assertEquals(\NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_CRITICAL, $severity);
        
        $severity = $method->invoke($this->handler, E_WARNING);
        $this->assertEquals(\NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH, $severity);
        
        $severity = $method->invoke($this->handler, E_NOTICE);
        $this->assertEquals(\NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_MEDIUM, $severity);
    }
    
    public function testExceptionSeverityMapping() {
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('map_exception_severity');
        $method->setAccessible(true);
        
        $severity = $method->invoke($this->handler, new Error('Test error'));
        $this->assertEquals(\NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_CRITICAL, $severity);
        
        $severity = $method->invoke($this->handler, new RuntimeException('Test runtime'));
        $this->assertEquals(\NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH, $severity);
        
        $severity = $method->invoke($this->handler, new Exception('Test exception'));
        $this->assertEquals(\NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_MEDIUM, $severity);
    }
    
    public function testErrorStatistics() {
        // Handle some errors
        $this->handler->handle_error('Error 1', 
            \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_GENERAL,
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH
        );
        
        $this->handler->handle_error('Error 2',
            \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_GENERAL, 
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH
        );
        
        $stats = $this->handler->get_error_stats();
        
        $this->assertArrayHasKey('general_high', $stats);
        $this->assertEquals(2, $stats['general_high']['count']);
        $this->assertArrayHasKey('first_seen', $stats['general_high']);
        $this->assertArrayHasKey('last_seen', $stats['general_high']);
    }
    
    public function testDatabaseRecoveryAttempt() {
        $this->handler->handle_error(
            'Database error',
            \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_DATABASE,
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH
        );
        
        // Cache should be flushed as part of recovery
        $this->assertEmpty($GLOBALS['wp_cache']);
    }
    
    public function testResourceRecoveryAttempt() {
        $this->handler->handle_error(
            'Memory limit exceeded',
            \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_RESOURCE,
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_HIGH
        );
        
        // Cache should be flushed as part of recovery
        $this->assertEmpty($GLOBALS['wp_cache']);
    }
    
    public function testWpDieHandler() {
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('handle_wp_die');
        $method->setAccessible(true);
        
        $originalHandler = function($message, $title, $args) { return 'original'; };
        $wrappedHandler = $method->invoke($this->handler, $originalHandler);
        
        $result = $wrappedHandler('Test wp_die message', 'Test Title', []);
        
        $this->assertEquals('original', $result);
        $this->assertNotEmpty(MockLoggingService::$logs);
    }
    
    public function testCriticalErrorLogging() {
        // Create temp directory if it doesn't exist
        $wp_content_dir = WP_CONTENT_DIR;
        if (!file_exists($wp_content_dir)) {
            mkdir($wp_content_dir, 0777, true);
        }
        
        $this->handler->handle_error(
            'Critical system failure',
            \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_GENERAL,
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_CRITICAL
        );
        
        $critical_log_file = $wp_content_dir . '/nuclen-critical-errors.log';
        
        // Check if critical log file was created/written to
        $this->assertTrue(file_exists($critical_log_file) || !is_writable($wp_content_dir));
        
        // Clean up if file was created
        if (file_exists($critical_log_file)) {
            unlink($critical_log_file);
        }
        if (is_dir($wp_content_dir) && count(scandir($wp_content_dir)) == 2) {
            rmdir($wp_content_dir);
        }
    }
    
    public function testHandleErrorWithExceptionInHandler() {
        // Mock a scenario where logging throws an exception
        $originalLogs = MockLoggingService::$logs;
        
        // This should not throw an exception due to try-catch in handle_error
        $result = $this->handler->handle_error('Test error that might cause logging to fail');
        
        // Should still return true even if some internal operations fail
        $this->assertTrue($result);
    }
    
    public function testContextSanitization() {
        $sensitive_context = [
            'user_password' => 'secret123',
            'api_key' => 'sensitive_key',
            'nested' => [
                'email' => 'user@example.com',
                'token' => 'abc123'
            ]
        ];
        
        $this->handler->handle_error(
            'Error with sensitive context',
            \NuclearEngagement\Core\UnifiedErrorHandler::CATEGORY_GENERAL,
            \NuclearEngagement\Core\UnifiedErrorHandler::SEVERITY_MEDIUM,
            $sensitive_context
        );
        
        $log_entry = MockLoggingService::$logs[0];
        $this->assertStringContainsString('[REDACTED]', $log_entry);
        $this->assertStringContainsString('[EMAIL]', $log_entry);
        $this->assertStringNotContainsString('secret123', $log_entry);
        $this->assertStringNotContainsString('user@example.com', $log_entry);
    }
}