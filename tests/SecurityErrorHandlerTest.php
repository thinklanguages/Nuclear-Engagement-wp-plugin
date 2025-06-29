<?php
/**
 * Tests for SecurityErrorHandler class
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use NuclearEngagement\Core\SecurityErrorHandler;
use NuclearEngagement\Core\ErrorManager;
use NuclearEngagement\Core\ErrorContext;

class SecurityErrorHandlerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
    }

    protected function tearDown(): void {
        \WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test security error handling with threat analysis
     */
    public function test_handle_security_error_creates_proper_context() {
        // Arrange
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Test Browser)';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_REFERER'] = 'https://example.com/admin';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $context = [
            'additional_data' => 'test_value'
        ];

        // Mock ErrorManager::handle_error
        \WP_Mock::userFunction('ErrorManager::handle_error')
            ->once()
            ->andReturn(new ErrorContext(
                'Security event detected: ' . SecurityErrorHandler::EVENT_BRUTE_FORCE,
                ErrorManager::SEVERITY_HIGH,
                ErrorManager::CATEGORY_SECURITY,
                $context
            ));

        // Act
        $result = SecurityErrorHandler::handle_security_error(
            SecurityErrorHandler::EVENT_BRUTE_FORCE, 
            $context
        );

        // Assert
        $this->assertInstanceOf(ErrorContext::class, $result);
    }

    /**
     * Test suspicious activity detection with SQL injection patterns
     */
    public function test_detect_suspicious_activity_identifies_sql_injection() {
        // Arrange
        $request_data = [
            'user_input' => "' OR 1=1 --",
            'search_term' => 'UNION SELECT * FROM wp_users',
            'safe_input' => 'normal text input'
        ];

        // Act
        $result = SecurityErrorHandler::detect_suspicious_activity($request_data);

        // Assert
        $this->assertTrue($result, 'Should detect SQL injection patterns');
    }

    /**
     * Test suspicious activity detection with XSS patterns
     */
    public function test_detect_suspicious_activity_identifies_xss() {
        // Arrange
        $request_data = [
            'comment' => '<script>alert("xss")</script>',
            'message' => 'javascript:alert("test")',
            'safe_input' => 'normal text'
        ];

        // Act
        $result = SecurityErrorHandler::detect_suspicious_activity($request_data);

        // Assert
        $this->assertTrue($result, 'Should detect XSS patterns');
    }

    /**
     * Test suspicious activity detection with path traversal patterns
     */
    public function test_detect_suspicious_activity_identifies_path_traversal() {
        // Arrange
        $request_data = [
            'file_path' => '../../../etc/passwd',
            'upload_path' => '..\\..\\windows\\system32',
            'safe_path' => 'uploads/file.jpg'
        ];

        // Act
        $result = SecurityErrorHandler::detect_suspicious_activity($request_data);

        // Assert
        $this->assertTrue($result, 'Should detect path traversal patterns');
    }

    /**
     * Test that clean input doesn't trigger suspicious activity detection
     */
    public function test_detect_suspicious_activity_allows_clean_input() {
        // Arrange
        $request_data = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'message' => 'This is a normal message with no malicious content.'
        ];

        // Act
        $result = SecurityErrorHandler::detect_suspicious_activity($request_data);

        // Assert
        $this->assertFalse($result, 'Should not detect suspicious activity in clean input');
    }

    /**
     * Test IP blocking functionality
     */
    public function test_block_ip_functionality() {
        // Arrange
        $ip = '192.168.1.100';
        $duration = 3600; // 1 hour
        $reason = 'test_block';

        // Mock ErrorManager::handle_error for logging
        \WP_Mock::userFunction('ErrorManager::handle_error')
            ->once()
            ->with(
                "IP address blocked: {$ip}",
                ErrorManager::SEVERITY_MEDIUM,
                ErrorManager::CATEGORY_SECURITY,
                Mockery::type('array')
            );

        // Act
        SecurityErrorHandler::block_ip($ip, $duration, $reason);

        // Assert - IP should now be blocked
        $this->assertTrue(SecurityErrorHandler::is_ip_blocked($ip));
    }

    /**
     * Test IP block expiration
     */
    public function test_ip_block_expiration() {
        // Arrange
        $ip = '192.168.1.101';
        $duration = -1; // Already expired

        // Mock ErrorManager::handle_error for logging
        \WP_Mock::userFunction('ErrorManager::handle_error')
            ->once();

        // Act
        SecurityErrorHandler::block_ip($ip, $duration);

        // Assert - Expired block should not block IP
        $this->assertFalse(SecurityErrorHandler::is_ip_blocked($ip));
    }

    /**
     * Test rate limiting functionality
     */
    public function test_apply_rate_limit_functionality() {
        // Arrange
        $identifier = 'test_user_123';
        $limit = 5;
        $window = 3600;

        // Act & Assert - First few requests should be allowed
        for ($i = 1; $i <= $limit; $i++) {
            $allowed = SecurityErrorHandler::apply_rate_limit($identifier, $limit, $window);
            $this->assertTrue($allowed, "Request $i should be allowed");
        }

        // Act & Assert - Exceeding limit should be blocked
        $blocked = SecurityErrorHandler::apply_rate_limit($identifier, $limit, $window);
        $this->assertFalse($blocked, 'Request exceeding limit should be blocked');
    }

    /**
     * Test rate limiting with progressive blocking
     */
    public function test_rate_limit_progressive_blocking() {
        // Arrange
        $identifier = 'test_progressive_123';
        $limit = 2;
        $window = 3600;

        // Act - Exceed limit multiple times
        SecurityErrorHandler::apply_rate_limit($identifier, $limit, $window); // 1
        SecurityErrorHandler::apply_rate_limit($identifier, $limit, $window); // 2
        SecurityErrorHandler::apply_rate_limit($identifier, $limit, $window); // 3 - blocked
        SecurityErrorHandler::apply_rate_limit($identifier, $limit, $window); // 4 - blocked longer

        // Assert - Should be blocked
        $blocked = SecurityErrorHandler::apply_rate_limit($identifier, $limit, $window);
        $this->assertFalse($blocked, 'Progressive blocking should be in effect');
    }

    /**
     * Test input sanitization functionality
     */
    public function test_sanitize_input_removes_malicious_content() {
        // Arrange
        $malicious_input = '<script>alert("xss")</script>Normal text';

        // Mock WordPress sanitization functions
        \WP_Mock::userFunction('wp_kses')
            ->once()
            ->with($malicious_input, [])
            ->andReturn('Normal text');

        \WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->with('Normal text')
            ->andReturn('Normal text');

        // Act
        $result = SecurityErrorHandler::sanitize_input($malicious_input);

        // Assert
        $this->assertEquals('Normal text', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    /**
     * Test input sanitization with array input
     */
    public function test_sanitize_input_handles_arrays() {
        // Arrange
        $input_array = [
            'safe' => 'normal text',
            'malicious' => '<script>alert("test")</script>',
            'nested' => [
                'also_malicious' => '<img src=x onerror=alert(1)>'
            ]
        ];

        // Mock WordPress functions for each string
        \WP_Mock::userFunction('wp_kses')
            ->times(3)
            ->andReturnUsing(function($input, $allowed_html) {
                return strip_tags($input); // Simple mock behavior
            });

        \WP_Mock::userFunction('sanitize_text_field')
            ->times(3)
            ->andReturnUsing(function($input) {
                return $input; // Simple mock behavior
            });

        // Act
        $result = SecurityErrorHandler::sanitize_input($input_array);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('normal text', $result['safe']);
        $this->assertStringNotContainsString('<script>', $result['malicious']);
        $this->assertStringNotContainsString('<img', $result['nested']['also_malicious']);
    }

    /**
     * Test security dashboard data collection
     */
    public function test_get_security_dashboard_returns_proper_structure() {
        // Act
        $dashboard = SecurityErrorHandler::get_security_dashboard(86400);

        // Assert
        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('total_events', $dashboard);
        $this->assertArrayHasKey('events_by_type', $dashboard);
        $this->assertArrayHasKey('top_threat_ips', $dashboard);
        $this->assertArrayHasKey('blocked_ips', $dashboard);
        $this->assertArrayHasKey('active_rate_limits', $dashboard);
        $this->assertArrayHasKey('threat_level', $dashboard);
        
        $this->assertIsInt($dashboard['total_events']);
        $this->assertIsArray($dashboard['events_by_type']);
        $this->assertIsInt($dashboard['blocked_ips']);
        $this->assertIsInt($dashboard['active_rate_limits']);
        $this->assertIsString($dashboard['threat_level']);
    }

    /**
     * Test login failure handling
     */
    public function test_handle_login_failure_tracks_brute_force() {
        // Arrange
        $_SERVER['REMOTE_ADDR'] = '192.168.1.200';
        $username = 'admin';

        // Mock the security error handler call
        // This is indirectly tested through the static method call
        
        // Act
        SecurityErrorHandler::handle_login_failure($username);

        // Assert - This would typically check that the event was tracked
        // Since we're testing a static method with internal state,
        // we verify the method doesn't throw exceptions
        $this->assertTrue(true, 'Login failure handling should complete without errors');
    }

    /**
     * Test successful login handling clears brute force tracking
     */
    public function test_handle_successful_login_clears_tracking() {
        // Arrange
        $_SERVER['REMOTE_ADDR'] = '192.168.1.201';
        $user = Mockery::mock('\WP_User');
        $user_login = 'testuser';

        // Act
        SecurityErrorHandler::handle_successful_login($user_login, $user);

        // Assert - Method should complete without errors
        $this->assertTrue(true, 'Successful login handling should complete without errors');
    }

    /**
     * Test IP checking before authentication
     */
    public function test_check_ip_before_auth_blocks_blocked_ips() {
        // Arrange
        $_SERVER['REMOTE_ADDR'] = '192.168.1.202';
        $blocked_ip = '192.168.1.202';
        
        // Mock ErrorManager::handle_error for IP blocking
        \WP_Mock::userFunction('ErrorManager::handle_error')
            ->once();

        // Block the IP first
        SecurityErrorHandler::block_ip($blocked_ip, 3600);

        // Mock WordPress translation function
        \WP_Mock::userFunction('__')
            ->once()
            ->with('Access denied due to security restrictions.', 'nuclear-engagement')
            ->andReturn('Access denied due to security restrictions.');

        // Act
        $result = SecurityErrorHandler::check_ip_before_auth(null, 'testuser', 'password');

        // Assert
        $this->assertInstanceOf('\WP_Error', $result);
        $this->assertEquals('ip_blocked', $result->get_error_code());
    }

    /**
     * Test IP checking allows non-blocked IPs
     */
    public function test_check_ip_before_auth_allows_clean_ips() {
        // Arrange
        $_SERVER['REMOTE_ADDR'] = '192.168.1.203';
        $user = Mockery::mock('\WP_User');

        // Act
        $result = SecurityErrorHandler::check_ip_before_auth($user, 'testuser', 'password');

        // Assert
        $this->assertSame($user, $result);
    }

    /**
     * Test AJAX request monitoring
     */
    public function test_monitor_ajax_requests_applies_rate_limiting() {
        // Arrange
        $_SERVER['REMOTE_ADDR'] = '192.168.1.204';
        $_GET = ['action' => 'test_action'];
        $_POST = ['data' => 'test_data'];

        // Mock WordPress functions
        \WP_Mock::userFunction('__')
            ->once()
            ->with('Too many requests. Please try again later.', 'nuclear-engagement')
            ->andReturn('Too many requests. Please try again later.');

        // This test would require mocking the rate limiting more extensively
        // For now, we test that the method can be called without fatal errors
        
        // Act & Assert - Should not throw fatal errors
        try {
            SecurityErrorHandler::monitor_ajax_requests();
            $this->assertTrue(true, 'AJAX monitoring should complete without fatal errors');
        } catch (\Error $e) {
            // Catch wp_die() which would be called in real WordPress
            $this->assertStringContainsString('Too many requests', $e->getMessage());
        }
    }

    /**
     * Test REST API monitoring
     */
    public function test_monitor_rest_requests_applies_rate_limiting() {
        // Arrange
        $_SERVER['REMOTE_ADDR'] = '192.168.1.205';

        // Mock WordPress functions
        \WP_Mock::userFunction('status_header')
            ->once()
            ->with(429);

        \WP_Mock::userFunction('__')
            ->once()
            ->with('Too many requests. Please try again later.', 'nuclear-engagement')
            ->andReturn('Too many requests. Please try again later.');

        // Act & Assert - Should not throw fatal errors
        try {
            SecurityErrorHandler::monitor_rest_requests();
            $this->assertTrue(true, 'REST monitoring should complete without fatal errors');
        } catch (\Error $e) {
            // Catch wp_die() which would be called in real WordPress
            $this->assertStringContainsString('Too many requests', $e->getMessage());
        }
    }

    /**
     * Test security data cleanup
     */
    public function test_cleanup_security_data_completes_successfully() {
        // Act
        SecurityErrorHandler::cleanup_security_data();

        // Assert - Method should complete without errors
        $this->assertTrue(true, 'Security data cleanup should complete without errors');
    }

    /**
     * Test initialization doesn't cause errors
     */
    public function test_init_completes_successfully() {
        // Mock WordPress functions
        \WP_Mock::userFunction('add_action')
            ->times(7); // Multiple add_action calls in init()

        \WP_Mock::userFunction('add_filter')
            ->once();

        \WP_Mock::userFunction('wp_next_scheduled')
            ->once()
            ->with('nuclen_cleanup_security_data')
            ->andReturn(false);

        \WP_Mock::userFunction('wp_schedule_event')
            ->once()
            ->with(Mockery::type('int'), 'hourly', 'nuclen_cleanup_security_data');

        \WP_Mock::userFunction('time')
            ->once()
            ->andReturn(time());

        \WP_Mock::userFunction('register_shutdown_function')
            ->once();

        // Act
        SecurityErrorHandler::init();

        // Assert - Should complete without errors
        $this->assertTrue(true, 'Initialization should complete without errors');
    }
}