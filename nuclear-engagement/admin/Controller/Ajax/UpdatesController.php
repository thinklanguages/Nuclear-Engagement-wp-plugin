<?php
/**
 * File: admin/Controller/Ajax/UpdatesController.php
 
 * Updates Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Requests\UpdatesRequest;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Responses\UpdatesResponse;
use NuclearEngagement\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for polling updates
 */
class UpdatesController {
    /**
     * @var RemoteApiService
     */
    private RemoteApiService $api;
    
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
     * @param RemoteApiService $api
     * @param ContentStorageService $storage
     */
    public function __construct(RemoteApiService $api, ContentStorageService $storage) {
        $this->api = $api;
        $this->storage = $storage;
        $this->utils = new Utils();
    }
    
    /**
     * Handle updates request
     */
    public function handle(): void {
        try {
            // Security check
            check_ajax_referer('nuclen_admin_ajax_nonce', 'security');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Not allowed']);
            }
            
            // Parse request
            $request = UpdatesRequest::fromPost($_POST);
            
            // Default generation ID if not provided (credits check)
            if (empty($request->generationId)) {
                $request->generationId = 'gen_' . uniqid('auto_', true);
            }
            
            // Fetch updates from API
            $data = $this->api->fetchUpdates($request->generationId);
            
            $this->utils->nuclen_log('Updates response: ' . json_encode($data));
            
            // Build response
            $response = new UpdatesResponse();
            
            // Handle success case
            if (isset($data['success']) && $data['success'] === true) {
                $response->success = true;
                
                if (isset($data['processed'])) {
                    $response->processed = (int) $data['processed'];
                }
                if (isset($data['total'])) {
                    $response->total = (int) $data['total'];
                }
                if (isset($data['remaining_credits'])) {
                    $response->remainingCredits = (int) $data['remaining_credits'];
                }
                if (isset($data['message'])) {
                    $response->message = $data['message'];
                }
                
                // Store results if any
                if (!empty($data['results']) && is_array($data['results'])) {
                    // Determine workflow type from first result
                    $firstResult = reset($data['results']);
                    $workflowType = isset($firstResult['questions']) ? 'quiz' : 'summary';
                    
                    $this->storage->storeResults($data['results'], $workflowType);
                    $response->results = $data['results'];
                }
                
                wp_send_json_success($response->toArray());
            } else {
                // Handle error case
                $message = $data['message'] ?? 'Invalid data received. Please try again later.';
                $this->utils->nuclen_log('Unexpected response from updates: ' . json_encode($data));
                wp_send_json_error(['message' => $message]);
            }
            
        } catch (\Exception $e) {
            $this->utils->nuclen_log('Error fetching updates: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
