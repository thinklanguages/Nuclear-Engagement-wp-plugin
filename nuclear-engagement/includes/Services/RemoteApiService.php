<?php

/**
 * File: includes/Services/RemoteApiService.php
 *
 * Remote API Service.
 *
 * @package NuclearEngagement\Services
 *
 * phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Utils;
use NuclearEngagement\Services\ApiException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for communicating with Nuclear Engagement remote API
 */
class RemoteApiService {
	/**
	 * @var string Base API URL
	 */
	private const API_BASE = 'https://app.nuclearengagement.com/api';

	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * @var Utils
	 */
	private Utils $utils;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository $settings
	 */
	public function __construct( SettingsRepository $settings ) {
		$this->settings = $settings;
		$this->utils    = new Utils();
	}

	/**
	 * Parse an error response body.
	 *
	 * @param string $body HTTP response body.
	 * @return array{message:string|null,error_code:?string}
	 */
	private function parse_error_response( string $body ): array {
		$data = json_decode( $body, true );
		$msg  = null;
		$code = null;
		if ( is_array( $data ) ) {
			if ( isset( $data['error'] ) ) {
				$msg = (string) $data['error'];
			} elseif ( isset( $data['message'] ) ) {
				$msg = (string) $data['message'];
			}
			if ( isset( $data['error_code'] ) ) {
				$code = (string) $data['error_code'];
			}
		}
		return array(
			'message'    => $msg,
			'error_code' => $code,
		);
	}

