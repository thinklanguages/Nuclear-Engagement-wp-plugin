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
	 * @return mixed Array response or WP_Error on failure.
	 * @throws ApiException On API errors after retries
	 */
	public function post( string $path, array $payload, string $api_key ) {
		$url = $this->get_api_base() . $path;

		return $this->retry_handler->execute(
			function () use ( $url, $payload, $api_key ) {
				// Set both connection and execution timeouts
				$args = array(
					'method'             => 'POST',
					'headers'            => array(
						'Content-Type' => 'application/json',
						'X-API-Key'    => $api_key,
					),
					'body'               => wp_json_encode( $payload ),
					'timeout'            => NUCLEN_API_TIMEOUT, // Total timeout
					'reject_unsafe_urls' => true,
					'user-agent'         => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
					'httpversion'        => '1.1',
					'blocking'           => true,
					'sslverify'          => true,
				);

				// Add HTTP request args filter to set connection timeout
				add_filter( 'http_request_args', array( $this, 'set_connection_timeout' ), 10, 2 );

				// Log the request for debugging
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[RemoteRequest] Sending POST to %s with timeout %ds', $url, NUCLEN_API_TIMEOUT )
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
							sprintf( '[RemoteRequest] Request timed out to %s: %s', $url, $error_message ),
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
			// Set connection timeout to 10 seconds (WordPress default is 5)
			// This gives time for DNS resolution and connection establishment
			if ( ! isset( $args['timeout'] ) ) {
				$args['timeout'] = 30;
			}

			// Add curl options for better timeout control
			if ( ! isset( $args['curl'] ) ) {
				$args['curl'] = array();
			}

			// CURLOPT_CONNECTTIMEOUT - timeout for connection phase
			$args['curl'][ CURLOPT_CONNECTTIMEOUT ] = 10;

			// CURLOPT_TIMEOUT - total timeout including data transfer
			$args['curl'][ CURLOPT_TIMEOUT ] = intval( $args['timeout'] );

			// CURLOPT_NOSIGNAL - required for timeout to work properly in multi-threaded environments
			$args['curl'][ CURLOPT_NOSIGNAL ] = 1;
		}

		return $args;
	}
}
