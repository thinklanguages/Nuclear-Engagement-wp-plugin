<?php
/**
 * File: admin/Controller/Ajax/GenerateController.php
 
 * Generate Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Requests\GenerateRequest;
use NuclearEngagement\Services\GenerationService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for content generation
 */
class GenerateController {
    /**
     * @var GenerationService
     */
    private GenerationService $service;
    
    /**
     * Constructor
     *
     * @param GenerationService $service
     */
    public function __construct(GenerationService $service) {
        $this->service = $service;
    }
    
    /**
     * Handle generation request
     */
    public function handle(): void {
        try {
            // Enable error logging for debugging
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                @ini_set('display_errors', 1);
                @error_reporting(E_ALL);
            }
            
            // Security check
            if (!check_ajax_referer('nuclen_admin_ajax_nonce', 'security', false)) {
                status_header(403);
                wp_send_json_error(['message' => 'Security check failed: Invalid nonce']);
                return;
            }
            
            if (!current_user_can('manage_options')) {
                status_header(403);
                wp_send_json_error(['message' => 'Not allowed']);
                return;
            }
            
            if (empty($_POST['payload'])) {
                status_header(400);
                wp_send_json_error(['message' => 'Missing payload in request']);
                return;
            }
            
            // Parse request
            $request = GenerateRequest::fromPost($_POST);
            
            // Process generation
            $response = $this->service->generateContent($request);
            
            // Return response
            wp_send_json_success($response->toArray());
            
        } catch (\InvalidArgumentException $e) {
            error_log('Nuclear Engagement validation error: ' . $e->getMessage());
            status_header(400);
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log('Nuclear Engagement generation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('Stack trace: ' . $e->getTraceAsString());
            status_header(500);
            wp_send_json_error(['message' => 'An unexpected error occurred. Please check your error logs.']);
        }
    }
}
