<?php
/**
 * RemoteRequest.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_Remote
 */

declare(strict_types=1);

namespace NuclearEngagement\Services\Remote;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\ApiRetryHandler;
use NuclearEngagement\Exceptions\ApiException;

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

	private ApiRetryHandler $retry_handler;
	
	/** @var int Current request timeout for progressive increases */
	private int $current_request_timeout = 30;

	public function __construct( SettingsRepository $settings_repository ) {
		$this->settings_repository = $settings_repository;
		$this->retry_handler       = new ApiRetryHandler( 3, array( 1000, 2000, 4000 ) );
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
	 * @param int    $retry_attempt Current retry attempt (used internally).
	 * @return mixed Array response or WP_Error on failure.
	 * @throws ApiException On API errors after retries
	 */
	public function post( string $path, array $payload, string $api_key, int $retry_attempt = 0 ) {
		$url = $this->get_api_base() . $path;

		return $this->retry_handler->execute(
			function ( $attempt ) use ( $url, $payload, $api_key ) {
				// Calculate progressive timeout based on retry attempt
				// Base timeout: 30s, then 45s, 60s, 90s
				$base_timeout = defined( 'NUCLEN_API_TIMEOUT' ) ? NUCLEN_API_TIMEOUT : 30;
				$timeout_multipliers = array( 1.0, 1.5, 2.0, 3.0 );
				$multiplier = isset( $timeout_multipliers[ $attempt ] ) ? $timeout_multipliers[ $attempt ] : 3.0;
				$current_timeout = intval( $base_timeout * $multiplier );
				
				// Cap maximum timeout at 120 seconds
				$current_timeout = min( $current_timeout, 120 );
				
				// Set both connection and execution timeouts
				$args = array(
					'method'             => 'POST',
					'headers'            => array(
						'Content-Type' => 'application/json',
						'X-API-Key'    => $api_key,
					),
					'body'               => wp_json_encode( $payload ),
					'timeout'            => $current_timeout, // Progressive timeout
					'reject_unsafe_urls' => true,
					'user-agent'         => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
					'httpversion'        => '1.1',
					'blocking'           => true,
					'sslverify'          => true,
				);
				
				// Store current timeout for filter
				$this->current_request_timeout = $current_timeout;

				// Add HTTP request args filter to set connection timeout
				add_filter( 'http_request_args', array( $this, 'set_connection_timeout' ), 10, 2 );

				// Log the request for debugging
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( 
						'[RemoteRequest] Sending POST to %s with timeout %ds (attempt %d)', 
						$url, 
						$current_timeout,
						$attempt + 1
					)
				);

				try {
					$response = wp_remote_post( $url, $args );
				} finally {
					// Always remove the filter
					remove_filter( 'http_request_args', array( $this, 'set_connection_timeout' ), 10 );
				}

				// Check for WP_Error
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();

					// Log timeout errors specifically
					if ( strpos( $error_message, 'cURL error 28' ) !== false ||
						strpos( $error_message, 'Operation timed out' ) !== false ) {
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( 
								'[RemoteRequest] Request timed out to %s after %ds: %s', 
								$url, 
								$current_timeout,
								$error_message 
							),
							'error'
						);
					}

					throw ApiException::networkError( $url, $error_message );
				}

				// Check HTTP status code
				$status_code = wp_remote_retrieve_response_code( $response );
				if ( $status_code >= 400 ) {
					$body          = wp_remote_retrieve_body( $response );
					$response_data = json_decode( $body, true ) ?: array();

					throw ApiException::httpError( $url, $status_code, $response_data );
				}

				return $response;
			},
			'nuclear_engagement_api',
			array(
				'path'   => $path,
				'method' => 'POST',
			)
		);
	}

	/**
	 * Set connection timeout for HTTP requests
	 *
	 * @param array  $args HTTP request arguments.
	 * @param string $url  The request URL.
	 * @return array Modified arguments.
	 */
	public function set_connection_timeout( array $args, string $url ): array {
		// Only apply to our API requests
		if ( strpos( $url, $this->get_api_base() ) === 0 ) {
			// Use the current request timeout which increases progressively
			if ( ! isset( $args['timeout'] ) || $args['timeout'] < $this->current_request_timeout ) {
				$args['timeout'] = $this->current_request_timeout;
			}

			// Add curl options for better timeout control
			if ( ! isset( $args['curl'] ) ) {
				$args['curl'] = array();
			}

			// CURLOPT_CONNECTTIMEOUT - timeout for connection phase
			// Scale connection timeout based on total timeout (25% of total, min 10s, max 30s)
			$connection_timeout = max( 10, min( 30, intval( $this->current_request_timeout * 0.25 ) ) );
			$args['curl'][ CURLOPT_CONNECTTIMEOUT ] = $connection_timeout;

			// CURLOPT_TIMEOUT - total timeout including data transfer
			$args['curl'][ CURLOPT_TIMEOUT ] = $this->current_request_timeout;

			// CURLOPT_NOSIGNAL - required for timeout to work properly in multi-threaded environments
			$args['curl'][ CURLOPT_NOSIGNAL ] = 1;
			
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 
					'[RemoteRequest] Curl timeouts set - Connection: %ds, Total: %ds', 
					$connection_timeout,
					$this->current_request_timeout
				)
			);
		}

		return $args;
	}
}
