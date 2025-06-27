<?php
/**
 * File: modules/summary/loader.php
 *
 * Loads the Nuclen Summary sub-module.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\Summary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Modules\Summary\Nuclen_Summary_Shortcode;
use NuclearEngagement\Modules\Summary\Nuclen_Summary_Metabox;

/*
------------------------------------------------------------------
 * Local constants (prefixed, module-scoped)
 * ------------------------------------------------------------------ */
define( 'NUCLEN_SUMMARY_DIR', __DIR__ . '/' );
define( 'NUCLEN_SUMMARY_URL', plugin_dir_url( __FILE__ ) );

/*
------------------------------------------------------------------
 * Includes
 * ------------------------------------------------------------------ */
require_once NUCLEN_SUMMARY_DIR . 'Nuclen_Summary_View.php';
require_once NUCLEN_SUMMARY_DIR . 'Nuclen_Summary_Shortcode.php';
require_once NUCLEN_SUMMARY_DIR . 'Nuclen_Summary_Metabox.php';

/*
------------------------------------------------------------------
 * Spin-up
 * ------------------------------------------------------------------ */
add_action(
	'plugins_loaded',
	static function () {
		new Nuclen_Summary_Shortcode( \NuclearEngagement\Core\SettingsRepository::get_instance(), new \NuclearEngagement\Front\FrontClass() );
		if ( is_admin() ) {
			new Nuclen_Summary_Metabox( \NuclearEngagement\Core\SettingsRepository::get_instance() );
		}
	}
);
