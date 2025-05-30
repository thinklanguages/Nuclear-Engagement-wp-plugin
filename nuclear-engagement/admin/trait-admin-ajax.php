<?php
/**
 * File: admin/trait-admin-ajax.php
 *
 * Handles AJAX callbacks and remote fetching for Nuclear Engagement.
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Admin_Ajax {

    /**
     * Fetch updates from the remote app.
     * If old plugin doesn't send generation_id, we use a dynamically generated one.
     */
    public function nuclen_fetch_app_updates() {
        // Security check
        check_ajax_referer( 'nuclen_admin_ajax_nonce', 'security' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
        }

        $api_url  = 'https://app.nuclearengagement.com/api/updates';
        $site_url = get_site_url();

        // Possibly empty if old plugin doesn't pass it
        $generation_id = isset( $_POST['generation_id'] )
            ? sanitize_text_field( wp_unslash( $_POST['generation_id'] ) )
            : '';

        // If missing, fallback to a unique generation ID
        if ( ! $generation_id ) {
            $generation_id = 'gen_' . uniqid( 'auto_', true );
        }

        // Retrieve the plugin's API key from settings repository
        $api_key = $this->get_settings_repository()->get( 'api_key', '' );

        // Build a JSON body
        $payload = array(
            'siteUrl'       => $site_url,
            'generation_id' => $generation_id,
        );

        // POST JSON
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key'    => $api_key,
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
            'method'  => 'POST',
        );

        $response = wp_remote_request( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            $err = $response->get_error_message();
            $this->nuclen_get_utils()->nuclen_log( "Error fetching updates: $err" );
            wp_send_json_error( array( 'message' => 'Failed to fetch updates from app: ' . $err ) );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        // Check for invalid credentials
        if ( $code === 401 || $code === 403 ) {
            if ( strpos( $body, 'invalid_api_key' ) !== false ) {
                wp_send_json_error( array( 'message' => 'Invalid API key. Please update it on the Setup page.' ) );
                return;
            }
            if ( strpos( $body, 'invalid_wp_app_pass' ) !== false ) {
                wp_send_json_error( array( 'message' => 'Invalid WP App Password. Please re-generate on the Setup page.' ) );
                return;
            }
            wp_send_json_error( array( 'message' => 'Authentication error (API key or WP App Password may be invalid).' ) );
            return;
        }

        if ( $code !== 200 ) {
            $this->nuclen_get_utils()->nuclen_log( "Error fetching updates: HTTP $code, response: $body" );
            wp_send_json_error( array( 'message' => 'Failed to fetch updates, code: ' . $code ) );
            return;
        }

        $data = json_decode( $body, true );
        if ( ! is_array( $data ) ) {
            $this->nuclen_get_utils()->nuclen_log( "Unexpected response from updates endpoint: $body" );
            wp_send_json_error( array( 'message' => 'Invalid data received... Please try again.' ) );
            return;
        }

        // Expect { success: true, processed, total, ... }
        if ( isset( $data['success'] ) && $data['success'] === true ) {
            // Return success
            wp_send_json_success( $data );
        } else {
            // Otherwise error
            $this->nuclen_get_utils()->nuclen_log( 'Unexpected response from updates: ' . json_encode( $data ) );
            wp_send_json_error(
                array(
                    'message' => $data['message'] ?? 'Invalid data received. Please try again later.',
                )
            );
        }
    }

    /**
     * AJAX to get a list of posts for bulk generation.
     */
    public function nuclen_get_posts_count() {
        check_ajax_referer( 'nuclen_admin_ajax_nonce', 'security' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
        }

        $utils      = $this->nuclen_get_utils();
        $query_args = $utils->nuclen_build_generation_query_args();
        $query      = new \WP_Query( $query_args );
        $count      = $query->found_posts;

        $post_ids = array();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_ids[] = get_the_ID();
            }
            wp_reset_postdata();
        }

        wp_send_json_success(
            array(
                'count'    => $count,
                'post_ids' => $post_ids,
            )
        );
    }

    /**
     * AJAX to start generation (bulk or single).
     * If old plugin doesn't send generation_id, we fallback to a unique generation ID.
     */
    public function nuclen_handle_trigger_generation() {
        try {
            // Enable error logging for this request
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                @ini_set('display_errors', 1);
                @error_reporting(E_ALL);
            }
        
        
        // Verify nonce and permissions
        if (!check_ajax_referer('nuclen_admin_ajax_nonce', 'security', false)) {
            $error_msg = 'Security check failed: Invalid nonce';
            
            status_header(403);
            wp_send_json_error(array('message' => $error_msg));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            $error_msg = 'Permission denied for user: ' . get_current_user_id();
            error_log($error_msg);
            status_header(403);
            wp_send_json_error(array('message' => 'Not allowed'));
            return;
        }

        if (empty($_POST['payload'])) {
            $error_msg = 'Missing payload in request';
            error_log($error_msg);
            status_header(400);
            wp_send_json_error(array('message' => $error_msg));
            return;
        }
        $payload_raw = wp_unslash($_POST['payload']);
        $payload = json_decode($payload_raw, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'JSON decode error: ' . json_last_error_msg() . ' | Raw: ' . substr($payload_raw, 0, 200);
            error_log($error_msg);
            status_header(400);
            wp_send_json_error(array('message' => 'Invalid JSON payload: ' . json_last_error_msg()));
            return;
        }
        
        if (!is_array($payload)) {
            $error_msg = 'Invalid payload structure: ' . print_r($payload, true);
            error_log($error_msg);
            status_header(400);
            wp_send_json_error(array('message' => 'Invalid payload structure'));
            return;
        }

        $post_ids_json = $payload['nuclen_selected_post_ids'] ?? '';
        $post_ids_array = json_decode($post_ids_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Failed to decode post IDs: ' . json_last_error_msg() . ' | Raw: ' . $post_ids_json;
            error_log($error_msg);
            status_header(400);
            wp_send_json_error(array('message' => 'Invalid post IDs format'));
            return;
        }
        
        if (empty($post_ids_array) || !is_array($post_ids_array)) {
            $error_msg = 'No valid posts selected. Post IDs: ' . print_r($post_ids_array, true);
            error_log($error_msg);
            status_header(400);
            wp_send_json_error(array('message' => 'No valid posts selected'));
            return;
        }

        $post_status = $payload['nuclen_selected_post_status'] ?? 'any';
        $post_type   = $payload['nuclen_selected_post_type'] ?? 'any';

        // Query posts
        $args             = array(
            'post__in'    => $post_ids_array,
            'numberposts' => -1,
            'post_type'   => $post_type,
            'post_status' => $post_status,
        );
        $posts_to_process = get_posts( $args );
        if ( empty( $posts_to_process ) ) {
            wp_send_json_error( array( 'message' => 'No matching posts found' ) );
        }

        $posts_data = array();
        foreach ( $posts_to_process as $p ) {
            $posts_data[] = array(
                'id'      => $p->ID,
                'title'   => get_the_title( $p->ID ),
                'content' => wp_strip_all_tags( $p->post_content ),
            );
        }

        // Workflow
        $workflow_type = $payload['nuclen_selected_generate_workflow'] ?? '';
        if ( ! in_array( $workflow_type, array( 'quiz', 'summary' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid or missing workflow type' ) );
        }

        $summary_format = $payload['nuclen_selected_summary_format'] ?? 'paragraph';
        $summary_length = isset( $payload['nuclen_selected_summary_length'] )
            ? intval( $payload['nuclen_selected_summary_length'] )
            : 30;
        $summary_items  = isset( $payload['nuclen_selected_summary_number_of_items'] )
            ? intval( $payload['nuclen_selected_summary_number_of_items'] )
            : 3;

        $workflow = array(
            'type'                    => $workflow_type,
            'summary_format'          => $summary_format,
            'summary_length'          => $summary_length,
            'summary_number_of_items' => $summary_items,
        );

        // Fallback to a unique generation_id if the old plugin didn't pass one
        $generation_id = ! empty( $payload['generation_id'] )
            ? sanitize_text_field( $payload['generation_id'] )
            : 'gen_' . uniqid( 'auto_', true );

        // Data to send
        $data_to_send = array(
            'posts'         => $posts_data,
            'workflow'      => $workflow,
            'generation_id' => $generation_id,
        );

        // Send to remote
        try {
            $settings_repo = $this->get_settings_repository();
            $public_class = new \NuclearEngagement\Front\FrontClass(
                $this->nuclen_get_plugin_name(),
                $this->nuclen_get_version(),
                $settings_repo
            );
            $result = $public_class->nuclen_send_posts_to_app_backend($data_to_send);
            
            if ($result === false) {
                throw new Exception('Failed to send data to app: ' . print_r(error_get_last(), true));
            }
        } catch (Exception $e) {
            $error_msg = 'Error sending to app backend: ' . $e->getMessage();
            error_log($error_msg);
            status_header(500);
            wp_send_json_error(array('message' => 'Failed to send data to app: ' . $e->getMessage()));
            return;
        }

        // Check remote errors
        if ( ! empty( $result['status_code'] ) && in_array( $result['status_code'], array( 401, 403 ), true ) ) {
            if ( ! empty( $result['error_code'] ) && $result['error_code'] === 'invalid_api_key' ) {
                wp_send_json_error( array( 'message' => 'Invalid API key.' ) );
            } elseif ( ! empty( $result['error_code'] ) && $result['error_code'] === 'invalid_wp_app_pass' ) {
                wp_send_json_error( array( 'message' => 'Invalid WP App Password.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Authentication error.' ) );
            }
            return;
        }
        if ( ! empty( $result['error'] ) ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }

        // If we got generated content
        if ( ! empty( $result['results'] ) && is_array( $result['results'] ) ) {
            $settings_repo = $this->get_settings_repository();
            $update_last_modified = $settings_repo->get( 'update_last_modified', false );
            
            foreach ( $result['results'] as $post_id_string => $generated_post_data ) {
                $pid = (int) $post_id_string;
                $meta_key = $workflow_type === 'quiz' ? 'nuclen-quiz-data' : 'nuclen-summary-data';
                update_post_meta( $pid, $meta_key, $generated_post_data );
                
                // Update post modified time if enabled
                if ( $update_last_modified ) {
                    $time = current_time( 'mysql' );
                    $post_data = array(
                        'ID'                => $pid,
                        'post_modified'     => $time,
                        'post_modified_gmt' => get_gmt_from_date( $time ),
                    );
                    wp_update_post( $post_data );
                    clean_post_cache( $pid );
                } else {
                    clean_post_cache( $pid );
                }
            }
        }

            // Return generation_id so the front-end can poll
            $result['generation_id'] = $generation_id;
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            $error_msg = 'Unexpected error in nuclen_handle_trigger_generation: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            error_log($error_msg);
            error_log('Stack trace: ' . $e->getTraceAsString());
            status_header(500);
            wp_send_json_error(array('message' => 'An unexpected error occurred. Please check your error logs.'));
        }
    }
}