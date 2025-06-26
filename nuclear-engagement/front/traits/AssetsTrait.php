<?php
declare(strict_types=1);
/**
 * File: front/traits/assets-trait.php
 *
 * Trait: AssetsTrait
 *
 * Handles front-end CSS & JS enqueues and localisation.
 *
 * Host class must expose `$plugin_name` and `nuclen_get_settings_repository()`.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

use NuclearEngagement\Core\AssetVersions;
use NuclearEngagement\Modules\Summary\Summary_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AssetsTrait {

	/** Force asset enqueue regardless of detection checks. */
	private bool $force_assets = false;

		/**
		 * Retrieve URL/version for the custom theme file if selected.
		 */
	private function get_theme_assets( string $theme_choice ): array {
		if ( $theme_choice !== 'custom' ) {
				return array(
					'url'     => '',
					'version' => '',
				);
		}

                        $css_info = \NuclearEngagement\Utils\Utils::nuclen_get_custom_css_info();
		if ( empty( $css_info ) || empty( $css_info['url'] ) ) {
				\NuclearEngagement\Services\LoggingService::log( 'Invalid custom CSS info - skipping' );
				return array(
					'url'     => '',
					'version' => '',
				);
		}

			return array(
				'url'     => $css_info['url'],
				'version' => get_option( 'nuclen_custom_css_version', AssetVersions::get( 'front_css' ) ),
			);
	}

	/**
	 * Determine if front-end assets should load on the current request.
	 *
	 * @return bool
	 */
	private function should_load_assets(): bool {
		if ( $this->force_assets ) {
			return true;
		}
		if ( is_admin() || ! is_singular() ) {
			return false;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		$post    = get_post( $post_id );
		$content = $post ? $post->post_content : '';

		if ( function_exists( 'has_block' ) && $post ) {
			if ( has_block( 'nuclear-engagement/quiz', $post ) || has_block( 'nuclear-engagement/summary', $post ) ) {
				return true;
			}
		}

		if ( has_shortcode( $content, 'nuclear_engagement_quiz' ) || has_shortcode( $content, 'nuclear_engagement_summary' ) ) {
			return true;
		}

		$settings_repo   = $this->nuclen_get_settings_repository();
		$display_quiz    = $settings_repo->get( 'display_quiz', 'manual' );
		$display_summary = $settings_repo->get( 'display_summary', 'manual' );

		if ( $display_quiz !== 'manual' && $display_quiz !== 'none' ) {
			$quiz_meta = maybe_unserialize( get_post_meta( $post_id, 'nuclen-quiz-data', true ) );
			if ( is_array( $quiz_meta ) && ! empty( $quiz_meta['questions'] ) ) {
				return true;
			}
		}

                if ( $display_summary !== 'manual' && $display_summary !== 'none' ) {
                        $summary_meta = get_post_meta( $post_id, Summary_Service::META_KEY, true );
			if ( is_array( $summary_meta ) && ! empty( trim( $summary_meta['summary'] ?? '' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/*
	────────────────────────────
		STYLES
	──────────────────────────── */
	public function wp_enqueue_styles() {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		/* Base CSS */
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . '../css/nuclen-front.css',
			array(),
			AssetVersions::get( 'front_css' ),
			'all'
		);

				/* Custom theme CSS */
				$settings_repo = $this->nuclen_get_settings_repository();
				$theme_choice  = $settings_repo->get( 'theme', 'bright' );

		if ( $theme_choice === 'custom' ) {
				$assets = $this->get_theme_assets( $theme_choice );
			if ( $assets['url'] !== '' ) {
						wp_enqueue_style(
							$this->plugin_name . '-theme',
							$assets['url'],
							array(),
							$assets['version'],
							'all'
						);
			}
		}
	}

	/*
	────────────────────────────
		SCRIPTS
	──────────────────────────── */
	public function wp_enqueue_scripts() {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		/* Main bundle */
		wp_enqueue_script(
			$this->plugin_name . '-front',
			plugin_dir_url( __DIR__ ) . 'js/nuclen-front.js',
			array(),
			AssetVersions::get( 'front_js' ),
			true
		);

		$settings_repo = $this->nuclen_get_settings_repository();

		/* ───── Inline scalars (booleans & strings) ───── */
		$inline_js  = '';
		$inline_js .= 'var NuclenOptinEnabled  = ' . ( $settings_repo->get( 'enable_optin', false ) ? 'true' : 'false' ) . ";\n";

		$raw_mandatory   = $settings_repo->get( 'optin_mandatory', false );
		$optin_mandatory = ( $raw_mandatory === true || $raw_mandatory === 1 || $raw_mandatory === '1' );
		$inline_js      .= 'var NuclenOptinMandatory = ' . ( $optin_mandatory ? 'true' : 'false' ) . ";\n";

		$inline_js .= 'var NuclenOptinPosition = ' . json_encode( $settings_repo->get( 'optin_position', 'with_results' ) ) . ";\n";
		$inline_js .= 'var NuclenOptinPromptText = ' . json_encode( $settings_repo->get( 'optin_prompt_text', 'Please enter your details to view your score:' ) ) . ";\n";
		$inline_js .= 'var NuclenOptinButtonText = ' . json_encode( $settings_repo->get( 'optin_button_text', 'Submit' ) ) . ";\n";
		$inline_js .= 'var NuclenOptinWebhook = ' . json_encode( $settings_repo->get( 'optin_webhook', '' ) ) . ";\n";
		$inline_js .= 'var NuclenOptinSuccessMessage = ' . json_encode( $settings_repo->get( 'optin_success_message', '' ) ) . ";\n";
		$inline_js .= 'var NuclenCustomQuizHtmlAfter = ' . json_encode( $settings_repo->get( 'custom_quiz_html_after', '' ) ) . ";\n";

		wp_add_inline_script( $this->plugin_name . '-front', $inline_js, 'before' );

		/* ► NEW ◄ – endpoint & nonce for AJAX opt-in storage */
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenOptinAjax',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'nuclen_optin_nonce' ),
			)
		);

		/* Per-post quiz data */
		$post_id   = get_the_ID();
		$quiz_meta = maybe_unserialize( get_post_meta( $post_id, 'nuclen-quiz-data', true ) );
		$questions = ( is_array( $quiz_meta ) && isset( $quiz_meta['questions'] ) ) ? $quiz_meta['questions'] : array();
		wp_localize_script( $this->plugin_name . '-front', 'postQuizData', $questions );

		/* Numeric settings */
		wp_localize_script(
			$this->plugin_name . '-front',
			'NuclenSettings',
			array(
				'questions_per_quiz'   => $settings_repo->get_int( 'questions_per_quiz', 10 ),
				'answers_per_question' => $settings_repo->get_int( 'answers_per_question', 4 ),
			)
		);

		/* Translatable strings for the quiz */
		$ne_strings = array(
			'retake_test'   => $settings_repo->get( 'quiz_label_retake_test', __( 'Retake Test', 'nuclear-engagement' ) ),
			'your_score'    => $settings_repo->get( 'quiz_label_your_score', __( 'Your Score', 'nuclear-engagement' ) ),
			'perfect'       => $settings_repo->get( 'quiz_label_perfect', __( 'Perfect!', 'nuclear-engagement' ) ),
			'well_done'     => $settings_repo->get( 'quiz_label_well_done', __( 'Well done!', 'nuclear-engagement' ) ),
			'retake_prompt' => $settings_repo->get( 'quiz_label_retake_prompt', __( 'Why not retake the test?', 'nuclear-engagement' ) ),
			'correct'       => $settings_repo->get( 'quiz_label_correct', __( 'Correct:', 'nuclear-engagement' ) ),
			'your_answer'   => $settings_repo->get( 'quiz_label_your_answer', __( 'Your answer:', 'nuclear-engagement' ) ),
		);
		wp_localize_script( $this->plugin_name . '-front', 'NuclenStrings', $ne_strings );
	}

	/**
	 * Force asset enqueue when shortcodes run outside post content.
	 */
	public function nuclen_force_enqueue_assets(): void {
		$this->force_assets = true;
		$this->wp_enqueue_styles();
		$this->wp_enqueue_scripts();
		$this->force_assets = false;
	}
}
