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
		if ( ! function_exists( 'register_block_type' ) ) {
			error_log( 'Nuclear Engagement: block registration unavailable.' );
			return;
		}

		if ( ! wp_script_is( 'nuclen-admin', 'registered' ) ) {
			error_log( 'Nuclear Engagement: nuclen-admin script missing.' );
			return;
		}

		register_block_type(
			'nuclear-engagement/quiz',
			array(
				'api_version'     => 2,
				'title'           => __( 'Quiz', 'nuclear-engagement' ),
				'category'        => 'widgets',
				'icon'            => 'editor-help',
				'render_callback' => static function (): string {
					$out = do_shortcode( '[nuclear_engagement_quiz]' );
					if ( ! is_string( $out ) || trim( $out ) === '' ) {
						return '<p>' . esc_html__( 'Quiz unavailable.', 'nuclear-engagement' ) . '</p>';
					}
					return $out;
				},
				'editor_script'   => 'nuclen-admin',
			)
		);

		register_block_type(
			'nuclear-engagement/summary',
			array(
				'api_version'     => 2,
				'title'           => __( 'Summary', 'nuclear-engagement' ),
				'category'        => 'widgets',
				'icon'            => 'excerpt-view',
				'render_callback' => static function (): string {
					$out = do_shortcode( '[nuclear_engagement_summary]' );
					if ( ! is_string( $out ) || trim( $out ) === '' ) {
						return '<p>' . esc_html__( 'Summary unavailable.', 'nuclear-engagement' ) . '</p>';
					}
					return $out;
				},
				'editor_script'   => 'nuclen-admin',
			)
		);
	}
}
