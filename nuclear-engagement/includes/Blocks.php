<?php
declare(strict_types=1);
namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Blocks {
    public static function register(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            error_log( 'Nuclear Engagement: block registration unavailable.' );
            return;
        }

        register_block_type(
            'nuclear-engagement/quiz',
            [
                'render_callback' => static function (): string {
                    $out = do_shortcode('[nuclear_engagement_quiz]');
                    if ( ! is_string( $out ) || trim( $out ) === '' ) {
                        return '<p>' . esc_html__( 'Quiz unavailable.', 'nuclear-engagement' ) . '</p>';
                    }
                    return $out;
                },
                'editor_script'   => 'nuclen-admin',
            ]
        );

        register_block_type(
            'nuclear-engagement/summary',
            [
                'render_callback' => static function (): string {
                    $out = do_shortcode('[nuclear_engagement_summary]');
                    if ( ! is_string( $out ) || trim( $out ) === '' ) {
                        return '<p>' . esc_html__( 'Summary unavailable.', 'nuclear-engagement' ) . '</p>';
                    }
                    return $out;
                },
                'editor_script'   => 'nuclen-admin',
            ]
        );
    }
}

