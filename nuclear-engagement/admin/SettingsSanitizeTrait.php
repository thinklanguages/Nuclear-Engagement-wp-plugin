<?php
/**
 * File: admin/SettingsSanitizeTrait.php
 *
 * Sanitises and normalises every option field.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

trait SettingsSanitizeTrait {

	/**
	 * Sanitise all settings.
	 *
	 * @param array $input Raw $_POST‐like array.
	 * @return array       Clean, validated settings.
	 */
	public function nuclen_sanitize_settings( $input ) {
		$valid_themes = array( 'bright', 'dark', 'custom', 'none' );
		$theme        = isset( $input['theme'] ) && ! empty( $input['theme'] ) ? sanitize_text_field( $input['theme'] ) : 'bright';
		if ( ! in_array( $theme, $valid_themes, true ) ) {
			$theme = 'bright';
		}

		/* ─────── QUIZ STYLE ─────── */
		$quiz_font_size = isset( $input['font_size'] ) ? (int) $input['font_size'] : 16;
		$quiz_font_size = max( 10, min( 50, $quiz_font_size ) );

		$quiz_font_color = isset( $input['font_color'] ) && ! empty( $input['font_color'] )
			? sanitize_hex_color( $input['font_color'] )
			: '#000000';

		$quiz_bg_color = isset( $input['bg_color'] ) && ! empty( $input['bg_color'] )
			? sanitize_hex_color( $input['bg_color'] )
			: '#ffffff';

		$quiz_border_color = isset( $input['quiz_border_color'] ) ? sanitize_hex_color( $input['quiz_border_color'] ) : '';
		$quiz_border_style = isset( $input['quiz_border_style'] ) ? sanitize_text_field( $input['quiz_border_style'] ) : '';
		$quiz_border_width = isset( $input['quiz_border_width'] ) ? (int) $input['quiz_border_width'] : '';

		// Back-compat for old border_* keys.
		if ( empty( $quiz_border_color ) && ! empty( $input['border_color'] ) ) {
			$quiz_border_color = sanitize_hex_color( $input['border_color'] );
		}
		if ( empty( $quiz_border_style ) && ! empty( $input['border_style'] ) ) {
			$quiz_border_style = sanitize_text_field( $input['border_style'] );
		}
		if ( empty( $quiz_border_width ) && isset( $input['border_width'] ) ) {
			$quiz_border_width = (int) $input['border_width'];
		}
		if ( empty( $quiz_border_color ) ) {
			$quiz_border_color = '#000000';
		}
		if ( empty( $quiz_border_style ) ) {
			$quiz_border_style = 'solid';
		}
		if ( empty( $quiz_border_width ) ) {
			$quiz_border_width = 1;
		}

		$quiz_border_radius = isset( $input['quiz_border_radius'] ) ? (int) $input['quiz_border_radius'] : 6;
		$quiz_shadow_color  = isset( $input['quiz_shadow_color'] ) ? sanitize_text_field( $input['quiz_shadow_color'] ) : 'rgba(0,0,0,0.15)';
		$quiz_shadow_blur   = isset( $input['quiz_shadow_blur'] ) ? (int) $input['quiz_shadow_blur'] : 8;

		$quiz_answer_button_bg_color      = isset( $input['quiz_answer_button_bg_color'] )      ? sanitize_hex_color( $input['quiz_answer_button_bg_color'] )      : '#94544A';
		$quiz_answer_button_border_color  = isset( $input['quiz_answer_button_border_color'] )  ? sanitize_hex_color( $input['quiz_answer_button_border_color'] )  : '#94544A';
		$quiz_answer_button_border_width  = isset( $input['quiz_answer_button_border_width'] )  ? (int) $input['quiz_answer_button_border_width']                  : 2;
		$quiz_answer_button_border_radius = isset( $input['quiz_answer_button_border_radius'] ) ? (int) $input['quiz_answer_button_border_radius']                 : 4;

		$quiz_progress_bar_fg_color = isset( $input['quiz_progress_bar_fg_color'] ) ? sanitize_hex_color( $input['quiz_progress_bar_fg_color'] ) : '#1B977D';
		$quiz_progress_bar_bg_color = isset( $input['quiz_progress_bar_bg_color'] ) ? sanitize_hex_color( $input['quiz_progress_bar_bg_color'] ) : '#e0e0e0';
		$quiz_progress_bar_height   = isset( $input['quiz_progress_bar_height'] )   ? (int) $input['quiz_progress_bar_height']                   : 10;

		/* ─────── SUMMARY STYLE ─────── */
		$summary_font_size = isset( $input['summary_font_size'] ) ? (int) $input['summary_font_size'] : 16;
		$summary_font_size = max( 10, min( 50, $summary_font_size ) );

		$summary_font_color = isset( $input['summary_font_color'] ) && ! empty( $input['summary_font_color'] )
			? sanitize_hex_color( $input['summary_font_color'] )
			: '#000000';

		$summary_bg_color = isset( $input['summary_bg_color'] ) && ! empty( $input['summary_bg_color'] )
			? sanitize_hex_color( $input['summary_bg_color'] )
			: '#ffffff';

		$summary_border_color = isset( $input['summary_border_color'] ) ? sanitize_hex_color( $input['summary_border_color'] ) : '';
		$summary_border_style = isset( $input['summary_border_style'] ) ? sanitize_text_field( $input['summary_border_style'] ) : '';
		$summary_border_width = isset( $input['summary_border_width'] ) ? (int) $input['summary_border_width'] : '';

		// Back-compat again.
		if ( empty( $summary_border_color ) && ! empty( $input['border_color'] ) ) {
			$summary_border_color = sanitize_hex_color( $input['border_color'] );
		}
		if ( empty( $summary_border_style ) && ! empty( $input['border_style'] ) ) {
			$summary_border_style = sanitize_text_field( $input['border_style'] );
		}
		if ( empty( $summary_border_width ) && isset( $input['border_width'] ) ) {
			$summary_border_width = (int) $input['border_width'];
		}
		if ( empty( $summary_border_color ) ) {
			$summary_border_color = '#000000';
		}
		if ( empty( $summary_border_style ) ) {
			$summary_border_style = 'solid';
		}
		if ( empty( $summary_border_width ) ) {
			$summary_border_width = 1;
		}

		$summary_border_radius = isset( $input['summary_border_radius'] ) ? (int) $input['summary_border_radius'] : 6;
		$summary_shadow_color  = isset( $input['summary_shadow_color'] )  ? sanitize_text_field( $input['summary_shadow_color'] )  : 'rgba(0,0,0,0.15)';
		$summary_shadow_blur   = isset( $input['summary_shadow_blur'] )   ? (int) $input['summary_shadow_blur']   : 8;

		/* ─────── QUIZ/ANSWER COUNTS ─────── */
		$questions_per_quiz = isset( $input['questions_per_quiz'] ) ? (int) $input['questions_per_quiz'] : 3;
		$questions_per_quiz = max( 3, min( 10, $questions_per_quiz ) );

		$answers_per_question = isset( $input['answers_per_question'] ) ? (int) $input['answers_per_question'] : 2;
		$answers_per_question = max( 2, min( 4, $answers_per_question ) );

		/* ─────── PLACEMENT ─────── */
		$allowed_display = array( 'manual', 'before', 'after' );

		$display_summary = isset( $input['display_summary'] ) ? sanitize_text_field( $input['display_summary'] ) : 'manual';
		if ( ! in_array( $display_summary, $allowed_display, true ) ) {
			$display_summary = 'manual';
		}

		$display_quiz = isset( $input['display_quiz'] ) ? sanitize_text_field( $input['display_quiz'] ) : 'manual';
		if ( ! in_array( $display_quiz, $allowed_display, true ) ) {
			$display_quiz = 'manual';
		}

		/* ─────── CUSTOM HTML / TITLES ─────── */
		$custom_quiz_html_before = isset( $input['custom_quiz_html_before'] ) ? wp_kses_post( $input['custom_quiz_html_before'] ) : '';
		$custom_quiz_html_after  = isset( $input['custom_quiz_html_after'] )  ? wp_kses_post( $input['custom_quiz_html_after'] )  : '';

		$quiz_title    = isset( $input['quiz_title'] )    ? sanitize_text_field( $input['quiz_title'] )    : 'Test your knowledge';
		$summary_title = isset( $input['summary_title'] ) ? sanitize_text_field( $input['summary_title'] ) : 'Key Facts';

		/* ─────── OPT-IN ─────── */
		$allowed_optin_positions = array( 'with_results', 'before_results' );
		$optin_position          = isset( $input['optin_position'] ) ? sanitize_text_field( $input['optin_position'] ) : 'with_results';
		if ( ! in_array( $optin_position, $allowed_optin_positions, true ) ) {
			$optin_position = 'with_results';
		}
		$optin_mandatory      = isset( $input['optin_mandatory'] ) ? (bool) $input['optin_mandatory'] : false;
		$optin_success_message = isset( $input['optin_success_message'] ) ? sanitize_text_field( $input['optin_success_message'] ) : 'Thank you, your submission was successful!';
		$enable_optin          = isset( $input['enable_optin'] ) ? (bool) $input['enable_optin'] : false;
		$optin_webhook         = isset( $input['optin_webhook'] ) ? esc_url_raw( trim( $input['optin_webhook'] ) ) : '';
		if ( $enable_optin && empty( $optin_webhook ) ) {
			$enable_optin = false;
		}

		/* ─────── GENERATION ─────── */
		$gen_post_types = isset( $input['generation_post_types'] ) ? $input['generation_post_types'] : array( 'post' );
		if ( ! is_array( $gen_post_types ) ) {
			$gen_post_types = array( 'post' );
		}
		$gen_post_types = array_map( 'sanitize_text_field', $gen_post_types );

		$update_last_modified             = isset( $input['update_last_modified'] )             ? (bool) $input['update_last_modified']             : false;
		$auto_generate_quiz_on_publish    = isset( $input['auto_generate_quiz_on_publish'] )    ? (bool) $input['auto_generate_quiz_on_publish']    : false;
		$auto_generate_summary_on_publish = isset( $input['auto_generate_summary_on_publish'] ) ? (bool) $input['auto_generate_summary_on_publish'] : false;

		/* ─────── ATTRIBUTION ─────── */
		$show_attribution = isset( $input['show_attribution'] ) ? (bool) $input['show_attribution'] : false;

		/* ─────── FINAL ARRAY ─────── */
		return array(
			'theme'                            => $theme,

			/* quiz style */
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

			/* summary style */
			'summary_font_size'                => $summary_font_size,
			'summary_font_color'               => $summary_font_color,
			'summary_bg_color'                 => $summary_bg_color,

			'summary_border_color'             => $summary_border_color,
			'summary_border_style'             => $summary_border_style,
			'summary_border_width'             => $summary_border_width,
			'summary_border_radius'            => $summary_border_radius,
			'summary_shadow_color'             => $summary_shadow_color,
			'summary_shadow_blur'              => $summary_shadow_blur,

			/* quiz items */
			'questions_per_quiz'               => $questions_per_quiz,
			'answers_per_question'             => $answers_per_question,

			/* display */
			'display_summary'                  => $display_summary,
			'display_quiz'                     => $display_quiz,

			'custom_quiz_html_before'          => $custom_quiz_html_before,
			'custom_quiz_html_after'           => $custom_quiz_html_after,

			'quiz_title'                       => $quiz_title,
			'summary_title'                    => $summary_title,

			/* opt-in */
			'enable_optin'                     => $enable_optin,
			'optin_webhook'                    => $optin_webhook,
			'optin_success_message'            => $optin_success_message,
			'optin_position'                   => $optin_position,
			'optin_mandatory'                  => $optin_mandatory,

			/* generation */
			'generation_post_types'            => $gen_post_types,
			'update_last_modified'             => $update_last_modified,
			'auto_generate_quiz_on_publish'    => $auto_generate_quiz_on_publish,
			'auto_generate_summary_on_publish' => $auto_generate_summary_on_publish,

			/* attribution */
			'show_attribution'                 => $show_attribution,
		);
	}
}
