<?php
/**
 * File: includes/Services/SetupService.php
 *
 * Handles API validation and credential storage during plugin setup.
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for communicating with the Nuclear Engagement setup endpoints.
 */
class SetupService {
    /**
     * Base URL for remote API.
     */
    private const API_BASE = 'https://app.nuclearengagement.com/api';

    /**
     * @var Utils
     */
    private Utils $utils;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->utils = new Utils();
    }

    /**
     * Validate the provided API key with the SaaS.
     *
     * @param string $apiKey API key to validate.
     * @return bool Whether the key is valid.
     */
    public function validate_api_key(string $apiKey): bool {
        if ($apiKey === '') {
            return false;
        }

        $response = wp_remote_post(
            self::API_BASE . '/check-api-key',
            [
                'method'  => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['appApiKey' => $apiKey]),
                'timeout' => NUCLEN_API_TIMEOUT,
                'reject_unsafe_urls' => true,
                'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
            ]
        );

        if (is_wp_error($response)) {
\NuclearEngagement\Services\LoggingService::log('API-key validation error: ' . $response->get_error_message());
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Send the generated WordPress credentials to the SaaS.
     *
     * @param array $data Credential payload.
     * @return bool Whether the request succeeded.
     */
    public function send_app_password(array $data): bool {
        if (empty($data['appApiKey'])) {
            return false;
        }

        $response = wp_remote_post(
            self::API_BASE . '/store-wp-creds',
            [
                'method'  => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode($data),
                'timeout' => NUCLEN_API_TIMEOUT,
                'reject_unsafe_urls' => true,
                'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
            ]
        );

        if (is_wp_error($response)) {
\NuclearEngagement\Services\LoggingService::log('Error sending creds: ' . $response->get_error_message());
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }
}
