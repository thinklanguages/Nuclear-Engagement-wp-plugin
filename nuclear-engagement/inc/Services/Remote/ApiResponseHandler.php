<?php
declare(strict_types=1);

namespace NuclearEngagement\Services\Remote;

use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles parsing and validation of remote API responses.
 */
class ApiResponseHandler {
	/**
	 * Validate and decode the HTTP response.
	 *
	 * @param mixed $response Response from wp_remote_post.
	 * @return array Parsed response data.
	 * @throws ApiException When the response indicates an error.
	 */
	public function handle( $response ): array {
		if ( is_wp_error( $response ) ) {
			$error = 'API request failed: ' . $response->get_error_message();
			LoggingService::log( $error );
			LoggingService::notify_admin( __( 'Failed to contact the Nuclear Engagement API.', 'nuclear-engagement' ) );
			throw new ApiException( $error );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		LoggingService::debug( "API response body: {$body}" );
		LoggingService::log( "API response code: {$code}" );

		if ( 401 === $code || 403 === $code ) {
			$auth = $this->handle_auth_error( $body, $code );
			throw new ApiException( $auth['error'], $auth['status_code'], $auth['error_code'] ?? null );
		}

		if ( 200 !== $code ) {
			LoggingService::log( "Unexpected response code: {$code}, body: {$body}" );
			$parsed = $this->parse_error_response( $body );
			$msg    = $parsed['message'] ?? "Failed request, code: {$code}";
			throw new ApiException( $msg, $code, $parsed['error_code'] );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			LoggingService::log( "Invalid JSON response: {$body}" );
			throw new ApiException( 'Invalid data received from API', $code );
		}
		if ( isset( $data['success'] ) && false === $data['success'] ) {
			$msg = $data['error'] ?? 'API error';
			throw new ApiException( $msg, $code, $data['error_code'] ?? null );
		}

		return $data;
	}

	/**
	 * Parse an error response body.
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
	 * Build an error array for authentication failures.
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
