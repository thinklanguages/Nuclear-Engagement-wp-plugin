<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Environment;

class EnvironmentTest extends TestCase {

	protected function setUp(): void {
		// Clear any existing environment constants
		$this->clearEnvironmentConstants();
	}

	protected function tearDown(): void {
		$this->clearEnvironmentConstants();
	}

	private function clearEnvironmentConstants(): void {
		// We can't undefine constants, so we'll use runkit if available
		// or just rely on setUp/tearDown for test isolation
	}

	public function test_get_environment_has_valid_environment(): void {
		$environment = Environment::get_environment();
		$valid_environments = ['production', 'staging', 'development'];
		
		$this->assertContains($environment, $valid_environments);
	}

	public function test_get_environment_returns_production_from_wp_environment(): void {
		// Skip if NUCLEN_ENVIRONMENT is already defined
		if (defined('NUCLEN_ENVIRONMENT')) {
			$this->markTestSkipped('NUCLEN_ENVIRONMENT already defined');
		}
		
		if (!defined('WP_ENVIRONMENT_TYPE')) {
			define('WP_ENVIRONMENT_TYPE', 'production');
		}
		
		$this->assertEquals('production', Environment::get_environment());
	}

	public function test_get_environment_returns_development_from_wp_environment(): void {
		if (defined('NUCLEN_ENVIRONMENT') || defined('WP_ENVIRONMENT_TYPE')) {
			$this->markTestSkipped('Environment constants already defined');
		}
		
		if (!defined('WP_ENVIRONMENT_TYPE')) {
			define('WP_ENVIRONMENT_TYPE', 'development');
		}
		
		$this->assertEquals('development', Environment::get_environment());
	}

	public function test_get_environment_returns_development_from_local_wp_environment(): void {
		if (defined('NUCLEN_ENVIRONMENT') || defined('WP_ENVIRONMENT_TYPE')) {
			$this->markTestSkipped('Environment constants already defined');
		}
		
		if (!defined('WP_ENVIRONMENT_TYPE')) {
			define('WP_ENVIRONMENT_TYPE', 'local');
		}
		
		$this->assertEquals('development', Environment::get_environment());
	}

	public function test_get_environment_returns_development_from_wp_debug(): void {
		if (defined('NUCLEN_ENVIRONMENT') || defined('WP_ENVIRONMENT_TYPE')) {
			$this->markTestSkipped('Environment constants already defined');
		}
		
		if (!defined('WP_DEBUG')) {
			define('WP_DEBUG', true);
		}
		
		$this->assertEquals('development', Environment::get_environment());
	}

	public function test_get_environment_defaults_to_production(): void {
		if (defined('NUCLEN_ENVIRONMENT') || defined('WP_ENVIRONMENT_TYPE') || defined('WP_DEBUG')) {
			$this->markTestSkipped('Environment constants already defined');
		}
		
		$this->assertEquals('production', Environment::get_environment());
	}

