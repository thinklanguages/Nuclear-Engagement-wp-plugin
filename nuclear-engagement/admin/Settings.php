<?php
// file: nuclear-engagement/admin/Settings.php

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'nuclen_enqueue_color_picker' ] );
	}

	public function nuclen_enqueue_color_picker( $hook_suffix ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(document).ready(function($){$(".wp-color-picker-field").wpColorPicker();});' );
	}

	/**
	 * Sanitize all "free plugin" settings. We skip generation fields here.
	 * Instead, we let the Pro plugin handle them via a filter, so we don’t
	 * overwrite or discard them.
	 */
	public function nuclen_sanitize_settings( $input ) {

		// We load all defaults (which DO include generation fields),
		// but we'll handle only the free subset here.
		$valid_themes = [ 'bright', 'dark', 'custom', 'none' ];
		$theme        = isset( $input['theme'] ) ? sanitize_text_field( $input['theme'] ) : 'bright';
		if ( ! in_array( $theme, $valid_themes, true ) ) {
			$theme = 'bright';
		}

		$quiz_font_size = isset( $input['font_size'] ) ? (int) $input['font_size'] : 16;
		if ( $quiz_font_size < 10 ) {
			$quiz_font_size = 10;
		} elseif ( $quiz_font_size > 50 ) {
			$quiz_font_size = 50;
		}
		$quiz_font_color = isset( $input['font_color'] ) ? sanitize_hex_color( $input['font_color'] ) : '#000000';
		$quiz_bg_color   = isset( $input['bg_color'] ) ? sanitize_text_field( $input['bg_color'] ) : '#ffffff';

		$quiz_border_color = isset( $input['quiz_border_color'] ) ? sanitize_hex_color( $input['quiz_border_color'] ) : '#000000';
		$quiz_border_style = isset( $input['quiz_border_style'] ) ? sanitize_text_field( $input['quiz_border_style'] ) : 'solid';
		$quiz_border_width = isset( $input['quiz_border_width'] ) ? (int) $input['quiz_border_width'] : 1;
		$quiz_border_radius = isset( $input['quiz_border_radius'] ) ? (int) $input['quiz_border_radius'] : 6;
		$quiz_shadow_color  = isset( $input['quiz_shadow_color'] ) ? sanitize_text_field( $input['quiz_shadow_color'] ) : 'rgba(0,0,0,0.15)';
		$quiz_shadow_blur   = isset( $input['quiz_shadow_blur'] ) ? (int) $input['quiz_shadow_blur'] : 8;

		$quiz_answer_button_bg_color      = isset( $input['quiz_answer_button_bg_color'] ) ? sanitize_hex_color( $input['quiz_answer_button_bg_color'] ) : '#94544A';
		$quiz_answer_button_border_color  = isset( $input['quiz_answer_button_border_color'] ) ? sanitize_hex_color( $input['quiz_answer_button_border_color'] ) : '#94544A';
		$quiz_answer_button_border_width  = isset( $input['quiz_answer_button_border_width'] ) ? (int) $input['quiz_answer_button_border_width'] : 2;
		$quiz_answer_button_border_radius = isset( $input['quiz_answer_button_border_radius'] ) ? (int) $input['quiz_answer_button_border_radius'] : 4;

		$quiz_progress_bar_fg_color = isset( $input['quiz_progress_bar_fg_color'] ) ? sanitize_hex_color( $input['quiz_progress_bar_fg_color'] ) : '#1B977D';
		$quiz_progress_bar_bg_color = isset( $input['quiz_progress_bar_bg_color'] ) ? sanitize_hex_color( $input['quiz_progress_bar_bg_color'] ) : '#e0e0e0';
		$quiz_progress_bar_height   = isset( $input['quiz_progress_bar_height'] ) ? (int) $input['quiz_progress_bar_height'] : 10;

		$summary_font_size  = isset( $input['summary_font_size'] ) ? (int) $input['summary_font_size'] : 16;
		if ( $summary_font_size < 10 ) {
			$summary_font_size = 10;
		} elseif ( $summary_font_size > 50 ) {
			$summary_font_size = 50;
		}
		$summary_font_color = isset( $input['summary_font_color'] ) ? sanitize_hex_color( $input['summary_font_color'] ) : '#000000';
		$summary_bg_color   = isset( $input['summary_bg_color'] ) ? sanitize_text_field( $input['summary_bg_color'] ) : '#ffffff';

		$summary_border_color = isset( $input['summary_border_color'] ) ? sanitize_hex_color( $input['summary_border_color'] ) : '#000000';
		$summary_border_style = isset( $input['summary_border_style'] ) ? sanitize_text_field( $input['summary_border_style'] ) : 'solid';
		$summary_border_width = isset( $input['summary_border_width'] ) ? (int) $input['summary_border_width'] : 1;
		$summary_border_radius = isset( $input['summary_border_radius'] ) ? (int) $input['summary_border_radius'] : 6;
		$summary_shadow_color  = isset( $input['summary_shadow_color'] ) ? sanitize_text_field( $input['summary_shadow_color'] ) : 'rgba(0,0,0,0.15)';
		$summary_shadow_blur   = isset( $input['summary_shadow_blur'] ) ? (int) $input['summary_shadow_blur'] : 8;

		$questions_per_quiz   = isset( $input['questions_per_quiz'] ) ? (int) $input['questions_per_quiz'] : 10;
		$answers_per_question = isset( $input['answers_per_question'] ) ? (int) $input['answers_per_question'] : 4;

		$allowed_display = [ 'manual', 'before', 'after' ];
		$display_summary = isset( $input['display_summary'] ) ? sanitize_text_field( $input['display_summary'] ) : 'manual';
		if ( ! in_array( $display_summary, $allowed_display, true ) ) {
			$display_summary = 'manual';
		}
		$display_quiz = isset( $input['display_quiz'] ) ? sanitize_text_field( $input['display_quiz'] ) : 'manual';
		if ( ! in_array( $display_quiz, $allowed_display, true ) ) {
			$display_quiz = 'manual';
		}

		$custom_quiz_html_before = isset( $input['custom_quiz_html_before'] ) ? wp_kses_post( $input['custom_quiz_html_before'] ) : '';
		$custom_quiz_html_after  = isset( $input['custom_quiz_html_after'] ) ? wp_kses_post( $input['custom_quiz_html_after'] ) : '';

		$quiz_title    = isset( $input['quiz_title'] ) ? sanitize_text_field( $input['quiz_title'] ) : 'Test your knowledge';
		$summary_title = isset( $input['summary_title'] ) ? sanitize_text_field( $input['summary_title'] ) : 'Key Facts';

		$enable_optin          = isset( $input['enable_optin'] ) ? boolval( $input['enable_optin'] ) : false;
		$optin_webhook         = isset( $input['optin_webhook'] ) ? esc_url_raw( $input['optin_webhook'] ) : '';
		$optin_success_message = isset( $input['optin_success_message'] ) ? sanitize_text_field( $input['optin_success_message'] ) : 'Thank you, your submission was successful!';
		if ( $enable_optin && empty( $optin_webhook ) ) {
			$enable_optin = false;
		}

		$show_attribution = isset( $input['show_attribution'] ) ? boolval( $input['show_attribution'] ) : false;

		// Build the sanitized array for free fields only
		$sanitized = [
			'theme'                            => $theme,

			'font_size'                        => $quiz_font_size,
			'font_color'                       => $quiz_font_color,
			'bg_color'                         => $quiz_bg_color,

			'quiz_border_color'                => $quiz_border_color,
			'quiz_border_style'                => $quiz_border_style,
			'quiz_border_width'                => $quiz_border_width,
			'quiz_border_radius'               => $quiz_border_radius,
			'quiz_shadow_color'                => $quiz_shadow_color,
			'quiz_shadow_blur'                 => $quiz_shadow_blur,

			'quiz_answer_button_bg_color'      => $quiz_answer_button_bg_color,
			'quiz_answer_button_border_color'  => $quiz_answer_button_border_color,
			'quiz_answer_button_border_width'  => $quiz_answer_button_border_width,
			'quiz_answer_button_border_radius' => $quiz_answer_button_border_radius,

			'quiz_progress_bar_fg_color'       => $quiz_progress_bar_fg_color,
			'quiz_progress_bar_bg_color'       => $quiz_progress_bar_bg_color,
			'quiz_progress_bar_height'         => $quiz_progress_bar_height,

			'summary_font_size'                => $summary_font_size,
			'summary_font_color'               => $summary_font_color,
			'summary_bg_color'                 => $summary_bg_color,

			'summary_border_color'             => $summary_border_color,
			'summary_border_style'             => $summary_border_style,
			'summary_border_width'             => $summary_border_width,
			'summary_border_radius'            => $summary_border_radius,
			'summary_shadow_color'             => $summary_shadow_color,
			'summary_shadow_blur'              => $summary_shadow_blur,

			'questions_per_quiz'               => $questions_per_quiz,
			'answers_per_question'             => $answers_per_question,

			'display_summary'                  => $display_summary,
			'display_quiz'                     => $display_quiz,

			'custom_quiz_html_before'          => $custom_quiz_html_before,
			'custom_quiz_html_after'           => $custom_quiz_html_after,

			'quiz_title'                       => $quiz_title,
			'summary_title'                    => $summary_title,

			'enable_optin'                     => $enable_optin,
			'optin_webhook'                    => $optin_webhook,
			'optin_success_message'            => $optin_success_message,

			'show_attribution'                 => $show_attribution,
		];

		// Now let Pro plugin add/sanitize the generation fields before returning.
		$sanitized = apply_filters( 'nuclear_engagement_pro_sanitize_generation_fields', $sanitized, $input );

		return $sanitized;
	}

	public function nuclen_display_settings_page() {
		$raw_settings = get_option( 'nuclear_engagement_settings', [] );
		$defaults     = \NuclearEngagement\Defaults::nuclen_get_default_settings();
		// Merge them once for initial display
		$settings     = wp_parse_args( $this->nuclen_sanitize_settings( $raw_settings ), $defaults );

		if (
			isset( $_POST['nuclen_save_settings'] )
			&& check_admin_referer( 'nuclen_settings_nonce', 'nuclen_settings_nonce_field' )
		) {
			$raw_new_settings = [];

			// Collect the free plugin fields from $_POST:
			$fields_map = [
				'nuclen_theme'                        => 'theme',
				'nuclen_font_size'                    => 'font_size',
				'nuclen_font_color'                   => 'font_color',
				'nuclen_bg_color'                     => 'bg_color',
				'nuclen_quiz_border_color'            => 'quiz_border_color',
				'nuclen_quiz_border_style'            => 'quiz_border_style',
				'nuclen_quiz_border_width'            => 'quiz_border_width',
				'nuclen_quiz_border_radius'           => 'quiz_border_radius',
				'nuclen_quiz_shadow_color'            => 'quiz_shadow_color',
				'nuclen_quiz_shadow_blur'             => 'quiz_shadow_blur',
				'nuclen_quiz_answer_button_bg_color'  => 'quiz_answer_button_bg_color',
				'nuclen_quiz_answer_button_border_color'  => 'quiz_answer_button_border_color',
				'nuclen_quiz_answer_button_border_width'  => 'quiz_answer_button_border_width',
				'nuclen_quiz_answer_button_border_radius' => 'quiz_answer_button_border_radius',
				'nuclen_quiz_progress_bar_fg_color'   => 'quiz_progress_bar_fg_color',
				'nuclen_quiz_progress_bar_bg_color'   => 'quiz_progress_bar_bg_color',
				'nuclen_quiz_progress_bar_height'     => 'quiz_progress_bar_height',
				'nuclen_summary_font_size'            => 'summary_font_size',
				'nuclen_summary_font_color'           => 'summary_font_color',
				'nuclen_summary_bg_color'             => 'summary_bg_color',
				'nuclen_summary_border_color'         => 'summary_border_color',
				'nuclen_summary_border_style'         => 'summary_border_style',
				'nuclen_summary_border_width'         => 'summary_border_width',
				'nuclen_summary_border_radius'        => 'summary_border_radius',
				'nuclen_summary_shadow_color'         => 'summary_shadow_color',
				'nuclen_summary_shadow_blur'          => 'summary_shadow_blur',
				'nuclen_questions_per_quiz'           => 'questions_per_quiz',
				'nuclen_answers_per_question'         => 'answers_per_question',
				'nuclen_display_summary'              => 'display_summary',
				'nuclen_display_quiz'                 => 'display_quiz',
			];

			foreach ( $fields_map as $post_key => $settings_key ) {
				if ( isset( $_POST[ $post_key ] ) ) {
					$raw_new_settings[ $settings_key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
				}
			}

			$raw_new_settings['custom_quiz_html_before'] = isset( $_POST['custom_quiz_html_before'] )
				? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_before'] ) )
				: '';
			$raw_new_settings['custom_quiz_html_after'] = isset( $_POST['custom_quiz_html_after'] )
				? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_after'] ) )
				: '';
			$raw_new_settings['quiz_title']    = isset( $_POST['quiz_title'] )
				? sanitize_text_field( wp_unslash( $_POST['quiz_title'] ) )
				: '';
			$raw_new_settings['summary_title'] = isset( $_POST['summary_title'] )
				? sanitize_text_field( wp_unslash( $_POST['summary_title'] ) )
				: '';

			$raw_new_settings['enable_optin'] = ! empty( $_POST['enable_optin'] );
			if ( isset( $_POST['optin_webhook'] ) ) {
				$raw_new_settings['optin_webhook'] = esc_url_raw( wp_unslash( $_POST['optin_webhook'] ) );
			}
			$raw_new_settings['optin_success_message'] = isset( $_POST['optin_success_message'] )
				? sanitize_text_field( wp_unslash( $_POST['optin_success_message'] ) )
				: '';

			$raw_new_settings['show_attribution'] = ! empty( $_POST['show_attribution'] );

			// Let the Pro plugin collect its generation fields from $_POST as well
			$raw_new_settings = apply_filters( 'nuclear_engagement_pro_collect_generation_fields', $raw_new_settings );

			// Now sanitize everything together
			$new_settings = $this->nuclen_sanitize_settings( $raw_new_settings );

			update_option( 'nuclear_engagement_settings', $new_settings );

			// If custom theme, regenerate custom CSS
			if ( $new_settings['theme'] === 'custom' ) {
				$custom_css = "
:root {
  --nuclen-quiz-font-color: {$new_settings['font_color']};
  --nuclen-quiz-bg-color: {$new_settings['bg_color']};
  --nuclen-quiz-border-color: {$new_settings['quiz_border_color']};
  --nuclen-quiz-border-style: {$new_settings['quiz_border_style']};
  --nuclen-quiz-border-width: {$new_settings['quiz_border_width']}px;
  --nuclen-quiz-border-radius: {$new_settings['quiz_border_radius']}px;
  --nuclen-quiz-shadow-color: {$new_settings['quiz_shadow_color']};
  --nuclen-quiz-shadow-blur: {$new_settings['quiz_shadow_blur']}px;

  --nuclen-quiz-button-bg: {$new_settings['quiz_answer_button_bg_color']};
  --nuclen-quiz-button-border-color: {$new_settings['quiz_answer_button_border_color']};
  --nuclen-quiz-button-border-width: {$new_settings['quiz_answer_button_border_width']}px;
  --nuclen-quiz-button-border-radius: {$new_settings['quiz_answer_button_border_radius']}px;

  --nuclen-quiz-progress-fg: {$new_settings['quiz_progress_bar_fg_color']};
  --nuclen-quiz-progress-bg: {$new_settings['quiz_progress_bar_bg_color']};
  --nuclen-quiz-progress-height: {$new_settings['quiz_progress_bar_height']}px;

  --nuclen-summary-font-color: {$new_settings['summary_font_color']};
  --nuclen-summary-bg-color: {$new_settings['summary_bg_color']};
  --nuclen-summary-border-color: {$new_settings['summary_border_color']};
  --nuclen-summary-border-style: {$new_settings['summary_border_style']};
  --nuclen-summary-border-width: {$new_settings['summary_border_width']}px;
  --nuclen-summary-border-radius: {$new_settings['summary_border_radius']}px;
  --nuclen-summary-shadow-color: {$new_settings['summary_shadow_color']};
  --nuclen-summary-shadow-blur: {$new_settings['summary_shadow_blur']}px;
}
#nuclen-quiz-container {
  font-size: {$new_settings['font_size']}px;
  color: var(--nuclen-quiz-font-color);
  background-color: var(--nuclen-quiz-bg-color);
  border: var(--nuclen-quiz-border-width) var(--nuclen-quiz-border-style) var(--nuclen-quiz-border-color);
  border-radius: var(--nuclen-quiz-border-radius);
  box-shadow: 0 0 var(--nuclen-quiz-shadow-blur) var(--nuclen-quiz-shadow-color);
}
#nuclen-summary-container {
  font-size: {$new_settings['summary_font_size']}px;
  color: var(--nuclen-summary-font-color);
  background-color: var(--nuclen-summary-bg-color);
  border: var(--nuclen-summary-border-width) var(--nuclen-summary-border-style) var(--nuclen-summary-border-color);
  border-radius: var(--nuclen-summary-border-radius);
  box-shadow: 0 0 var(--nuclen-summary-shadow-blur) var(--nuclen-summary-shadow-color);
}
";
				$css_info = \NuclearEngagement\Utils::nuclen_get_custom_css_info();
				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				WP_Filesystem();
				global $wp_filesystem;

				if ( $wp_filesystem && $wp_filesystem->exists( $css_info['dir'] ) && $wp_filesystem->is_writable( $css_info['dir'] ) ) {
					$wp_filesystem->put_contents( $css_info['path'], $custom_css );
				} else {
					@file_put_contents( $css_info['path'], $custom_css );
				}
			}

			// Re-merge for display
			$settings = wp_parse_args( $new_settings, $defaults );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'nuclear-engagement' ) . '</p></div>';
		}

		include plugin_dir_path( __FILE__ ) . 'partials/nuclen-admin-settings.php';
	}
}
