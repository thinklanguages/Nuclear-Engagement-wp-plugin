<?php
/**
 * RemoteRequest.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_Remote
 */

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
	 * @return mixed Array response or WP_Error on failure.
	 */
	public function post( string $path, array $payload, string $api_key ) {
		return wp_remote_post(
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
	}
}
