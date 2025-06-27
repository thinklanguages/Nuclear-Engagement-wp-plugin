<?php
declare(strict_types=1);
/**
 * File: admin/Traits/SettingsPersistTrait.php
 *
 * Persists sanitized settings and outputs admin notices.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait SettingsPersistTrait {
         * Sanitize input and merge with defaults.
         */
        private function nuclen_sanitize_and_defaults( array $raw, array $defaults ): array {
                $new_settings = $this->nuclen_sanitize_settings( $raw );

                $toc_keys = array(
                        'toc_font_color',
                        'toc_bg_color',
                        'toc_border_color',
                        'toc_border_style',
                        'toc_border_width',
                        'toc_border_radius',
                        'toc_shadow_color',
                        'toc_shadow_blur',
                        'toc_link_color',
                        'toc_heading_levels',
                        'toc_z_index',
                        'toc_sticky_offset_x',
                        'toc_sticky_offset_y',
                        'toc_sticky_max_width',
                );

                foreach ( $toc_keys as $k ) {
                        if ( isset( $raw[ $k ] ) && $raw[ $k ] !== '' ) {
                                $new_settings[ $k ] = $raw[ $k ];
                        }
                }

                return wp_parse_args( $new_settings, $defaults );
        }

        /**
         * Persist the sanitized settings and return the saved array.
         */
        private function nuclen_persist_settings( array $new_settings ): array {
                $settings_repo = $this->nuclen_get_settings_repository();

                foreach ( $new_settings as $key => $value ) {
                        $settings_repo->set( $key, $value );
                }

                $settings_repo->save();

                return $settings_repo->get_all();
        }

        /**
         * Output the success admin notice after saving settings.
         */
        private function nuclen_output_save_notice(): void {
                echo '<div class="notice notice-success"><p>' .
                        esc_html__( 'Settings saved.', 'nuclear-engagement' ) .
                        '</p></div>';
        }
}
