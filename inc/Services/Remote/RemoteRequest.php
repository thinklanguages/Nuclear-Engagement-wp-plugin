<?php
declare(strict_types=1);

namespace NuclearEngagement\Services\Remote;

use NuclearEngagement\Core\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for sending HTTP requests to the Nuclear Engagement API.
 */
class RemoteRequest {
	/** Default API URL if none configured. */
	private const DEFAULT_API_BASE = 'https://app.nuclearengagement.com/api';
	
	private SettingsRepository $settings_repository;
	
	public function __construct( SettingsRepository $settings_repository ) {
		$this->settings_repository = $settings_repository;
	}
	
	/**
	 * Get the configured API base URL.
	 *
	 * @return string The API base URL.
	 */
	public function get_api_base(): string {
		$configured_url = $this->settings_repository->get( 'api_base_url' );
		if ( ! empty( $configured_url ) && filter_var( $configured_url, FILTER_VALIDATE_URL ) ) {
			return rtrim( $configured_url, '/' );
		}
		return self::DEFAULT_API_BASE;
	}

	/**
	 * Send a POST request to the given API endpoint.
	 *
	 * @param string $path API path beginning with '/'.
	 * @param array  $payload Request payload.
	 * @param string $api_key API key header.
	 * @param int    $retry_count Number of retries attempted.
	 * @return mixed Array response or WP_Error on failure.
	 */
	public function post( string $path, array $payload, string $api_key, int $retry_count = 0 ) {
		$max_retries = defined( 'NUCLEN_API_MAX_RETRIES' ) ? (int) NUCLEN_API_MAX_RETRIES : 3;
		$retry_delay = defined( 'NUCLEN_API_RETRY_DELAY' ) ? (int) NUCLEN_API_RETRY_DELAY : 1;
		
		$response = wp_remote_post(
			$this->get_api_base() . $path,
			array(
				'method'             => 'POST',
				'headers'            => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => $api_key,
				),
				'body'               => wp_json_encode( $payload ),
				'timeout'            => NUCLEN_API_TIMEOUT,
				'reject_unsafe_urls' => true,
				'user-agent'         => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
			)
		);

		// Handle timeout and connection errors with retry logic
		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			$retriable_errors = array( 'http_request_failed', 'http_request_timeout', 'connect_error' );
			
			if ( in_array( $error_code, $retriable_errors, true ) && $retry_count < $max_retries ) {
				// Log retry attempt
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'API request failed with error "%s", retrying (attempt %d of %d)',
						$response->get_error_message(),
						$retry_count + 1,
						$max_retries
					)
				);
				
				// Exponential backoff: wait longer between each retry
				sleep( $retry_delay * pow( 2, $retry_count ) );
				
				// Retry the request
				return $this->post( $path, $payload, $api_key, $retry_count + 1 );
			}
		}
		
		// Check for HTTP error codes that might be retriable
		if ( ! is_wp_error( $response ) ) {
			$response_code = wp_remote_retrieve_response_code( $response );
			$retriable_http_codes = array( 502, 503, 504 ); // Bad Gateway, Service Unavailable, Gateway Timeout
			
			if ( in_array( $response_code, $retriable_http_codes, true ) && $retry_count < $max_retries ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'API request received HTTP %d, retrying (attempt %d of %d)',
						$response_code,
						$retry_count + 1,
						$max_retries
					)
				);
				
				sleep( $retry_delay * pow( 2, $retry_count ) );
				return $this->post( $path, $payload, $api_key, $retry_count + 1 );
			}
		}
		
		return $response;
	}
}
