<?php
namespace NuclearEngagement\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Utils;

/**
 * FrontClass for the FREE plugin
 * (Removed AI remote calls, "send_posts_to_app_backend", or REST endpoints.)
 */
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
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/nuclen-front.css',
			[],
			NUCLEN_ASSET_VERSION
		);
		$options      = get_option( 'nuclear_engagement_settings', [ 'theme' => 'bright' ] );
		$theme_choice = $options['theme'] ?? 'bright';

		if ( $theme_choice === 'none' ) {
			return;
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
			[],
			NUCLEN_ASSET_VERSION
		);
	}

	public function wp_enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name . '-front',
			plugin_dir_url( __DIR__ ) . 'front/js/nuclen-front.js',
			[],
			NUCLEN_ASSET_VERSION,
			true
		);

		$options = get_option( 'nuclear_engagement_settings', [] );
		wp_localize_script( $this->plugin_name . '-front', 'NuclenCustomQuizHtmlAfter', $options['custom_quiz_html_after'] ?? '' );

		$enable_optin          = ! empty( $options['enable_optin'] );
		$optin_webhook         = $options['optin_webhook'] ?? '';
		$optin_success_message = $options['optin_success_message'] ?? __( 'Thank you!', 'nuclear-engagement' );

		wp_localize_script( $this->plugin_name . '-front', 'NuclenOptinEnabled', $enable_optin );
		wp_localize_script( $this->plugin_name . '-front', 'NuclenOptinWebhook', $optin_webhook );
		wp_localize_script( $this->plugin_name . '-front', 'NuclenOptinSuccessMessage', $optin_success_message );

		// For the current post, localize quiz data
		$post_id   = get_the_ID();
		$quiz_data = get_post_meta( $post_id, 'nuclen-quiz-data', true );

		$final_questions = [];
		if ( ! empty( $quiz_data ) && is_array( $quiz_data ) && ! empty( $quiz_data['questions'] ) ) {
			$final_questions = $quiz_data['questions'];
		}
		wp_localize_script( $this->plugin_name . '-front', 'postQuizData', $final_questions );

		$questions_per_quiz   = isset( $options['questions_per_quiz'] ) ? (int) $options['questions_per_quiz'] : 10;
		$answers_per_question = isset( $options['answers_per_question'] ) ? (int) $options['answers_per_question'] : 4;

		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenSettings',
			[
				'questions_per_quiz'   => $questions_per_quiz,
				'answers_per_question' => $answers_per_question,
			]
		);
	}

	public function nuclen_register_quiz_shortcode() {
		add_shortcode( 'nuclear_engagement_quiz', [ $this, 'nuclen_render_quiz_shortcode' ] );
	}

	public function nuclen_register_summary_shortcode() {
		add_shortcode( 'nuclear_engagement_summary', [ $this, 'nuclen_render_summary_shortcode' ] );
	}

	public function nuclen_auto_insert_shortcodes( $content ) {
		$options          = get_option( 'nuclear_engagement_settings', [] );
		$summary_position = $options['display_summary'] ?? 'manual';
		$quiz_position    = $options['display_quiz'] ?? 'manual';

		if ( $summary_position === $quiz_position && in_array( $summary_position, [ 'before', 'after' ], true ) ) {
			if ( $summary_position === 'before' ) {
				return do_shortcode( '[nuclear_engagement_summary]' )
				     . do_shortcode( '[nuclear_engagement_quiz]' )
				     . $content;
			} else {
				return $content
				     . do_shortcode( '[nuclear_engagement_summary]' )
				     . do_shortcode( '[nuclear_engagement_quiz]' );
			}
		}

		if ( $quiz_position === 'before' ) {
			$content = do_shortcode( '[nuclear_engagement_quiz]' ) . $content;
		} elseif ( $quiz_position === 'after' ) {
			$content .= do_shortcode( '[nuclear_engagement_quiz]' );
		}

		if ( $summary_position === 'before' ) {
			$content = do_shortcode( '[nuclear_engagement_summary]' ) . $content;
		} elseif ( $summary_position === 'after' ) {
			$content .= do_shortcode( '[nuclear_engagement_summary]' );
		}

		return $content;
	}

	public function nuclen_render_quiz_shortcode() {
		$post_id   = get_the_ID();
		$quiz_data = get_post_meta( $post_id, 'nuclen-quiz-data', true );
		if ( empty( $quiz_data ) ) {
			return '';
		}

		$options     = get_option( 'nuclear_engagement_settings', [] );
		$quiz_title  = $options['quiz_title'] ?? __( 'Test your knowledge', 'nuclear-engagement' );
		$html_before = $options['custom_quiz_html_before'] ?? '';

		$html = '<section id="nuclen-quiz-container">';
		if ( ! empty( trim( $html_before ) ) ) {
			$html .= '<div id="nuclen-quiz-start-message">' . shortcode_unautop( $html_before ) . '</div>';
		}
		$html .= '
			<h2 class="nuclen-fg">' . esc_html( $quiz_title ) . '</h2>
			<div id="nuclen-quiz-progress-bar-container"><div id="nuclen-quiz-progress-bar"></div></div>
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
		if ( empty( $summary_data ) || empty( $summary_data['summary'] ) ) {
			return '';
		}

		$options         = get_option( 'nuclear_engagement_settings', [] );
		$summary_content = wp_kses_post( $summary_data['summary'] );
		$summary_title   = $options['summary_title'] ?? __( 'Key Facts', 'nuclear-engagement' );

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
