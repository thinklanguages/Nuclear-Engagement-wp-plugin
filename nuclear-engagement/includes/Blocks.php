<?php
declare(strict_types=1);
namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Blocks {
    public static function register(): void {
        if ( ! wp_script_is( 'nuclen-admin', 'registered' ) ) {
            return;
        }

        register_block_type(
            'nuclear-engagement/quiz',
            [
                'render_callback' => static function (): string {
                    return do_shortcode('[nuclear_engagement_quiz]');
                },
                'editor_script'   => 'nuclen-admin',
                'title'           => __( 'Nuclear Engagement Quiz', 'nuclear-engagement' ),
                'category'        => 'widgets',
                'icon'            => 'clipboard',
                'description'     => __( 'Interactive quiz generated for this post.', 'nuclear-engagement' ),
            ]
        );

        register_block_type(
            'nuclear-engagement/summary',
            [
                'render_callback' => static function (): string {
                    return do_shortcode('[nuclear_engagement_summary]');
                },
                'editor_script'   => 'nuclen-admin',
                'title'           => __( 'Nuclear Engagement Summary', 'nuclear-engagement' ),
                'category'        => 'widgets',
                'icon'            => 'list-view',
                'description'     => __( 'Key facts summary for this post.', 'nuclear-engagement' ),
            ]
        );
    }
}

