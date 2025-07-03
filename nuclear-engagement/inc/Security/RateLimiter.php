<?php
declare(strict_types=1);

namespace NuclearEngagement\Security;

use NuclearEngagement\Utils\ServerUtils;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secure rate limiting implementation.
 *
 * This class provides consistent rate limiting across the plugin
 * with proper security measures and monitoring.
 */
final class RateLimiter {
	
	/** Default rate limits per action */
	private const DEFAULT_LIMITS = [
		'api_request'    => [ 'requests' => 60,  'window' => 3600 ], // 60 per hour
		'login_attempt'  => [ 'requests' => 5,   'window' => 900 ],  // 5 per 15 minutes
		'form_submit'    => [ 'requests' => 30,  'window' => 3600 ], // 30 per hour
		'file_upload'    => [ 'requests' => 10,  'window' => 3600 ], // 10 per hour
		'search_query'   => [ 'requests' => 100, 'window' => 3600 ], // 100 per hour
		'password_reset' => [ 'requests' => 3,   'window' => 1800 ], // 3 per 30 minutes
	];

	/** Maximum violations before temporary block */
	private const MAX_VIOLATIONS = 5;

	/** Block duration in seconds */
	private const BLOCK_DURATION = 3600; // 1 hour
	
	/**
	 * Check if an action is rate limited for a given identifier.
	 *
	 * @param string $action       The action being performed.
	 * @param string $identifier   Unique identifier (IP hash, user ID, etc.).
	 * @param array  $custom_limit Custom limit override.
	 * @return bool True if rate limited, false if allowed.
	 */
	public static function is_rate_limited( string $action, string $identifier, array $custom_limit = [] ): bool {
		// Check for temporary blocks first
		if ( self::is_temporarily_blocked( $identifier ) ) {
			return true;
		}

		// Validate inputs
		if ( empty( $action ) || empty( $identifier ) ) {
			return true; // Block invalid requests
		}

		$limit = $custom_limit ?: ( self::DEFAULT_LIMITS[ $action ] ?? self::DEFAULT_LIMITS['api_request'] );
		
		// Validate limit structure
		if ( ! isset( $limit['requests'] ) || ! isset( $limit['window'] ) ) {
			return true; // Block if invalid limit configuration
		}

		$key = self::get_cache_key( $action, $identifier );
		$current_count = (int) get_transient( $key );
		
		if ( $current_count >= $limit['requests'] ) {
			// Log rate limit hit
			self::log_rate_limit_event( $action, $identifier, $current_count, $limit['requests'] );
			return true;
		}
		
		// Increment counter for next check
		self::increment_counter( $key, $limit['window'] );
		
		return false;
	}
	
	/**
	 * Record a rate limit violation.
	 *
	 * @param string $action     The action that was rate limited.
	 * @param string $identifier The identifier that was rate limited.
	 * @return void
	 */
	public static function record_violation( string $action, string $identifier ): void {
		$violation_key = self::get_violation_key( $action, $identifier );
		$violations = (int) get_transient( $violation_key );
		$new_violation_count = $violations + 1;
		
		// Store violation with 24-hour expiry
		set_transient( $violation_key, $new_violation_count, DAY_IN_SECONDS );
		
		// Log security event with context
		$log_data = [
			'action' => $action,
			'identifier' => $identifier,
			'violation_count' => $new_violation_count,
			'client_ip' => ServerUtils::get_client_ip(),
			'user_agent' => ServerUtils::get_user_agent(),
			'timestamp' => time(),
		];
		
		if ( class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
			LoggingService::log( 'Rate limit violation: ' . wp_json_encode( $log_data ) );
		} else {
			error_log( 'Nuclear Engagement Rate Limit: ' . wp_json_encode( $log_data ) );
		}
		
		// Apply temporary block for repeat violators
		if ( $new_violation_count >= self::MAX_VIOLATIONS ) {
			self::apply_temporary_block( $identifier, $action );
		}
	}
	
	/**
	 * Get remaining requests for an action
	 *
	 * @param string $action     The action to check.
	 * @param string $identifier The identifier to check.
	 * @param array  $custom_limit Custom limit override.
	 * @return int Number of remaining requests.
	 */
	public static function get_remaining_requests( string $action, string $identifier, array $custom_limit = [] ): int {
		$limit = $custom_limit ?: ( self::DEFAULT_LIMITS[ $action ] ?? self::DEFAULT_LIMITS['api_request'] );
		
		$key = self::get_cache_key( $action, $identifier );
		$current_count = (int) get_transient( $key );
		
		return max( 0, $limit['requests'] - $current_count );
	}
	
	/**
	 * Reset rate limit for a specific action and identifier
	 *
	 * @param string $action     The action to reset.
	 * @param string $identifier The identifier to reset.
	 * @return void
	 */
	public static function reset_limit( string $action, string $identifier ): void {
		$key = self::get_cache_key( $action, $identifier );
		delete_transient( $key );
	}
	
	/**
	 * Get a safe identifier from IP address
	 *
	 * @param string $ip_address The IP address.
	 * @return string Hashed identifier.
	 */
	public static function get_ip_identifier( string $ip_address ): string {
		return 'ip_' . substr( hash_hmac( 'sha256', $ip_address, wp_salt() ), 0, 16 );
	}
	
	/**
	 * Generate cache key for rate limiting
	 *
	 * @param string $action     The action.
	 * @param string $identifier The identifier.
	 * @return string Cache key.
	 */
	private static function get_cache_key( string $action, string $identifier ): string {
		return 'rate_limit_' . $action . '_' . $identifier;
	}
	
