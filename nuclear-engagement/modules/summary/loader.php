<?php
/**
 * File: modules/summary/loader.php
 *
 * Loads the Nuclen Summary sub-module.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------------------------------------------
 * Local constants (prefixed, module-scoped)
 * ------------------------------------------------------------------ */
define( 'NUCLEN_SUMMARY_DIR', __DIR__ . '/' );
define( 'NUCLEN_SUMMARY_URL', plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------
 * Includes
 * ------------------------------------------------------------------ */
require_once NUCLEN_SUMMARY_DIR . 'includes/class-nuclen-summary-view.php';
require_once NUCLEN_SUMMARY_DIR . 'includes/class-nuclen-summary-shortcode.php';
require_once NUCLEN_SUMMARY_DIR . 'includes/class-nuclen-summary-metabox.php';

/* ------------------------------------------------------------------
 * Spin-up
 * ------------------------------------------------------------------ */
add_action(
    'plugins_loaded',
    static function () {
        new Nuclen_Summary_Shortcode( \NuclearEngagement\SettingsRepository::get_instance(), new \NuclearEngagement\Front\FrontClass() );
        if ( is_admin() ) {
            new Nuclen_Summary_Metabox( \NuclearEngagement\SettingsRepository::get_instance() );
        }
    }
);
