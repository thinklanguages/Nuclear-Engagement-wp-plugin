<?php
/**
 * File: includes/SettingsSanitizer.php
 *
 * Provides sanitization helpers for plugin settings.
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SettingsSanitizer {
    /**
     * Sanitization rules for settings.
     *
     * @var array<string, callable|string>
     */
    private const SANITIZATION_RULES = [
        'api_key'                        => 'sanitize_text_field',
        'theme'                          => 'sanitize_text_field',
        'font_size'                      => 'absint',
        'font_color'                     => 'sanitize_hex_color',
        'bg_color'                       => 'sanitize_hex_color',
        'border_color'                   => 'sanitize_hex_color',
        'border_style'                   => 'sanitize_text_field',
        'border_width'                   => 'absint',
        'quiz_title'                     => 'sanitize_text_field',
        'summary_title'                  => 'sanitize_text_field',
        'toc_title'                      => 'sanitize_text_field',
        'show_attribution'               => 'rest_sanitize_boolean',
        'display_summary'                => 'sanitize_text_field',
        'display_quiz'                   => 'sanitize_text_field',
        'display_toc'                    => 'sanitize_text_field',
        'connected'                      => 'rest_sanitize_boolean',
        'wp_app_pass_created'            => 'rest_sanitize_boolean',
        'wp_app_pass_uuid'               => 'sanitize_text_field',
        'plugin_password'                => 'sanitize_text_field',
        'delete_settings_on_uninstall'   => 'rest_sanitize_boolean',
        'delete_generated_content_on_uninstall' => 'rest_sanitize_boolean',
        'delete_optin_data_on_uninstall' => 'rest_sanitize_boolean',
        'delete_log_file_on_uninstall'   => 'rest_sanitize_boolean',
        'delete_custom_css_on_uninstall' => 'rest_sanitize_boolean',
        'toc_heading_levels'             => [ self::class, 'sanitize_heading_levels' ],
        'generation_post_types'          => [ self::class, 'sanitize_post_types' ],
    ];

    /**
     * Sanitize an array of settings values.
     *
     * @param array $settings Raw settings.
     * @return array Sanitized settings.
     */
    public static function sanitize_settings( array $settings ): array {
        $sanitized = [];
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
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
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
     * @param array $values Values to sanitize.
     * @return array Sanitized values.
     */
    private static function sanitize_array( array $values ): array {
        return array_map( function ( $value ) {
            if ( is_array( $value ) ) {
                return self::sanitize_array( $value );
            }
            return is_string( $value ) ? sanitize_text_field( $value ) : $value;
        }, $values );
    }

    /**
     * Sanitize heading levels array.
     *
     * @param mixed $value Raw value.
     * @return array<int> Sanitized levels.
     */
    public static function sanitize_heading_levels( $value ): array {
        if ( ! is_array( $value ) ) {
            return [ 2, 3, 4, 5, 6 ];
        }
        return array_values( array_filter( array_map( 'absint', $value ), function ( $level ) {
            return $level >= 1 && $level <= 6;
        } ) );
    }

    /**
     * Sanitize post types array.
     *
     * @param mixed $value Raw value.
     * @return array<int,string> Sanitized post types.
     */
    public static function sanitize_post_types( $value ): array {
        if ( ! is_array( $value ) ) {
            return [ 'post' ];
        }
        return array_values( array_filter( array_map( 'sanitize_key', $value ), 'post_type_exists' ) );
    }
}
