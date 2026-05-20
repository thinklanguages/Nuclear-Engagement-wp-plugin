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

		/* ── Security fix: Sanitize all CSS values to prevent injection attacks ── */
		$s = CssSanitizer::sanitize_css_settings( $s );

		$css  = ':root{' . "\n";
		$css .= '/* ───── Modern Design System Integration ───── */' . "\n";
		$css .= '/* Map user settings to design tokens while maintaining backward compatibility */' . "\n\n";
		$css .= '/* ───── Quiz container ───── */' . "\n";
		$css .= '--nuclen-fg-color: ' . $s['font_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-font-color: ' . $s['font_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-bg-color: ' . $s['bg_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-border-color: ' . $s['quiz_border_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-border-style: ' . $s['quiz_border_style'] . ';' . "\n";
		$css .= '	--nuclen-quiz-border-width: ' . $s['quiz_border_width'] . 'px;' . "\n";
		$css .= '	--nuclen-quiz-border-radius: ' . $s['quiz_border_radius'] . 'px;' . "\n";
		$css .= '	--nuclen-quiz-shadow-color: ' . $s['quiz_shadow_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-shadow-blur: ' . $s['quiz_shadow_blur'] . 'px;' . "\n\n";
		$css .= '	/* ───── Quiz answer buttons ───── */' . "\n";
		$css .= '	--nuclen-quiz-button-bg: ' . $s['quiz_answer_button_bg_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-button-border-color: ' . $s['quiz_answer_button_border_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-button-border-width: ' . $s['quiz_answer_button_border_width'] . 'px;' . "\n";
		$css .= '	--nuclen-quiz-button-border-radius: ' . $s['quiz_answer_button_border_radius'] . 'px;' . "\n\n";
		$css .= '	/* ───── Progress bar ───── */' . "\n";
		$css .= '	--nuclen-quiz-progress-fg: ' . $s['quiz_progress_bar_fg_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-progress-bg: ' . $s['quiz_progress_bar_bg_color'] . ';' . "\n";
		$css .= '	--nuclen-quiz-progress-height: ' . $s['quiz_progress_bar_height'] . 'px;' . "\n\n";
		$css .= '	/* ───── Summary container ───── */' . "\n";
		$css .= '	--nuclen-summary-font-color: ' . $s['summary_font_color'] . ';' . "\n";
		$css .= '	--nuclen-summary-bg-color: ' . $s['summary_bg_color'] . ';' . "\n";
		$css .= '	--nuclen-summary-border-color: ' . $s['summary_border_color'] . ';' . "\n";
		$css .= '	--nuclen-summary-border-style: ' . $s['summary_border_style'] . ';' . "\n";
		$css .= '	--nuclen-summary-border-width: ' . $s['summary_border_width'] . 'px;' . "\n";
		$css .= '	--nuclen-summary-border-radius: ' . $s['summary_border_radius'] . 'px;' . "\n";
		$css .= '	--nuclen-summary-shadow-color: ' . $s['summary_shadow_color'] . ';' . "\n";
		$css .= '	--nuclen-summary-shadow-blur: ' . $s['summary_shadow_blur'] . 'px;' . "\n\n";
		$css .= '	/* ───── TOC container ───── */' . "\n";
		$css .= '	--nuclen-toc-font-size: ' . $s['toc_font_size'] . 'px;' . "\n";
		$css .= '	--nuclen-toc-font-color: ' . $s['toc_font_color'] . ';' . "\n";
		$css .= '	--nuclen-toc-bg-color: ' . $s['toc_bg_color'] . ';' . "\n";
		$css .= '	--nuclen-toc-border-color: ' . $s['toc_border_color'] . ';' . "\n";
		$css .= '	--nuclen-toc-border-style: ' . $s['toc_border_style'] . ';' . "\n";
		$css .= '	--nuclen-toc-border-width: ' . $s['toc_border_width'] . 'px;' . "\n";
		$css .= '	--nuclen-toc-border-radius: ' . $s['toc_border_radius'] . 'px;' . "\n";
		$css .= '	--nuclen-toc-shadow-color: ' . $s['toc_shadow_color'] . ';' . "\n";
		$css .= '	--nuclen-toc-shadow-blur: ' . $s['toc_shadow_blur'] . 'px;' . "\n";
		$css .= '	--nuclen-toc-link: ' . $s['toc_link_color'] . ';' . "\n";
		$css .= '	--nuclen-toc-sticky-max-width: ' . $s['toc_sticky_max_width'] . 'px;' . "\n\n";
		$css .= '	/* ───── Design System Overrides ───── */' . "\n";
		$css .= '	/* Override design tokens with user customizations */' . "\n";
		$css .= '	--ne-text-primary: ' . $s['font_color'] . ';' . "\n";
		$css .= '	--ne-bg-primary: ' . $s['bg_color'] . ';' . "\n";
		$css .= '	--ne-border-primary: ' . $s['quiz_border_color'] . ';' . "\n";
		$css .= '	--ne-color-primary-500: ' . $s['quiz_answer_button_border_color'] . ';' . "\n";
		$css .= '	--ne-color-primary-100: color-mix(in srgb, ' . $s['quiz_answer_button_border_color'] . ' 20%, transparent);' . "\n";
		$css .= '	--ne-color-primary-050: color-mix(in srgb, ' . $s['quiz_answer_button_border_color'] . ' 10%, transparent);' . "\n\n";
		$css .= '	/* ───── Legacy fallbacks ───── */' . "\n";
		$css .= '	--nuclen-fg-color: var(--nuclen-quiz-font-color);' . "\n";
		$css .= '	--nuclen-border-color: var(--nuclen-quiz-border-color);' . "\n";
		$css .= '	--nuclen-border-style: var(--nuclen-quiz-border-style);' . "\n";
		$css .= '	--nuclen-border-width: var(--nuclen-quiz-border-width);' . "\n";
		$css .= '	--nuclen-border-radius: var(--nuclen-quiz-border-radius);' . "\n";
		$css .= '	--nuclen-progress-fg: var(--nuclen-quiz-progress-fg);' . "\n";
		$css .= '	--nuclen-progress-bg: var(--nuclen-quiz-progress-bg);' . "\n";
		$css .= '}' . "\n\n";
		$css .= '/* ─── Apply variables to actual elements ─── */' . "\n";
		$css .= '.nuclen-root .nuclen-quiz{' . "\n";
		$css .= '	border: var(--nuclen-quiz-border-width) var(--nuclen-quiz-border-style) var(--nuclen-quiz-border-color);' . "\n";
		$css .= '	border-radius: var(--nuclen-quiz-border-radius);' . "\n";
		$css .= '	box-shadow: 0 0 var(--nuclen-quiz-shadow-blur) var(--nuclen-quiz-shadow-color);' . "\n";
		$css .= '	background: var(--nuclen-quiz-bg-color);' . "\n";
		$css .= '	color: var(--nuclen-quiz-font-color, var(--nuclen-fg-color));' . "\n";
		$css .= '}' . "\n\n";
		$css .= '.nuclen-root .nuclen-summary{' . "\n";
		$css .= '	border: var(--nuclen-summary-border-width) var(--nuclen-summary-border-style) var(--nuclen-summary-border-color);' . "\n";
		$css .= '	border-radius: var(--nuclen-summary-border-radius);' . "\n";
		$css .= '	box-shadow: 0 0 var(--nuclen-summary-shadow-blur) var(--nuclen-summary-shadow-color);' . "\n";
		$css .= '	background: var(--nuclen-summary-bg-color);' . "\n";
		$css .= '	color: var(--nuclen-summary-font-color, var(--nuclen-fg-color));' . "\n";
		$css .= '}' . "\n\n";
		$css .= '.nuclen-root .nuclen-toc-wrapper{' . "\n";
		$css .= '	font-size: var(--nuclen-toc-font-size);' . "\n";
		$css .= '	border: var(--nuclen-toc-border-width) var(--nuclen-toc-border-style) var(--nuclen-toc-border-color);' . "\n";
		$css .= '	border-radius: var(--nuclen-toc-border-radius);' . "\n";
		$css .= '	box-shadow: 0 0 var(--nuclen-toc-shadow-blur) var(--nuclen-toc-shadow-color);' . "\n";
		$css .= '	background: var(--nuclen-toc-bg-color);' . "\n";
		$css .= '	color: var(--nuclen-toc-font-color);' . "\n";
		$css .= '}' . "\n";
		$css .= '.nuclen-root .nuclen-toc-wrapper a{' . "\n";
		$css .= '	color: var(--nuclen-toc-link);' . "\n";
		$css .= '}' . "\n\n";
		$css .= '.nuclen-root #nuclen-quiz-progress-bar-container{' . "\n";
		$css .= '	background: var(--nuclen-quiz-progress-bg);' . "\n";
		$css .= '	height: var(--nuclen-quiz-progress-height);' . "\n";
		$css .= '}' . "\n";
		$css .= '.nuclen-root #nuclen-quiz-progress-bar{' . "\n";
		$css .= '	background: var(--nuclen-quiz-progress-fg);' . "\n";
		$css .= '	height: 100%;' . "\n";
		$css .= '	width: 0;' . "\n";
		$css .= '}' . "\n";
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
