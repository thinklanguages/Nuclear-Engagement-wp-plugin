<?php
/**
 * File: front/FrontClass.php
 *
 * No new changes this iteration except continuing from previous updates.
 * Full code with no omissions.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use NuclearEngagement\Utils;

class FrontClass {

	private $plugin_name;
	private $version;
	private $utils;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->utils       = new Utils();
	}

	public function wp_enqueue_styles() {
		// Base CSS
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/nuclen-front.css',
			array(),
			NUCLEN_ASSET_VERSION,
			'all'
		);

		$options      = get_option( 'nuclear_engagement_settings', array( 'theme' => 'bright' ) );
		$theme_choice = isset( $options['theme'] ) ? $options['theme'] : 'bright';
		if ( $theme_choice === 'none' ) {
			return; // skip theme
		}

		if ( $theme_choice === 'bright' ) {
			$quiz_theme_css_url = plugin_dir_url( __FILE__ ) . 'css/nuclen-theme-bright.css';
		} elseif ( $theme_choice === 'dark' ) {
			$quiz_theme_css_url = plugin_dir_url( __FILE__ ) . 'css/nuclen-theme-dark.css';
		} elseif ( $theme_choice === 'custom' ) {
			$css_info           = Utils::nuclen_get_custom_css_info();
			$quiz_theme_css_url = $css_info['url'];
		} else {
			$quiz_theme_css_url = plugin_dir_url( __FILE__ ) . 'css/nuclen-theme-bright.css';
		}

		wp_enqueue_style(
			$this->plugin_name . '-theme',
			$quiz_theme_css_url,
			array(),
			NUCLEN_ASSET_VERSION,
			'all'
		);
	}

	public function wp_enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name . '-front',
			plugin_dir_url( __DIR__ ) . 'front/js/nuclen-front.js',
			array(),
			NUCLEN_ASSET_VERSION,
			true
		);

		$options                = get_option( 'nuclear_engagement_settings', array() );
		$custom_quiz_html_after = isset( $options['custom_quiz_html_after'] ) ? $options['custom_quiz_html_after'] : '';
		wp_localize_script( $this->plugin_name . '-front', 'NuclenCustomQuizHtmlAfter', $custom_quiz_html_after );

		$enable_optin          = ! empty( $options['enable_optin'] );
		$optin_webhook         = ! empty( $options['optin_webhook'] ) ? $options['optin_webhook'] : '';
		$optin_success_message = ! empty( $options['optin_success_message'] )
			? $options['optin_success_message']
			: esc_html__( 'Thank you, your submission was successful!', 'nuclear-engagement' );

		wp_localize_script( $this->plugin_name . '-front', 'NuclenOptinEnabled', $enable_optin );
		wp_localize_script( $this->plugin_name . '-front', 'NuclenOptinWebhook', $optin_webhook );
		wp_localize_script( $this->plugin_name . '-front', 'NuclenOptinSuccessMessage', $optin_success_message );

		$post_id   = get_the_ID();
		$quiz_data = get_post_meta( $post_id, 'nuclen-quiz-data', true );

		$final_questions = array();
		if ( ! empty( $quiz_data ) && is_array( $quiz_data ) ) {
			$quiz_data = maybe_unserialize( $quiz_data );
			if ( isset( $quiz_data['questions'] ) && is_array( $quiz_data['questions'] ) ) {
				$final_questions = $quiz_data['questions'];
			}
		}
		wp_localize_script( $this->plugin_name . '-front', 'postQuizData', $final_questions );

		$questions_per_quiz   = ! empty( $options['questions_per_quiz'] ) ? (int) $options['questions_per_quiz'] : 10;
		$answers_per_question = ! empty( $options['answers_per_question'] ) ? (int) $options['answers_per_question'] : 4;
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenSettings',
			array(
				'questions_per_quiz'   => $questions_per_quiz,
				'answers_per_question' => $answers_per_question,
			)
		);
	}

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
		if ( ! isset( $data['workflow'] ) ) {
			$this->utils->nuclen_log( 'Missing workflow in request: ' . json_encode( $data ) );
			return new \WP_Error( 'no_workflow', __( 'No workflow found in request', 'nuclear-engagement' ), array( 'status' => 400 ) );
		}
		if ( ! isset( $data['results'] ) || ! is_array( $data['results'] ) ) {
			$this->utils->nuclen_log( 'Invalid results data in request: ' . json_encode( $data ) );
			return new \WP_Error( 'no_results', __( 'No results data found in request', 'nuclear-engagement' ), array( 'status' => 400 ) );
		}

		$workflow = sanitize_text_field( $data['workflow'] );
		$results  = $data['results'];

		if ( $workflow === 'quiz' ) {
			foreach ( $results as $post_id => $quiz_data ) {
				if ( ! $this->nuclen_validate_and_store_quiz_data( $post_id, $quiz_data ) ) {
					$this->utils->nuclen_log( "Failed quiz data for Post ID: $post_id" );
					continue;
				}
			}
			reset( $results );
			$firstKey  = key( $results );
			$stored    = get_post_meta( $firstKey, 'nuclen-quiz-data', true );
			$finalDate = ( is_array( $stored ) && ! empty( $stored['date'] ) ) ? $stored['date'] : '';
			return new \WP_REST_Response(
				array(
					'message'   => __( 'Quiz data received and stored successfully', 'nuclear-engagement' ),
					'finalDate' => $finalDate,
				),
				200
			);
		}

		if ( $workflow === 'summary' ) {
			foreach ( $results as $post_id => $summary_data ) {
				if ( ! isset( $summary_data['summary'] ) || ! is_string( $summary_data['summary'] ) ) {
					$this->utils->nuclen_log( "Invalid summary data for Post ID: $post_id" );
					continue;
				}
				$formatted_summary_data = array(
					'summary' => wp_kses_post( $summary_data['summary'] ),
					'date'    => current_time( 'mysql' ),
				);
				update_post_meta( $post_id, 'nuclen-summary-data', $formatted_summary_data );
				clean_post_cache( $post_id );
			}
			reset( $results );
			$firstKey  = key( $results );
			$stored    = get_post_meta( $firstKey, 'nuclen-summary-data', true );
			$finalDate = ( is_array( $stored ) && ! empty( $stored['date'] ) ) ? $stored['date'] : '';
			return new \WP_REST_Response(
				array(
					'message'   => __( 'Summary data received and stored successfully', 'nuclear-engagement' ),
					'finalDate' => $finalDate,
				),
				200
			);
		}

		$this->utils->nuclen_log( "Invalid workflow specified: $workflow" );
		return new \WP_Error( 'invalid_workflow', __( 'Invalid workflow', 'nuclear-engagement' ), array( 'status' => 400 ) );
	}

	public function nuclen_validate_and_store_quiz_data( $post_id, $quiz_data ) {
		if ( empty( $quiz_data['questions'] ) || ! is_array( $quiz_data['questions'] ) ) {
			$this->utils->nuclen_log( "Invalid quiz data for $post_id" );
			return false;
		}
		$finalQuestions = array();
		foreach ( $quiz_data['questions'] as $qItem ) {
			$answers          = isset( $qItem['answers'] ) && is_array( $qItem['answers'] ) ? $qItem['answers'] : array();
			$finalQuestions[] = array(
				'question'    => isset( $qItem['question'] ) ? $qItem['question'] : '',
				'answers'     => $answers,
				'explanation' => isset( $qItem['explanation'] ) ? $qItem['explanation'] : '',
			);
		}
		$formatted_quiz_data = array(
			'questions' => $finalQuestions,
			'date'      => current_time( 'mysql' ),
		);
		if ( update_post_meta( $post_id, 'nuclen-quiz-data', $formatted_quiz_data ) ) {
			clean_post_cache( $post_id );
			$this->utils->nuclen_log( "Stored quiz-data for $post_id" );
			return true;
		}
		$this->utils->nuclen_log( "Failed to store quiz-data for $post_id" );
		return false;
	}

	/**
	 * Send post data to remote app (unchanged)
	 */
	public function nuclen_send_posts_to_app_backend( $data_to_send ) {
		$api_url = 'https://app.nuclearengagement.com/api/process-posts';
		if ( ! filter_var( $api_url, FILTER_VALIDATE_URL ) ) {
			$this->utils->nuclen_log( "Invalid API URL: $api_url" );
			return false;
		}
		$app_setup = get_option( 'nuclear_engagement_setup', array() );
		$api_key   = isset( $app_setup['api_key'] ) ? $app_setup['api_key'] : '';
		$site_url  = get_site_url();

		$generation_id = $data_to_send['generation_id'] ?? array();
		$this->utils->nuclen_log( 'Generation id: ' . json_encode( $generation_id ) );

		$posts    = array_filter(
			$data_to_send['posts'] ?? array(),
			function ( $p ) {
				return ! empty( $p['id'] ) && ! empty( $p['title'] ) && ! empty( $p['content'] );
			}
		);
		$workflow = $data_to_send['workflow'] ?? array();
		$this->utils->nuclen_log( 'Workflow: ' . json_encode( $workflow ) );

		$data = array(
			'generation_id' => $generation_id,
			'api_key'       => $api_key,
			'siteUrl'       => $site_url,
			'posts'         => array_values( $posts ),
			'workflow'      => $workflow,
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
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->utils->nuclen_log( 'Error sending data: ' . $response->get_error_message() );
			return false;
		}
		$response_body = wp_remote_retrieve_body( $response );
		$this->utils->nuclen_log( 'Response: ' . json_encode( $response_body ) );
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$this->utils->nuclen_log( "Unexpected response code: $response_code" );
			return false;
		}
		return json_decode( $response_body, true );
	}

	/**
	 * Insert shortcodes automatically if user selected before/after
	 */
	public function nuclen_auto_insert_shortcodes( $content ) {
		$options          = get_option( 'nuclear_engagement_settings', array() );
		$summary_position = ! empty( $options['display_summary'] ) ? $options['display_summary'] : 'none';
		$quiz_position    = ! empty( $options['display_quiz'] ) ? $options['display_quiz'] : 'none';

		if ( $summary_position === $quiz_position && in_array( $summary_position, array( 'before', 'after' ), true ) ) {
			if ( $summary_position === 'before' ) {
				$content = do_shortcode( '[nuclear_engagement_summary]' ) . do_shortcode( '[nuclear_engagement_quiz]' ) . $content;
			} else {
				$content .= do_shortcode( '[nuclear_engagement_summary]' ) . do_shortcode( '[nuclear_engagement_quiz]' );
			}
			return $content;
		}

		if ( $quiz_position === 'before' ) {
			$content = do_shortcode( '[nuclear_engagement_quiz]' ) . $content;
		}
		if ( $summary_position === 'before' ) {
			$content = do_shortcode( '[nuclear_engagement_summary]' ) . $content;
		}
		if ( $quiz_position === 'after' ) {
			$content .= do_shortcode( '[nuclear_engagement_quiz]' );
		}
		if ( $summary_position === 'after' ) {
			$content .= do_shortcode( '[nuclear_engagement_summary]' );
		}
		return $content;
	}

	public function nuclen_register_quiz_shortcodeodes() {
		add_shortcode( 'nuclear_engagement_quiz', array( $this, 'nuclen_render_quiz_shortcode' ) );
	}

	public function nuclen_register_summary_shortcode() {
		add_shortcode( 'nuclear_engagement_summary', array( $this, 'nuclen_render_summary_shortcode' ) );
	}

	public function nuclen_render_quiz_shortcode() {
		$post_id   = get_the_ID();
		$quiz_meta   = get_post_meta( $post_id, 'nuclen-quiz-data', true );
		$quiz_data   = maybe_unserialize( $quiz_meta );
		if ( ! is_array( $quiz_data ) || empty( $quiz_data['questions'] ) ) {
			return '';
		}
		$valid_questions = array_filter(
			$quiz_data['questions'],
			function ( $q ) {
				return isset( $q['question'] ) && trim( $q['question'] ) !== '';
			}
		);
		if ( empty( $valid_questions ) ) {
			return '';
		}

		$options     = get_option( 'nuclear_engagement_settings', array() );
		$quiz_title  = isset( $options['quiz_title'] ) ? $options['quiz_title'] : __( 'Test your knowledge', 'nuclear-engagement' );
		$html_before = isset( $options['custom_quiz_html_before'] ) ? $options['custom_quiz_html_before'] : '';

		$html = '<section id="nuclen-quiz-container">';
		if ( ! empty( trim( $html_before ) ) ) {
			$html .= '<div id="nuclen-quiz-start-message" class="nuclen-fg">' . shortcode_unautop( $html_before ) . '</div>';
		}

		$html .= '
            <h2 class="nuclen-fg">' . esc_html( $quiz_title ) . '</h2>
            <div id="nuclen-quiz-progress-bar-container">
                <div id="nuclen-quiz-progress-bar"></div>
            </div>
            <div id="nuclen-quiz-question-container" class="nuclen-fg"></div>
            <div id="nuclen-quiz-answers-container" class="nuclen-quiz-answers-grid"></div>
            <div id="nuclen-quiz-result-container"></div>
            <div id="nuclen-quiz-explanation-container" class="nuclen-fg nuclen-quiz-hidden"></div>
            <button id="nuclen-quiz-next-button" class="nuclen-quiz-hidden">' . esc_html__( 'Next', 'nuclear-engagement' ) . '</button>
            <div id="nuclen-quiz-final-result-container"></div>
        </section>';

		if ( ! empty( $options['show_attribution'] ) ) {
			$html .= '<div class="nuclen-attribution">'
					. esc_html__( 'Quiz by', 'nuclear-engagement' )
					. ' <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank">'
					. esc_html__( 'Nuclear Engagement', 'nuclear-engagement' )
					. '</a></div>';
		}
		return $html;
	}

	public function nuclen_render_summary_shortcode() {
		$post_id      = get_the_ID();
		$summary_data = get_post_meta( $post_id, 'nuclen-summary-data', true );
		if ( empty( $summary_data ) ) {
			return '';
		}
		if ( empty( trim( $summary_data['summary'] ?? '' ) ) ) {
			return '';
		}

		$options         = get_option( 'nuclear_engagement_settings', array() );
		$summary_content = wp_kses_post( $summary_data['summary'] );
		$summary_title   = isset( $options['summary_title'] ) ? $options['summary_title'] : __( 'Key Facts', 'nuclear-engagement' );

		$html = '<section id="nuclen-summary-container">
            <h2 class="nuclen-fg">' . esc_html( $summary_title ) . '</h2>
            <div class="nuclen-fg" id="nuclen-summary-body">' . $summary_content . '</div>
        </section>';

		if ( ! empty( $options['show_attribution'] ) ) {
			$html .= '<div class="nuclen-attribution">'
					. esc_html__( 'Summary by', 'nuclear-engagement' )
					. ' <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank">'
					. esc_html__( 'Nuclear Engagement', 'nuclear-engagement' )
					. '</a></div>';
		}
		return $html;
	}
}
