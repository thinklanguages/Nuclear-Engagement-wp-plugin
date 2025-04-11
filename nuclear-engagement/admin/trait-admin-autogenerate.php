<?php
/**
 * File: admin/trait-admin-autogenerate.php
 *
 * Handles auto-generation on Publish
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_AutoGenerate {

	/**
	 * => NEW METHOD to auto-generate quiz/summary on publish (including scheduled)
	 */
	public function nuclen_auto_generate_on_publish( $new_status, $old_status, $post ) {
		// Only run if post transitions from something to "publish"
		if ( $old_status === 'publish' || $new_status !== 'publish' ) {
			return;
		}

		// Fetch plugin settings
		$nuclen_settings = get_option( 'nuclear_engagement_settings', array() );

		// Allowed post types from settings (fallback to ['post'] if empty)
		$allowed_post_types = $nuclen_settings['generation_post_types'] ?? array( 'post' );

		// If this post type is not in the allowed list, skip
		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return;
		}

		$quiz_enabled    = ! empty( $nuclen_settings['auto_generate_quiz_on_publish'] ) && (int) $nuclen_settings['auto_generate_quiz_on_publish'] === 1;
		$summary_enabled = ! empty( $nuclen_settings['auto_generate_summary_on_publish'] ) && (int) $nuclen_settings['auto_generate_summary_on_publish'] === 1;

		if ( ! $quiz_enabled && ! $summary_enabled ) {
			return;
		}

		// Generate quiz if needed
		if ( $quiz_enabled ) {
			$this->nuclen_generate_single( $post->ID, 'quiz' );
		}

		// Generate summary if needed
		if ( $summary_enabled ) {
			$this->nuclen_generate_single( $post->ID, 'summary' );
		}
	}

	/**
	 * => HELPER to replicate single-generation logic for one post
	 */
	private function nuclen_generate_single( $post_id, $workflow_type ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Build the data to send
		$posts_data = array(
			array(
				'id'      => $post_id,
				'title'   => get_the_title( $post_id ),
				'content' => wp_strip_all_tags( $post->post_content ),
			),
		);

		// For summary, pick some defaults
		$summary_format = 'paragraph';
		$summary_length = 30;
		$summary_items  = 3;

		// Construct a workflow
		$workflow = array(
			'type'                    => $workflow_type, // quiz or summary
			'summary_format'          => $summary_format,
			'summary_length'          => $summary_length,
			'summary_number_of_items' => $summary_items,
		);

		// Add a generation ID for this auto-generation
		$data_to_send = array(
			'posts'         => $posts_data,
			'workflow'      => $workflow,
			'generation_id' => 'auto_' . $post_id . '_' . time(), // Generates a unique generation_id
		);

		// Send to remote
		$public_class = new \NuclearEngagement\Front\FrontClass( $this->nuclen_get_plugin_name(), $this->nuclen_get_version() );
		$result       = $public_class->nuclen_send_posts_to_app_backend( $data_to_send );

		// If request entirely failed:
		if ( $result === false ) {
			return;
		}

		// === MAIN CHANGE: Check "results" instead of "posts"
		if ( empty( $result['results'] ) || ! is_array( $result['results'] ) ) {
			// No data returned
			return;
		}

		// The remote returns e.g. "results" => [ "123" => [ "questions" => [...], ... ] ]
		foreach ( $result['results'] as $pid_str => $generated_post_data ) {
			$pid = (int) $pid_str;

			if ( $pid === $post_id ) {
				// For quiz or summary, store the entire array
				if ( $workflow_type === 'quiz' ) {
					update_post_meta( $pid, 'nuclen-quiz-data', $generated_post_data );
					clean_post_cache( $pid );
				} else {
					update_post_meta( $pid, 'nuclen-summary-data', $generated_post_data );
					clean_post_cache( $pid );
				}

				// Then maybe update last_modified
				$nuclen_settings = get_option( 'nuclear_engagement_settings', array() );
				if ( isset( $nuclen_settings['update_last_modified'] ) && (int) $nuclen_settings['update_last_modified'] === 1 ) {
					$time      = current_time( 'mysql' );
					$post_data = array(
						'ID'                => $pid,
						'post_modified'     => $time,
						'post_modified_gmt' => get_gmt_from_date( $time ),
					);
					wp_update_post( $post_data );
				}
			}
		}
	}
}
