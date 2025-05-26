<?php
/**
 * Trait: RestTrait
 *
 * REST-API route for receiving quiz / summary data
 * and helper functions to send posts to the remote app.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait RestTrait {

	/* ---------- REST route ---------- */
	public function nuclen_register_content_endpoint() {
		register_rest_route(
			'nuclear-engagement/v1',
			'/receive-content',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'nuclen_receive_content' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public function nuclen_receive_content( \WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( empty( $data['workflow'] ) ) {
			$this->utils->nuclen_log( 'Missing workflow in request: ' . json_encode( $data ) );
			return new \WP_Error( 'no_workflow', __( 'No workflow found in request', 'nuclear-engagement' ), array( 'status' => 400 ) );
		}
		if ( empty( $data['results'] ) || ! is_array( $data['results'] ) ) {
			$this->utils->nuclen_log( 'Invalid results data: ' . json_encode( $data ) );
			return new \WP_Error( 'no_results', __( 'No results data found in request', 'nuclear-engagement' ), array( 'status' => 400 ) );
		}

		$workflow = sanitize_text_field( $data['workflow'] );
		$results  = $data['results'];

		if ( $workflow === 'quiz' ) {
			foreach ( $results as $post_id => $quiz_data ) {
				$this->nuclen_validate_and_store_quiz_data( $post_id, $quiz_data );
			}
			reset( $results );
			$first  = key( $results );
			$stored = get_post_meta( $first, 'nuclen-quiz-data', true );
			$date   = is_array( $stored ) && ! empty( $stored['date'] ) ? $stored['date'] : '';
			return new \WP_REST_Response(
				array(
					'message'   => __( 'Quiz data received and stored successfully', 'nuclear-engagement' ),
					'finalDate' => $date,
				),
				200
			);
		}

		if ( $workflow === 'summary' ) {
			foreach ( $results as $post_id => $summary_data ) {
				if ( ! isset( $summary_data['summary'] ) || ! is_string( $summary_data['summary'] ) ) {
					$this->utils->nuclen_log( "Invalid summary for $post_id" );
					continue;
				}
				$allowed_html = array(
					'a' => array(
						'href' => array(),
						'title' => array(),
						'target' => array(),
					),
					'br' => array(),
					'em' => array(),
					'strong' => array(),
					'p' => array(),
					'ul' => array(),
					'ol' => array(),
					'li' => array(),
					'h1' => array(),
					'h2' => array(),
					'h3' => array(),
					'h4' => array(),
					'div' => array('class' => array()),
					'span' => array('class' => array()),
				);

				update_post_meta(
					$post_id,
					'nuclen-summary-data',
					array(
						'summary' => wp_kses( $summary_data['summary'], $allowed_html ),
						'date'    => current_time( 'mysql' ),
					)
				);
				clean_post_cache( $post_id );
			}
			reset( $results );
			$first  = key( $results );
			$stored = get_post_meta( $first, 'nuclen-summary-data', true );
			$date   = is_array( $stored ) && ! empty( $stored['date'] ) ? $stored['date'] : '';
			return new \WP_REST_Response(
				array(
					'message'   => __( 'Summary data received and stored successfully', 'nuclear-engagement' ),
					'finalDate' => $date,
				),
				200
			);
		}

		$this->utils->nuclen_log( "Invalid workflow: $workflow" );
		return new \WP_Error( 'invalid_workflow', __( 'Invalid workflow', 'nuclear-engagement' ), array( 'status' => 400 ) );
	}

	/* ---------- Validation & storage ---------- */
	public function nuclen_validate_and_store_quiz_data( $post_id, $quiz_data ) : bool {
		if ( empty( $quiz_data['questions'] ) || ! is_array( $quiz_data['questions'] ) ) {
			$this->utils->nuclen_log( "Invalid quiz data for $post_id" );
			return false;
		}
		$final_questions = array();
		foreach ( $quiz_data['questions'] as $q ) {
			$final_questions[] = array(
				'question'    => $q['question']    ?? '',
				'answers'     => $q['answers']     ?? array(),
				'explanation' => $q['explanation'] ?? '',
			);
		}
		$payload = array(
			'questions' => $final_questions,
			'date'      => current_time( 'mysql' ),
		);
		if ( update_post_meta( $post_id, 'nuclen-quiz-data', $payload ) ) {
			clean_post_cache( $post_id );
			$this->utils->nuclen_log( "Stored quiz-data for $post_id" );
			return true;
		}
		$this->utils->nuclen_log( "Failed storing quiz-data for $post_id" );
		return false;
	}

	/* ---------- Outbound: send posts to remote app ---------- */
	public function nuclen_send_posts_to_app_backend( $data_to_send ) {
		$api_url = 'https://app.nuclearengagement.com/api/process-posts';
		if ( ! filter_var( $api_url, FILTER_VALIDATE_URL ) ) {
			$this->utils->nuclen_log( "Invalid API URL: $api_url" );
			return false;
		}
		$app_setup = get_option( 'nuclear_engagement_setup', array() );
		$api_key   = $app_setup['api_key'] ?? '';
		$site_url  = get_site_url();

		$data = array(
			'generation_id' => $data_to_send['generation_id'] ?? array(),
			'api_key'       => $api_key,
			'siteUrl'       => $site_url,
			'posts'         => array_values(
				array_filter(
					$data_to_send['posts'] ?? array(),
					function ( $p ) {
						return ! empty( $p['id'] ) && ! empty( $p['title'] ) && ! empty( $p['content'] );
					}
				)
			),
			'workflow'      => $data_to_send['workflow'] ?? array(),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => $api_key,
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
				'reject_unsafe_urls' => true,
				'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->utils->nuclen_log( 'Error sending data: ' . $response->get_error_message() );
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$this->utils->nuclen_log( "Unexpected response code: $code" );
			return false;
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
