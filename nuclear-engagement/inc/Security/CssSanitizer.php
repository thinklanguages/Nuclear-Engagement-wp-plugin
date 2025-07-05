<?php
/**
 * CSS Security and Sanitization utility.
 *
 * This class provides comprehensive CSS sanitization to prevent
 * code injection attacks through custom CSS content.
 *
 * @package NuclearEngagement\Security
 * @since   1.1.0
 */

declare(strict_types=1);

namespace NuclearEngagement\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSS Sanitizer for security hardening.
 *
 * This class provides methods to safely sanitize CSS values and prevent
 * malicious code injection through CSS properties and values.
 *
 * @since 1.1.0
 */
class CssSanitizer {

	/**
	 * Allowed CSS units for numeric values.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private const ALLOWED_UNITS = array(
		'px',
		'em',
		'rem',
		'%',
		'vh',
		'vw',
		'pt',
		'pc',
		'in',
		'cm',
		'mm',
	);

	/**
	 * Dangerous CSS patterns that should be blocked.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private const DANGEROUS_PATTERNS = array(
		'/javascript\s*:/i',           // JavaScript URLs.
		'/data\s*:/i',                 // Data URLs (can contain JS).
		'/expression\s*\(/i',          // IE expression().
		'/behaviour\s*:/i',            // IE behavior.
		'/binding\s*:/i',              // Mozilla binding.
		'/@import/i',                  // CSS imports.
		'/url\s*\(/i',                 // URL functions (potential for data: URLs).
		'/\\\\[0-9a-f]/i',            // Unicode escapes.
		'/&#/i',                       // HTML entities.
		'/&\w+;/i',                    // Named entities.
	);

	/**
	 * Sanitize a CSS color value.
	 *
	 * @since 1.1.0
	 *
	 * @param string $color Color value to sanitize.
	 * @return string Sanitized color value.
	 */
	public static function sanitize_color( string $color ): string {
		$color = trim( $color );

		// Remove any dangerous patterns.
		foreach ( self::DANGEROUS_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $color ) ) {
				return '#000000'; // Safe fallback.
			}
		}

		// Allow only valid color formats.
		$color_patterns = array(
			'/^#([0-9a-f]{3}|[0-9a-f]{6})$/i',                           // Hex colors.
		);

		// Check RGB/RGBA values with proper validation
		if ( preg_match( '/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i', $color, $matches ) ) {
			$r = (int) $matches[1];
			$g = (int) $matches[2];
			$b = (int) $matches[3];
			if ( $r >= 0 && $r <= 255 && $g >= 0 && $g <= 255 && $b >= 0 && $b <= 255 ) {
				return $color;
			}
		}

		if ( preg_match( '/^rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/i', $color, $matches ) ) {
			$r = (int) $matches[1];
			$g = (int) $matches[2];
			$b = (int) $matches[3];
			$a = (float) $matches[4];
			if ( $r >= 0 && $r <= 255 && $g >= 0 && $g <= 255 && $b >= 0 && $b <= 255 && $a >= 0 && $a <= 1 ) {
				return $color;
			}
		}

		// Check HSL/HSLA values with proper validation
		if ( preg_match( '/^hsl\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*\)$/i', $color, $matches ) ) {
			$h = (int) $matches[1];
			$s = (int) $matches[2];
			$l = (int) $matches[3];
			if ( $h >= 0 && $h <= 360 && $s >= 0 && $s <= 100 && $l >= 0 && $l <= 100 ) {
				return $color;
			}
		}

		if ( preg_match( '/^hsla\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*,\s*([\d.]+)\s*\)$/i', $color, $matches ) ) {
			$h = (int) $matches[1];
			$s = (int) $matches[2];
			$l = (int) $matches[3];
			$a = (float) $matches[4];
			if ( $h >= 0 && $h <= 360 && $s >= 0 && $s <= 100 && $l >= 0 && $l <= 100 && $a >= 0 && $a <= 1 ) {
				return $color;
			}
		}

		// Check CSS keywords
		if ( preg_match( '/^(transparent|currentcolor)$/i', $color ) ) {
			return $color;
		}

		// Allow predefined CSS color names.
		$valid_color_names = array(
			'black',
			'white',
			'red',
			'green',
			'blue',
			'yellow',
			'cyan',
			'magenta',
			'gray',
			'grey',
			'orange',
			'purple',
			'pink',
			'brown',
			'lime',
			'navy',
		);

		foreach ( $color_patterns as $pattern ) {
			if ( preg_match( $pattern, $color ) ) {
				return $color;
			}
		}

		if ( in_array( strtolower( $color ), $valid_color_names, true ) ) {
			return strtolower( $color );
		}

		// If nothing matches, return safe fallback.
		return '#000000';
	}

	/**
	 * Sanitize a CSS size value with unit.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int $value Size value to sanitize.
	 * @param string     $unit  Default unit if none provided.
	 * @return string Sanitized size value with unit.
	 */
	public static function sanitize_size( $value, string $unit = 'px' ): string {
		// Convert to string and trim.
		$value = trim( (string) $value );

		// Check for dangerous patterns.
		foreach ( self::DANGEROUS_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return '0px'; // Safe fallback.
			}
		}

		// Extract numeric value and unit.
		if ( preg_match( '/^(-?\d*\.?\d+)(' . implode( '|', self::ALLOWED_UNITS ) . ')?$/i', $value, $matches ) ) {
			$number        = (float) $matches[1];
			$detected_unit = $matches[2] ?? $unit;

			// Validate unit is allowed.
			if ( ! in_array( strtolower( $detected_unit ), self::ALLOWED_UNITS, true ) ) {
				$detected_unit = $unit;
			}

			// Prevent negative values for properties that shouldn't have them.
			if ( $number < 0 && in_array( $unit, array( 'px', 'em', 'rem', '%' ), true ) ) {
				$number = 0;
			}

			return $number . $detected_unit;
		}

		// If parsing fails, return safe fallback.
		return '0' . $unit;
	}

	/**
	 * Sanitize a CSS border style value.
	 *
	 * @since 1.1.0
	 *
	 * @param string $style Border style to sanitize.
	 * @return string Sanitized border style.
	 */
	public static function sanitize_border_style( string $style ): string {
		$allowed_styles = array(
			'none',
			'solid',
			'dashed',
			'dotted',
			'double',
			'groove',
			'ridge',
			'inset',
			'outset',
		);

		$style = strtolower( trim( $style ) );

		// Check for dangerous patterns.
		foreach ( self::DANGEROUS_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $style ) ) {
				return 'solid'; // Safe fallback.
			}
		}

		return in_array( $style, $allowed_styles, true ) ? $style : 'solid';
	}

	/**
	 * Sanitize CSS custom property value.
	 *
	 * @since 1.1.0
	 *
	 * @param string $value CSS value to sanitize.
	 * @param string $type  Type of CSS value (color, size, style).
	 * @return string Sanitized CSS value.
	 */
	public static function sanitize_css_value( string $value, string $type = 'general' ): string {
		switch ( $type ) {
			case 'color':
				return self::sanitize_color( $value );
			case 'size':
				return self::sanitize_size( $value );
			case 'border-style':
				return self::sanitize_border_style( $value );
			default:
				return self::sanitize_general_css_value( $value );
		}
	}

	/**
	 * Sanitize general CSS value.
	 *
	 * @since 1.1.0
	 *
	 * @param string $value CSS value to sanitize.
	 * @return string Sanitized CSS value.
	 */
	private static function sanitize_general_css_value( string $value ): string {
		$value = trim( $value );

		// Remove dangerous patterns.
		foreach ( self::DANGEROUS_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return 'initial'; // Safe CSS reset value.
			}
		}

		// Remove any quotes and normalize whitespace.
		$value = preg_replace( '/["\']/', '', $value );
		$value = preg_replace( '/\s+/', ' ', $value );

		// Limit value length to prevent DoS attacks.
		if ( strlen( $value ) > 100 ) {
			$value = substr( $value, 0, 100 );
		}

		return $value;
	}

	/**
	 * Sanitize an entire CSS settings array.
	 *
	 * @since 1.1.0
	 *
	 * @param array $settings CSS settings to sanitize.
	 * @return array Sanitized CSS settings.
	 */
	public static function sanitize_css_settings( array $settings ): array {
		$sanitized = array();

		// Define sanitization rules for different setting types.
		$sanitization_rules = array(
			// Colors.
			'font_color'                       => 'color',
			'bg_color'                         => 'color',
			'quiz_border_color'                => 'color',
			'quiz_shadow_color'                => 'color',
			'quiz_answer_button_bg_color'      => 'color',
			'quiz_answer_button_border_color'  => 'color',
			'quiz_progress_bar_fg_color'       => 'color',
			'quiz_progress_bar_bg_color'       => 'color',
			'summary_font_color'               => 'color',
			'summary_bg_color'                 => 'color',
			'summary_border_color'             => 'color',
			'summary_shadow_color'             => 'color',
			'toc_font_color'                   => 'color',
			'toc_bg_color'                     => 'color',
			'toc_border_color'                 => 'color',
			'toc_shadow_color'                 => 'color',
			'toc_link_color'                   => 'color',

			// Sizes.
			'quiz_border_width'                => 'size',
			'quiz_border_radius'               => 'size',
			'quiz_shadow_blur'                 => 'size',
			'quiz_answer_button_border_width'  => 'size',
			'quiz_answer_button_border_radius' => 'size',
			'quiz_progress_bar_height'         => 'size',
			'summary_border_width'             => 'size',
			'summary_border_radius'            => 'size',
			'summary_shadow_blur'              => 'size',
			'toc_font_size'                    => 'size',
			'toc_border_width'                 => 'size',
			'toc_border_radius'                => 'size',
			'toc_shadow_blur'                  => 'size',
			'toc_sticky_max_width'             => 'size',

			// Border styles.
			'quiz_border_style'                => 'border-style',
			'summary_border_style'             => 'border-style',
			'toc_border_style'                 => 'border-style',
		);

		foreach ( $settings as $key => $value ) {
			// Skip arrays and objects.
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			if ( isset( $sanitization_rules[ $key ] ) ) {
				$sanitized[ $key ] = self::sanitize_css_value( (string) $value, $sanitization_rules[ $key ] );
			} else {
				// For unknown settings, apply general sanitization.
				$sanitized[ $key ] = self::sanitize_general_css_value( (string) $value );
			}
		}

		return $sanitized;
	}
}
