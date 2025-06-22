<?php
declare(strict_types=1);
namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers Gutenberg blocks that proxy existing shortcodes.
 * Linking the blocks to shortcodes keeps legacy output intact while
 * enabling block editor workflows.
 */
final class Blocks {
    public static function register(): void {
        register_block_type(
            'nuclear-engagement/quiz',
            [
                'render_callback' => static function (): string {
                    return do_shortcode('[nuclear_engagement_quiz]');
                },
                'editor_script'   => 'nuclen-admin',
            ]
        );

        register_block_type(
            'nuclear-engagement/summary',
            [
                'render_callback' => static function (): string {
                    return do_shortcode('[nuclear_engagement_summary]');
                },
                'editor_script'   => 'nuclen-admin',
            ]
        );
    }
}

