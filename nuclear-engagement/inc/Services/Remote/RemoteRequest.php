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
				$response = wp_remote_post(
					$url,
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

				// Check for WP_Error
				if ( is_wp_error( $response ) ) {
					throw ApiException::networkError( $url, $response->get_error_message() );
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
}
