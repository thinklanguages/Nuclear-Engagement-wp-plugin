<?php
declare(strict_types=1);
namespace NuclearEngagement\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RateLimiter {
	
	/** Default rate limits per action */
	private const DEFAULT_LIMITS = [
		'api_request'    => [ 'requests' => 100, 'window' => 3600 ], // 100 per hour
		'login_attempt'  => [ 'requests' => 5, 'window' => 900 ],   // 5 per 15 minutes
		'form_submit'    => [ 'requests' => 20, 'window' => 3600 ], // 20 per hour
	];
	
	/**
	 * Check if an action is rate limited for a given identifier
	 *
	 * @param string $action    The action being performed.
	 * @param string $identifier Unique identifier (IP hash, user ID, etc.).
	 * @param array  $custom_limit Custom limit override.
	 * @return bool True if rate limited, false if allowed.
	 */
	public static function is_rate_limited( string $action, string $identifier, array $custom_limit = [] ): bool {
		$limit = $custom_limit ?: ( self::DEFAULT_LIMITS[ $action ] ?? self::DEFAULT_LIMITS['api_request'] );
		
		$key = self::get_cache_key( $action, $identifier );
		$current_count = (int) get_transient( $key );
		
		if ( $current_count >= $limit['requests'] ) {
			return true;
		}
		
		// Increment counter
		self::increment_counter( $key, $limit['window'] );
		
		return false;
	}
	
	/**
	 * Record a rate limit violation
	 *
	 * @param string $action     The action that was rate limited.
	 * @param string $identifier The identifier that was rate limited.
	 * @return void
	 */
	public static function record_violation( string $action, string $identifier ): void {
		$violation_key = 'rate_limit_violation_' . $action . '_' . $identifier;
		$violations = (int) get_transient( $violation_key );
		set_transient( $violation_key, $violations + 1, DAY_IN_SECONDS );
		
		// Log security event
		error_log( sprintf(
			'[Nuclear Engagement] Rate limit exceeded for action: %s, identifier: %s',
			$action,
			$identifier
		) );
		
		// Consider temporary blocks for repeat violators
		if ( $violations > 10 ) {
			self::apply_temporary_block( $identifier );
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
	 * Apply temporary block for repeat violators
	 *
	 * @param string $identifier The identifier to block.
	 * @return void
	 */
	private static function apply_temporary_block( string $identifier ): void {
		$block_key = 'temp_block_' . $identifier;
		set_transient( $block_key, true, HOUR_IN_SECONDS * 24 ); // 24 hour block
		
		error_log( sprintf(
			'[Nuclear Engagement] Applied temporary 24h block for identifier: %s',
			$identifier
		) );
	}
	
	/**
	 * Check if an identifier is temporarily blocked
	 *
	 * @param string $identifier The identifier to check.
	 * @return bool True if blocked.
	 */
	public static function is_temporarily_blocked( string $identifier ): bool {
		return (bool) get_transient( 'temp_block_' . $identifier );
	}
}