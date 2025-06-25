<?php
/**
 * File: modules/toc/loader.php
 *
 * Loads the Nuclen TOC sub-module.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use NuclearEngagement\Modules\TOC\Nuclen_TOC_Utils;
use NuclearEngagement\Modules\TOC\Nuclen_TOC_Headings;
use NuclearEngagement\Modules\TOC\Nuclen_TOC_Render;
use NuclearEngagement\Modules\TOC\Nuclen_TOC_Admin;

/*
 * ------------------------------------------------------------------
 * Local constants (prefixed, module-scoped)
 * ------------------------------------------------------------------
 */
define( 'NUCLEN_TOC_DIR', __DIR__ . '/' );
define( 'NUCLEN_TOC_URL', plugin_dir_url( __FILE__ ) );

/*
 * ------------------------------------------------------------------
 * Includes
 * ------------------------------------------------------------------
 */
require_once NUCLEN_TOC_DIR . 'includes/polyfills.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-utils.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-assets.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-view.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-headings.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-render.php';
require_once NUCLEN_TOC_DIR . 'includes/class-nuclen-toc-admin.php';

// Clear caches when posts are saved or deleted.
add_action( 'save_post', array( 'Nuclen_TOC_Utils', 'clear_cache_for_post' ) );
add_action( 'delete_post', array( 'Nuclen_TOC_Utils', 'clear_cache_for_post' ) );

/*
 * ------------------------------------------------------------------
 * Spin-up
 * ------------------------------------------------------------------
 */
add_action(
    'plugins_loaded',
    static function () {
        new Nuclen_TOC_Headings();  // filter for heading IDs.
        new Nuclen_TOC_Render();    // shortcode handler.
        if ( is_admin() ) {
            new Nuclen_TOC_Admin();    // settings page.
        }
    }
);
