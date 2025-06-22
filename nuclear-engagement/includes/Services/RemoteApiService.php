<?php
declare(strict_types=1);
/**
 * File: includes/Services/RemoteApiService.php

 * Remote API Service
 *
 * @package NuclearEngagement\Services
 */

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
    public function __construct(SettingsRepository $settings) {
        $this->settings = $settings;
        $this->utils = new Utils();
    }

    /**
     * Parse an error response body.
     *
     * @param string $body
     * @return array{message:string|null,error_code:?string}
     */
    private function parseErrorResponse(string $body): array {
        $data = json_decode($body, true);
        $msg  = null;
        $code = null;
        if (is_array($data)) {
            if (isset($data['error'])) {
                $msg = (string) $data['error'];
            } elseif (isset($data['message'])) {
                $msg = (string) $data['message'];
            }
            if (isset($data['error_code'])) {
                $code = (string) $data['error_code'];
            }
        }
        return ['message' => $msg, 'error_code' => $code];
    }

    /**
     * Send posts to remote API for content generation
     *
     * @param array $data
     * @return array Response data on success
     * @throws \RuntimeException On API errors
     */
    public function sendPostsToGenerate(array $data): array {
        $apiKey = $this->settings->get_string('api_key', '');

        if (empty($apiKey)) {
            throw new \RuntimeException('API key not configured');
        }

        $payload = [
            'generation_id' => $data['generation_id'] ?? '',
            'api_key' => $apiKey,
            'siteUrl' => get_site_url(),
            'posts' => array_values(
                array_filter(
                    $data['posts'] ?? [],
                    function ($p) {
                        return !empty($p['id']) && !empty($p['title']) && !empty($p['content']);
                    }
                )
            ),
            'workflow' => $data['workflow'] ?? [],
        ];

\NuclearEngagement\Services\LoggingService::log('Sending generation request: ' . $data['generation_id']);

        $response = wp_remote_post(
            self::API_BASE . '/process-posts',
            [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $apiKey,
                ],
                'body' => wp_json_encode($payload),
                'timeout' => NUCLEN_API_TIMEOUT,
                'reject_unsafe_urls' => true,
                'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
            ]
        );

        if (is_wp_error($response)) {
            $error = 'API request failed: ' . $response->get_error_message();
\NuclearEngagement\Services\LoggingService::log($error);
            throw new ApiException($error);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 0) {
            \NuclearEngagement\Services\LoggingService::log('Network error: status code 0');
            throw new ApiException('Network error when calling API', 0);
        }

\NuclearEngagement\Services\LoggingService::log("API response code: {$code}");

        // Check for auth errors
        if ($code === 401 || $code === 403) {
            $authResult = $this->handleAuthError($body, $code);
            throw new ApiException($authResult['error'], $authResult['status_code'], $authResult['error_code'] ?? null);
        }

        if ($code !== 200) {
\NuclearEngagement\Services\LoggingService::log("Unexpected response code: {$code}, body: {$body}");
            $parsed = $this->parseErrorResponse($body);
            $msg = $parsed['message'] ?? "Failed to fetch updates, code: {$code}";
            throw new ApiException($msg, $code, $parsed['error_code']);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            \NuclearEngagement\Services\LoggingService::log("Invalid JSON response: {$body}");
            throw new ApiException('Invalid data received from API', $code);
        }

        if (isset($data['success']) && $data['success'] === false) {
            $msg = $data['message'] ?? 'API reported failure';
            throw new ApiException($msg, $code, $data['error_code'] ?? null);
        }

        return $data;
    }

    /**
     * Fetch generation updates from remote API
     *
     * @param string $generationId
     * @return array
     * @throws \RuntimeException On API errors
     */
    public function fetchUpdates(string $generationId): array {
        $apiKey = $this->settings->get_string('api_key', '');

        if (empty($apiKey)) {
            throw new \RuntimeException('API key not configured');
        }

        $payload = [
            'siteUrl' => get_site_url(),
            'generation_id' => $generationId,
        ];

        if (empty($generationId)) {
\NuclearEngagement\Services\LoggingService::log('Fetching credits only (no generation_id)');
        } else {
\NuclearEngagement\Services\LoggingService::log("Fetching updates for generation: {$generationId}");
        }

        $response = wp_remote_post(
            self::API_BASE . '/updates',
            [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $apiKey,
                ],
                'body' => wp_json_encode($payload),
                'timeout' => NUCLEN_API_TIMEOUT,
                'reject_unsafe_urls' => true,
                'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
            ]
        );

        if (is_wp_error($response)) {
            $error = 'API request failed: ' . $response->get_error_message();
\NuclearEngagement\Services\LoggingService::log($error);
            throw new ApiException($error);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Check for auth errors
        if ($code === 401 || $code === 403) {
            $authResult = $this->handleAuthError($body, $code);
            throw new ApiException($authResult['error'], $authResult['status_code'], $authResult['error_code'] ?? null);
        }

        if ($code !== 200) {
\NuclearEngagement\Services\LoggingService::log("Unexpected response code: {$code}, body: {$body}");
            $parsed = $this->parseErrorResponse($body);
            $msg = $parsed['message'] ?? "Failed to fetch updates, code: {$code}";
            throw new ApiException($msg, $code, $parsed['error_code']);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
\NuclearEngagement\Services\LoggingService::log("Invalid JSON response: {$body}");
            throw new ApiException('Invalid data received from API', $code);
        }

        return $data;
    }

    /**
     * Handle authentication errors from API
     *
     * @param string $body Response body
     * @param int $code HTTP status code
     * @return array Error data
     */
    private function handleAuthError(string $body, int $code): array {
        $data = json_decode($body, true);

        if (is_array($data) && isset($data['error_code'])) {
            $errorCode = $data['error_code'];
            if ($errorCode === 'invalid_api_key') {
                return [
                    'error' => 'Invalid API key. Please update it on the Setup page.',
                    'error_code' => 'invalid_api_key',
                    'status_code' => $code,
                ];
            }

            if ($errorCode === 'invalid_wp_app_pass') {
                return [
                    'error' => 'Invalid WP App Password. Please re-generate on the Setup page.',
                    'error_code' => 'invalid_wp_app_pass',
                    'status_code' => $code,
                ];
            }
        }

        if (strpos($body, 'invalid_api_key') !== false) {
            return [
                'error' => 'Invalid API key. Please update it on the Setup page.',
                'error_code' => 'invalid_api_key',
                'status_code' => $code,
            ];
        }

        if (strpos($body, 'invalid_wp_app_pass') !== false) {
            return [
                'error' => 'Invalid WP App Password. Please re-generate on the Setup page.',
                'error_code' => 'invalid_wp_app_pass',
                'status_code' => $code,
            ];
        }

        return [
            'error' => 'Authentication error (API key or WP App Password may be invalid).',
            'status_code' => $code,
        ];
    }
}
