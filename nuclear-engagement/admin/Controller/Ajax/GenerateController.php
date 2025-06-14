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
            \NuclearEngagement\Services\LoggingService::log(
                'Nuclear Engagement validation error: ' . $e->getMessage()
            );
            status_header(400);
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            \NuclearEngagement\Services\LoggingService::log(
                'Nuclear Engagement generation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            );
            \NuclearEngagement\Services\LoggingService::log('Stack trace: ' . $e->getTraceAsString());
            status_header(500);
            wp_send_json_error(['message' => 'An unexpected error occurred. Please check your error logs.']);
        }
    }
}
