<?php
/**
 * File: admin/Settings.php
 *
 * No inline color help text now, and everything else is unchanged except from the previous session's code.
 * Full code, no omissions.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'nuclen_enqueue_color_picker' ) );
	}

	/**
	 * Enqueue WP Color Picker on admin pages
	 */
	public function nuclen_enqueue_color_picker( $hook_suffix ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		$inline_js = 'jQuery(document).ready(function($){ $(".wp-color-picker-field").wpColorPicker(); });';
		wp_add_inline_script( 'wp-color-picker', $inline_js );
	}

	/**
	 * Sanitize all settings
	 *
	 * @param array $input
	 * @return array
	 */
	public function nuclen_sanitize_settings( $input ) {
		$valid_themes = array( 'bright', 'dark', 'custom', 'none' );
		$theme        = isset( $input['theme'] ) && ! empty( $input['theme'] ) ? sanitize_text_field( $input['theme'] ) : 'bright';
		if ( ! in_array( $theme, $valid_themes, true ) ) {
			$theme = 'bright';
		}

		// QUIZ style
		$quiz_font_size = isset( $input['font_size'] ) ? (int) $input['font_size'] : 16;
		if ( $quiz_font_size < 10 ) {
			$quiz_font_size = 10;
		}
		if ( $quiz_font_size > 50 ) {
			$quiz_font_size = 50;
		}

		$quiz_font_color = isset( $input['font_color'] ) && ! empty( $input['font_color'] )
			? sanitize_hex_color( $input['font_color'] )
			: '#000000';

		$quiz_bg_color = isset( $input['bg_color'] ) && ! empty( $input['bg_color'] )
			? sanitize_text_field( $input['bg_color'] )
			: '#ffffff';

		$quiz_border_color = isset( $input['quiz_border_color'] ) ? sanitize_hex_color( $input['quiz_border_color'] ) : '';
		$quiz_border_style = isset( $input['quiz_border_style'] ) ? sanitize_text_field( $input['quiz_border_style'] ) : '';
		$quiz_border_width = isset( $input['quiz_border_width'] ) ? (int) $input['quiz_border_width'] : '';

		// Migrate from old border_* if new quiz_border_* empty
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

		$quiz_answer_button_bg_color      = isset( $input['quiz_answer_button_bg_color'] ) ? sanitize_hex_color( $input['quiz_answer_button_bg_color'] ) : '#94544A';
		$quiz_answer_button_border_color  = isset( $input['quiz_answer_button_border_color'] ) ? sanitize_hex_color( $input['quiz_answer_button_border_color'] ) : '#94544A';
		$quiz_answer_button_border_width  = isset( $input['quiz_answer_button_border_width'] ) ? (int) $input['quiz_answer_button_border_width'] : 2;
		$quiz_answer_button_border_radius = isset( $input['quiz_answer_button_border_radius'] ) ? (int) $input['quiz_answer_button_border_radius'] : 4;

		$quiz_progress_bar_fg_color = isset( $input['quiz_progress_bar_fg_color'] ) ? sanitize_hex_color( $input['quiz_progress_bar_fg_color'] ) : '#1B977D';
		$quiz_progress_bar_bg_color = isset( $input['quiz_progress_bar_bg_color'] ) ? sanitize_hex_color( $input['quiz_progress_bar_bg_color'] ) : '#e0e0e0';
		$quiz_progress_bar_height   = isset( $input['quiz_progress_bar_height'] ) ? (int) $input['quiz_progress_bar_height'] : 10;

		// SUMMARY style
		$summary_font_size = isset( $input['summary_font_size'] ) ? (int) $input['summary_font_size'] : 16;
		if ( $summary_font_size < 10 ) {
			$summary_font_size = 10;
		}
		if ( $summary_font_size > 50 ) {
			$summary_font_size = 50;
		}

		$summary_font_color = isset( $input['summary_font_color'] ) && ! empty( $input['summary_font_color'] )
			? sanitize_hex_color( $input['summary_font_color'] )
			: '#000000';

		$summary_bg_color = isset( $input['summary_bg_color'] ) && ! empty( $input['summary_bg_color'] )
			? sanitize_text_field( $input['summary_bg_color'] )
			: '#ffffff';

		$summary_border_color = isset( $input['summary_border_color'] ) ? sanitize_hex_color( $input['summary_border_color'] ) : '';
		$summary_border_style = isset( $input['summary_border_style'] ) ? sanitize_text_field( $input['summary_border_style'] ) : '';
		$summary_border_width = isset( $input['summary_border_width'] ) ? (int) $input['summary_border_width'] : '';

		// fallback
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
		$summary_shadow_color  = isset( $input['summary_shadow_color'] ) ? sanitize_text_field( $input['summary_shadow_color'] ) : 'rgba(0,0,0,0.15)';
		$summary_shadow_blur   = isset( $input['summary_shadow_blur'] ) ? (int) $input['summary_shadow_blur'] : 8;

		// quiz question/answer counts
		$questions_per_quiz = isset( $input['questions_per_quiz'] ) ? (int) $input['questions_per_quiz'] : 3;
		if ( $questions_per_quiz < 3 ) {
			$questions_per_quiz = 3;
		}
		if ( $questions_per_quiz > 10 ) {
			$questions_per_quiz = 10;
		}

		$answers_per_question = isset( $input['answers_per_question'] ) ? (int) $input['answers_per_question'] : 2;
		if ( $answers_per_question < 2 ) {
			$answers_per_question = 2;
		}
		if ( $answers_per_question > 4 ) {
			$answers_per_question = 4;
		}

		// placement
		$allowed_display = array( 'manual', 'before', 'after' );
		$display_summary = isset( $input['display_summary'] ) ? sanitize_text_field( $input['display_summary'] ) : 'manual';
		if ( ! in_array( $display_summary, $allowed_display, true ) ) {
			$display_summary = 'manual';
		}
		$display_quiz = isset( $input['display_quiz'] ) ? sanitize_text_field( $input['display_quiz'] ) : 'manual';
		if ( ! in_array( $display_quiz, $allowed_display, true ) ) {
			$display_quiz = 'manual';
		}

		// custom HTML
		$custom_quiz_html_before = isset( $input['custom_quiz_html_before'] ) ? wp_kses_post( $input['custom_quiz_html_before'] ) : '';
		$custom_quiz_html_after  = isset( $input['custom_quiz_html_after'] ) ? wp_kses_post( $input['custom_quiz_html_after'] ) : '';

		// Titles
		$quiz_title    = isset( $input['quiz_title'] ) ? sanitize_text_field( $input['quiz_title'] ) : 'Test your knowledge';
		$summary_title = isset( $input['summary_title'] ) ? sanitize_text_field( $input['summary_title'] ) : 'Key Facts';

		// opt-in
		$optin_success_message = isset( $input['optin_success_message'] ) ? sanitize_text_field( $input['optin_success_message'] ) : 'Thank you, your submission was successful!';
		$enable_optin          = isset( $input['enable_optin'] ) ? boolval( $input['enable_optin'] ) : false;
		$optin_webhook         = isset( $input['optin_webhook'] ) ? esc_url_raw( trim( $input['optin_webhook'] ) ) : '';
		if ( $enable_optin && empty( $optin_webhook ) ) {
			$enable_optin = false;
		}

		// theme=custom => fallback
		if ( $theme === 'custom' ) {
			if ( empty( $quiz_font_size ) ) {
				$quiz_font_size = 16;
			}
			if ( empty( $quiz_font_color ) ) {
				$quiz_font_color = '#000000';
			}
			if ( empty( $quiz_bg_color ) ) {
				$quiz_bg_color = '#ffffff';
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
			if ( empty( $summary_font_size ) ) {
				$summary_font_size = 16;
			}
			if ( empty( $summary_font_color ) ) {
				$summary_font_color = '#000000';
			}
			if ( empty( $summary_bg_color ) ) {
				$summary_bg_color = '#ffffff';
			}
		}

		// generation
		$gen_post_types = isset( $input['generation_post_types'] ) ? $input['generation_post_types'] : array( 'post' );
		if ( ! is_array( $gen_post_types ) ) {
			$gen_post_types = array( 'post' );
		}
		$gen_post_types = array_map( 'sanitize_text_field', $gen_post_types );

		$update_last_modified             = isset( $input['update_last_modified'] ) ? boolval( $input['update_last_modified'] ) : false;
		$auto_generate_quiz_on_publish    = isset( $input['auto_generate_quiz_on_publish'] ) ? boolval( $input['auto_generate_quiz_on_publish'] ) : false;
		$auto_generate_summary_on_publish = isset( $input['auto_generate_summary_on_publish'] ) ? boolval( $input['auto_generate_summary_on_publish'] ) : false;

		// attribution
		$show_attribution = isset( $input['show_attribution'] ) ? boolval( $input['show_attribution'] ) : false;

		return array(
			'theme'                            => $theme,

			// quiz style
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

			// summary style
			'summary_font_size'                => $summary_font_size,
			'summary_font_color'               => $summary_font_color,
			'summary_bg_color'                 => $summary_bg_color,

			'summary_border_color'             => $summary_border_color,
			'summary_border_style'             => $summary_border_style,
			'summary_border_width'             => $summary_border_width,
			'summary_border_radius'            => $summary_border_radius,
			'summary_shadow_color'             => $summary_shadow_color,
			'summary_shadow_blur'              => $summary_shadow_blur,

			// quiz items
			'questions_per_quiz'               => $questions_per_quiz,
			'answers_per_question'             => $answers_per_question,

			// display
			'display_summary'                  => $display_summary,
			'display_quiz'                     => $display_quiz,

			'custom_quiz_html_before'          => $custom_quiz_html_before,
			'custom_quiz_html_after'           => $custom_quiz_html_after,

			'quiz_title'                       => $quiz_title,
			'summary_title'                    => $summary_title,

			// optin
			'enable_optin'                     => $enable_optin,
			'optin_webhook'                    => $optin_webhook,
			'optin_success_message'            => $optin_success_message,

			// generation
			'generation_post_types'            => $gen_post_types,
			'update_last_modified'             => $update_last_modified,
			'auto_generate_quiz_on_publish'    => $auto_generate_quiz_on_publish,
			'auto_generate_summary_on_publish' => $auto_generate_summary_on_publish,

			// attribution
			'show_attribution'                 => $show_attribution,
		);
	}

	/**
	 * Settings page HTML
	 */
	public function nuclen_display_settings_page() {
		$raw_settings = get_option( 'nuclear_engagement_settings', array() );
		$defaults     = \NuclearEngagement\Defaults::nuclen_get_default_settings();
		$settings     = wp_parse_args( $this->nuclen_sanitize_settings( $raw_settings ), $defaults );

		if ( isset( $_POST['nuclen_save_settings'] ) && check_admin_referer( 'nuclen_settings_nonce', 'nuclen_settings_nonce_field' ) ) {
			// gather raw input
			$raw_new_settings          = array();
			$raw_new_settings['theme'] = isset( $_POST['nuclen_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['nuclen_theme'] ) ) : '';

			$field_map = array(
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

				'nuclen_summary_font_size'                => 'summary_font_size',
				'nuclen_summary_font_color'               => 'summary_font_color',
				'nuclen_summary_bg_color'                 => 'summary_bg_color',

				'nuclen_summary_border_color'             => 'summary_border_color',
				'nuclen_summary_border_style'             => 'summary_border_style',
				'nuclen_summary_border_width'             => 'summary_border_width',
				'nuclen_summary_border_radius'            => 'summary_border_radius',
				'nuclen_summary_shadow_color'             => 'summary_shadow_color',
				'nuclen_summary_shadow_blur'              => 'summary_shadow_blur',

				'nuclen_border_color'                     => 'border_color',
				'nuclen_border_style'                     => 'border_style',
				'nuclen_border_width'                     => 'border_width',

				'nuclen_questions_per_quiz'               => 'questions_per_quiz',
				'nuclen_answers_per_question'             => 'answers_per_question',

				'nuclen_display_summary'                  => 'display_summary',
				'nuclen_display_quiz'                     => 'display_quiz',
			);

			foreach ( $field_map as $post_key => $arr_key ) {
				if ( isset( $_POST[ $post_key ] ) ) {
					$raw_new_settings[ $arr_key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
				}
			}
			// custom quiz HTML
			$raw_new_settings['custom_quiz_html_before'] = isset( $_POST['custom_quiz_html_before'] ) ? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_before'] ) ) : '';
			$raw_new_settings['custom_quiz_html_after']  = isset( $_POST['custom_quiz_html_after'] ) ? wp_kses_post( wp_unslash( $_POST['custom_quiz_html_after'] ) ) : '';

			$raw_new_settings['quiz_title']    = isset( $_POST['quiz_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_title'] ) ) : '';
			$raw_new_settings['summary_title'] = isset( $_POST['summary_title'] ) ? sanitize_text_field( wp_unslash( $_POST['summary_title'] ) ) : '';

			$raw_new_settings['optin_success_message'] = isset( $_POST['optin_success_message'] ) ? sanitize_text_field( wp_unslash( $_POST['optin_success_message'] ) ) : '';
			$raw_new_settings['enable_optin']          = isset( $_POST['enable_optin'] ) ? boolval( wp_unslash( $_POST['enable_optin'] ) ) : '';
			if ( isset( $_POST['optin_webhook'] ) ) {
				$raw_new_settings['optin_webhook'] = esc_url_raw( trim( sanitize_text_field( wp_unslash( $_POST['optin_webhook'] ) ) ) );
			}

			// generation
			$raw_new_settings['update_last_modified']             = isset( $_POST['update_last_modified'] ) ? boolval( wp_unslash( $_POST['update_last_modified'] ) ) : '';
			$raw_new_settings['auto_generate_quiz_on_publish']    = isset( $_POST['auto_generate_quiz_on_publish'] ) ? boolval( wp_unslash( $_POST['auto_generate_quiz_on_publish'] ) ) : '';
			$raw_new_settings['auto_generate_summary_on_publish'] = isset( $_POST['auto_generate_summary_on_publish'] ) ? boolval( wp_unslash( $_POST['auto_generate_summary_on_publish'] ) ) : '';

			// show_attribution
			$raw_new_settings['show_attribution'] = isset( $_POST['show_attribution'] ) ? boolval( wp_unslash( $_POST['show_attribution'] ) ) : '';

			// Multi-select generation_post_types
			$posted_generation_post_types = filter_input(
				INPUT_POST,
				'nuclen_generation_post_types',
				FILTER_SANITIZE_FULL_SPECIAL_CHARS,
				FILTER_REQUIRE_ARRAY
			);
			if ( ! is_null( $posted_generation_post_types ) && is_array( $posted_generation_post_types ) ) {
				$posted_generation_post_types              = wp_unslash( $posted_generation_post_types );
				$raw_new_settings['generation_post_types'] = array_map( 'sanitize_text_field', $posted_generation_post_types );
			} else {
				$raw_new_settings['generation_post_types'] = array( 'post' );
			}

			// Now sanitize
			$new_settings = $this->nuclen_sanitize_settings( $raw_new_settings );
			update_option( 'nuclear_engagement_settings', $new_settings );

			// If custom, generate custom CSS
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
					$written = @file_put_contents( $custom_css_path, $custom_css );
					if ( ! $written ) {
						echo '<div class="notice notice-error"><p>Could not write custom CSS file.</p></div>';
					}
				}
			}

			$settings = wp_parse_args( $new_settings, $defaults );
			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		include plugin_dir_path( __FILE__ ) . 'partials/nuclen-admin-settings.php';
	}
}
