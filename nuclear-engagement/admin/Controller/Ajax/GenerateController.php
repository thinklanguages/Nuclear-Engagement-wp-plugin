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
class GenerateController extends BaseController {
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

            if (!$this->verifyRequest('nuclen_admin_ajax_nonce')) {
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

    /**
     * Get generation progress.
     */
    public function progress(): void {
        try {
            if (!$this->verifyRequest('nuclen_admin_ajax_nonce')) {
                return;
            }

            $generation_id = isset($_POST['generation_id']) ? sanitize_text_field(wp_unslash($_POST['generation_id'])) : null;
            $progress = $this->service->getProgress($generation_id);

            wp_send_json_success(['generations' => $progress]);
        } catch (\Exception $e) {
            error_log('Nuclear Engagement progress error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to get progress']);
        }
    }

    /**
     * Dismiss a generation notice.
     */
    public function dismiss(): void {
        try {
            if (!$this->verifyRequest('nuclen_admin_ajax_nonce')) {
                return;
            }

            if (empty($_POST['generation_id'])) {
                wp_send_json_error(['message' => 'Missing generation ID']);
                return;
            }

            $generation_id = sanitize_text_field(wp_unslash($_POST['generation_id']));
            $container = \NuclearEngagement\Container::getInstance();
            /** @var \NuclearEngagement\Services\GenerationTracker $tracker */
            $tracker = $container->get('generation_tracker');

            if ($tracker->dismiss($generation_id)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed to dismiss generation']);
            }
        } catch (\Exception $e) {
            error_log('Nuclear Engagement dismiss error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to dismiss']);
        }
    }
}
