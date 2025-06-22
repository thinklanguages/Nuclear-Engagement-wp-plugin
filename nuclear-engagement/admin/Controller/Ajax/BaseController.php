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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides common security helpers for AJAX controllers.
 */
abstract class BaseController {
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
            status_header(403);
            wp_send_json_error(['message' => 'Security check failed']);
            return false;
        }

        if (!current_user_can($capability)) {
            status_header(403);
            wp_send_json_error(['message' => 'Not allowed']);
            return false;
        }

        return true;
    }
}
