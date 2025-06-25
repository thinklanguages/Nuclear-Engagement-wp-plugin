<?php
declare(strict_types=1);

namespace NuclearEngagement\Services\Remote;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper for sending HTTP requests to the Nuclear Engagement API.
 */
class RemoteRequest {
    /** Base API URL. */
    private const API_BASE = 'https://app.nuclearengagement.com/api';

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
            self::API_BASE . $path,
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
