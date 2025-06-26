<?php
/**
 * File: includes/SettingsSanitizer.php
 *
 * Provides sanitization helpers for plugin settings.
 *
 * @package NuclearEngagement
 * @subpackage Core
 * @since     1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement\Core;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles sanitization of all plugin settings.
 *
 * This class provides methods to sanitize various types of settings
 * according to WordPress security best practices.
 *
 * @since 1.0.0
 */
final class SettingsSanitizer {
    /**
     * Sanitization rules for settings.
     *
     * Maps setting keys to their respective sanitization callbacks.
     *
     * @since 1.0.0
     * @var array<string, callable|string>
    private const SANITIZATION_RULES = array(
        'api_key'                              => 'sanitize_text_field',
        'theme'                              => 'sanitize_text_field',
        'font_size'                          => 'absint',
        'font_color'                            => 'sanitize_hex_color',
        'bg_color'                            => 'sanitize_hex_color',
        'border_color'                        => 'sanitize_hex_color',
        'border_style'                        => 'sanitize_text_field',
        'border_width'                        => 'absint',
        'quiz_title'                            => 'sanitize_text_field',
        'summary_title'                      => 'sanitize_text_field',
        'toc_title'                          => 'sanitize_text_field',
        'quiz_label_retake_test'                => 'sanitize_text_field',
        'quiz_label_your_score'              => 'sanitize_text_field',
        'quiz_label_perfect'                    => 'sanitize_text_field',
        'quiz_label_well_done'                => 'sanitize_text_field',
        'quiz_label_retake_prompt'            => 'sanitize_text_field',
        'quiz_label_correct'                    => 'sanitize_text_field',
        'quiz_label_your_answer'                => 'sanitize_text_field',
        'show_attribution'                    => 'rest_sanitize_boolean',
        'display_summary'                      => 'sanitize_text_field',
        'display_quiz'                        => 'sanitize_text_field',
        'display_toc'                          => 'sanitize_text_field',
        'connected'                          => 'rest_sanitize_boolean',
        'wp_app_pass_created'                  => 'rest_sanitize_boolean',
        'wp_app_pass_uuid'                    => 'sanitize_text_field',
        'plugin_password'                      => 'sanitize_text_field',
        'delete_settings_on_uninstall'        => 'rest_sanitize_boolean',
        'delete_generated_content_on_uninstall' => 'rest_sanitize_boolean',
        'delete_optin_data_on_uninstall'        => 'rest_sanitize_boolean',
        'delete_log_file_on_uninstall'        => 'rest_sanitize_boolean',
        'delete_custom_css_on_uninstall'        => 'rest_sanitize_boolean',
        'toc_heading_levels'                    => array( self::class, 'sanitize_heading_levels' ),
        'generation_post_types'              => array( self::class, 'sanitize_post_types' ),
    );

    /**
     * Sanitize an array of settings values.
     *
     * @since 1.0.0
     *
     * @param array $settings Raw settings array to sanitize.
     * @return array Sanitized settings array.
     */
    public static function sanitize_settings( array $settings ): array {
        $sanitized = array();
        foreach ( $settings as $key => $value ) {
            if ( ! is_string( $key ) ) {
                continue;
            }
            $sanitized[ $key ] = self::sanitize_setting( $key, $value );
        }
        return $sanitized;
    }

    /**
     * Sanitize a single setting value.
     *
     * @since 1.0.0
     *
     * @param string $key   Setting key to identify the sanitization rule.
     * @param mixed  $value Setting value to sanitize.
     * @return mixed Sanitized value.
     */
    public static function sanitize_setting( string $key, $value ) {
        if ( isset( self::SANITIZATION_RULES[ $key ] ) ) {
            $rule = self::SANITIZATION_RULES[ $key ];
            return is_callable( $rule ) ? call_user_func( $rule, $value ) : $value;
        }

        if ( is_array( $value ) ) {
            return self::sanitize_array( $value );
        }

        if ( is_bool( $value ) ) {
            return (bool) $value;
        }

        if ( is_numeric( $value ) ) {
            return is_float( $value ) ? (float) $value : (int) $value;
        }

        return sanitize_text_field( (string) $value );
    }

    /**
     * Recursively sanitize an array of values.
     *
     * @since 1.0.0
     * @access private
     *
     * @param array $values Values to sanitize.
     * @return array Sanitized values with all strings properly escaped.
     */
    private static function sanitize_array( array $values ): array {
        return array_map(
            function ( $value ) {
                if ( is_array( $value ) ) {
                        return self::sanitize_array( $value );
                }
                return is_string( $value ) ? sanitize_text_field( $value ) : $value;
            },
            $values
        );
    }

    /**
     * Sanitize heading levels array.
     *
     * @since 1.0.0
     *
     * @param mixed $value Raw value to sanitize as heading levels.
     * @return array<int> Sanitized array of heading levels (1-6).
     */
    public static function sanitize_heading_levels( $value ): array {
        if ( ! is_array( $value ) ) {
            return array( 2, 3, 4, 5, 6 );
        }
        return array_values(
            array_filter(
                array_map( 'absint', $value ),
                function ( $level ) {
                    return $level >= 1 && $level <= 6;
                }
            )
        );
    }

    /**
     * Sanitize post types array.
     *
     * @since 1.0.0
     *
     * @param mixed $value Raw value to sanitize as post types.
     * @return array<int,string> Sanitized array of valid post type slugs.
     */
    public static function sanitize_post_types( $value ): array {
        if ( ! is_array( $value ) ) {
            return array( 'post' );
        }
        return array_values( array_filter( array_map( 'sanitize_key', $value ), 'post_type_exists' ) );
    }
}
