<?php
/**
 * BulkGenerationTimeoutHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles timeout prevention for bulk generation operations
 */
class BulkGenerationTimeoutHandler {

	/**
	 * Set extended timeout for bulk operations
	 *
	 * @return array Original timeout values for restoration
	 */
	public static function set_extended_timeout(): array {
		$original_values = array();

		// Store original values
		if ( function_exists( 'ini_get' ) ) {
			$original_values['max_execution_time'] = ini_get( 'max_execution_time' );
			$original_values['memory_limit']       = ini_get( 'memory_limit' );
		}

		// Set extended timeout for bulk operations
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // 5 minutes
		}

		// Increase memory limit for bulk operations
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'memory_limit', '512M' );
		}

		// Disable output compression to prevent buffering issues
		if ( ! headers_sent() ) {
			@ini_set( 'zlib.output_compression', '0' );
			@ini_set( 'implicit_flush', '1' );
			@ob_implicit_flush( true );
		}

		// Add filter to extend HTTP timeout for API calls
		add_filter( 'http_request_timeout', array( __CLASS__, 'extend_http_timeout' ), 10, 2 );
		add_filter( 'http_request_args', array( __CLASS__, 'modify_http_args' ), 10, 2 );

		return $original_values;
	}

	/**
	 * Restore original timeout values
	 *
	 * @param array $original_values Original timeout values
	 */
	public static function restore_timeout( array $original_values ): void {
		if ( function_exists( 'ini_set' ) && ! empty( $original_values ) ) {
			if ( isset( $original_values['max_execution_time'] ) ) {
				@ini_set( 'max_execution_time', $original_values['max_execution_time'] );
			}
			if ( isset( $original_values['memory_limit'] ) ) {
				@ini_set( 'memory_limit', $original_values['memory_limit'] );
			}
		}

		// Remove filters
		remove_filter( 'http_request_timeout', array( __CLASS__, 'extend_http_timeout' ), 10 );
		remove_filter( 'http_request_args', array( __CLASS__, 'modify_http_args' ), 10 );
	}

	/**
	 * Extend HTTP timeout for Nuclear Engagement API calls
	 *
	 * @param int    $timeout Current timeout value
	 * @param string $url Request URL
	 * @return int Modified timeout
	 */
	public static function extend_http_timeout( $timeout, $url ) {
		if ( strpos( $url, 'nuclearengagement.com' ) !== false ) {
			return 120; // 2 minutes for Nuclear Engagement API calls
		}
		return $timeout;
	}

	/**
	 * Modify HTTP request arguments for better reliability
	 *
	 * @param array  $args HTTP request arguments
	 * @param string $url Request URL
	 * @return array Modified arguments
	 */
	public static function modify_http_args( $args, $url ) {
		if ( strpos( $url, 'nuclearengagement.com' ) !== false ) {
			// Increase timeout
			$args['timeout'] = 120;

			// Disable SSL verification in non-production environments
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$args['sslverify'] = false;
			}

			// Add retry capability
			$args['retry_count'] = 3;
			$args['retry_delay'] = 2; // seconds
		}
		return $args;
	}

	/**
	 * Send keepalive signal to prevent connection timeout
	 */
	public static function send_keepalive(): void {
		if ( ! headers_sent() && ob_get_level() > 0 ) {
			echo ' '; // Send whitespace to keep connection alive
			@ob_flush();
			@flush();
		}
	}
}
