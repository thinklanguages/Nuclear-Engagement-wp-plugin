<?php
/**
 * File: modules/toc/loader.php
 *
 * Loads the Nuclen TOC sub-module.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
