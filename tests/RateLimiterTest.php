<?php
/**
 * Tests for RateLimiter class
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use NuclearEngagement\Security\RateLimiter;

class RateLimiterTest extends TestCase {

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
     * Test rate limiting allows requests within limit
     */
    public function test_is_rate_limited_allows_requests_within_limit() {
        // Arrange
        $action = 'api_request';
        $identifier = 'test_user_123';
        
        // Mock transient functions - no existing count
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_api_request_test_user_123')
            ->andReturn(0);

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->with('rate_limit_api_request_test_user_123', 1, 3600)
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier);

        // Assert
        $this->assertFalse($result, 'First request should not be rate limited');
    }

    /**
     * Test rate limiting blocks requests exceeding limit
     */
    public function test_is_rate_limited_blocks_requests_exceeding_limit() {
        // Arrange
        $action = 'api_request';
        $identifier = 'test_user_456';
        
        // Mock transient functions - already at limit
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_api_request_test_user_456')
            ->andReturn(100); // At default limit for api_request

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier);

        // Assert
        $this->assertTrue($result, 'Request exceeding limit should be rate limited');
    }

    /**
     * Test rate limiting with custom limits
     */
    public function test_is_rate_limited_with_custom_limits() {
        // Arrange
        $action = 'custom_action';
        $identifier = 'test_user_789';
        $customLimit = ['requests' => 5, 'window' => 1800];
        
        // Mock transient functions - under custom limit
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_custom_action_test_user_789')
            ->andReturn(3);

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->with('rate_limit_custom_action_test_user_789', 4, 1800)
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier, $customLimit);

        // Assert
        $this->assertFalse($result, 'Request under custom limit should not be rate limited');
    }

    /**
     * Test rate limiting with custom limits at threshold
     */
    public function test_is_rate_limited_with_custom_limits_at_threshold() {
        // Arrange
        $action = 'custom_action';
        $identifier = 'test_user_threshold';
        $customLimit = ['requests' => 5, 'window' => 1800];
        
        // Mock transient functions - at custom limit
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_custom_action_test_user_threshold')
            ->andReturn(5);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier, $customLimit);

        // Assert
        $this->assertTrue($result, 'Request at custom limit should be rate limited');
    }

    /**
     * Test recording rate limit violations
     */
    public function test_record_violation_increments_violation_count() {
        // Arrange
        $action = 'login_attempt';
        $identifier = 'violator_123';
        
        // Mock transient functions
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_violation_login_attempt_violator_123')
            ->andReturn(2); // Existing violations

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->with('rate_limit_violation_login_attempt_violator_123', 3, DAY_IN_SECONDS)
            ->andReturn(true);

        // Mock error_log for security logging
        \WP_Mock::userFunction('error_log')
            ->once()
            ->with(Mockery::type('string'));

        // Act
        RateLimiter::record_violation($action, $identifier);

        // Assert - Method should complete without errors
        $this->assertTrue(true, 'Violation recording should complete successfully');
    }

    /**
     * Test recording violations triggers temporary block for repeat violators
     */
    public function test_record_violation_triggers_temporary_block_for_repeat_violators() {
        // Arrange
        $action = 'form_submit';
        $identifier = 'repeat_violator_456';
        
        // Mock transient functions - high violation count
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_violation_form_submit_repeat_violator_456')
            ->andReturn(15); // More than 10 violations

        \WP_Mock::userFunction('set_transient')
            ->twice() // Once for violation count, once for temporary block
            ->andReturn(true);

        // Mock error_log for both violation and block logging
        \WP_Mock::userFunction('error_log')
            ->twice();

        // Act
        RateLimiter::record_violation($action, $identifier);

        // Assert - Method should complete without errors
        $this->assertTrue(true, 'High violation count should trigger temporary block');
    }

    /**
     * Test getting remaining requests
     */
    public function test_get_remaining_requests_returns_correct_count() {
        // Arrange
        $action = 'api_request';
        $identifier = 'test_user_remaining';
        
        // Mock transient functions
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_api_request_test_user_remaining')
            ->andReturn(25); // 25 requests used out of 100 default

        // Act
        $remaining = RateLimiter::get_remaining_requests($action, $identifier);

        // Assert
        $this->assertEquals(75, $remaining, 'Should return 75 remaining requests');
    }

    /**
     * Test getting remaining requests with custom limit
     */
    public function test_get_remaining_requests_with_custom_limit() {
        // Arrange
        $action = 'custom_action';
        $identifier = 'test_user_custom_remaining';
        $customLimit = ['requests' => 20, 'window' => 3600];
        
        // Mock transient functions
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_custom_action_test_user_custom_remaining')
            ->andReturn(12);

        // Act
        $remaining = RateLimiter::get_remaining_requests($action, $identifier, $customLimit);

        // Assert
        $this->assertEquals(8, $remaining, 'Should return 8 remaining requests with custom limit');
    }

    /**
     * Test getting remaining requests returns zero when limit exceeded
     */
    public function test_get_remaining_requests_returns_zero_when_exceeded() {
        // Arrange
        $action = 'login_attempt';
        $identifier = 'test_user_exceeded';
        
        // Mock transient functions - exceeded limit
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_login_attempt_test_user_exceeded')
            ->andReturn(10); // More than default limit of 5

        // Act
        $remaining = RateLimiter::get_remaining_requests($action, $identifier);

        // Assert
        $this->assertEquals(0, $remaining, 'Should return 0 when limit is exceeded');
    }

    /**
     * Test resetting rate limit
     */
    public function test_reset_limit_clears_transient() {
        // Arrange
        $action = 'api_request';
        $identifier = 'test_user_reset';
        
        // Mock delete_transient
        \WP_Mock::userFunction('delete_transient')
            ->once()
            ->with('rate_limit_api_request_test_user_reset')
            ->andReturn(true);

        // Act
        RateLimiter::reset_limit($action, $identifier);

        // Assert - Method should complete without errors
        $this->assertTrue(true, 'Rate limit reset should complete successfully');
    }

    /**
     * Test IP identifier generation
     */
    public function test_get_ip_identifier_generates_safe_identifier() {
        // Arrange
        $ipAddress = '192.168.1.100';
        
        // Mock wp_salt function
        \WP_Mock::userFunction('wp_salt')
            ->once()
            ->andReturn('test_salt_value_for_hashing');

        // Act
        $identifier = RateLimiter::get_ip_identifier($ipAddress);

        // Assert
        $this->assertIsString($identifier);
        $this->assertStringStartsWith('ip_', $identifier);
        $this->assertEquals(19, strlen($identifier)); // 'ip_' + 16 chars = 19 total
        $this->assertStringNotContainsString($ipAddress, $identifier, 'IP should be hashed for privacy');
    }

    /**
     * Test IP identifier consistency
     */
    public function test_get_ip_identifier_returns_consistent_results() {
        // Arrange
        $ipAddress = '10.0.0.1';
        
        // Mock wp_salt function
        \WP_Mock::userFunction('wp_salt')
            ->times(2)
            ->andReturn('consistent_salt_value');

        // Act
        $identifier1 = RateLimiter::get_ip_identifier($ipAddress);
        $identifier2 = RateLimiter::get_ip_identifier($ipAddress);

        // Assert
        $this->assertEquals($identifier1, $identifier2, 'Same IP should produce same identifier');
    }

    /**
     * Test different IPs produce different identifiers
     */
    public function test_get_ip_identifier_produces_different_identifiers_for_different_ips() {
        // Arrange
        $ipAddress1 = '192.168.1.1';
        $ipAddress2 = '192.168.1.2';
        
        // Mock wp_salt function
        \WP_Mock::userFunction('wp_salt')
            ->times(2)
            ->andReturn('salt_for_different_ips');

        // Act
        $identifier1 = RateLimiter::get_ip_identifier($ipAddress1);
        $identifier2 = RateLimiter::get_ip_identifier($ipAddress2);

        // Assert
        $this->assertNotEquals($identifier1, $identifier2, 'Different IPs should produce different identifiers');
    }

    /**
     * Test temporary block checking
     */
    public function test_is_temporarily_blocked_returns_true_for_blocked_identifier() {
        // Arrange
        $identifier = 'blocked_user_123';
        
        // Mock get_transient to return true (blocked)
        \WP_Mock::userFunction('get_transient')
            ->with('temp_block_blocked_user_123')
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_temporarily_blocked($identifier);

        // Assert
        $this->assertTrue($result, 'Should return true for blocked identifier');
    }

    /**
     * Test temporary block checking for non-blocked identifier
     */
    public function test_is_temporarily_blocked_returns_false_for_non_blocked_identifier() {
        // Arrange
        $identifier = 'clean_user_456';
        
        // Mock get_transient to return false (not blocked)
        \WP_Mock::userFunction('get_transient')
            ->with('temp_block_clean_user_456')
            ->andReturn(false);

        // Act
        $result = RateLimiter::is_temporarily_blocked($identifier);

        // Assert
        $this->assertFalse($result, 'Should return false for non-blocked identifier');
    }

    /**
     * Test rate limiting with login attempt action uses correct defaults
     */
    public function test_login_attempt_action_uses_correct_defaults() {
        // Arrange
        $action = 'login_attempt';
        $identifier = 'login_test_user';
        
        // Mock transient functions - under login limit (5 requests)
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_login_attempt_login_test_user')
            ->andReturn(3);

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->with('rate_limit_login_attempt_login_test_user', 4, 900) // 15 minutes
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier);

        // Assert
        $this->assertFalse($result, 'Should use login attempt defaults (5 requests, 15 minutes)');
    }

    /**
     * Test rate limiting with form submit action uses correct defaults
     */
    public function test_form_submit_action_uses_correct_defaults() {
        // Arrange
        $action = 'form_submit';
        $identifier = 'form_test_user';
        
        // Mock transient functions - under form limit (20 requests)
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_form_submit_form_test_user')
            ->andReturn(15);

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->with('rate_limit_form_submit_form_test_user', 16, 3600) // 1 hour
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier);

        // Assert
        $this->assertFalse($result, 'Should use form submit defaults (20 requests, 1 hour)');
    }

    /**
     * Test unknown action falls back to api_request defaults
     */
    public function test_unknown_action_uses_api_request_defaults() {
        // Arrange
        $action = 'unknown_action_type';
        $identifier = 'unknown_test_user';
        
        // Mock transient functions - uses api_request defaults (100 requests, 1 hour)
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_unknown_action_type_unknown_test_user')
            ->andReturn(50);

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->with('rate_limit_unknown_action_type_unknown_test_user', 51, 3600)
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier);

        // Assert
        $this->assertFalse($result, 'Should fall back to api_request defaults for unknown actions');
    }

    /**
     * Test cache key generation
     */
    public function test_cache_key_format() {
        // This test verifies the internal cache key format by testing the public behavior
        // We can infer the key format from the mocked transient calls
        
        // Arrange
        $action = 'test_action';
        $identifier = 'test_identifier';
        
        // The expected key format is 'rate_limit_' + action + '_' + identifier
        $expectedKey = 'rate_limit_test_action_test_identifier';
        
        // Mock transient functions with expected key
        \WP_Mock::userFunction('get_transient')
            ->with($expectedKey)
            ->andReturn(0);

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->with($expectedKey, 1, 3600)
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier);

        // Assert
        $this->assertFalse($result, 'Cache key format should match expected pattern');
    }

    /**
     * Test edge case with empty identifier
     */
    public function test_rate_limiting_with_empty_identifier() {
        // Arrange
        $action = 'api_request';
        $identifier = '';
        
        // Mock transient functions
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit_api_request_')
            ->andReturn(0);

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier);

        // Assert
        $this->assertFalse($result, 'Should handle empty identifier gracefully');
    }

    /**
     * Test edge case with empty action
     */
    public function test_rate_limiting_with_empty_action() {
        // Arrange
        $action = '';
        $identifier = 'test_user';
        
        // Mock transient functions - should fall back to api_request defaults
        \WP_Mock::userFunction('get_transient')
            ->with('rate_limit__test_user')
            ->andReturn(0);

        \WP_Mock::userFunction('set_transient')
            ->once()
            ->with('rate_limit__test_user', 1, 3600)
            ->andReturn(true);

        // Act
        $result = RateLimiter::is_rate_limited($action, $identifier);

        // Assert
        $this->assertFalse($result, 'Should handle empty action gracefully');
    }
}