<?php
/**
 * Defaults.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);
/**
 * File: includes/Defaults.php
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Defaults {

	public static function nuclen_get_default_settings() {
		return array(
			'theme'                                 => 'light',

			/* ───── Quiz styling ───── */
			'font_size'                             => '16',
			'font_color'                            => '#000000',
			'bg_color'                              => '#ffffff',
			'quiz_border_color'                     => '#000000',
			'quiz_border_style'                     => 'solid',
			'quiz_border_width'                     => '1',
			'quiz_border_radius'                    => '6',
			'quiz_shadow_color'                     => 'rgba(0,0,0,0.15)',
			'quiz_shadow_blur'                      => '8',
			'quiz_answer_button_bg_color'           => '#94544A',
			'quiz_answer_button_border_color'       => '#94544A',
			'quiz_answer_button_border_width'       => '2',
			'quiz_answer_button_border_radius'      => '4',
			'quiz_progress_bar_fg_color'            => '#1B977D',
			'quiz_progress_bar_bg_color'            => '#e0e0e0',
			'quiz_progress_bar_height'              => '10',

			/* ───── Summary styling ───── */
			'summary_font_size'                     => '16',
			'summary_font_color'                    => '#000000',
			'summary_bg_color'                      => '#ffffff',
			'summary_border_color'                  => '#000000',
			'summary_border_style'                  => 'solid',
			'summary_border_width'                  => '1',
			'summary_border_radius'                 => '6',
			'summary_shadow_color'                  => 'rgba(0,0,0,0.15)',
			'summary_shadow_blur'                   => '8',

			/* ───── TOC styling ───── */
			'toc_font_size'                         => '16',
			'toc_font_color'                        => '#000000',
			'toc_bg_color'                          => '#ffffff',
			'toc_border_color'                      => '#000000',
			'toc_border_style'                      => 'solid',
			'toc_border_width'                      => '1',
			'toc_border_radius'                     => '6',
			'toc_shadow_color'                      => 'rgba(0,0,0,0.05)',
			'toc_shadow_blur'                       => '8',
			'toc_z_index'                           => '100',
			'toc_link_color'                        => '#1e73be',

			/* ───── Quiz items ───── */
			'questions_per_quiz'                    => '10',
			'answers_per_question'                  => '4',

			/* ───── Display ───── */
			'display_summary'                       => 'before',
			'display_quiz'                          => 'after',
			'display_toc'                           => 'manual',
			'toc_sticky'                            => false, // Whether to make TOC sticky when scrolling.
			'toc_sticky_offset_x'                   => '20',   // X offset (left) for sticky TOC in pixels.
			'toc_sticky_offset_y'                   => '20',   // Y offset (top) for sticky TOC in pixels.
			'toc_sticky_max_width'                  => '300',  // Max width for sticky TOC in pixels.
			'toc_show_toggle'                       => true,   // Whether to show the TOC toggle button.
			'toc_show_content'                      => true,   // Whether to show TOC content by default (only used if toggle is enabled).
			'toc_heading_levels'                    => array( 2, 3, 4, 5, 6 ), // Which heading levels to include in TOC (H2-H6).
			'custom_quiz_html_before'               => '',
			'custom_quiz_html_after'                => '',
			'quiz_title'                            => 'Test your knowledge',
			'summary_title'                         => 'Key Facts',
			'toc_title'                             => 'Table of Contents',

			/* ───── Quiz strings ───── */
			'quiz_label_retake_test'                => 'Retake Test',
			'quiz_label_your_score'                 => 'Your Score',
			'quiz_label_perfect'                    => 'Perfect!',
			'quiz_label_well_done'                  => 'Well done!',
			'quiz_label_retake_prompt'              => 'Why not retake the test?',
			'quiz_label_correct'                    => 'Correct:',
			'quiz_label_your_answer'                => 'Your answer:',

			/* ───── Opt-in ───── */
			'enable_optin'                          => false,
			'optin_webhook'                         => '',
			'optin_position'                        => 'with_results',
			'optin_mandatory'                       => false,
			'optin_prompt_text'                     => 'Please enter your details to view your score:',
			'optin_button_text'                     => 'Submit',

			/* ───── Generation ───── */
			'update_last_modified'                  => false,
			'auto_generate_quiz_on_publish'         => 0,
			'auto_generate_summary_on_publish'      => 0,
			'generation_post_types'                 => array( 'post' ),

			/* ───── Attribution ───── */
			'show_attribution'                      => false,

			/* ───── Uninstall ───── */
			'delete_settings_on_uninstall'          => false,
			'delete_generated_content_on_uninstall' => false,
			'delete_optin_data_on_uninstall'        => false,
			'delete_log_file_on_uninstall'          => false,
			'delete_custom_css_on_uninstall'        => false,
		);
	}
}
