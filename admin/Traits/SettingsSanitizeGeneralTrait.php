<?php
declare(strict_types=1);
/**
 * File: admin/Traits/SettingsSanitizeGeneralTrait.php
 *
 * Sanitises all *non-style* settings (theme, counts, placement, etc.).
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin\Traits;

trait SettingsSanitizeGeneralTrait {

	/**
	 * Sanitise everything except style & opt-in.
	 *
	 * @param array $in Raw settings.
	 * @return array    Clean keys (no style, no opt-in).
	 */
	private function nuclen_sanitize_general( array $in ): array {

		/* Theme */
		$themes = array( 'light', 'dark', 'custom', 'bright', 'none' );
		// Convert 'bright' to 'light' for backward compatibility
		if (isset($in['theme']) && $in['theme'] === 'bright') {
			$in['theme'] = 'light';
		}
		$theme  = in_array( $in['theme'] ?? 'light', $themes, true ) ? $in['theme'] : 'light';

		/* Counts */
		$q_per_quiz = max( 3, min( 10, (int) ( $in['questions_per_quiz'] ?? 3 ) ) );
		$a_per_q    = max( 2, min( 4, (int) ( $in['answers_per_question'] ?? 2 ) ) );

		/* Placement */
		$disp  = array( 'manual', 'before', 'after' );
		$d_sum = in_array( $in['display_summary'] ?? 'manual', $disp, true ) ? $in['display_summary'] : 'manual';
		$d_q   = in_array( $in['display_quiz'] ?? 'manual', $disp, true ) ? $in['display_quiz'] : 'manual';
		$d_toc = in_array( $in['display_toc'] ?? 'manual', $disp, true ) ? $in['display_toc'] : 'manual';

		$toc_sticky       = ! empty( $in['toc_sticky'] ) ? '1' : '0';
		$toc_show_toggle  = ! empty( $in['toc_show_toggle'] ) ? '1' : '0';
		$toc_show_content = ! empty( $in['toc_show_content'] ) ? '1' : '0';

		// Sticky TOC offsets & width
		$off_x = isset( $in['toc_sticky_offset_x'] ) ? max( 0, min( 1000, (int) $in['toc_sticky_offset_x'] ) ) : 20;
		$off_y = isset( $in['toc_sticky_offset_y'] ) ? max( 0, min( 1000, (int) $in['toc_sticky_offset_y'] ) ) : 20;
		$max_w = isset( $in['toc_sticky_max_width'] ) ? max( 200, min( 800, (int) $in['toc_sticky_max_width'] ) ) : 300;

		// Heading levels
		$toc_heading_levels = array();
		if ( isset( $in['toc_heading_levels'] ) && is_array( $in['toc_heading_levels'] ) ) {
			$toc_heading_levels = array_map( 'intval', $in['toc_heading_levels'] );
			$toc_heading_levels = array_filter(
				$toc_heading_levels,
				static function ( $l ) {
					return $l >= 2 && $l <= 6;
				}
			);
		}
		if ( empty( $toc_heading_levels ) ) {
			$toc_heading_levels = range( 2, 6 );
		}
		$toc_heading_levels = array_values( array_unique( $toc_heading_levels ) );
		sort( $toc_heading_levels );

		/* Custom HTML / titles */
		$html_before = isset( $in['custom_quiz_html_before'] ) ? wp_kses_post( $in['custom_quiz_html_before'] ) : '';
		$html_after  = isset( $in['custom_quiz_html_after'] ) ? wp_kses_post( $in['custom_quiz_html_after'] ) : '';

		$quiz_title    = sanitize_text_field( $in['quiz_title'] ?? 'Test your knowledge' );
		$summary_title = sanitize_text_field( $in['summary_title'] ?? 'Key Facts' );
		$toc_title     = sanitize_text_field( $in['toc_title'] ?? 'Table of Contents' );

		$label_retake_test   = sanitize_text_field( $in['quiz_label_retake_test'] ?? 'Retake Test' );
		$label_your_score    = sanitize_text_field( $in['quiz_label_your_score'] ?? 'Your Score' );
		$label_perfect       = sanitize_text_field( $in['quiz_label_perfect'] ?? 'Perfect!' );
		$label_well_done     = sanitize_text_field( $in['quiz_label_well_done'] ?? 'Well done!' );
		$label_retake_prompt = sanitize_text_field( $in['quiz_label_retake_prompt'] ?? 'Why not retake the test?' );
		$label_correct       = sanitize_text_field( $in['quiz_label_correct'] ?? 'Correct:' );
		$label_your_answer   = sanitize_text_field( $in['quiz_label_your_answer'] ?? 'Your answer:' );

		/* Generation */
		$gen_pt = is_array( $in['generation_post_types'] ?? null ) ? $in['generation_post_types'] : array( 'post' );
		$gen_pt = array_map( 'sanitize_text_field', $gen_pt );

		$update_last          = (bool) ( $in['update_last_modified'] ?? false );
		$auto_quiz            = (bool) ( $in['auto_generate_quiz_on_publish'] ?? false );
				$auto_summary = (bool) ( $in['auto_generate_summary_on_publish'] ?? false );

				/* Attribution */
				$show_attr = (bool) ( $in['show_attribution'] ?? false );

				/* Uninstall options */
				$delete_settings  = (bool) ( $in['delete_settings_on_uninstall'] ?? false );
				$delete_generated = (bool) ( $in['delete_generated_content_on_uninstall'] ?? false );
				$delete_optin     = (bool) ( $in['delete_optin_data_on_uninstall'] ?? false );
				$delete_log       = (bool) ( $in['delete_log_file_on_uninstall'] ?? false );
				$delete_css       = (bool) ( $in['delete_custom_css_on_uninstall'] ?? false );

		return array(
			/* theme */
			'theme'                                 => $theme,

			/* quiz items */
			'questions_per_quiz'                    => $q_per_quiz,
			'answers_per_question'                  => $a_per_q,

			/* display */
			'display_summary'                       => $d_sum,
			'display_quiz'                          => $d_q,
			'display_toc'                           => $d_toc,
			'toc_sticky'                            => $toc_sticky,
			'toc_show_toggle'                       => $toc_show_toggle,
			'toc_show_content'                      => $toc_show_content,
			'toc_heading_levels'                    => $toc_heading_levels,

			// ► NEW – sticky offsets & max-width ◄
			'toc_sticky_offset_x'                   => $off_x,
			'toc_sticky_offset_y'                   => $off_y,
			'toc_sticky_max_width'                  => $max_w,

			'custom_quiz_html_before'               => $html_before,
			'custom_quiz_html_after'                => $html_after,

			'quiz_title'                            => $quiz_title,
			'summary_title'                         => $summary_title,
			'toc_title'                             => $toc_title,
			'quiz_label_retake_test'                => $label_retake_test,
			'quiz_label_your_score'                 => $label_your_score,
			'quiz_label_perfect'                    => $label_perfect,
			'quiz_label_well_done'                  => $label_well_done,
			'quiz_label_retake_prompt'              => $label_retake_prompt,
			'quiz_label_correct'                    => $label_correct,
			'quiz_label_your_answer'                => $label_your_answer,

			/* generation */
			'generation_post_types'                 => $gen_pt,
			'update_last_modified'                  => $update_last,
			'auto_generate_quiz_on_publish'         => $auto_quiz,
			'auto_generate_summary_on_publish'      => $auto_summary,

			/* attribution */
			'show_attribution'                      => $show_attr,

			/* uninstall */
			'delete_settings_on_uninstall'          => $delete_settings,
			'delete_generated_content_on_uninstall' => $delete_generated,
			'delete_optin_data_on_uninstall'        => $delete_optin,
			'delete_log_file_on_uninstall'          => $delete_log,
			'delete_custom_css_on_uninstall'        => $delete_css,
		);
	}
}
