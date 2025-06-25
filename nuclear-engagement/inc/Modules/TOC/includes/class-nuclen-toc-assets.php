<?php
/**
 * Front-end asset registration and enqueueing for the TOC module.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

use NuclearEngagement\AssetVersions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Front-end assets helper for the TOC module.
 */
final class Nuclen_TOC_Assets {
    /** Default vertical offset for scroll-to behaviour. */
    public const DEFAULT_SCROLL_OFFSET = NUCLEN_TOC_SCROLL_OFFSET_DEFAULT;

    /**
     * Whether assets have been registered.
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Current scroll offset in pixels.
     *
     * @var int
     */
    private int $scroll_offset = self::DEFAULT_SCROLL_OFFSET;

    /**
     * Enqueue assets and apply runtime tweaks.
     *
     * @param array $a Shortcode attributes.
     */
    public function enqueue( array $a ): void {
        if ( ! is_singular() ) {
            return;
        }
        if ( ! $this->registered ) {
            $this->register();
        }
        wp_enqueue_style( 'nuclen-toc-front' );
        if ( 'true' === $a['toggle'] || 'true' === $a['highlight'] ) {
            wp_enqueue_script( 'nuclen-toc-front' );
        }
        $off = max( 0, min( 500, (int) $a['offset'] ) );
        if ( $this->scroll_offset !== $off ) {
            wp_add_inline_style( 'nuclen-toc-front', ':root{--nuclen-toc-offset:' . $off . 'px}' );
            $this->scroll_offset = $off;
        }
        if ( 'true' === $a['smooth'] ) {
            wp_add_inline_style( 'nuclen-toc-front', 'html{scroll-behavior:smooth}' );
        }
    }

    /**
     * Register scripts and styles.
     */
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
