<?php
declare(strict_types=1);
/**
 * File: admin/Traits/SettingsPageSaveTrait.php
 *
 * Handles saving of settings from the admin form.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SettingsPageSaveTrait {

	/**
	 * Process and save submitted settings.
	 *
	 * @param array $settings      Current settings (passed by reference).
	 * @param array $defaults      Default settings.
	 * @param array &$new_settings Output: new sanitized settings.
	 * @return bool                True if a save occurred.
	 */
        protected function nuclen_handle_save_settings( array &$settings, array $defaults, array &$new_settings ): bool {

                /* ───────── Bail if not a form submission ───────── */
                if (
                        ! isset( $_POST['nuclen_save_settings'] ) ||
                        ! check_admin_referer( 'nuclen_settings_nonce', 'nuclen_settings_nonce_field', false )
                ) {
                        return false;
                }

                $raw          = $this->nuclen_collect_input();
                $new_settings = $this->nuclen_sanitize_and_defaults( $raw, $defaults );
                $settings     = $this->nuclen_persist_settings( $new_settings );

                if ( 'custom' === $settings['theme'] && method_exists( $this, 'nuclen_write_custom_css' ) ) {
                        $this->nuclen_write_custom_css( $settings );
                }

                $this->nuclen_output_save_notice();

                return true;
        }

        /**
         * Collect raw input values from $_POST.
         */
        private function nuclen_collect_input(): array {
                $raw = array();

                // theme preset
                $raw['theme'] = isset( $_POST['nuclen_theme'] )
                        ? sanitize_text_field( wp_unslash( $_POST['nuclen_theme'] ) )
                        : '';

                // map <form field> → <settings key>
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

                        /* —— TOC style —— */
                        'nuclen_toc_font_size'                    => 'toc_font_size',
                        'nuclen_toc_font_color'                   => 'toc_font_color',
                        'nuclen_toc_bg_color'                     => 'toc_bg_color',
                        'nuclen_toc_border_color'                 => 'toc_border_color',
                        'nuclen_toc_border_style'                 => 'toc_border_style',
                        'nuclen_toc_heading_levels'               => 'toc_heading_levels',
                        'nuclen_toc_show_toggle'                  => 'toc_show_toggle',
                        'nuclen_toc_show_content'                 => 'toc_show_content',
                        'nuclen_toc_border_width'                 => 'toc_border_width',
                        'nuclen_toc_border_radius'                => 'toc_border_radius',
                        'nuclen_toc_shadow_color'                 => 'toc_shadow_color',
                        'nuclen_toc_shadow_blur'                  => 'toc_shadow_blur',
                        'nuclen_toc_link_color'                   => 'toc_link_color',
                        'nuclen_toc_title'                        => 'toc_title',

                        /* —— NEW – sticky TOC options & z-index —— */
                        'toc_zindex'                              => 'toc_z_index',
                        'toc_sticky_offset_x'                     => 'toc_sticky_offset_x',
                        'toc_sticky_offset_y'                     => 'toc_sticky_offset_y',
                        'toc_sticky_max_width'                    => 'toc_sticky_max_width',

                        /* —— Legacy generic —— */
                        'nuclen_border_color'                     => 'border_color',
                        'nuclen_border_style'                     => 'border_style',
                        'nuclen_border_width'                     => 'border_width',

                        /* —— Quiz structure —— */
                        'nuclen_questions_per_quiz'               => 'questions_per_quiz',
                        'nuclen_answers_per_question'             => 'answers_per_question',

                        /* —— Placement —— */
                        'nuclen_display_summary'                  => 'display_summary',
                        'nuclen_display_quiz'                     => 'display_quiz',
                        'nuclen_display_toc'                      => 'display_toc',
                        'toc_sticky'                              => 'toc_sticky',
                );

                foreach ( $field_map as $post_key => $opt_key ) {
                        if ( isset( $_POST[ $post_key ] ) ) {
                                $raw[ $opt_key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
                        }
                }

                /* —— TOC Heading Levels —— */
                $raw['toc_heading_levels'] = isset( $_POST['nuclear_engagement_settings']['toc_heading_levels'] )
                        ? array_map( 'intval', (array) $_POST['nuclear_engagement_settings']['toc_heading_levels'] )
                        : range( 2, 6 );

                /* —— Custom HTML & titles —— */
                $raw['custom_quiz_html_before']  = isset( $_POST['custom_quiz_html_before'] )
                        ? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_before'] ) )
                        : '';
                $raw['custom_quiz_html_after']   = isset( $_POST['custom_quiz_html_after'] )
                        ? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_after'] ) )
                        : '';
                $raw['quiz_title']               = isset( $_POST['quiz_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_title'] ) ) : '';
                $raw['summary_title']            = isset( $_POST['summary_title'] ) ? sanitize_text_field( wp_unslash( $_POST['summary_title'] ) ) : '';
                $raw['quiz_label_retake_test']   = isset( $_POST['quiz_label_retake_test'] )
                        ? sanitize_text_field( wp_unslash( $_POST['quiz_label_retake_test'] ) )
                        : '';
                $raw['quiz_label_your_score']    = isset( $_POST['quiz_label_your_score'] )
                        ? sanitize_text_field( wp_unslash( $_POST['quiz_label_your_score'] ) )
                        : '';
                $raw['quiz_label_perfect']       = isset( $_POST['quiz_label_perfect'] )
                        ? sanitize_text_field( wp_unslash( $_POST['quiz_label_perfect'] ) )
                        : '';
                $raw['quiz_label_well_done']     = isset( $_POST['quiz_label_well_done'] )
                        ? sanitize_text_field( wp_unslash( $_POST['quiz_label_well_done'] ) )
                        : '';
                $raw['quiz_label_retake_prompt'] = isset( $_POST['quiz_label_retake_prompt'] )
                        ? sanitize_text_field( wp_unslash( $_POST['quiz_label_retake_prompt'] ) )
                        : '';
                $raw['quiz_label_correct']       = isset( $_POST['quiz_label_correct'] )
                        ? sanitize_text_field( wp_unslash( $_POST['quiz_label_correct'] ) )
                        : '';
                $raw['quiz_label_your_answer']   = isset( $_POST['quiz_label_your_answer'] )
                        ? sanitize_text_field( wp_unslash( $_POST['quiz_label_your_answer'] ) )
                        : '';

                /* —— Opt-in block —— */
                $raw['enable_optin']      = isset( $_POST['enable_optin'] ) ? (bool) wp_unslash( $_POST['enable_optin'] ) : false;
                $raw['optin_position']    = isset( $_POST['nuclen_optin_position'] )
                        ? sanitize_text_field( wp_unslash( $_POST['nuclen_optin_position'] ) )
                        : 'with_results';
                $raw['optin_mandatory']   = isset( $_POST['optin_mandatory'] ) ? (bool) wp_unslash( $_POST['optin_mandatory'] ) : false;
                $raw['optin_prompt_text'] = isset( $_POST['optin_prompt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['optin_prompt_text'] ) ) : '';
                $raw['optin_button_text'] = isset( $_POST['optin_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['optin_button_text'] ) ) : '';
                if ( isset( $_POST['optin_webhook'] ) ) {
                        $raw['optin_webhook'] = esc_url_raw( trim( sanitize_text_field( wp_unslash( $_POST['optin_webhook'] ) ) ) );
                }

                /* —— Flags & generation —— */
                $raw['update_last_modified']                          = isset( $_POST['update_last_modified'] ) ? (bool) wp_unslash( $_POST['update_last_modified'] ) : false;
                $raw['auto_generate_quiz_on_publish']                 = isset( $_POST['auto_generate_quiz_on_publish'] ) ? (bool) wp_unslash( $_POST['auto_generate_quiz_on_publish'] ) : false;
                $raw['auto_generate_summary_on_publish']              = isset( $_POST['auto_generate_summary_on_publish'] ) ? (bool) wp_unslash( $_POST['auto_generate_summary_on_publish'] ) : false;
                $raw['show_attribution']                      = isset( $_POST['show_attribution'] ) ? (bool) wp_unslash( $_POST['show_attribution'] ) : false;
                $raw['delete_settings_on_uninstall']          = isset( $_POST['delete_settings_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_settings_on_uninstall'] ) : false;
                $raw['delete_generated_content_on_uninstall'] = isset( $_POST['delete_generated_content_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_generated_content_on_uninstall'] ) : false;
                $raw['delete_optin_data_on_uninstall']        = isset( $_POST['delete_optin_data_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_optin_data_on_uninstall'] ) : false;
                $raw['delete_log_file_on_uninstall']          = isset( $_POST['delete_log_file_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_log_file_on_uninstall'] ) : false;
                $raw['delete_custom_css_on_uninstall']        = isset( $_POST['delete_custom_css_on_uninstall'] ) ? (bool) wp_unslash( $_POST['delete_custom_css_on_uninstall'] ) : false;

                /* —— Generation post types —— */
                $posted_types                 = filter_input(
                        INPUT_POST,
                        'nuclen_generation_post_types',
                        FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                        FILTER_REQUIRE_ARRAY
                );
                $raw['generation_post_types'] = is_array( $posted_types )
                        ? array_map( 'sanitize_text_field', wp_unslash( $posted_types ) )
                        : array( 'post' );

                return $raw;
        }

        /**
         * Sanitize input and merge with defaults.
         */
        private function nuclen_sanitize_and_defaults( array $raw, array $defaults ): array {
                $new_settings = $this->nuclen_sanitize_settings( $raw );

                $toc_keys = array(
                        'toc_font_color',
                        'toc_bg_color',
                        'toc_border_color',
                        'toc_border_style',
                        'toc_border_width',
                        'toc_border_radius',
                        'toc_shadow_color',
                        'toc_shadow_blur',
                        'toc_link_color',
                        'toc_heading_levels',
                        'toc_z_index',
                        'toc_sticky_offset_x',
                        'toc_sticky_offset_y',
                        'toc_sticky_max_width',
                );

                foreach ( $toc_keys as $k ) {
                        if ( isset( $raw[ $k ] ) && $raw[ $k ] !== '' ) {
                                $new_settings[ $k ] = $raw[ $k ];
                        }
                }

                return wp_parse_args( $new_settings, $defaults );
        }

        /**
         * Persist the sanitized settings and return the saved array.
         */
        private function nuclen_persist_settings( array $new_settings ): array {
                $settings_repo = $this->nuclen_get_settings_repository();

                foreach ( $new_settings as $key => $value ) {
                        $settings_repo->set( $key, $value );
                }

                $settings_repo->save();

                return $settings_repo->get_all();
        }

        /**
         * Output the success admin notice after saving settings.
         */
        private function nuclen_output_save_notice(): void {
                echo '<div class="notice notice-success"><p>' .
                        esc_html__( 'Settings saved.', 'nuclear-engagement' ) .
                        '</p></div>';
        }
}
