<?php
declare(strict_types=1);
/**
 * File: admin/trait-settings-custom-css.php
 *
 * Generates / writes the custom-theme CSS file.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SettingsPageCustomCSSTrait {

	/**
	 * Write (or overwrite) the custom-theme CSS file.
	 *
	 * @param array $s Sanitised settings array.
	 */
	protected function nuclen_write_custom_css( array $s ): void {

		/* ── Fill any missing values so we never output empty CSS vars ── */
		$s = wp_parse_args( $s, \NuclearEngagement\Defaults::nuclen_get_default_settings() );

		$css = <<<CSS
:root{
    /* ───── Quiz container ───── */
    --nuclen-fg-color: {$s['font_color']};
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

    /* ───── TOC container ───── */
    --nuclen-toc-font-size: {$s['toc_font_size']}px;
    --nuclen-toc-font-color: {$s['toc_font_color']};
    --nuclen-toc-bg-color: {$s['toc_bg_color']};
    --nuclen-toc-border-color: {$s['toc_border_color']};
    --nuclen-toc-border-style: {$s['toc_border_style']};
    --nuclen-toc-border-width: {$s['toc_border_width']}px;
    --nuclen-toc-border-radius: {$s['toc_border_radius']}px;
    --nuclen-toc-shadow-color: {$s['toc_shadow_color']};
    --nuclen-toc-shadow-blur: {$s['toc_shadow_blur']}px;
    --nuclen-toc-link: {$s['toc_link_color']};
    --nuclen-toc-sticky-max-width: {$s['toc_sticky_max_width']}px;

    /* ───── Legacy fallbacks ───── */
    --nuclen-fg-color: var(--nuclen-quiz-font-color);
    --nuclen-border-color: var(--nuclen-quiz-border-color);
    --nuclen-border-style: var(--nuclen-quiz-border-style);
    --nuclen-border-width: var(--nuclen-quiz-border-width);
    --nuclen-border-radius: var(--nuclen-quiz-border-radius);
    --nuclen-progress-fg: var(--nuclen-quiz-progress-fg);
    --nuclen-progress-bg: var(--nuclen-quiz-progress-bg);
}

/* ─── Apply variables to actual elements ─── */
.nuclen-root .nuclen-quiz{
    border: var(--nuclen-quiz-border-width) var(--nuclen-quiz-border-style) var(--nuclen-quiz-border-color);
    border-radius: var(--nuclen-quiz-border-radius);
    box-shadow: 0 0 var(--nuclen-quiz-shadow-blur) var(--nuclen-quiz-shadow-color);
    background: var(--nuclen-quiz-bg-color);
    color: var(--nuclen-quiz-font-color, var(--nuclen-fg-color));
}

.nuclen-root .nuclen-summary{
    border: var(--nuclen-summary-border-width) var(--nuclen-summary-border-style) var(--nuclen-summary-border-color);
    border-radius: var(--nuclen-summary-border-radius);
    box-shadow: 0 0 var(--nuclen-summary-shadow-blur) var(--nuclen-summary-shadow-color);
    background: var(--nuclen-summary-bg-color);
    color: var(--nuclen-summary-font-color, var(--nuclen-fg-color));
}

.nuclen-root .nuclen-toc-wrapper{
    font-size: var(--nuclen-toc-font-size);
    border: var(--nuclen-toc-border-width) var(--nuclen-toc-border-style) var(--nuclen-toc-border-color);
    border-radius: var(--nuclen-toc-border-radius);
    box-shadow: 0 0 var(--nuclen-toc-shadow-blur) var(--nuclen-toc-shadow-color);
    background: var(--nuclen-toc-bg-color);
    color: var(--nuclen-toc-font-color);
}
.nuclen-root .nuclen-toc-wrapper a{
    color: var(--nuclen-toc-link);
}

.nuclen-root #nuclen-quiz-progress-bar-container{
    background: var(--nuclen-quiz-progress-bg);
    height: var(--nuclen-quiz-progress-height);
}
.nuclen-root #nuclen-quiz-progress-bar{
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
			if ( $wp_filesystem->put_contents( $custom_css_path, $css ) !== false ) {
				// Update the version hash when the file is successfully saved
				$file_mtime = time();
				$file_hash  = md5( $css );
				$version    = $file_mtime . '-' . substr( $file_hash, 0, 8 );
				update_option( 'nuclen_custom_css_version', $version );
			}
		} else {
			/* fallback */
			if ( ! file_exists( $custom_dir ) && ! wp_mkdir_p( $custom_dir ) ) {
				echo '<div class="notice notice-error"><p>' .
					esc_html__( 'Could not create custom CSS directory.', 'nuclear-engagement' ) .
					'</p></div>';
				return;
			}
			if ( false !== @file_put_contents( $custom_css_path, $css, LOCK_EX ) ) {
				// Update the version hash when the file is successfully saved (fallback)
				$file_mtime = time();
				$file_hash  = md5( $css );
				$version    = $file_mtime . '-' . substr( $file_hash, 0, 8 );
				update_option( 'nuclen_custom_css_version', $version );
			} else {
				echo '<div class="notice notice-error"><p>' .
					esc_html__( 'Could not write custom CSS file.', 'nuclear-engagement' ) .
					'</p></div>';
			}
		}
	}
}
