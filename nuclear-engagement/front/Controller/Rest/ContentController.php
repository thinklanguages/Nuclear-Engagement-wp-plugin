<?php
/**
 * File: front/Controller/Rest/ContentController.php
 *
 * Content Controller
 *
 * @package NuclearEngagement\Front\Controller\Rest
 */

namespace NuclearEngagement\Front\Controller\Rest;

use NuclearEngagement\Requests\ContentRequest;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller for receiving content
 */
class ContentController {
    /**
     * @var ContentStorageService
     */
    private ContentStorageService $storage;
    
    /**
     * @var Utils
     */
    private Utils $utils;
    
    /**
     * Constructor
     *
     * @param ContentStorageService $storage
     */
    public function __construct(ContentStorageService $storage) {
        $this->storage = $storage;
        $this->utils = new Utils();
    }
    
    /**
     * Handle content receive request
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle(\WP_REST_Request $request) {
        try {
            // Verify REST nonce before processing any data
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new \WP_Error('rest_invalid_nonce', __('Invalid nonce', 'nuclear-engagement'), ['status' => 403]);
            }

            $data = $request->get_json_params();
            
            $this->utils->nuclen_log('Received content via REST: ' . json_encode([
                'workflow' => $data['workflow'] ?? 'unknown',
                'results_count' => is_array($data['results'] ?? null) ? count($data['results']) : 0,
            ]));
            
            $contentRequest = ContentRequest::fromJson($data);
            
            // Store the results
            $this->storage->storeResults($contentRequest->results, $contentRequest->workflow);
            
            // Get date from first stored item
            reset($contentRequest->results);
            $firstPostId = key($contentRequest->results);
            $metaKey = $contentRequest->workflow === 'quiz' ? 'nuclen-quiz-data' : 'nuclen-summary-data';
            $stored = get_post_meta($firstPostId, $metaKey, true);
            $date = is_array($stored) && !empty($stored['date']) ? $stored['date'] : '';
            
            $message = sprintf(
                __('%s data received and stored successfully', 'nuclear-engagement'),
                ucfirst($contentRequest->workflow)
            );
            
            return new \WP_REST_Response([
                'message' => $message,
                'finalDate' => $date,
            ], 200);
            
        } catch (\InvalidArgumentException $e) {
            $this->utils->nuclen_log('REST validation error: ' . $e->getMessage());
            return new \WP_Error('ne_invalid', $e->getMessage(), ['status' => 400]);
        } catch (\Exception $e) {
            $this->utils->nuclen_log('REST error: ' . $e->getMessage());
            return new \WP_Error('ne_error', __('An error occurred', 'nuclear-engagement'), ['status' => 500]);
        }
    }
    
    /**
     * Check permissions
     *
     * @return bool
     */
    public function permissions(): bool {
        return current_user_can('manage_options');
    }
}
