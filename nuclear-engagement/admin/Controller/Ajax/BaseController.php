<?php
declare(strict_types=1);
/**
 * File: admin/Controller/Ajax/BaseController.php
 *
 * Base class for AJAX controllers.
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides common security helpers for AJAX controllers.
 */
abstract class BaseController {
    /**
     * Send a standardized JSON error response.
     *
     * @param string $message Error message.
     * @param int    $code    HTTP status code.
     */
    protected function sendError(string $message, int $code = 500): void {
        status_header($code);
        wp_send_json(
            [
                'success' => false,
                'message' => $message,
            ],
            $code
        );
    }

    /**
     * Verify nonce and permissions.
     *
     * @param string $nonceAction Nonce action.
     * @param string $nonceField  Nonce field name.
     * @param string $capability  Capability to check.
     * @return bool Whether the request is valid.
     */
    protected function verifyRequest(
        string $nonceAction,
        string $nonceField = 'security',
        string $capability = 'manage_options'
    ): bool {
        if (!check_ajax_referer($nonceAction, $nonceField, false)) {
            $this->sendError(
                __('Security check failed', 'nuclear-engagement'),
                403
            );
            return false;
        }

        if (!current_user_can($capability)) {
            $this->sendError(__('Not allowed', 'nuclear-engagement'), 403);
            return false;
        }

        return true;
    }
}
