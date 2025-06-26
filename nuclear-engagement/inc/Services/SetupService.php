<?php
/**
 * File: includes/Services/SetupService.php
 *
 * Handles API validation and credential storage during plugin setup.
 *
 * @package NuclearEngagement
 * @subpackage Services
 * @since     1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement\Services;

use NuclearEngagement\Utils\Utils;
use NuclearEngagement\Services\Remote\RemoteRequest;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service for handling plugin setup and API communication.
 *
 * This class is responsible for validating API keys and managing
 * communication with the Nuclear Engagement service during setup.
 *
 * @since 1.0.0
 */
class SetupService {

    /**
     * Default timeout for API requests in seconds.
     *
     * @since 1.0.0
     * @var int
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * Utilities instance.
     *
     * @since 1.0.0
     * @var Utils
     */
    private Utils $utils;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->utils = new Utils();
    }

    /**
     * Validate the provided API key with the SaaS.
     *
     * @since 1.0.0
     *
     * @param string $api_key API key to validate.
     * @return bool Whether the key is valid.
     */
    public function validate_api_key( string $api_key ): bool {
        if ( '' === $api_key ) {
            return false;
        }

        $timeout = defined( 'NUCLEN_API_TIMEOUT' ) ? NUCLEN_API_TIMEOUT : self::DEFAULT_TIMEOUT;

        $response = wp_remote_post(
            RemoteRequest::API_BASE . '/check-api-key',
            array(
                'method'             => 'POST',
                'headers'            => array( 'Content-Type' => 'application/json' ),
                'body'               => wp_json_encode( array( 'appApiKey' => $api_key ) ),
                'timeout'            => $timeout,
                'reject_unsafe_urls' => true,
                'user-agent'         => 'NuclearEngagement/' . ( defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            LoggingService::log( 'API-key validation error: ' . $response->get_error_message() );
            LoggingService::notify_admin( 'Failed to validate API key.' );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        LoggingService::debug( 'API key validation response: ' . $body );

        if ( 200 !== $code ) {
            LoggingService::log( 'Unexpected API-key validation response code: ' . $code . ', body: ' . $body );
            LoggingService::notify_admin( 'Failed to validate API key.' );
            return false;
        }

        return true;
    }

    /**
     * Send the generated WordPress credentials to the SaaS.
     *
     * @since 1.0.0
     *
     * @param array $data Credential payload containing appApiKey and other credentials.
     * @return bool True on success, false on failure.
     */
    public function send_app_password( array $data ): bool {
        if ( empty( $data['appApiKey'] ) ) {
            return false;
        }

        $response = wp_remote_post(
            RemoteRequest::API_BASE . '/store-wp-creds',
            array(
                'method'             => 'POST',
                'headers'            => array( 'Content-Type' => 'application/json' ),
                'body'               => wp_json_encode( $data ),
                'timeout'            => NUCLEN_API_TIMEOUT,
                'reject_unsafe_urls' => true,
                'user-agent'         => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
            )
        );

        if ( is_wp_error( $response ) ) {
                \NuclearEngagement\Services\LoggingService::log( 'Error sending creds: ' . $response->get_error_message() );
                \NuclearEngagement\Services\LoggingService::notify_admin( 'Failed to send WordPress credentials.' );
                return false;
        }

                $code = wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );
                \NuclearEngagement\Services\LoggingService::debug( 'Send creds response: ' . $body );

        if ( 200 !== $code ) {
                \NuclearEngagement\Services\LoggingService::log( 'Unexpected creds response code: ' . $code . ', body: ' . $body );
                \NuclearEngagement\Services\LoggingService::notify_admin( 'Failed to send WordPress credentials.' );
                return false;
        }

                return true;
    }
}
