<?php
/**
 * File: includes/Services/RemoteApiService.php
 
 * Remote API Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Utils;

if (!defined('ABSPATH')) {
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
     * Send posts to remote API for content generation
     *
     * @param array $data
     * @return array
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
        
        $this->utils->nuclen_log('Sending generation request: ' . $data['generation_id']);
        
        $response = wp_remote_post(
            self::API_BASE . '/process-posts',
            [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $apiKey,
                ],
                'body' => wp_json_encode($payload),
                'timeout' => 30,
                'reject_unsafe_urls' => true,
                'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
            ]
        );
        
        if (is_wp_error($response)) {
            $error = 'API request failed: ' . $response->get_error_message();
            $this->utils->nuclen_log($error);
            return ['error' => $error];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->utils->nuclen_log("API response code: {$code}");
        
        // Check for auth errors
        if ($code === 401 || $code === 403) {
            return $this->handleAuthError($body, $code);
        }
        
        if ($code !== 200) {
            $this->utils->nuclen_log("Unexpected response code: {$code}, body: {$body}");
            return ['error' => "Failed to fetch updates, code: {$code}"];
        }
        
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->utils->nuclen_log("Invalid JSON response: {$body}");
            return ['error' => 'Invalid data received from API'];
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
            $this->utils->nuclen_log('Fetching credits only (no generation_id)');
        } else {
            $this->utils->nuclen_log("Fetching updates for generation: {$generationId}");
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
                'timeout' => 30,
                'reject_unsafe_urls' => true,
                'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
            ]
        );
        
        if (is_wp_error($response)) {
            $error = 'API request failed: ' . $response->get_error_message();
            $this->utils->nuclen_log($error);
            throw new \RuntimeException($error);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Check for auth errors
        if ($code === 401 || $code === 403) {
            $authResult = $this->handleAuthError($body, $code);
            throw new \RuntimeException($authResult['error']);
        }
        
        if ($code !== 200) {
            $this->utils->nuclen_log("Unexpected response code: {$code}, body: {$body}");
            throw new \RuntimeException("Failed to fetch updates, code: {$code}");
        }
        
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->utils->nuclen_log("Invalid JSON response: {$body}");
            throw new \RuntimeException('Invalid data received from API');
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
