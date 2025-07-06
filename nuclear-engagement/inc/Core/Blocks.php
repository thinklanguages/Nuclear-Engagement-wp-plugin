<?php
/**
 * Blocks.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);
namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Gutenberg blocks that proxy existing shortcodes.
 * Linking the blocks to shortcodes keeps legacy output intact while
 * enabling block editor workflows.
 */
final class Blocks {
	/**
	 * Track if blocks have been registered to prevent duplicates.
	 *
	 * @var bool
	 */
	private static $registered = false;

	public static function register(): void {
		// Prevent duplicate registration
		if ( self::$registered ) {
			return;
		}

		if ( ! function_exists( 'register_block_type' ) ) {
				\NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: block registration unavailable.' );
				return;
		}

		// Build block configuration
		$block_args = array(
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
		);

		// Only add editor script if it's available
		if ( wp_script_is( 'nuclen-admin', 'registered' ) ) {
			$block_args['editor_script'] = 'nuclen-admin';
		}

		register_block_type( 'nuclear-engagement/quiz', $block_args );

		// Summary block
		$summary_args = array(
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
		);
		if ( wp_script_is( 'nuclen-admin', 'registered' ) ) {
			$summary_args['editor_script'] = 'nuclen-admin';
		}
		register_block_type( 'nuclear-engagement/summary', $summary_args );

		// TOC block
		$toc_args = array(
			'api_version'     => 2,
			'title'           => __( 'TOC', 'nuclear-engagement' ),
			'category'        => 'widgets',
			'icon'            => 'list-view',
			'render_callback' => static function (): string {
				$out = do_shortcode( '[nuclear_engagement_toc]' );
				if ( ! is_string( $out ) || trim( $out ) === '' ) {
					return '';
				}
				return $out;
			},
		);
		if ( wp_script_is( 'nuclen-admin', 'registered' ) ) {
			$toc_args['editor_script'] = 'nuclen-admin';
		}
		register_block_type( 'nuclear-engagement/toc', $toc_args );

		// Mark as registered to prevent duplicates
		self::$registered = true;
	}
}
