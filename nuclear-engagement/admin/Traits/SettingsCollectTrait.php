<?php
declare(strict_types=1);
/**
 * File: admin/Traits/SettingsCollectTrait.php
 *
 * Gathers raw settings values from the posted form.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin\Traits;

use NuclearEngagement\Helpers\FormSanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SettingsCollectTrait {
		/**
		 * Collect raw input values from $_POST.
		 */
	   private function nuclen_collect_input(): array {
			   $raw = array();

			   // theme preset
			   $raw['theme'] = FormSanitizer::sanitize_post_text( 'nuclen_theme' );

			   $raw = array_merge(
					   $raw,
					   $this->collect_quiz_style(),
					   $this->collect_quiz_structure(),
					   $this->collect_toc_options(),
					   $this->collect_custom_text(),
					   $this->collect_optin_options(),
					   $this->collect_flags_and_generation()
			   );

			   return $raw;
	   }

	   /**
		* Collect quiz and summary style fields.
		*/
	   private function collect_quiz_style(): array {
			   $style     = array();
			   $field_map = array(
					   /* —— Quiz style —— */
					   'nuclen_font_size'                        => 'font_size',
					   'nuclen_font_color'                       => 'font_color',
					   'nuclen_bg_color'                         => 'bg_color',
					   'nuclen_quiz_border_color'                => 'quiz_border_color',
					   'nuclen_quiz_border_style'                => 'quiz_border_style',
					   'nuclen_quiz_border_width'                => 'quiz_border_width',
					   'nuclen_quiz_border_radius'               => 'quiz_border_radius',
					   'nuclen_quiz_shadow_color'                => 'quiz_shadow_color',
					   'nuclen_quiz_shadow_blur'                 => 'quiz_shadow_blur',

					   'nuclen_quiz_answer_button_bg_color'      => 'quiz_answer_button_bg_color',
					   'nuclen_quiz_answer_button_border_color'  => 'quiz_answer_button_border_color',
					   'nuclen_quiz_answer_button_border_width'  => 'quiz_answer_button_border_width',
					   'nuclen_quiz_answer_button_border_radius' => 'quiz_answer_button_border_radius',

					   'nuclen_quiz_progress_bar_fg_color'       => 'quiz_progress_bar_fg_color',
					   'nuclen_quiz_progress_bar_bg_color'       => 'quiz_progress_bar_bg_color',
					   'nuclen_quiz_progress_bar_height'         => 'quiz_progress_bar_height',

					   /* —— Summary style —— */
					   'nuclen_summary_font_size'                => 'summary_font_size',
					   'nuclen_summary_font_color'               => 'summary_font_color',
					   'nuclen_summary_bg_color'                 => 'summary_bg_color',
					   'nuclen_summary_border_color'             => 'summary_border_color',
					   'nuclen_summary_border_style'             => 'summary_border_style',
					   'nuclen_summary_border_width'             => 'summary_border_width',
					   'nuclen_summary_border_radius'            => 'summary_border_radius',
					   'nuclen_summary_shadow_color'             => 'summary_shadow_color',
					   'nuclen_summary_shadow_blur'              => 'summary_shadow_blur',

					   /* —— Legacy generic —— */
					   'nuclen_border_color'                     => 'border_color',
					   'nuclen_border_style'                     => 'border_style',
					   'nuclen_border_width'                     => 'border_width',
			   );

			   foreach ( $field_map as $post_key => $opt_key ) {
					   if ( isset( $_POST[ $post_key ] ) ) {
							   $style[ $opt_key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
					   }
			   }

			   return $style;
	   }

	   /**
		* Collect quiz structure counts.
		*/
	   private function collect_quiz_structure(): array {
			   $structure  = array();
			   $field_map = array(
					   'nuclen_questions_per_quiz'   => 'questions_per_quiz',
					   'nuclen_answers_per_question' => 'answers_per_question',
			   );

			   foreach ( $field_map as $post_key => $opt_key ) {
					   if ( isset( $_POST[ $post_key ] ) ) {
							   $structure[ $opt_key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
					   }
			   }

			   return $structure;
	   }

	   /**
		* Collect TOC placement and style fields.
		*/
	   private function collect_toc_options(): array {
			   $toc       = array();
			   $field_map = array(
					   'nuclen_toc_font_size'   => 'toc_font_size',
					   'nuclen_toc_font_color'  => 'toc_font_color',
					   'nuclen_toc_bg_color'    => 'toc_bg_color',
					   'nuclen_toc_border_color'=> 'toc_border_color',
					   'nuclen_toc_border_style'=> 'toc_border_style',
					   'nuclen_toc_heading_levels' => 'toc_heading_levels',
					   'nuclen_toc_show_toggle' => 'toc_show_toggle',
					   'nuclen_toc_show_content'=> 'toc_show_content',
					   'nuclen_toc_border_width'=> 'toc_border_width',
					   'nuclen_toc_border_radius'=> 'toc_border_radius',
					   'nuclen_toc_shadow_color'=> 'toc_shadow_color',
					   'nuclen_toc_shadow_blur' => 'toc_shadow_blur',
					   'nuclen_toc_link_color'  => 'toc_link_color',
					   'nuclen_toc_title'       => 'toc_title',
					   'toc_zindex'             => 'toc_z_index',
					   'toc_sticky_offset_x'    => 'toc_sticky_offset_x',
					   'toc_sticky_offset_y'    => 'toc_sticky_offset_y',
					   'toc_sticky_max_width'   => 'toc_sticky_max_width',
					   'nuclen_display_summary' => 'display_summary',
					   'nuclen_display_quiz'    => 'display_quiz',
					   'nuclen_display_toc'     => 'display_toc',
					   'toc_sticky'             => 'toc_sticky',
			   );

			   foreach ( $field_map as $post_key => $opt_key ) {
					   if ( isset( $_POST[ $post_key ] ) ) {
							   $toc[ $opt_key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
					   }
			   }

			   /* —— TOC Heading Levels —— */
			   $toc['toc_heading_levels'] = isset( $_POST['nuclear_engagement_settings']['toc_heading_levels'] )
					   ? array_map( 'intval', (array) $_POST['nuclear_engagement_settings']['toc_heading_levels'] )
					   : range( 2, 6 );

			   return $toc;
	   }

	   /**
		* Collect custom text and label fields.
		*/
	   private function collect_custom_text(): array {
			   return array(
					   'custom_quiz_html_before'  => isset( $_POST['custom_quiz_html_before'] )
							   ? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_before'] ) )
							   : '',
					   'custom_quiz_html_after'   => isset( $_POST['custom_quiz_html_after'] )
							   ? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_after'] ) )
							   : '',
					   'quiz_title'               => isset( $_POST['quiz_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_title'] ) ) : '',
					   'summary_title'            => isset( $_POST['summary_title'] ) ? sanitize_text_field( wp_unslash( $_POST['summary_title'] ) ) : '',
					   'quiz_label_retake_test'   => isset( $_POST['quiz_label_retake_test'] )
							   ? sanitize_text_field( wp_unslash( $_POST['quiz_label_retake_test'] ) )
							   : '',
					   'quiz_label_your_score'    => isset( $_POST['quiz_label_your_score'] )
							   ? sanitize_text_field( wp_unslash( $_POST['quiz_label_your_score'] ) )
							   : '',
					   'quiz_label_perfect'       => isset( $_POST['quiz_label_perfect'] )
							   ? sanitize_text_field( wp_unslash( $_POST['quiz_label_perfect'] ) )
							   : '',
					   'quiz_label_well_done'     => isset( $_POST['quiz_label_well_done'] )
							   ? sanitize_text_field( wp_unslash( $_POST['quiz_label_well_done'] ) )
							   : '',
					   'quiz_label_retake_prompt' => isset( $_POST['quiz_label_retake_prompt'] )
							   ? sanitize_text_field( wp_unslash( $_POST['quiz_label_retake_prompt'] ) )
							   : '',
					   'quiz_label_correct'       => isset( $_POST['quiz_label_correct'] )
							   ? sanitize_text_field( wp_unslash( $_POST['quiz_label_correct'] ) )
							   : '',
					   'quiz_label_your_answer'   => isset( $_POST['quiz_label_your_answer'] )
							   ? sanitize_text_field( wp_unslash( $_POST['quiz_label_your_answer'] ) )
							   : '',
			   );
	   }

	   /**
		* Collect Opt-In related fields.
		*/
	   private function collect_optin_options(): array {
			   $optin = array(
					   'enable_optin'      => isset( $_POST['enable_optin'] ) ? (bool) wp_unslash( $_POST['enable_optin'] ) : false,
					   'optin_position'    => isset( $_POST['nuclen_optin_position'] )
							   ? sanitize_text_field( wp_unslash( $_POST['nuclen_optin_position'] ) )
							   : 'with_results',
					   'optin_mandatory'   => isset( $_POST['optin_mandatory'] ) ? (bool) wp_unslash( $_POST['optin_mandatory'] ) : false,
					   'optin_prompt_text' => isset( $_POST['optin_prompt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['optin_prompt_text'] ) ) : '',
					   'optin_button_text' => isset( $_POST['optin_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['optin_button_text'] ) ) : '',
			   );

			   if ( isset( $_POST['optin_webhook'] ) ) {
					   $optin['optin_webhook'] = esc_url_raw( trim( sanitize_text_field( wp_unslash( $_POST['optin_webhook'] ) ) ) );
			   }

			   return $optin;
	   }

	   /**
		* Collect various flags and generation settings.
		*/
	   private function collect_flags_and_generation(): array {
			   $data = array(
					   'update_last_modified'          => isset( $_POST['update_last_modified'] ) ? (bool) wp_unslash( $_POST['update_last_modified'] ) : false,
					   'auto_generate_quiz_on_publish' => isset( $_POST['auto_generate_quiz_on_publish'] ) ? (bool) wp_unslash( $_POST['auto_generate_quiz_on_publish'] ) : false,
					   'auto_generate_summary_on_publish' => isset( $_POST['auto_generate_summary_on_publish'] ) ? (bool) wp_unslash( $_POST['auto_generate_summary_on_publish'] ) : false,
					   'show_attribution'               => isset( $_POST['show_attribution'] ) ? (bool) wp_unslash( $_POST['show_attribution'] ) : false,
					   'delete_settings_on_uninstall'          => isset( $_POST['delete_settings_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_settings_on_uninstall'] ) : false,
					   'delete_generated_content_on_uninstall' => isset( $_POST['delete_generated_content_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_generated_content_on_uninstall'] ) : false,
					   'delete_optin_data_on_uninstall'        => isset( $_POST['delete_optin_data_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_optin_data_on_uninstall'] ) : false,
					   'delete_log_file_on_uninstall'          => isset( $_POST['delete_log_file_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_log_file_on_uninstall'] ) : false,
					   'delete_custom_css_on_uninstall'        => isset( $_POST['delete_custom_css_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_custom_css_on_uninstall'] ) : false,
			   );

			   $posted_types = filter_input(
					   INPUT_POST,
					   'nuclen_generation_post_types',
					   FILTER_SANITIZE_FULL_SPECIAL_CHARS,
					   FILTER_REQUIRE_ARRAY
			   );
			   $data['generation_post_types'] = is_array( $posted_types )
					   ? array_map( 'sanitize_text_field', wp_unslash( $posted_types ) )
					   : array( 'post' );

			   return $data;
	   }

}
