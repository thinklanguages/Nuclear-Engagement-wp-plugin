<?php
/**
 * File: includes/BaseAjaxController.php
 *
 * Abstract controller providing common AJAX helpers.
 *
 * @package NuclearEngagement\Includes
 */

namespace NuclearEngagement\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base class for AJAX controllers.
 */
abstract class BaseAjaxController {

    /**
     * Verify nonce and permissions for an AJAX request.
     *
     * @param string $nonceAction Action name for nonce verification.
     * @param string $nonceField  Optional. Nonce field name. Default 'security'.
     * @param string $capability  Optional. Required capability. Default 'manage_options'.
     *
     * @return bool True on success, false if verification failed and a JSON response was sent.
     */
    protected function verify_request( string $nonceAction, string $nonceField = 'security', string $capability = 'manage_options' ): bool {
        if ( ! check_ajax_referer( $nonceAction, $nonceField, false ) ) {
            status_header( 403 );
            wp_send_json_error( array( 'message' => 'Security check failed: Invalid nonce' ) );
            return false;
        }

        if ( ! current_user_can( $capability ) ) {
            status_header( 403 );
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
            return false;
        }

        return true;
    }
}
