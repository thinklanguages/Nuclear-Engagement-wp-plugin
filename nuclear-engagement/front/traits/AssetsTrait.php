<?php
/**
 * Trait: AssetsTrait
 *
 * Handles front-end CSS & JS enqueues and localisation.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AssetsTrait {

	/* ---------- Styles ---------- */
	public function wp_enqueue_styles() {
		/* Base CSS */
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . '../css/nuclen-front.css',
			array(),
			NUCLEN_ASSET_VERSION,
			'all'
		);

		/* Theme CSS (bright / dark / custom / none) */
		$options      = get_option( 'nuclear_engagement_settings', array( 'theme' => 'bright' ) );
		$theme_choice = $options['theme'] ?? 'bright';
		if ( $theme_choice === 'none' ) {
			return;
		}

		if ( $theme_choice === 'bright' ) {
			$quiz_theme_css_url = plugin_dir_url( __FILE__ ) . '../css/nuclen-theme-bright.css';
		} elseif ( $theme_choice === 'dark' ) {
			$quiz_theme_css_url = plugin_dir_url( __FILE__ ) . '../css/nuclen-theme-dark.css';
		} elseif ( $theme_choice === 'custom' ) {
			$css_info           = \NuclearEngagement\Utils::nuclen_get_custom_css_info();
			$quiz_theme_css_url = $css_info['url'];
		} else {
			$quiz_theme_css_url = plugin_dir_url( __FILE__ ) . '../css/nuclen-theme-bright.css';
		}

		wp_enqueue_style(
			$this->plugin_name . '-theme',
			$quiz_theme_css_url,
			array(),
			NUCLEN_ASSET_VERSION,
			'all'
		);
	}

	/* ---------- Scripts ---------- */
	public function wp_enqueue_scripts() {

		wp_enqueue_script(
			$this->plugin_name . '-front',
			plugin_dir_url( dirname( __FILE__ ) ) . 'js/nuclen-front.js',
			array(),
			NUCLEN_ASSET_VERSION,
			true
		);

		$options = get_option( 'nuclear_engagement_settings', array() );

		/* Localise quiz/summary custom HTML & opt-in settings */
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenCustomQuizHtmlAfter',
			$options['custom_quiz_html_after'] ?? ''
		);

		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenOptinEnabled',
			! empty( $options['enable_optin'] )
		);
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenOptinWebhook',
			$options['optin_webhook'] ?? ''
		);
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenOptinSuccessMessage',
			$options['optin_success_message'] ?? __( 'Thank you, your submission was successful!', 'nuclear-engagement' )
		);
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenOptinPosition',
			$options['optin_position'] ?? 'with_results'
		);
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenOptinMandatory',
			! empty( $options['optin_mandatory'] )
		);

		/* Per-post quiz data */
		$post_id   = get_the_ID();
		$quiz_data = get_post_meta( $post_id, 'nuclen-quiz-data', true );
		$quiz_data = maybe_unserialize( $quiz_data );
		$questions = ( is_array( $quiz_data ) && isset( $quiz_data['questions'] ) )
			? $quiz_data['questions']
			: array();
		wp_localize_script( $this->plugin_name . '-front', 'postQuizData', $questions );

		/* Display counts */
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenSettings',
			array(
				'questions_per_quiz'   => (int) ( $options['questions_per_quiz']   ?? 10 ),
				'answers_per_question' => (int) ( $options['answers_per_question'] ?? 4 ),
			)
		);
	}
}
