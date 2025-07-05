<?php
/**
 * ApiUserManagerTest.php - Test suite for the ApiUserManager class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Security\ApiUserManager;
use NuclearEngagement\Services\LoggingService;
use NuclearEngagement\Utils\ServerUtils;
use Brain\Monkey\Functions;

/**
 * Test suite for the ApiUserManager class
 */
class ApiUserManagerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		
		// Mock WordPress functions
		Functions\when('get_role')->justReturn(null);
		Functions\when('add_role')->justReturn(true);
		Functions\when('__')->returnArg();
		Functions\when('get_option')->justReturn(false);
		Functions\when('get_user_by')->justReturn(false);
		Functions\when('home_url')->justReturn('https://example.com');
		Functions\when('parse_url')->justReturn('example.com');
		Functions\when('wp_generate_password')->justReturn('test_password_123');
		Functions\when('wp_create_user')->justReturn(123);
		Functions\when('is_wp_error')->justReturn(false);
		Functions\when('update_option')->justReturn(true);
		Functions\when('get_current_user_id')->justReturn(1);
		Functions\when('current_time')->justReturn('2023-01-01 12:00:00');
		Functions\when('wp_json_encode')->returnArg();
		Functions\when('filter_var')->justReturn('127.0.0.1');
		Functions\when('preg_replace')->returnArg();
		Functions\when('hash')->justReturn('abcdef123456');
		Functions\when('wp_salt')->justReturn('salt123');
		Functions\when('substr')->justReturn('abcdef123456');
		Functions\when('sanitize_text_field')->returnArg();
		Functions\when('preg_match')->justReturn(true);
		Functions\when('wp_delete_user')->justReturn(true);
		Functions\when('delete_option')->justReturn(true);
		Functions\when('remove_role')->justReturn(true);
		
		// Mock static method calls
		Functions\when('NuclearEngagement\Services\LoggingService::log')->justReturn(true);
		Functions\when('NuclearEngagement\Utils\ServerUtils::get_client_ip')->justReturn('127.0.0.1');
		Functions\when('NuclearEngagement\Utils\ServerUtils::get_user_agent')->justReturn('Test Browser');
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test class constants are defined correctly
	 */
	public function test_class_constants() {
		$this->assertEquals('nuclear_engagement_api', ApiUserManager::API_ROLE);
		$this->assertEquals('nuclear_engagement_api_service', ApiUserManager::SERVICE_ACCOUNT_USERNAME);
		$this->assertEquals('nuclear_engagement_api_user_id', ApiUserManager::SERVICE_ACCOUNT_OPTION);
	}

	/**
	 * Test init method creates role when it doesn't exist
	 */
	public function test_init_creates_role_when_not_exists() {
		Functions\expect('get_role')
			->once()
			->with(ApiUserManager::API_ROLE)
			->andReturn(null);
		
		Functions\expect('add_role')
			->once()
			->with(
				ApiUserManager::API_ROLE,
				'Nuclear Engagement API',
				\Mockery::type('array')
			);
		
		Functions\expect('get_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION)
			->andReturn(false);
		
		ApiUserManager::init();
	}

	/**
	 * Test init method doesn't create role when it exists
	 */
	public function test_init_doesnt_create_role_when_exists() {
		$role_mock = $this->createMock('WP_Role');
		
		Functions\expect('get_role')
			->once()
			->with(ApiUserManager::API_ROLE)
			->andReturn($role_mock);
		
		Functions\expect('add_role')->never();
		
		Functions\expect('get_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION)
			->andReturn(false);
		
		ApiUserManager::init();
	}

	/**
	 * Test ensure_service_account returns existing user ID
	 */
	public function test_ensure_service_account_returns_existing_user() {
		$user_mock = $this->createMock('WP_User');
		
		Functions\expect('get_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION)
			->andReturn(123);
		
		Functions\expect('get_user_by')
			->once()
			->with('id', 123)
			->andReturn($user_mock);
		
		$result = ApiUserManager::ensure_service_account();
		
		$this->assertEquals(123, $result);
	}

	/**
	 * Test ensure_service_account creates new user when none exists
	 */
	public function test_ensure_service_account_creates_new_user() {
		Functions\expect('get_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION)
			->andReturn(false);
		
		Functions\expect('home_url')->once()->andReturn('https://example.com');
		Functions\expect('parse_url')->once()->andReturn('example.com');
		Functions\expect('wp_generate_password')->once()->andReturn('test_password');
		
		Functions\expect('wp_create_user')
			->once()
			->with(
				ApiUserManager::SERVICE_ACCOUNT_USERNAME,
				'test_password',
				'api@example.com'
			)
			->andReturn(456);
		
		Functions\expect('is_wp_error')->once()->with(456)->andReturn(false);
		
		$user_mock = $this->createMock('WP_User');
		$user_mock->expects($this->once())->method('set_role')->with(ApiUserManager::API_ROLE);
		
		Functions\expect('update_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION, 456);
		
		// Mock WP_User constructor
		Functions\when('new')->alias(function($class, $id) use ($user_mock) {
			if ($class === 'WP_User' && $id === 456) {
				return $user_mock;
			}
			return new $class($id);
		});
		
		$result = ApiUserManager::ensure_service_account();
		
		$this->assertEquals(456, $result);
	}

	/**
	 * Test ensure_service_account handles WP_Error
	 */
	public function test_ensure_service_account_handles_wp_error() {
		Functions\expect('get_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION)
			->andReturn(false);
		
		$error_mock = $this->createMock('WP_Error');
		$error_mock->expects($this->once())
			->method('get_error_message')
			->willReturn('Test error message');
		
		Functions\expect('wp_create_user')->once()->andReturn($error_mock);
		Functions\expect('is_wp_error')->once()->with($error_mock)->andReturn(true);
		
		$result = ApiUserManager::ensure_service_account();
		
		$this->assertFalse($result);
	}

	/**
	 * Test get_service_account returns user object
	 */
	public function test_get_service_account_returns_user() {
		$user_mock = $this->createMock('WP_User');
		
		Functions\expect('get_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION)
			->andReturn(123);
		
		Functions\expect('get_user_by')
			->twice()
			->with('id', 123)
			->andReturn($user_mock);
		
		$result = ApiUserManager::get_service_account();
		
		$this->assertSame($user_mock, $result);
	}

	/**
	 * Test get_service_account returns false when no account
	 */
	public function test_get_service_account_returns_false_when_no_account() {
		Functions\expect('get_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION)
			->andReturn(false);
		
		Functions\expect('wp_create_user')->once()->andReturn(false);
		Functions\expect('is_wp_error')->once()->andReturn(true);
		
		$result = ApiUserManager::get_service_account();
		
		$this->assertFalse($result);
	}

	/**
	 * Test user_can_api with valid user and capability
	 */
	public function test_user_can_api_with_valid_user() {
		$user_mock = $this->createMock('WP_User');
		$user_mock->expects($this->once())
			->method('has_cap')
			->with('manage_nuclear_engagement_content')
			->willReturn(true);
		
		Functions\expect('get_user_by')
			->once()
			->with('id', 123)
			->andReturn($user_mock);
		
		$result = ApiUserManager::user_can_api(123, 'manage_nuclear_engagement_content');
		
		$this->assertTrue($result);
	}

	/**
	 * Test user_can_api with invalid user
	 */
	public function test_user_can_api_with_invalid_user() {
		Functions\expect('get_user_by')
			->once()
			->with('id', 999)
			->andReturn(false);
		
		$result = ApiUserManager::user_can_api(999, 'manage_nuclear_engagement_content');
		
		$this->assertFalse($result);
	}

	/**
	 * Test user_can_api with user lacking capability
	 */
	public function test_user_can_api_with_user_lacking_capability() {
		$user_mock = $this->createMock('WP_User');
		$user_mock->expects($this->once())
			->method('has_cap')
			->with('admin_capability')
			->willReturn(false);
		
		Functions\expect('get_user_by')
			->once()
			->with('id', 123)
			->andReturn($user_mock);
		
		$result = ApiUserManager::user_can_api(123, 'admin_capability');
		
		$this->assertFalse($result);
	}

	/**
	 * Test log_api_operation logs correctly
	 */
	public function test_log_api_operation() {
		Functions\expect('get_current_user_id')->once()->andReturn(1);
		Functions\expect('current_time')->once()->with('mysql', true)->andReturn('2023-01-01 12:00:00');
		Functions\expect('wp_json_encode')->once()->andReturn('{"operation":"test"}');
		
		// We can't easily test the LoggingService::log call due to static nature,
		// but we can verify the method executes without error
		ApiUserManager::log_api_operation('test_operation', ['key' => 'value']);
		
		// If we get here without exception, the test passes
		$this->addToAssertionCount(1);
	}

	/**
	 * Test sanitize_ip_address method via reflection
	 */
	public function test_sanitize_ip_address() {
		$reflection = new \ReflectionClass(ApiUserManager::class);
		$method = $reflection->getMethod('sanitize_ip_address');
		$method->setAccessible(true);
		
		// Test unknown IP
		$result = $method->invoke(null, 'unknown');
		$this->assertEquals('unknown', $result);
		
		// Test valid IP
		Functions\expect('preg_replace')->once()->andReturn('127.0.0.1');
		Functions\expect('filter_var')->once()->andReturn('127.0.0.1');
		Functions\expect('hash')->once()->andReturn('abcdef123456789012345678901234567890');
		Functions\expect('wp_salt')->once()->andReturn('salt');
		Functions\expect('substr')->once()->andReturn('abcdef123456');
		
		$result = $method->invoke(null, '127.0.0.1');
		$this->assertEquals('ip_abcdef123456', $result);
	}

	/**
	 * Test sanitize_user_agent method via reflection
	 */
	public function test_sanitize_user_agent() {
		$reflection = new \ReflectionClass(ApiUserManager::class);
		$method = $reflection->getMethod('sanitize_user_agent');
		$method->setAccessible(true);
		
		// Test unknown user agent
		$result = $method->invoke(null, 'unknown');
		$this->assertEquals('unknown', $result);
		
		// Test valid user agent
		Functions\expect('sanitize_text_field')->once()->andReturn('Mozilla/5.0');
		Functions\expect('substr')->once()->andReturn('Mozilla/5.0');
		Functions\expect('preg_match')->once()->andReturnUsing(function($pattern, $subject, &$matches) {
			$matches = ['Mozilla/5.0', 'Mozilla'];
			return 1;
		});
		
		$result = $method->invoke(null, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
		$this->assertEquals('Mozilla_browser', $result);
	}

	/**
	 * Test cleanup method
	 */
	public function test_cleanup() {
		Functions\expect('get_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION)
			->andReturn(123);
		
		Functions\expect('wp_delete_user')
			->once()
			->with(123);
		
		Functions\expect('delete_option')
			->once()
			->with(ApiUserManager::SERVICE_ACCOUNT_OPTION);
		
		Functions\expect('remove_role')
			->once()
			->with(ApiUserManager::API_ROLE);
		
		ApiUserManager::cleanup();
		
		// If we get here without exception, the test passes
		$this->addToAssertionCount(1);
	}

	/**
	 * Test create_api_role method creates role with correct capabilities
	 */
	public function test_create_api_role_capabilities() {
		$expected_capabilities = [
			'read' => true,
			'edit_posts' => true,
			'edit_published_posts' => true,
			'publish_posts' => true,
			'upload_files' => true,
			'manage_nuclear_engagement_content' => true
		];
		
		Functions\expect('add_role')
			->once()
			->with(
				ApiUserManager::API_ROLE,
				'Nuclear Engagement API',
				$expected_capabilities
			);
		
		// Access private method via reflection
		$reflection = new \ReflectionClass(ApiUserManager::class);
		$method = $reflection->getMethod('create_api_role');
		$method->setAccessible(true);
		
		$method->invoke(null);
	}

	/**
	 * Test that ApiUserManager has all expected public constants
	 */
	public function test_public_constants_exist() {
		$reflection = new \ReflectionClass(ApiUserManager::class);
		$constants = $reflection->getConstants();
		
		$this->assertArrayHasKey('API_ROLE', $constants);
		$this->assertArrayHasKey('SERVICE_ACCOUNT_USERNAME', $constants);
		$this->assertArrayHasKey('SERVICE_ACCOUNT_OPTION', $constants);
	}

	/**
	 * Test that all public methods exist
	 */
	public function test_public_methods_exist() {
		$expected_methods = [
			'init',
			'ensure_service_account',
			'get_service_account',
			'user_can_api',
			'log_api_operation',
			'cleanup'
		];
		
		foreach ($expected_methods as $method) {
			$this->assertTrue(method_exists(ApiUserManager::class, $method));
		}
	}
}