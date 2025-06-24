<?php
declare(strict_types=1);
/**
 * File: modules/toc/includes/class-nuclen-toc-assets.php
 *
 * Handles front-end asset registration and enqueueing for the TOC module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\AssetVersions;

final class Nuclen_TOC_Assets {
		/** Default vertical offset for scroll-to behaviour. */
	public const DEFAULT_SCROLL_OFFSET = NUCLEN_TOC_SCROLL_OFFSET_DEFAULT;

	private bool $registered   = false;
	private int $scroll_offset = self::DEFAULT_SCROLL_OFFSET;

		/**
		 * Enqueue assets and apply runtime tweaks.
		 */
	public function enqueue( array $a ): void {
		if ( ! is_singular() ) {
				return;
		}
		if ( ! $this->registered ) {
				$this->register();
		}
			wp_enqueue_style( 'nuclen-toc-front' );
		if ( $a['toggle'] === 'true' || $a['highlight'] === 'true' ) {
				wp_enqueue_script( 'nuclen-toc-front' );
		}
			$off = max( 0, min( 500, (int) $a['offset'] ) );
		if ( $off !== $this->scroll_offset ) {
				wp_add_inline_style( 'nuclen-toc-front', ':root{--nuclen-toc-offset:' . $off . 'px}' );
				$this->scroll_offset = $off;
		}
		if ( $a['smooth'] === 'true' ) {
				wp_add_inline_style( 'nuclen-toc-front', 'html{scroll-behavior:smooth}' );
		}
	}

		/** Register scripts and styles. */
	private function register(): void {
		if ( $this->registered ) {
				return;
		}
			$this->registered = true;

			$css_p = NUCLEN_TOC_DIR . 'assets/css/nuclen-toc-front.css';
			$js_p  = NUCLEN_TOC_DIR . 'assets/js/nuclen-toc-front.js';

			$css_v = AssetVersions::get( 'toc_front_css' );
			$js_v  = AssetVersions::get( 'toc_front_js' );

			wp_register_style(
				'nuclen-toc-front',
				NUCLEN_TOC_URL . 'assets/css/nuclen-toc-front.css',
				array(),
				$css_v
			);

			wp_register_script(
				'nuclen-toc-front',
				NUCLEN_TOC_URL . 'assets/js/nuclen-toc-front.js',
				array(),
				$js_v,
				true
			);

			wp_localize_script(
				'nuclen-toc-front',
				'nuclenTocL10n',
				array(
					'hide' => __( 'Hide', 'nuclen-toc-shortcode' ),
					'show' => __( 'Show', 'nuclen-toc-shortcode' ),
				)
			);
	}
}
