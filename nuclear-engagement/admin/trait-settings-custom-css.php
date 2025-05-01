<?php
/**
 * File: admin/trait-settings-custom-css.php
 *
 * Generates / writes the custom‑theme CSS file.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SettingsPageCustomCSSTrait {

	/**
	 * Write (or overwrite) the custom‑theme CSS file.
	 *
	 * @param array $s Sanitised settings array.
	 */
	protected function nuclen_write_custom_css( array $s ): void {

		$css = <<<CSS
:root{
	/* ───── Quiz container ───── */
	--nuclen-quiz-font-color: {$s['font_color']};
	--nuclen-quiz-bg-color: {$s['bg_color']};
	--nuclen-quiz-border-color: {$s['quiz_border_color']};
	--nuclen-quiz-border-style: {$s['quiz_border_style']};
	--nuclen-quiz-border-width: {$s['quiz_border_width']}px;
	--nuclen-quiz-border-radius: {$s['quiz_border_radius']}px;
	--nuclen-quiz-shadow-color: {$s['quiz_shadow_color']};
	--nuclen-quiz-shadow-blur: {$s['quiz_shadow_blur']}px;

	/* ───── Quiz answer buttons ───── */
	--nuclen-quiz-button-bg: {$s['quiz_answer_button_bg_color']};
	--nuclen-quiz-button-border-color: {$s['quiz_answer_button_border_color']};
	--nuclen-quiz-button-border-width: {$s['quiz_answer_button_border_width']}px;
	--nuclen-quiz-button-border-radius: {$s['quiz_answer_button_border_radius']}px;

	/* ───── Progress bar ───── */
	--nuclen-quiz-progress-fg: {$s['quiz_progress_bar_fg_color']};
	--nuclen-quiz-progress-bg: {$s['quiz_progress_bar_bg_color']};
	--nuclen-quiz-progress-height: {$s['quiz_progress_bar_height']}px;

	/* ───── Summary container ───── */
	--nuclen-summary-font-color: {$s['summary_font_color']};
	--nuclen-summary-bg-color: {$s['summary_bg_color']};
	--nuclen-summary-border-color: {$s['summary_border_color']};
	--nuclen-summary-border-style: {$s['summary_border_style']};
	--nuclen-summary-border-width: {$s['summary_border_width']}px;
	--nuclen-summary-border-radius: {$s['summary_border_radius']}px;
	--nuclen-summary-shadow-color: {$s['summary_shadow_color']};
	--nuclen-summary-shadow-blur: {$s['summary_shadow_blur']}px;

	/* ───── Legacy fallbacks ───── */
	--nuclen-border-color: var(--nuclen-quiz-border-color);
	--nuclen-border-style: var(--nuclen-quiz-border-style);
	--nuclen-border-width: var(--nuclen-quiz-border-width);
	--nuclen-border-radius: var(--nuclen-quiz-border-radius);
	--nuclen-progress-fg: var(--nuclen-quiz-progress-fg);
	--nuclen-progress-bg: var(--nuclen-quiz-progress-bg);
}

/* ─── Apply variables to actual elements ─── */
.nuclen-quiz{
	border: var(--nuclen-quiz-border-width) var(--nuclen-quiz-border-style) var(--nuclen-quiz-border-color);
	border-radius: var(--nuclen-quiz-border-radius);
	box-shadow: 0 0 var(--nuclen-quiz-shadow-blur) var(--nuclen-quiz-shadow-color);
	background: var(--nuclen-quiz-bg-color);
	color: var(--nuclen-quiz-font-color);
}

.nuclen-summary{
	border: var(--nuclen-summary-border-width) var(--nuclen-summary-border-style) var(--nuclen-summary-border-color);
	border-radius: var(--nuclen-summary-border-radius);
	box-shadow: 0 0 var(--nuclen-summary-shadow-blur) var(--nuclen-summary-shadow-color);
	background: var(--nuclen-summary-bg-color);
	color: var(--nuclen-summary-font-color);
}

#nuclen-quiz-progress-bar-container{
	background: var(--nuclen-quiz-progress-bg);
	height: var(--nuclen-quiz-progress-height);
}
#nuclen-quiz-progress-bar{
	background: var(--nuclen-quiz-progress-fg);
	height: 100%;
	width: 0;
}
CSS;

		$css_info        = \NuclearEngagement\Utils::nuclen_get_custom_css_info();
		$custom_dir      = $css_info['dir'];
		$custom_css_path = $css_info['path'];

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		/* create dir if needed, then write */
		if ( is_object( $wp_filesystem ) ) {
			if ( ! $wp_filesystem->exists( $custom_dir ) && ! wp_mkdir_p( $custom_dir ) ) {
				echo '<div class="notice notice-error"><p>' .
				     esc_html__( 'Could not create custom CSS directory.', 'nuclear-engagement' ) .
				     '</p></div>';
				return;
			}
			if ( ! $wp_filesystem->is_writable( $custom_dir ) ) {
				echo '<div class="notice notice-error"><p>' .
				     esc_html__( 'Custom CSS directory not writable.', 'nuclear-engagement' ) .
				     '</p></div>';
				return;
			}
			$wp_filesystem->put_contents( $custom_css_path, $css );
		} else {
			/* fallback */
			if ( ! file_exists( $custom_dir ) && ! wp_mkdir_p( $custom_dir ) ) {
				echo '<div class="notice notice-error"><p>' .
				     esc_html__( 'Could not create custom CSS directory.', 'nuclear-engagement' ) .
				     '</p></div>';
				return;
			}
			if ( false === @file_put_contents( $custom_css_path, $css ) ) {
				echo '<div class="notice notice-error"><p>' .
				     esc_html__( 'Could not write custom CSS file.', 'nuclear-engagement' ) .
				     '</p></div>';
			}
		}
	}
}