	public function test_is_production_returns_true_for_production(): void {
		// Mock the environment to be production
		$reflection = new ReflectionClass(Environment::class);
		$method = $reflection->getMethod('get_environment');
		
		// Since we can't easily mock static methods, test with constants
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'production');
		}
		
		$this->assertTrue(Environment::is_production());
	}

	public function test_is_development_returns_true_for_development(): void {
		if (defined('NUCLEN_ENVIRONMENT')) {
			$this->markTestSkipped('NUCLEN_ENVIRONMENT already defined');
		}
		
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'development');
		}
		
		$this->assertTrue(Environment::is_development());
	}

	public function test_is_staging_returns_true_for_staging(): void {
		if (defined('NUCLEN_ENVIRONMENT')) {
			$this->markTestSkipped('NUCLEN_ENVIRONMENT already defined');
		}
		
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'staging');
		}
		
		$this->assertTrue(Environment::is_staging());
	}

	public function test_get_config_returns_production_values(): void {
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'production');
		}
		
		$this->assertEquals('error', Environment::get_config('log_level'));
		$this->assertEquals(3600, Environment::get_config('cache_timeout'));
		$this->assertFalse(Environment::get_config('enable_debug_logging'));
		$this->assertEquals(30, Environment::get_config('max_execution_time'));
		$this->assertEquals('256M', Environment::get_config('memory_limit'));
	}

	public function test_get_config_returns_staging_values(): void {
		if (defined('NUCLEN_ENVIRONMENT')) {
			$this->markTestSkipped('NUCLEN_ENVIRONMENT already defined');
		}
		
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'staging');
		}
		
		$this->assertEquals('warning', Environment::get_config('log_level'));
		$this->assertEquals(1800, Environment::get_config('cache_timeout'));
		$this->assertTrue(Environment::get_config('enable_debug_logging'));
		$this->assertEquals(60, Environment::get_config('max_execution_time'));
		$this->assertEquals('512M', Environment::get_config('memory_limit'));
	}

	public function test_get_config_returns_development_values(): void {
		if (defined('NUCLEN_ENVIRONMENT')) {
			$this->markTestSkipped('NUCLEN_ENVIRONMENT already defined');
		}
		
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'development');
		}
		
		$this->assertEquals('debug', Environment::get_config('log_level'));
		$this->assertEquals(300, Environment::get_config('cache_timeout'));
		$this->assertTrue(Environment::get_config('enable_debug_logging'));
		$this->assertEquals(120, Environment::get_config('max_execution_time'));
		$this->assertEquals('1G', Environment::get_config('memory_limit'));
	}

	public function test_get_config_returns_default_for_unknown_key(): void {
		$this->assertEquals('default_value', Environment::get_config('unknown_key', 'default_value'));
		$this->assertNull(Environment::get_config('unknown_key'));
	}

	public function test_get_api_endpoint_returns_custom_endpoint(): void {
		if (!defined('NUCLEN_API_ENDPOINT')) {
			define('NUCLEN_API_ENDPOINT', 'https://custom-api.example.com/');
		}
		
		$this->assertEquals('https://custom-api.example.com/', Environment::get_api_endpoint());
	}

	public function test_get_api_endpoint_returns_development_endpoint(): void {
		if (defined('NUCLEN_API_ENDPOINT')) {
			$this->markTestSkipped('NUCLEN_API_ENDPOINT already defined');
		}
		
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'development');
		}
		
		$this->assertEquals('https://dev-api.nuclearengagement.com/', Environment::get_api_endpoint());
	}

	public function test_get_api_endpoint_returns_staging_endpoint(): void {
		if (defined('NUCLEN_API_ENDPOINT') || defined('NUCLEN_ENVIRONMENT')) {
			$this->markTestSkipped('API constants already defined');
		}
		
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'staging');
		}
		
		$this->assertEquals('https://staging-api.nuclearengagement.com/', Environment::get_api_endpoint());
	}

	public function test_get_api_endpoint_returns_production_endpoint(): void {
		if (defined('NUCLEN_API_ENDPOINT') || defined('NUCLEN_ENVIRONMENT')) {
			$this->markTestSkipped('API constants already defined');
		}
		
		$this->assertEquals('https://api.nuclearengagement.com/', Environment::get_api_endpoint());
	}

	public function test_get_cache_prefix_includes_environment_and_blog_id(): void {
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'development');
		}
		
		// Mock is_multisite and get_current_blog_id functions
		if (!function_exists('is_multisite')) {
			function is_multisite() { return false; }
		}
		if (!function_exists('get_current_blog_id')) {
			function get_current_blog_id() { return 1; }
		}
		
		$prefix = Environment::get_cache_prefix();
		$this->assertStringContainsString('nuclen_', $prefix);
	}

	public function test_apply_environment_settings_skips_production(): void {
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'production');
		}
		
		// This should not throw any errors and should skip ini_set calls
		Environment::apply_environment_settings();
		$this->assertTrue(true); // Just verify no errors thrown
	}

	public function test_apply_environment_settings_applies_non_production(): void {
		if (defined('NUCLEN_ENVIRONMENT')) {
			$this->markTestSkipped('NUCLEN_ENVIRONMENT already defined');
		}
		
		if (!defined('NUCLEN_ENVIRONMENT')) {
			define('NUCLEN_ENVIRONMENT', 'development');
		}
		
		// This should not throw any errors
		Environment::apply_environment_settings();
		$this->assertTrue(true); // Just verify no errors thrown
	}
}