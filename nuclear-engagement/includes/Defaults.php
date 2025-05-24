<?php
/**
 * File: includes/Defaults.php
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Defaults {

	public static function nuclen_get_default_settings() {
		return array(
			'theme'                            => 'bright',

			/* ───── Quiz styling ───── */
			'font_size'                        => '16',
			'font_color'                       => '#000000',
			'bg_color'                         => '#ffffff',
			'quiz_border_color'                => '#000000',
			'quiz_border_style'                => 'solid',
			'quiz_border_width'                => '1',
			'quiz_border_radius'               => '6',
			'quiz_shadow_color'                => 'rgba(0,0,0,0.15)',
			'quiz_shadow_blur'                 => '8',

			'quiz_answer_button_bg_color'      => '#94544A',
			'quiz_answer_button_border_color'  => '#94544A',
			'quiz_answer_button_border_width'  => '2',
			'quiz_answer_button_border_radius' => '4',

			'quiz_progress_bar_fg_color'       => '#1B977D',
			'quiz_progress_bar_bg_color'       => '#e0e0e0',
			'quiz_progress_bar_height'         => '10',

			/* ───── Summary styling ───── */
			'summary_font_size'                => '16',
			'summary_font_color'               => '#000000',
			'summary_bg_color'                 => '#ffffff',
			'summary_border_color'             => '#000000',
			'summary_border_style'             => 'solid',
			'summary_border_width'             => '1',
			'summary_border_radius'            => '6',
			'summary_shadow_color'             => 'rgba(0,0,0,0.15)',
			'summary_shadow_blur'              => '8',

			/* ───── TOC styling ───── */
			'toc_font_size'        => '16',
			'toc_font_color'       => '#000000',
			'toc_bg_color'         => '#ffffff',
			'toc_border_color'     => '#000000',
			'toc_border_style'     => 'solid',
			'toc_border_width'     => '1',
			'toc_border_radius'    => '6',
			'toc_shadow_color'     => 'rgba(0,0,0,0.05)',
			'toc_shadow_blur'      => '8',
			'toc_link_color'       => '#1e73be',

			/* ───── Quiz items ───── */
			'questions_per_quiz'               => '10',
			'answers_per_question'             => '4',

			/* ───── Display ───── */
			'display_summary'                  => 'before',
			'display_quiz'                     => 'after',
			'display_toc'                      => 'manual',
			'custom_quiz_html_before'          => '',
			'custom_quiz_html_after'           => '',
			'quiz_title'                       => 'Test your knowledge',
			'summary_title'                    => 'Key Facts',
			'toc_title'                        => 'Table of Contents',

			/* ───── Opt-in ───── */
			'enable_optin'                     => false,
			'optin_webhook'                    => '',
			'optin_position'                   => 'with_results',
			'optin_mandatory'                  => false,
			'optin_prompt_text'                => 'Please enter your details to view your score:',
			'optin_button_text'                => 'Submit',

			/* ───── Generation ───── */
			'update_last_modified'             => false,
			'auto_generate_quiz_on_publish'    => 0,
			'auto_generate_summary_on_publish' => 0,
			'generation_post_types'            => array( 'post' ),

			/* ───── Attribution ───── */
			'show_attribution'                 => false,
		);
	}
}
