<?php
/**
 * File: admin/trait-settings-sanitize-general.php
 *
 * Sanitises all *non-style* settings (theme, counts, placement, etc.).
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

trait SettingsSanitizeGeneralTrait {

	/**
	 * Sanitise everything except style & opt-in.
	 *
	 * @param array $in Raw settings.
	 * @return array    Clean keys (no style, no opt-in).
	 */
	private function nuclen_sanitize_general( array $in ): array {

		/* Theme */
		$themes = array( 'bright', 'dark', 'custom', 'none' );
		$theme  = in_array( $in['theme'] ?? 'bright', $themes, true ) ? $in['theme'] : 'bright';

		/* Counts */
		$q_per_quiz = max( 3, min( 10, (int) ( $in['questions_per_quiz'] ?? 3 ) ) );
		$a_per_q    = max( 2, min( 4,  (int) ( $in['answers_per_question'] ?? 2 ) ) );

		/* Placement */
		$disp = array( 'manual', 'before', 'after' );
		$d_sum = in_array( $in['display_summary'] ?? 'manual', $disp, true ) ? $in['display_summary'] : 'manual';
		$d_q   = in_array( $in['display_quiz']    ?? 'manual', $disp, true ) ? $in['display_quiz']    : 'manual';
		$d_toc = in_array( $in['display_toc']     ?? 'manual', $disp, true ) ? $in['display_toc']     : 'manual';

		/* Custom HTML / titles */
		$html_before = isset( $in['custom_quiz_html_before'] ) ? wp_kses_post( $in['custom_quiz_html_before'] ) : '';
		$html_after  = isset( $in['custom_quiz_html_after'] )  ? wp_kses_post( $in['custom_quiz_html_after'] )  : '';

		$quiz_title    = sanitize_text_field( $in['quiz_title']    ?? 'Test your knowledge' );
		$summary_title = sanitize_text_field( $in['summary_title'] ?? 'Key Facts' );
		$toc_title     = sanitize_text_field( $in['toc_title']     ?? 'Table of Contents' );

		/* Generation */
		$gen_pt = is_array( $in['generation_post_types'] ?? null ) ? $in['generation_post_types'] : array( 'post' );
		$gen_pt = array_map( 'sanitize_text_field', $gen_pt );

		$update_last  = (bool) ( $in['update_last_modified']             ?? false );
		$auto_quiz    = (bool) ( $in['auto_generate_quiz_on_publish']    ?? false );
		$auto_summary = (bool) ( $in['auto_generate_summary_on_publish'] ?? false );

		/* Attribution */
		$show_attr = (bool) ( $in['show_attribution'] ?? false );

		return array(
			/* theme */
			'theme'                            => $theme,

			/* quiz items */
			'questions_per_quiz'               => $q_per_quiz,
			'answers_per_question'             => $a_per_q,

			/* display */
			'display_summary'                  => $d_sum,
			'display_quiz'                     => $d_q,
			'display_toc'                      => $d_toc,

			'custom_quiz_html_before'          => $html_before,
			'custom_quiz_html_after'           => $html_after,

			'quiz_title'                       => $quiz_title,
			'summary_title'                    => $summary_title,
			'toc_title'                        => $toc_title,

			/* generation */
			'generation_post_types'            => $gen_pt,
			'update_last_modified'             => $update_last,
			'auto_generate_quiz_on_publish'    => $auto_quiz,
			'auto_generate_summary_on_publish' => $auto_summary,

			/* attribution */
			'show_attribution'                 => $show_attr,
		);
	}
}