	/**
	 * Send posts to remote API for content generation
	 *
	 * @param array $data Data to send.
	 * @return array Response data on success
	 * @throws \RuntimeException On API errors
	 */
	public function send_posts_to_generate( array $data ): array {
		$api_key = $this->settings->get_string( 'api_key', '' );

		if ( empty( $api_key ) ) {
			throw new \RuntimeException( 'API key not configured' );
		}

		$payload = array(
			'generation_id' => $data['generation_id'] ?? '',
			'api_key'       => $api_key,
			'siteUrl'       => get_site_url(),
			'posts'         => array_values(
				array_filter(
					$data['posts'] ?? array(),
					function ( $p ) {
						return ! empty( $p['id'] ) && ! empty( $p['title'] ) && ! empty( $p['content'] );
					}
				)
			),
			'workflow'      => $data['workflow'] ?? array(),
		);

		\NuclearEngagement\Services\LoggingService::log( 'Sending generation request: ' . $data['generation_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$response = wp_remote_post(
			self::API_BASE . '/process-posts',
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

                if ( is_wp_error( $response ) ) {
                        $error = 'API request failed: ' . $response->get_error_message();
                        \NuclearEngagement\Services\LoggingService::log( $error ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        \NuclearEngagement\Services\LoggingService::notify_admin( __( 'Failed to contact the Nuclear Engagement API.', 'nuclear-engagement' ) );
                        throw new ApiException( $error );
                }

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		\NuclearEngagement\Services\LoggingService::debug( "API response body: {$body}" );

		\NuclearEngagement\Services\LoggingService::log( "API response code: {$code}" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Check for auth errors
		if ( 401 === $code || 403 === $code ) {
			$auth_result = $this->handle_auth_error( $body, $code );
			throw new ApiException( $auth_result['error'], $auth_result['status_code'], $auth_result['error_code'] ?? null );
		}

		if ( 200 !== $code ) {
						\NuclearEngagement\Services\LoggingService::log( "Unexpected response code: {$code}, body: {$body}" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$parsed = $this->parse_error_response( $body );
			$msg            = $parsed['message'] ?? "Failed to fetch updates, code: {$code}";
			throw new ApiException( $msg, $code, $parsed['error_code'] );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
						\NuclearEngagement\Services\LoggingService::log( "Invalid JSON response: {$body}" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			throw new ApiException( 'Invalid data received from API', $code );
		}
		if ( isset( $data['success'] ) && false === $data['success'] ) {
			$msg = $data['error'] ?? 'API error';
			throw new ApiException( $msg, $code, $data['error_code'] ?? null );
		}

		return $data;
	}

	/**
	 * Fetch generation updates from remote API
	 *
	 * @param string $generation_id Generation identifier.
	 * @return array API response data.
	 * @throws \RuntimeException On API errors
	 */
	public function fetch_updates( string $generation_id ): array {
		$api_key = $this->settings->get_string( 'api_key', '' );

		if ( empty( $api_key ) ) {
			throw new \RuntimeException( 'API key not configured' );
		}

		$payload = array(
			'siteUrl'       => get_site_url(),
			'generation_id' => $generation_id,
		);

		if ( empty( $generation_id ) ) {
			\NuclearEngagement\Services\LoggingService::log( 'Fetching credits only (no generation_id)' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			\NuclearEngagement\Services\LoggingService::log( "Fetching updates for generation: {$generation_id}" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$response = wp_remote_post(
			self::API_BASE . '/updates',
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

                if ( is_wp_error( $response ) ) {
                        $error = 'API request failed: ' . $response->get_error_message();
                                        \NuclearEngagement\Services\LoggingService::log( $error ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        \NuclearEngagement\Services\LoggingService::notify_admin( __( 'Failed to contact the Nuclear Engagement API.', 'nuclear-engagement' ) );
                        throw new ApiException( $error );
                }

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Check for auth errors
		if ( 401 === $code || 403 === $code ) {
			$auth_result = $this->handle_auth_error( $body, $code );
			throw new ApiException( $auth_result['error'], $auth_result['status_code'], $auth_result['error_code'] ?? null );
		}

		if ( 200 !== $code ) {
						\NuclearEngagement\Services\LoggingService::log( "Unexpected response code: {$code}, body: {$body}" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$parsed = $this->parse_error_response( $body );
			$msg            = $parsed['message'] ?? "Failed to fetch updates, code: {$code}";
			throw new ApiException( $msg, $code, $parsed['error_code'] );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
						\NuclearEngagement\Services\LoggingService::log( "Invalid JSON response: {$body}" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			throw new ApiException( 'Invalid data received from API', $code );
		}
		if ( isset( $data['success'] ) && false === $data['success'] ) {
			$msg = $data['error'] ?? 'API error';
			throw new ApiException( $msg, $code, $data['error_code'] ?? null );
		}

		return $data;
	}

	/**
	 * Handle authentication errors from API
	 *
	 * @param string $body Response body.
	 * @param int    $code HTTP status code.
	 * @return array Error data.
	 */
	private function handle_auth_error( string $body, int $code ): array {
		$data = json_decode( $body, true );

		if ( is_array( $data ) && isset( $data['error_code'] ) ) {
			$error_code = $data['error_code'];
			if ( 'invalid_api_key' === $error_code ) {
				return array(
					'error'       => 'Invalid API key. Please update it on the Setup page.',
					'error_code'  => 'invalid_api_key',
					'status_code' => $code,
				);
			}

			if ( 'invalid_wp_app_pass' === $error_code ) {
                               return array(
                                       'error'       => 'Invalid plugin password. Please re-generate on the Setup page.',
                                       'error_code'  => 'invalid_wp_app_pass',
                                       'status_code' => $code,
                               );
			}
		}

		if ( false !== strpos( $body, 'invalid_api_key' ) ) {
			return array(
				'error'       => 'Invalid API key. Please update it on the Setup page.',
				'error_code'  => 'invalid_api_key',
				'status_code' => $code,
			);
		}

                if ( false !== strpos( $body, 'invalid_wp_app_pass' ) ) {
                        return array(
                                'error'       => 'Invalid plugin password. Please re-generate on the Setup page.',
                                'error_code'  => 'invalid_wp_app_pass',
                                'status_code' => $code,
                        );
                }

                return array(
                        'error'       => 'Authentication error (API key or plugin password may be invalid).',
                        'status_code' => $code,
                );
	}
}