	/**
	 * Increment the counter for an action
	 *
	 * @param string $key    Cache key.
	 * @param int    $window Time window in seconds.
	 * @return void
	 */
	private static function increment_counter( string $key, int $window ): void {
		$current = (int) get_transient( $key );
		set_transient( $key, $current + 1, $window );
	}
	
	/**
	 * Apply temporary block for repeat violators.
	 *
	 * @param string $identifier The identifier to block.
	 * @param string $action     The action that triggered the block.
	 * @return void
	 */
	private static function apply_temporary_block( string $identifier, string $action = '' ): void {
		$block_key = self::get_block_key( $identifier );
		$block_data = [
			'blocked_at' => time(),
			'trigger_action' => $action,
			'expires_at' => time() + self::BLOCK_DURATION,
		];
		
		set_transient( $block_key, $block_data, self::BLOCK_DURATION );
		
		// Log block application
		$log_data = [
			'identifier' => $identifier,
			'trigger_action' => $action,
			'block_duration' => self::BLOCK_DURATION,
			'client_ip' => ServerUtils::get_client_ip(),
			'timestamp' => time(),
		];
		
		if ( class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
			LoggingService::log( 'Temporary block applied: ' . wp_json_encode( $log_data ) );
		} else {
			error_log( 'Nuclear Engagement Block Applied: ' . wp_json_encode( $log_data ) );
		}
		
		// Trigger action for security monitoring
		do_action( 'nuclen_rate_limit_block_applied', $identifier, $action );
	}

	/**
	 * Log rate limit event.
	 *
	 * @param string $action        The action being rate limited.
	 * @param string $identifier    The identifier.
	 * @param int    $current_count Current request count.
	 * @param int    $limit         Request limit.
	 * @return void
	 */
	private static function log_rate_limit_event( string $action, string $identifier, int $current_count, int $limit ): void {
		$log_data = [
			'event' => 'rate_limit_exceeded',
			'action' => $action,
			'identifier' => $identifier,
			'current_count' => $current_count,
			'limit' => $limit,
			'client_ip' => ServerUtils::get_client_ip(),
			'timestamp' => time(),
		];
		
		if ( class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
			LoggingService::log( 'Rate limit exceeded: ' . wp_json_encode( $log_data ) );
		}
	}

	/**
	 * Get violation key for an action and identifier.
	 *
	 * @param string $action     The action.
	 * @param string $identifier The identifier.
	 * @return string Violation cache key.
	 */
	private static function get_violation_key( string $action, string $identifier ): string {
		return 'nuclen_violations_' . hash( 'md5', $action . '_' . $identifier );
	}

	/**
	 * Get block key for an identifier.
	 *
	 * @param string $identifier The identifier.
	 * @return string Block cache key.
	 */
	private static function get_block_key( string $identifier ): string {
		return 'nuclen_block_' . hash( 'md5', $identifier );
	}
	
	/**
	 * Check if an identifier is temporarily blocked.
	 *
	 * @param string $identifier The identifier to check.
	 * @return bool True if blocked.
	 */
	public static function is_temporarily_blocked( string $identifier ): bool {
		$block_key = self::get_block_key( $identifier );
		$block_data = get_transient( $block_key );
		
		return $block_data !== false;
	}

	/**
	 * Get block information for an identifier.
	 *
	 * @param string $identifier The identifier to check.
	 * @return array|null Block information or null if not blocked.
	 */
	public static function get_block_info( string $identifier ): ?array {
		$block_key = self::get_block_key( $identifier );
		$block_data = get_transient( $block_key );
		
		return $block_data !== false ? $block_data : null;
	}

	/**
	 * Manually remove a block for an identifier.
	 *
	 * @param string $identifier The identifier to unblock.
	 * @return bool True if block was removed.
	 */
	public static function remove_block( string $identifier ): bool {
		$block_key = self::get_block_key( $identifier );
		return delete_transient( $block_key );
	}

	/**
	 * Get rate limit statistics for monitoring.
	 *
	 * @return array Rate limit statistics.
	 */
	public static function get_statistics(): array {
		global $wpdb;
		
		// Get count of active rate limits
		$rate_limit_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_rate_limit_%'
			)
		);
		
		// Get count of active blocks
		$block_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_nuclen_block_%'
			)
		);
		
		// Get count of violations
		$violation_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_nuclen_violations_%'
			)
		);
		
		return [
			'active_rate_limits' => (int) $rate_limit_count,
			'active_blocks' => (int) $block_count,
			'tracked_violations' => (int) $violation_count,
			'default_limits' => self::DEFAULT_LIMITS,
			'max_violations' => self::MAX_VIOLATIONS,
			'block_duration' => self::BLOCK_DURATION,
		];
	}

	/**
	 * Clean up expired rate limit data (for maintenance).
	 *
	 * @return int Number of items cleaned up.
	 */
	public static function cleanup_expired(): int {
		global $wpdb;
		
		$cleanup_count = 0;
		
		// Clean expired transients related to rate limiting
		$prefixes = [
			'_transient_rate_limit_',
			'_transient_nuclen_block_',
			'_transient_nuclen_violations_',
		];
		
		foreach ( $prefixes as $prefix ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
					$prefix . '%',
					time()
				)
			);
			
			$cleanup_count += $deleted ?: 0;
		}
		
		return $cleanup_count;
	}
}