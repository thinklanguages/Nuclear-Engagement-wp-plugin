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

use NuclearEngagement\Modules\TOC\Nuclen_TOC_Headings;
use NuclearEngagement\Modules\TOC\Nuclen_TOC_Render;
use NuclearEngagement\Modules\TOC\Nuclen_TOC_Admin;
use NuclearEngagement\Modules\TOC\TocCache;
use NuclearEngagement\Core\SettingsRepository;

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
require_once NUCLEN_TOC_DIR . 'SlugGenerator.php';
require_once NUCLEN_TOC_DIR . 'TocCache.php';
require_once NUCLEN_TOC_DIR . 'HeadingExtractor.php';
require_once NUCLEN_TOC_DIR . 'Nuclen_TOC_Assets.php';
require_once NUCLEN_TOC_DIR . 'Nuclen_TOC_View.php';
require_once NUCLEN_TOC_DIR . 'Nuclen_TOC_Headings.php';
require_once NUCLEN_TOC_DIR . 'Nuclen_TOC_Render.php';
require_once NUCLEN_TOC_DIR . 'Nuclen_TOC_Admin.php';

// Clear caches when posts are saved or deleted.
add_action( 'save_post', array( 'NuclearEngagement\\Modules\\TOC\\TocCache', 'clear_cache_for_post' ) );
add_action( 'delete_post', array( 'NuclearEngagement\\Modules\\TOC\\TocCache', 'clear_cache_for_post' ) );

/*
 * ------------------------------------------------------------------
 * Spin-up
 * ------------------------------------------------------------------
 */
// CRITICAL: TOC must be initialized immediately, not in plugins_loaded hook!
// Nuclen_TOC_Render constructor registers the 'nuclear_engagement_toc' shortcode
// If this is wrapped in plugins_loaded, the shortcode won't be available early enough
new Nuclen_TOC_Headings();  // filter for heading IDs.
new Nuclen_TOC_Render( \NuclearEngagement\Core\SettingsRepository::get_instance() ); // shortcode handler.
if ( is_admin() ) {
	new Nuclen_TOC_Admin();    // settings page.
}
