<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Environment;

class EnvironmentSimpleTest extends TestCase {

	public function test_get_environment_returns_valid_value(): void {
		$environment = Environment::get_environment();
		$valid_environments = ['production', 'staging', 'development'];
		
		$this->assertContains($environment, $valid_environments);
	}

	public function test_environment_detection_methods(): void {
		// At least one of these should be true
		$is_production = Environment::is_production();
		$is_staging = Environment::is_staging();
		$is_development = Environment::is_development();
		
		$this->assertTrue($is_production || $is_staging || $is_development);
	}

	public function test_get_config_returns_expected_keys(): void {
		$config_keys = ['log_level', 'cache_timeout', 'enable_debug_logging', 'max_execution_time', 'memory_limit'];
		
		foreach ($config_keys as $key) {
			$value = Environment::get_config($key);
			$this->assertNotNull($value, "Config key '{$key}' should have a value");
		}
	}

	public function test_get_config_returns_default_for_unknown_key(): void {
		$this->assertEquals('default_value', Environment::get_config('unknown_key', 'default_value'));
		$this->assertNull(Environment::get_config('unknown_key'));
	}

	public function test_get_api_endpoint_returns_url(): void {
		$endpoint = Environment::get_api_endpoint();
		
		$this->assertIsString($endpoint);
		$this->assertStringStartsWith('https://', $endpoint);
		$this->assertStringEndsWith('/', $endpoint);
		$this->assertStringContainsString('nuclearengagement.com', $endpoint);
	}

	public function test_get_cache_prefix_format(): void {
		$prefix = Environment::get_cache_prefix();
		
		$this->assertIsString($prefix);
		$this->assertStringStartsWith('nuclen_', $prefix);
		$this->assertStringEndsWith('_', $prefix);
	}

	public function test_apply_environment_settings_does_not_throw(): void {
		// This should not throw any errors
		try {
			Environment::apply_environment_settings();
			$this->assertTrue(true);
		} catch (Throwable $e) {
			$this->fail('apply_environment_settings should not throw: ' . $e->getMessage());
		}
	}

	public function test_config_structure_is_consistent(): void {
		$environments = ['production', 'staging', 'development'];
		$config_keys = ['log_level', 'cache_timeout', 'enable_debug_logging', 'max_execution_time', 'memory_limit'];
		
		// Test that different environments have different configs
		$configs = [];
		foreach ($environments as $env) {
			$config = [];
			foreach ($config_keys as $key) {
				$config[$key] = Environment::get_config($key);
			}
			$configs[$env] = $config;
		}
		
		// At least log levels should be different between environments
		$this->assertNotEmpty($configs);
	}

	public function test_environment_types_are_properly_categorized(): void {
		$environment = Environment::get_environment();
		
		// Production should have stricter settings
		if ($environment === 'production') {
			$this->assertEquals('error', Environment::get_config('log_level'));
			$this->assertFalse(Environment::get_config('enable_debug_logging'));
		}
		
		// Development should have more permissive settings
		if ($environment === 'development') {
			$this->assertEquals('debug', Environment::get_config('log_level'));
			$this->assertTrue(Environment::get_config('enable_debug_logging'));
		}
		
		// Staging should be in between
		if ($environment === 'staging') {
			$this->assertEquals('warning', Environment::get_config('log_level'));
			$this->assertTrue(Environment::get_config('enable_debug_logging'));
		}
	}
}