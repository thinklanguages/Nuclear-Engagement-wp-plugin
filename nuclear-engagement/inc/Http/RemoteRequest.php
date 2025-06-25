<?php
declare(strict_types=1);
/**
 * File: includes/Http/RemoteRequest.php
 *
 * Helper for sending remote API requests and handling errors.
 */

namespace NuclearEngagement\Http;

use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RemoteRequest {
    /**
     * Send a POST request with JSON body and handle common errors.
     *
     * @param string $url     Full endpoint URL.
     * @param array  $payload Request data.
     * @param string $api_key API key header value.
     * @return array Parsed JSON response.
     * @throws ApiException On request failure or unexpected response.
     */
    public function post( string $url, array $payload, string $api_key ): array {
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

        if ( is_wp_error( $response ) ) {
            $error = 'API request failed: ' . $response->get_error_message();
            LoggingService::log( $error );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            LoggingService::notify_admin( __( 'Failed to contact the Nuclear Engagement API.', 'nuclear-engagement' ) );
            throw new ApiException( $error );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        LoggingService::debug( "API response body: {$body}" );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        LoggingService::log( "API response code: {$code}" );

        if ( 401 === $code || 403 === $code ) {
            $auth = $this->handle_auth_error( $body, $code );
            throw new ApiException( $auth['error'], $auth['status_code'], $auth['error_code'] ?? null );
        }

        if ( 200 !== $code ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            LoggingService::log( "Unexpected response code: {$code}, body: {$body}" );
            $parsed = $this->parse_error_response( $body );
            $msg    = $parsed['message'] ?? "Failed to fetch updates, code: {$code}";
            throw new ApiException( $msg, $code, $parsed['error_code'] );
        }

        $data = json_decode( $body, true );
        if ( ! is_array( $data ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
     * Handle authentication errors from API.
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
