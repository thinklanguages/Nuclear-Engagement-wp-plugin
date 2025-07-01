<?php
declare(strict_types=1);
/**
 * File: inc/Core/Environment.php
 *
 * Environment configuration helper for Nuclear Engagement.
 *
 * @package NuclearEngagement\Core
 */

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages environment-specific configuration.
 */
class Environment {
	
	/** Environment types. */
	private const ENVIRONMENT_PRODUCTION = 'production';
	private const ENVIRONMENT_STAGING = 'staging';
	private const ENVIRONMENT_DEVELOPMENT = 'development';
	
	/**
	 * Get current environment type.
	 */
	public static function get_environment(): string {
		// Check for environment variable first
		if ( defined( 'NUCLEN_ENVIRONMENT' ) ) {
			return NUCLEN_ENVIRONMENT;
		}
		
		// Check for WordPress environment constants
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			switch ( WP_ENVIRONMENT_TYPE ) {
				case 'production':
					return self::ENVIRONMENT_PRODUCTION;
				case 'staging':
					return self::ENVIRONMENT_STAGING;
				case 'development':
				case 'local':
					return self::ENVIRONMENT_DEVELOPMENT;
			}
		}
		
		// Legacy check for debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return self::ENVIRONMENT_DEVELOPMENT;
		}
		
		// Default to production for safety
		return self::ENVIRONMENT_PRODUCTION;
	}
	
	/**
	 * Check if running in production environment.
	 */
	public static function is_production(): bool {
		return self::get_environment() === self::ENVIRONMENT_PRODUCTION;
	}
	
	/**
	 * Check if running in development environment.
	 */
	public static function is_development(): bool {
		return self::get_environment() === self::ENVIRONMENT_DEVELOPMENT;
	}
	
	/**
	 * Check if running in staging environment.
	 */
	public static function is_staging(): bool {
		return self::get_environment() === self::ENVIRONMENT_STAGING;
	}
	
	/**
	 * Get environment-specific configuration value.
	 */
	public static function get_config( string $key, $default = null ) {
		$config_map = array(
			self::ENVIRONMENT_PRODUCTION => array(
				'log_level' => 'error',
				'cache_timeout' => 3600,
				'enable_debug_logging' => false,
				'max_execution_time' => 30,
				'memory_limit' => '256M',
			),
			self::ENVIRONMENT_STAGING => array(
				'log_level' => 'warning',
				'cache_timeout' => 1800,
				'enable_debug_logging' => true,
				'max_execution_time' => 60,
				'memory_limit' => '512M',
			),
			self::ENVIRONMENT_DEVELOPMENT => array(
				'log_level' => 'debug',
				'cache_timeout' => 300,
				'enable_debug_logging' => true,
				'max_execution_time' => 120,
				'memory_limit' => '1G',
			),
		);
		
		$env = self::get_environment();
		
		if ( isset( $config_map[ $env ][ $key ] ) ) {
			return $config_map[ $env ][ $key ];
		}
		
		return $default;
	}
	
	/**
	 * Get API endpoint based on environment.
	 */
	public static function get_api_endpoint(): string {
		// Allow override via constant
		if ( defined( 'NUCLEN_API_ENDPOINT' ) ) {
			return NUCLEN_API_ENDPOINT;
		}
		
		switch ( self::get_environment() ) {
			case self::ENVIRONMENT_DEVELOPMENT:
				return 'https://dev-api.nuclearengagement.com/';
			case self::ENVIRONMENT_STAGING:
				return 'https://staging-api.nuclearengagement.com/';
			default:
				return 'https://api.nuclearengagement.com/';
		}
	}
	
	/**
	 * Get cache key prefix for environment isolation.
	 */
	public static function get_cache_prefix(): string {
		$env = self::get_environment();
		$blog_id = is_multisite() ? get_current_blog_id() : 1;
		
		return "nuclen_{$env}_{$blog_id}_";
	}
	
	/**
	 * Apply environment-specific settings.
	 */
	public static function apply_environment_settings(): void {
		$max_execution_time = self::get_config( 'max_execution_time', 30 );
		$memory_limit = self::get_config( 'memory_limit', '256M' );
		
		// Only adjust if we're in a safe environment and have permission
		if ( ! self::is_production() && function_exists( 'ini_set' ) ) {
			@ini_set( 'max_execution_time', (string) $max_execution_time );
			@ini_set( 'memory_limit', $memory_limit );
		}
	}
}