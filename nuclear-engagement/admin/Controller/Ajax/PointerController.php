<?php
/**
 * File: admin/Controller/Ajax/PointerController.php
 
 * Pointer Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Services\PointerService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for admin pointers
 */
class PointerController {
    /**
     * @var PointerService
     */
    private PointerService $service;
    
    /**
     * Constructor
     *
     * @param PointerService $service
     */
    public function __construct(PointerService $service) {
        $this->service = $service;
    }
    
    /**
     * Dismiss a pointer
     */
    public function dismiss(): void {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('No permission', 'nuclear-engagement')]);
            }
            
            check_ajax_referer('nuclen_dismiss_pointer_nonce', 'nonce');
            
            $pointerId = isset($_POST['pointer']) ? sanitize_text_field(wp_unslash($_POST['pointer'])) : '';
            $userId = get_current_user_id();
            
            $this->service->dismissPointer($pointerId, $userId);
            
            wp_send_json_success(['message' => __('Pointer dismissed.', 'nuclear-engagement')]);
            
        } catch (\InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('An error occurred', 'nuclear-engagement')]);
        }
    }
}