<?php
/**
 * File: admin/trait-settings-sanitize-style.php
 *
 * Sanitises every visual-style field for **quiz, summary and TOC**.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SettingsSanitizeStyleTrait {

	/*───────────────────────────────────────────────────────────*/
	/*  Helpers                                                  */
	/*───────────────────────────────────────────────────────────*/

	/** Validate a 3/4/6/8-digit HEX code or return an empty string. */
	private static function nuclen_validate_hex( string $raw ): string {
		$raw = ltrim( trim( $raw ), '#' );
		if ( $raw === '' ) {
			return '';
		}
		/* 3/4-digit → expand to 6/8-digit */
		if ( preg_match( '/^[0-9a-fA-F]{3,4}$/', $raw ) ) {
			$expanded = '';
			foreach ( str_split( $raw ) as $c ) {
				$expanded .= $c . $c;
			}
			return '#' . strtolower( $expanded );
		}
		/* 6/8-digit – keep as-is */
		if ( preg_match( '/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $raw ) ) {
			return '#' . strtolower( $raw );
		}
		return '';
	}

	/**
	 * Sanitise all style-related keys, returning a clean array
	 * that **now includes TOC fields**.
	 */
	private function nuclen_sanitize_style( array $in ): array {

		/* Helper: colour with fallback */
		$hx = static function ( $val, $fallback ) {
			$val = (string) $val;

			/* 1) HEX */
			$hex = self::nuclen_validate_hex( $val );
			if ( $hex !== '' ) {
				return $hex;
			}

			/* 2) rgb()/rgba() */
			$rgb_re = '/^rgba?\\(\\s*(?:\\d{1,3}\\s*,\\s*){2}\\d{1,3}(?:\\s*,\\s*(?:0|1|0?\\.\\d+))?\\s*\\)$/i';
			if ( preg_match( $rgb_re, $val ) ) {
				return $val;
			}
			return $fallback;
		};

		/* ───────── Quiz container ───────── */
		$font_size  = max( 10, min( 50, (int) ( $in['font_size'] ?? 16 ) ) );
		$font_color = $hx( $in['font_color'] ?? '', '#000000' );
		$bg_color   = $hx( $in['bg_color']   ?? '', '#ffffff' );

		$border_color  = $hx( $in['quiz_border_color']  ?? ( $in['border_color'] ?? '' ), '#000000' );
		$border_style  = sanitize_text_field( $in['quiz_border_style'] ?? ( $in['border_style'] ?? 'solid' ) );
		$border_width  = (int) ( $in['quiz_border_width']  ?? ( $in['border_width'] ?? 1 ) );
		$border_radius = (int) ( $in['quiz_border_radius'] ?? 6 );

		$shadow_color = sanitize_text_field( $in['quiz_shadow_color'] ?? 'rgba(0,0,0,0.15)' );
		$shadow_blur  = (int) ( $in['quiz_shadow_blur']  ?? 8 );

		$btn_bg       = $hx( $in['quiz_answer_button_bg_color']      ?? '', '#94544A' );
		$btn_border   = $hx( $in['quiz_answer_button_border_color']  ?? '', '#94544A' );
		$btn_b_width  = (int) ( $in['quiz_answer_button_border_width']  ?? 2 );
		$btn_b_radius = (int) ( $in['quiz_answer_button_border_radius'] ?? 4 );

		$pb_fg = $hx( $in['quiz_progress_bar_fg_color'] ?? '', '#1B977D' );
		$pb_bg = $hx( $in['quiz_progress_bar_bg_color'] ?? '', '#e0e0e0' );
		$pb_ht = (int) ( $in['quiz_progress_bar_height'] ?? 10 );

		/* ───────── Summary container ───────── */
		$s_font_size  = max( 10, min( 50, (int) ( $in['summary_font_size'] ?? 16 ) ) );
		$s_font_color = $hx( $in['summary_font_color'] ?? '', '#000000' );
		$s_bg_color   = $hx( $in['summary_bg_color']   ?? '', '#ffffff' );

		$s_border_color  = $hx( $in['summary_border_color'] ?? ( $in['border_color'] ?? '' ), '#000000' );
		$s_border_style  = sanitize_text_field( $in['summary_border_style'] ?? ( $in['border_style'] ?? 'solid' ) );
		$s_border_width  = (int) ( $in['summary_border_width']  ?? ( $in['border_width'] ?? 1 ) );
		$s_border_radius = (int) ( $in['summary_border_radius'] ?? 6 );

		$s_shadow_color = sanitize_text_field( $in['summary_shadow_color'] ?? 'rgba(0,0,0,0.15)' );
		$s_shadow_blur  = (int) ( $in['summary_shadow_blur'] ?? 8 );

		/* ───────── TOC container ───────── */
		$t_font_size  = max( 10, min( 50, (int) ( $in['toc_font_size'] ?? 16 ) ) );
		$t_font_color = $hx( $in['toc_font_color'] ?? '', '#000000' );
		$t_bg_color   = $hx( $in['toc_bg_color']   ?? '', '#ffffff' );

		$t_border_color  = $hx( $in['toc_border_color'] ?? ( $in['border_color'] ?? '' ), '#000000' );
		$t_border_style  = sanitize_text_field( $in['toc_border_style'] ?? ( $in['border_style'] ?? 'solid' ) );
		$t_border_width  = (int) ( $in['toc_border_width']  ?? ( $in['border_width'] ?? 1 ) );
		$t_border_radius = (int) ( $in['toc_border_radius'] ?? 6 );

		$t_shadow_color = sanitize_text_field( $in['toc_shadow_color'] ?? 'rgba(0,0,0,0.05)' );
		$t_shadow_blur  = (int) ( $in['toc_shadow_blur'] ?? 8 );

		$t_link_color = $hx( $in['toc_link_color'] ?? '', '#1e73be' );

		/*───────────────────────────────────────────────────────────*/
		/*  Return clean array                                       */
		/*───────────────────────────────────────────────────────────*/
		return array(
			/* quiz */
			'font_size'                        => $font_size,
			'font_color'                       => $font_color,
			'bg_color'                         => $bg_color,
			'quiz_border_color'                => $border_color,
			'quiz_border_style'                => $border_style,
			'quiz_border_width'                => $border_width,
			'quiz_border_radius'               => $border_radius,
			'quiz_shadow_color'                => $shadow_color,
			'quiz_shadow_blur'                 => $shadow_blur,
			'quiz_answer_button_bg_color'      => $btn_bg,
			'quiz_answer_button_border_color'  => $btn_border,
			'quiz_answer_button_border_width'  => $btn_b_width,
			'quiz_answer_button_border_radius' => $btn_b_radius,
			'quiz_progress_bar_fg_color'       => $pb_fg,
			'quiz_progress_bar_bg_color'       => $pb_bg,
			'quiz_progress_bar_height'         => $pb_ht,

			/* summary */
			'summary_font_size'                => $s_font_size,
			'summary_font_color'               => $s_font_color,
			'summary_bg_color'                 => $s_bg_color,
			'summary_border_color'             => $s_border_color,
			'summary_border_style'             => $s_border_style,
			'summary_border_width'             => $s_border_width,
			'summary_border_radius'            => $s_border_radius,
			'summary_shadow_color'             => $s_shadow_color,
			'summary_shadow_blur'              => $s_shadow_blur,

			/* toc */
			'toc_font_size'        => $t_font_size,
			'toc_font_color'       => $t_font_color,
			'toc_bg_color'         => $t_bg_color,
			'toc_border_color'     => $t_border_color,
			'toc_border_style'     => $t_border_style,
			'toc_border_width'     => $t_border_width,
			'toc_border_radius'    => $t_border_radius,
			'toc_shadow_color'     => $t_shadow_color,
			'toc_shadow_blur'      => $t_shadow_blur,
			'toc_link_color'       => $t_link_color,
		);
	}
}
