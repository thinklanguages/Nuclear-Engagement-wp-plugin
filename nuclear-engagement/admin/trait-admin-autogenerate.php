<?php
/**
 * File: admin/trait-admin-autogenerate.php
 *
 * Auto-generation on publish **with WP-Cron polling**.
 *
 * v3 – 25 Apr 2025
 * ----------------
 * • Adds `protected` checks to skip regeneration if quiz/summary is marked protected
 * • Maintains polling, result storage with guaranteed date
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Admin_AutoGenerate {

    /*──────────────────────────────────────────────────────────
      Register the WP-Cron action (called from Admin::__construct())
     ──────────────────────────────────────────────────────────*/
    public function nuclen_register_autogen_cron_hook() {
        add_action(
            'nuclen_poll_generation',
            array( $this, 'nuclen_cron_poll_generation' ),
            10,
            4 // generation_id, workflow_type, post_id, attempt
        );
    }

    /*──────────────────────────────────────────────────────────
      Hook: post transitions to “publish”
     ──────────────────────────────────────────────────────────*/
    public function nuclen_auto_generate_on_publish( $new_status, $old_status, $post ) {
        // Only when we enter publish
        if ( $old_status === 'publish' || $new_status !== 'publish' ) {
            return;
        }

        $settings           = get_option( 'nuclear_engagement_settings', array() );
        $allowed_post_types = $settings['generation_post_types'] ?? array( 'post' );
        if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
            return;
        }

        $gen_quiz    = ! empty( $settings['auto_generate_quiz_on_publish'] );
        $gen_summary = ! empty( $settings['auto_generate_summary_on_publish'] );
        if ( ! $gen_quiz && ! $gen_summary ) {
            return;
        }

        // Auto-generate quiz
        if ( $gen_quiz ) {
            // Skip if quiz is protected
            $protected = get_post_meta( $post->ID, 'nuclen_quiz_protected', true );
            if ( ! $protected ) {
                $this->nuclen_generate_single( $post->ID, 'quiz' );
            }
        }

        // Auto-generate summary
        if ( $gen_summary ) {
            // Skip if summary is protected
            $protected = get_post_meta( $post->ID, 'nuclen_summary_protected', true );
            if ( ! $protected ) {
                $this->nuclen_generate_single( $post->ID, 'summary' );
            }
        }
    }

    /*──────────────────────────────────────────────────────────
      Send post to SaaS & schedule polling
     ──────────────────────────────────────────────────────────*/
    private function nuclen_generate_single( $post_id, $workflow_type ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        // Skip if protected (double-check)
        if ( $workflow_type === 'quiz' ) {
            if ( get_post_meta( $post_id, 'nuclen_quiz_protected', true ) ) {
                return;
            }
        } else {
            if ( get_post_meta( $post_id, 'nuclen_summary_protected', true ) ) {
                return;
            }
        }

        $post_payload = array(
            array(
                'id'      => $post_id,
                'title'   => get_the_title( $post_id ),
                'content' => wp_strip_all_tags( $post->post_content ),
            ),
        );

        $workflow = array(
            'type'                    => $workflow_type,
            'summary_format'          => 'paragraph',
            'summary_length'          => 30,
            'summary_number_of_items' => 3,
        );

        $generation_id = 'auto_' . $post_id . '_' . time();

        $data = array(
            'posts'         => $post_payload,
            'workflow'      => $workflow,
            'generation_id' => $generation_id,
        );

        $front  = new \NuclearEngagement\Front\FrontClass(
            $this->nuclen_get_plugin_name(),
            $this->nuclen_get_version()
        );
        $result = $front->nuclen_send_posts_to_app_backend( $data );
        if ( $result === false ) {
            return;
        }

        // If SaaS returns synchronously
        if ( ! empty( $result['results'] ) && is_array( $result['results'] ) ) {
            $this->nuclen_store_results( $result['results'], $workflow_type );
            return;
        }

        // Otherwise, schedule first poll in 30s
        wp_schedule_single_event(
            time() + 30,
            'nuclen_poll_generation',
            array( $generation_id, $workflow_type, $post_id, 1 )
        );
    }

    /*──────────────────────────────────────────────────────────
      Cron callback: poll SaaS /updates
     ──────────────────────────────────────────────────────────*/
    public function nuclen_cron_poll_generation( $generation_id, $workflow_type, $post_id, $attempt ) {
        $max_attempts = 10;
        $retry_delay  = 30;

        $app_setup = get_option( 'nuclear_engagement_setup', array() );
        $api_key   = $app_setup['api_key'] ?? '';

        $response = wp_remote_post(
            'https://app.nuclearengagement.com/api/updates',
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-API-Key'    => $api_key,
                ),
                'body'    => wp_json_encode(
                    array(
                        'siteUrl'       => get_site_url(),
                        'generation_id' => $generation_id,
                    )
                ),
                'timeout' => 30,
                'reject_unsafe_urls' => true,
                'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
            )
        );

        if ( is_wp_error( $response ) ) {
            // Continue to retry on WP_Error
            $this->nuclen_get_utils()->nuclen_log(
                "Polling error for post $post_id ($workflow_type): " . $response->get_error_message()
            );
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            // On auth errors, log and abort immediately
            if ( in_array( $code, [401, 403], true ) ) {
                $this->nuclen_get_utils()->nuclen_log(
                    "Polling aborted due to authentication error ($code) for post $post_id ($workflow_type)"
                );
                return;
            }

            // On success, store results and return
            if ( $code === 200 && ! empty( $body['results'] ) ) {
                $this->nuclen_store_results( $body['results'], $workflow_type );
                return;
            }
        }

        if ( $attempt < $max_attempts ) {
            wp_schedule_single_event(
                time() + $retry_delay,
                'nuclen_poll_generation',
                array( $generation_id, $workflow_type, $post_id, $attempt + 1 )
            );
        } else {
            $this->nuclen_get_utils()->nuclen_log(
                "Polling aborted after $max_attempts attempts for post $post_id ($workflow_type)"
            );
        }
    }

    /*──────────────────────────────────────────────────────────
      Persist results + ensure date
     ──────────────────────────────────────────────────────────*/
    private function nuclen_store_results( array $results, string $workflow_type ) {
        $date_now = current_time( 'mysql' );

        foreach ( $results as $pid_str => $data ) {
            $pid = (int) $pid_str;

            // Ensure date is set
            if ( empty( $data['date'] ) ) {
                $data['date'] = $date_now;
            }

            if ( $workflow_type === 'quiz' ) {
                update_post_meta( $pid, 'nuclen-quiz-data', $data );
            } else {
                // Legacy: if summary under 'content'
                if ( ! isset( $data['summary'] ) && isset( $data['content'] ) ) {
                    $data['summary'] = $data['content'];
                    unset( $data['content'] );
                }
                update_post_meta( $pid, 'nuclen-summary-data', $data );
            }

            clean_post_cache( $pid );
        }
    }
}
