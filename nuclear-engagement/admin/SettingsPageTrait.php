<?php
/**
 * File: admin/SettingsPageTrait.php
 *
 * Renders the admin settings screen and persists options.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

trait SettingsPageTrait {

	/**
	 * Settings page HTML + save logic.
	 */
	public function nuclen_display_settings_page() {
		$raw_settings = get_option( 'nuclear_engagement_settings', array() );
		$defaults     = \NuclearEngagement\Defaults::nuclen_get_default_settings();
		$settings     = wp_parse_args( $this->nuclen_sanitize_settings( $raw_settings ), $defaults );

		/* ─────── SAVE ─────── */
		if ( isset( $_POST['nuclen_save_settings'] ) && check_admin_referer( 'nuclen_settings_nonce', 'nuclen_settings_nonce_field' ) ) {

			/* ─ gather raw input in $raw_new_settings (omitting no fields) ─ */
			$raw_new_settings          = array();
			$raw_new_settings['theme'] = isset( $_POST['nuclen_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['nuclen_theme'] ) ) : '';

			$field_map = array(
				/* quiz style */
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

				/* summary style */
				'nuclen_summary_font_size'                => 'summary_font_size',
				'nuclen_summary_font_color'               => 'summary_font_color',
				'nuclen_summary_bg_color'                 => 'summary_bg_color',
				'nuclen_summary_border_color'             => 'summary_border_color',
				'nuclen_summary_border_style'             => 'summary_border_style',
				'nuclen_summary_border_width'             => 'summary_border_width',
				'nuclen_summary_border_radius'            => 'summary_border_radius',
				'nuclen_summary_shadow_color'             => 'summary_shadow_color',
				'nuclen_summary_shadow_blur'              => 'summary_shadow_blur',

				/* legacy fallback keys */
				'nuclen_border_color'                     => 'border_color',
				'nuclen_border_style'                     => 'border_style',
				'nuclen_border_width'                     => 'border_width',

				/* quiz structure */
				'nuclen_questions_per_quiz'               => 'questions_per_quiz',
				'nuclen_answers_per_question'             => 'answers_per_question',

				/* front-end placement */
				'nuclen_display_summary'                  => 'display_summary',
				'nuclen_display_quiz'                     => 'display_quiz',
			);

			foreach ( $field_map as $post_key => $arr_key ) {
				if ( isset( $_POST[ $post_key ] ) ) {
					$raw_new_settings[ $arr_key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
				}
			}

			/* custom HTML & titles */
			$raw_new_settings['custom_quiz_html_before'] = isset( $_POST['custom_quiz_html_before'] ) ? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_before'] ) ) : '';
			$raw_new_settings['custom_quiz_html_after']  = isset( $_POST['custom_quiz_html_after'] )  ? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_after'] ) )  : '';

			$raw_new_settings['quiz_title']    = isset( $_POST['quiz_title'] )    ? sanitize_text_field( wp_unslash( $_POST['quiz_title'] ) )    : '';
			$raw_new_settings['summary_title'] = isset( $_POST['summary_title'] ) ? sanitize_text_field( wp_unslash( $_POST['summary_title'] ) ) : '';

			/* opt-in */
			$raw_new_settings['optin_success_message'] = isset( $_POST['optin_success_message'] ) ? sanitize_text_field( wp_unslash( $_POST['optin_success_message'] ) ) : '';
			$raw_new_settings['enable_optin']          = isset( $_POST['enable_optin'] )          ? (bool) wp_unslash( $_POST['enable_optin'] )                         : '';
			$raw_new_settings['optin_position']        = isset( $_POST['nuclen_optin_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nuclen_optin_position'] ) ) : 'with_results';
			$raw_new_settings['optin_mandatory']       = isset( $_POST['optin_mandatory'] )        ? (bool) wp_unslash( $_POST['optin_mandatory'] )                      : false;

			if ( isset( $_POST['optin_webhook'] ) ) {
				$raw_new_settings['optin_webhook'] = esc_url_raw( trim( sanitize_text_field( wp_unslash( $_POST['optin_webhook'] ) ) ) );
			}

			/* generation */
			$raw_new_settings['update_last_modified']             = isset( $_POST['update_last_modified'] )             ? (bool) wp_unslash( $_POST['update_last_modified'] )             : '';
			$raw_new_settings['auto_generate_quiz_on_publish']    = isset( $_POST['auto_generate_quiz_on_publish'] )    ? (bool) wp_unslash( $_POST['auto_generate_quiz_on_publish'] )    : '';
			$raw_new_settings['auto_generate_summary_on_publish'] = isset( $_POST['auto_generate_summary_on_publish'] ) ? (bool) wp_unslash( $_POST['auto_generate_summary_on_publish'] ) : '';

			/* attribution */
			$raw_new_settings['show_attribution'] = isset( $_POST['show_attribution'] ) ? (bool) wp_unslash( $_POST['show_attribution'] ) : '';

			/* multi-select generation_post_types */
			$posted_generation_post_types = filter_input(
				INPUT_POST,
				'nuclen_generation_post_types',
				FILTER_SANITIZE_FULL_SPECIAL_CHARS,
				FILTER_REQUIRE_ARRAY
			);
			if ( is_array( $posted_generation_post_types ) ) {
				$raw_new_settings['generation_post_types'] = array_map(
					'sanitize_text_field',
					wp_unslash( $posted_generation_post_types )
				);
			} else {
				$raw_new_settings['generation_post_types'] = array( 'post' );
			}

			/* ─ sanitise & save ─ */
			$new_settings = $this->nuclen_sanitize_settings( $raw_new_settings );
			update_option( 'nuclear_engagement_settings', $new_settings );

			/* ─ generate custom CSS if theme = custom ─ */
			if ( $new_settings['theme'] === 'custom' ) {
				$custom_css = "
:root{
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

#nuclen-quiz-container{
	font-size: {$new_settings['font_size']}px;
	color: var(--nuclen-quiz-font-color);
	background-color: var(--nuclen-quiz-bg-color);
	border: var(--nuclen-quiz-border-width) var(--nuclen-quiz-border-style) var(--nuclen-quiz-border-color);
	border-radius: var(--nuclen-quiz-border-radius);
	box-shadow: 0 0 var(--nuclen-quiz-shadow-blur) var(--nuclen-quiz-shadow-color);
}

#nuclen-summary-container{
	font-size: {$new_settings['summary_font_size']}px;
	color: var(--nuclen-summary-font-color);
	background-color: var(--nuclen-summary-bg-color);
	border: var(--nuclen-summary-border-width) var(--nuclen-summary-border-style) var(--nuclen-summary-border-color);
	border-radius: var(--nuclen-summary-border-radius);
	box-shadow: 0 0 var(--nuclen-summary-shadow-blur) var(--nuclen-summary-shadow-color);
}
";
				$css_info        = \NuclearEngagement\Utils::nuclen_get_custom_css_info();
				$custom_dir      = $css_info['dir'];
				$custom_css_path = $css_info['path'];

				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				WP_Filesystem();
				global $wp_filesystem;

				if ( is_object( $wp_filesystem ) ) {
					if ( ! $wp_filesystem->exists( $custom_dir ) ) {
						if ( ! wp_mkdir_p( $custom_dir ) ) {
							echo '<div class="notice notice-error"><p>Could not create custom CSS directory.</p></div>';
							return;
						}
					}
					if ( ! $wp_filesystem->is_writable( $custom_dir ) ) {
						echo '<div class="notice notice-error"><p>Custom CSS directory not writable.</p></div>';
					} else {
						$wp_filesystem->put_contents( $custom_css_path, $custom_css );
					}
				} else {
					if ( ! file_exists( $custom_dir ) && ! wp_mkdir_p( $custom_dir ) ) {
						echo '<div class="notice notice-error"><p>Could not create custom CSS directory.</p></div>';
						return;
					}
					if ( false === @file_put_contents( $custom_css_path, $custom_css ) ) {
						echo '<div class="notice notice-error"><p>Could not write custom CSS file.</p></div>';
					}
				}
			}

			$settings = wp_parse_args( $new_settings, $defaults );
			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		/* ─────── RENDER PAGE ─────── */
		include plugin_dir_path( __FILE__ ) . 'partials/nuclen-admin-settings.php';
	}
}
