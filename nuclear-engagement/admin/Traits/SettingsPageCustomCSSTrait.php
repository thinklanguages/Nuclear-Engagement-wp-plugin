<?php
/**
 * SettingsPageCustomCSSTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Traits
 */

declare(strict_types=1);
/**
 * File: admin/Traits/SettingsPageCustomCSSTrait.php
 *
 * Generates / writes the custom-theme CSS file with security hardening.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin\Traits;

use NuclearEngagement\Security\CssSanitizer;

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
		$css = $this->nuclen_build_custom_css( $s );
		$this->nuclen_save_custom_css_file( $css );
	}

		/**
		 * Generate the CSS string for the custom theme with security sanitization.
		 *
		 * Security fix: All CSS values are sanitized to prevent code injection attacks.
		 *
		 * @param array $s Sanitised settings array.
		 */
	private function nuclen_build_custom_css( array $s ): string {
		/* ── Fill any missing values so we never output empty CSS vars ── */
		$s = wp_parse_args( $s, \NuclearEngagement\Core\Defaults::nuclen_get_default_settings() );

		/* ── Debug: Log the settings before sanitization ── */
		\NuclearEngagement\Services\LoggingService::log( 'CSS Generation - Settings before sanitization: ' . wp_json_encode( array_slice( $s, 0, 10 ) ) );

		/* ── Security fix: Sanitize all CSS values to prevent injection attacks ── */
		$s = CssSanitizer::sanitize_css_settings( $s );

		/* ── Debug: Log the settings after sanitization ── */
		\NuclearEngagement\Services\LoggingService::log( 'CSS Generation - Settings after sanitization: ' . wp_json_encode( array_slice( $s, 0, 10 ) ) );

		$css = <<<CSS
:root{
/* ───── Modern Design System Integration ───── */
/* Map user settings to design tokens while maintaining backward compatibility */

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

	/* ───── Design System Overrides ───── */
	/* Override design tokens with user customizations */
	--ne-text-primary: {$s['font_color']};
	--ne-bg-primary: {$s['bg_color']};
	--ne-border-primary: {$s['quiz_border_color']};
	--ne-color-primary-500: {$s['quiz_answer_button_border_color']};
	--ne-color-primary-100: color-mix(in srgb, {$s['quiz_answer_button_border_color']} 20%, transparent);
	--ne-color-primary-050: color-mix(in srgb, {$s['quiz_answer_button_border_color']} 10%, transparent);

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
		return $css;
	}

		/**
		 * Save the generated CSS string to disk and update the version option.
		 *
		 * Security: CSS content has already been sanitized by CssSanitizer before reaching this method.
		 * All user inputs are validated and dangerous patterns removed to prevent injection attacks.
		 */
	private function nuclen_save_custom_css_file( string $css ): void {
		$css_info = \NuclearEngagement\Utils\Utils::nuclen_get_custom_css_info();
		if ( empty( $css_info ) ) {
				\NuclearEngagement\Services\LoggingService::notify_admin( __( 'Could not create custom CSS directory.', 'nuclear-engagement' ) );
				return;
		}

		$custom_dir      = $css_info['dir'];
		$custom_css_path = $css_info['path'];

		if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		$success = false;
		if ( is_object( $wp_filesystem ) ) {
			if ( ! $wp_filesystem->exists( $custom_dir ) && ! wp_mkdir_p( $custom_dir ) ) {
					echo '<div class="notice notice-error"><p>' .
							esc_html__( 'Could not create custom CSS directory.', 'nuclear-engagement' ) .
							'</p></div>';
					\NuclearEngagement\Services\LoggingService::log( 'Failed to create custom CSS directory: ' . $custom_dir );
					return;
			}
			if ( ! $wp_filesystem->is_writable( $custom_dir ) ) {
						echo '<div class="notice notice-error"><p>' .
								esc_html__( 'Custom CSS directory not writable.', 'nuclear-engagement' ) .
								'</p></div>';
						\NuclearEngagement\Services\LoggingService::log( 'Custom CSS directory not writable: ' . $custom_dir );
						return;
			}
				$success = $wp_filesystem->put_contents( $custom_css_path, $css ) !== false;
		} else {
			if ( ! file_exists( $custom_dir ) && ! wp_mkdir_p( $custom_dir ) ) {
					echo '<div class="notice notice-error"><p>' .
							esc_html__( 'Could not create custom CSS directory.', 'nuclear-engagement' ) .
							'</p></div>';
					\NuclearEngagement\Services\LoggingService::log( 'Failed to create custom CSS directory: ' . $custom_dir );
					return;
			}
				$success = file_put_contents( $custom_css_path, $css, LOCK_EX ) !== false;
		}

		if ( $success ) {
				$file_mtime = time();
				$file_hash  = md5( $css );
				$version    = $file_mtime . '-' . substr( $file_hash, 0, 8 );
				update_option( 'nuclen_custom_css_version', $version );
		} else {
				echo '<div class="notice notice-error"><p>' .
						esc_html__( 'Could not write custom CSS file.', 'nuclear-engagement' ) .
						'</p></div>';
				\NuclearEngagement\Services\LoggingService::log( 'Failed to write custom CSS file: ' . $custom_css_path );
		}
	}
}
