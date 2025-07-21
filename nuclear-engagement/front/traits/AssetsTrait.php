<?php
/**
 * AssetsTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Front
 */

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
	/**
	 * Build inline JS variables for opt-in settings.
	 */
	private function get_optin_inline_js(): string {
		$settings_repo   = $this->nuclen_get_settings_repository();
		$inline_js       = '';
		
		// Sanitize boolean values
		$inline_js      .= 'window.NuclenOptinEnabled  = ' . ( $settings_repo->get( 'enable_optin', false ) ? 'true' : 'false' ) . ";\n";
		$raw_mandatory   = $settings_repo->get( 'optin_mandatory', false );
		$optin_mandatory = ( $raw_mandatory === true || $raw_mandatory === 1 || $raw_mandatory === '1' );
		$inline_js      .= 'window.NuclenOptinMandatory = ' . ( $optin_mandatory ? 'true' : 'false' ) . ";\n";
		
		// Sanitize and encode string values
		$optin_position = sanitize_text_field( $settings_repo->get( 'optin_position', 'with_results' ) );
		$inline_js      .= 'window.NuclenOptinPosition = ' . wp_json_encode( $optin_position ) . ";\n";
		
		// Sanitize text fields before encoding
		$prompt_text = wp_kses_post( $settings_repo->get( 'optin_prompt_text', 'Please enter your details to view your score:' ) );
		$inline_js      .= 'window.NuclenOptinPromptText = ' . wp_json_encode( $prompt_text ) . ";\n";
		
		$button_text = sanitize_text_field( $settings_repo->get( 'optin_button_text', 'Submit' ) );
		$inline_js      .= 'window.NuclenOptinButtonText = ' . wp_json_encode( $button_text ) . ";\n";
		
		// Sanitize URL for webhook - don't expose if empty
		$webhook_url = esc_url_raw( $settings_repo->get( 'optin_webhook', '' ) );
		if ( ! empty( $webhook_url ) ) {
			// Only expose webhook URL if it's properly configured
			$inline_js      .= 'window.NuclenOptinWebhook = ' . wp_json_encode( $webhook_url ) . ";\n";
		} else {
			$inline_js      .= 'window.NuclenOptinWebhook = "";\n';
		}
		
		$success_message = wp_kses_post( $settings_repo->get( 'optin_success_message', '' ) );
		$inline_js      .= 'window.NuclenOptinSuccessMessage = ' . wp_json_encode( $success_message ) . ";\n";
		
		// Sanitize custom HTML more strictly
		$custom_html = wp_kses_post( $settings_repo->get( 'custom_quiz_html_after', '' ) );
		$inline_js      .= 'window.NuclenCustomQuizHtmlAfter = ' . wp_json_encode( $custom_html ) . ";\n";
		
		return $inline_js;
	}

	/**
	 * Data for AJAX opt-in requests.
	 */
	private function get_optin_ajax_data(): array {
		return array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'nuclen_optin_nonce' ),
		);
	}

	/**
	 * Retrieve quiz questions for the current post.
	 */
	private function get_post_quiz_data(): array {
		$post_id = get_the_ID();

		// Validate post ID before proceeding.
		if ( ! $post_id || ! is_int( $post_id ) ) {
			return array();
		}

		$quiz_meta = maybe_unserialize( get_post_meta( $post_id, 'nuclen-quiz-data', true ) );
		return ( is_array( $quiz_meta ) && isset( $quiz_meta['questions'] ) ) ? $quiz_meta['questions'] : array();
	}

	/**
	 * Numeric settings used by the front-end.
	 */
	private function get_numeric_settings(): array {
		$repo = $this->nuclen_get_settings_repository();
		return array(
			'questions_per_quiz'   => $repo->get_int( 'questions_per_quiz', 10 ),
			'answers_per_question' => $repo->get_int( 'answers_per_question', 4 ),
		);
	}

	/**
	 * Translatable labels for the quiz interface.
	 */
	private function get_translatable_strings(): array {
		$repo = $this->nuclen_get_settings_repository();
		return array(
			'retake_test'   => $repo->get( 'quiz_label_retake_test', __( 'Retake Test', 'nuclear-engagement' ) ),
			'your_score'    => $repo->get( 'quiz_label_your_score', __( 'Your Score', 'nuclear-engagement' ) ),
			'perfect'       => $repo->get( 'quiz_label_perfect', __( 'Perfect!', 'nuclear-engagement' ) ),
			'well_done'     => $repo->get( 'quiz_label_well_done', __( 'Well done!', 'nuclear-engagement' ) ),
			'retake_prompt' => $repo->get( 'quiz_label_retake_prompt', __( 'Why not retake the test?', 'nuclear-engagement' ) ),
			'correct'       => $repo->get( 'quiz_label_correct', __( 'Correct:', 'nuclear-engagement' ) ),
			'your_answer'   => $repo->get( 'quiz_label_your_answer', __( 'Your answer:', 'nuclear-engagement' ) ),
		);
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
				NUCLEN_PLUGIN_URL . 'front/css/nuclen-front.css',
				array(),
				NUCLEN_ASSET_VERSION,
				'all'
			);

				/* Theme CSS */
				$settings_repo = $this->nuclen_get_settings_repository();
				$theme_choice  = $settings_repo->get( 'theme', 'light' );

				// Convert 'bright' to 'light' for backward compatibility.
		if ( $theme_choice === 'bright' ) {
			$theme_choice = 'light';
		}

		// Theme-specific CSS is now handled via inline CSS variables in wp_head_custom_theme_vars().
		// The dark theme is handled via data-theme attribute in nuclen-front.css
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
			NUCLEN_PLUGIN_URL . 'front/js/nuclen-front.js',
			array(),
			NUCLEN_ASSET_VERSION,
			true
		);

		// Ensure script is loaded as ES6 module.
		add_filter(
			'script_loader_tag',
			function ( $tag, $handle, $src ) {
				if ( $handle === $this->plugin_name . '-front' ) {
					return '<script type="module" src="' . esc_url( $src ) . '"></script>' . "\n";
				}
				return $tag;
			},
			10,
			3
		);

		$settings_repo = $this->nuclen_get_settings_repository();

		/* ───── Inline scalars (booleans & strings) ───── */
		wp_add_inline_script( $this->plugin_name . '-front', $this->get_optin_inline_js(), 'before' );

		/* ► NEW ◄ – endpoint & nonce for AJAX opt-in storage */
		wp_localize_script( $this->plugin_name . '-front', 'NuclenOptinAjax', $this->get_optin_ajax_data() );

		/* Per-post quiz data */
				wp_localize_script( $this->plugin_name . '-front', 'postQuizData', $this->get_post_quiz_data() );

				/* Numeric settings */
				wp_localize_script( $this->plugin_name . '-front', 'NuclenSettings', $this->get_numeric_settings() );

				/* Translatable strings for the quiz */
		wp_localize_script( $this->plugin_name . '-front', 'NuclenStrings', $this->get_translatable_strings() );
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

	/**
	 * Output custom theme CSS variables inline
	 */
	public function wp_head_custom_theme_vars(): void {
		$settings_repo = $this->nuclen_get_settings_repository();
		$theme_choice  = $settings_repo->get( 'theme', 'light' );

		// Convert 'bright' to 'light' for backward compatibility.
		if ( $theme_choice === 'bright' ) {
			$theme_choice = 'light';
		}

		// Only output custom CSS variables for custom theme.
		if ( $theme_choice !== 'custom' ) {
			return;
		}

		// Get all settings with defaults.
		$defaults = \NuclearEngagement\Core\Defaults::nuclen_get_default_settings();
		$settings = array();
		foreach ( $defaults as $key => $default_value ) {
			$settings[ $key ] = $settings_repo->get( $key, $default_value );
		}

		// Output CSS variables inline.
		?>
		<style id="nuclen-custom-theme-vars">
		.nuclen-root {
			/* Quiz container */
			--nuclen-fg-color: <?php echo esc_attr( $settings['font_color'] ); ?>;
			--nuclen-quiz-font-size: <?php echo esc_attr( $settings['font_size'] ); ?>px;
			--nuclen-quiz-font-color: <?php echo esc_attr( $settings['font_color'] ); ?>;
			--nuclen-quiz-bg-color: <?php echo esc_attr( $settings['bg_color'] ); ?>;
			--nuclen-quiz-border-color: <?php echo esc_attr( $settings['quiz_border_color'] ); ?>;
			--nuclen-quiz-border-style: <?php echo esc_attr( $settings['quiz_border_style'] ); ?>;
			--nuclen-quiz-border-width: <?php echo esc_attr( $settings['quiz_border_width'] ); ?>px;
			--nuclen-quiz-border-radius: <?php echo esc_attr( $settings['quiz_border_radius'] ); ?>px;
			--nuclen-quiz-shadow-color: <?php echo esc_attr( $settings['quiz_shadow_color'] ); ?>;
			--nuclen-quiz-shadow-blur: <?php echo esc_attr( $settings['quiz_shadow_blur'] ); ?>px;

			/* Quiz answer buttons */
			--nuclen-quiz-button-bg: <?php echo esc_attr( $settings['quiz_answer_button_bg_color'] ); ?>;
			--nuclen-quiz-button-border-color: <?php echo esc_attr( $settings['quiz_answer_button_border_color'] ); ?>;
			--nuclen-quiz-button-border-width: <?php echo esc_attr( $settings['quiz_answer_button_border_width'] ); ?>px;
			--nuclen-quiz-button-border-radius: <?php echo esc_attr( $settings['quiz_answer_button_border_radius'] ); ?>px;

			/* Progress bar */
			--nuclen-quiz-progress-fg: <?php echo esc_attr( $settings['quiz_progress_bar_fg_color'] ); ?>;
			--nuclen-quiz-progress-bg: <?php echo esc_attr( $settings['quiz_progress_bar_bg_color'] ); ?>;
			--nuclen-quiz-progress-height: <?php echo esc_attr( $settings['quiz_progress_bar_height'] ); ?>px;

			/* Summary container */
			--nuclen-summary-font-size: <?php echo esc_attr( $settings['summary_font_size'] ); ?>px;
			--nuclen-summary-font-color: <?php echo esc_attr( $settings['summary_font_color'] ); ?>;
			--nuclen-summary-bg-color: <?php echo esc_attr( $settings['summary_bg_color'] ); ?>;
			--nuclen-summary-border-color: <?php echo esc_attr( $settings['summary_border_color'] ); ?>;
			--nuclen-summary-border-style: <?php echo esc_attr( $settings['summary_border_style'] ); ?>;
			--nuclen-summary-border-width: <?php echo esc_attr( $settings['summary_border_width'] ); ?>px;
			--nuclen-summary-border-radius: <?php echo esc_attr( $settings['summary_border_radius'] ); ?>px;
			--nuclen-summary-shadow-color: <?php echo esc_attr( $settings['summary_shadow_color'] ); ?>;
			--nuclen-summary-shadow-blur: <?php echo esc_attr( $settings['summary_shadow_blur'] ); ?>px;

			/* TOC container */
			--nuclen-toc-font-size: <?php echo esc_attr( $settings['toc_font_size'] ); ?>px;
			--nuclen-toc-font-color: <?php echo esc_attr( $settings['toc_font_color'] ); ?>;
			--nuclen-toc-bg-color: <?php echo esc_attr( $settings['toc_bg_color'] ); ?>;
			--nuclen-toc-border-color: <?php echo esc_attr( $settings['toc_border_color'] ); ?>;
			--nuclen-toc-border-style: <?php echo esc_attr( $settings['toc_border_style'] ); ?>;
			--nuclen-toc-border-width: <?php echo esc_attr( $settings['toc_border_width'] ); ?>px;
			--nuclen-toc-border-radius: <?php echo esc_attr( $settings['toc_border_radius'] ); ?>px;
			--nuclen-toc-shadow-color: <?php echo esc_attr( $settings['toc_shadow_color'] ); ?>;
			--nuclen-toc-shadow-blur: <?php echo esc_attr( $settings['toc_shadow_blur'] ); ?>px;
			--nuclen-toc-link: <?php echo esc_attr( $settings['toc_link_color'] ); ?>;
			--nuclen-toc-sticky-max-width: <?php echo esc_attr( $settings['toc_sticky_max_width'] ); ?>px;
		}
		</style>
		<?php
	}
}
