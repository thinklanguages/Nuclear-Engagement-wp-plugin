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
$settings = \NuclearEngagement\Core\SettingsRepository::get_instance();
$front    = new \NuclearEngagement\Front\FrontClass(
		'nuclear-engagement',
		defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0',
		$settings,
		new \NuclearEngagement\Core\Container()
);
new Nuclen_Summary_Shortcode( $settings, $front );
if ( is_admin() ) {
		new Nuclen_Summary_Metabox( $settings );
}
