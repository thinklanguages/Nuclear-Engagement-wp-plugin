<?php
/**
 * File: modules/quiz/loader.php
 *
 * Loads the Nuclen Quiz module.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\Quiz;

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Core\Container;

/*
------------------------------------------------------------------
 * Local constants (prefixed, module-scoped)
 * ------------------------------------------------------------------
*/
if ( ! defined( 'NUCLEN_QUIZ_DIR' ) ) {
		define( 'NUCLEN_QUIZ_DIR', __DIR__ . '/' );
}

/*
------------------------------------------------------------------
 * Includes
 * ------------------------------------------------------------------
*/
require_once NUCLEN_QUIZ_DIR . 'Quiz_Service.php';
require_once NUCLEN_QUIZ_DIR . 'Quiz_Admin.php';
require_once NUCLEN_QUIZ_DIR . 'Quiz_Shortcode.php';

/*
------------------------------------------------------------------
 * Spin-up
 * ------------------------------------------------------------------
*/
add_action(
		'plugins_loaded',
		static function () {
				$settings = SettingsRepository::get_instance();
				$service  = new Quiz_Service();

				if ( is_admin() ) {
						( new Quiz_Admin( $settings, $service ) )->register_hooks();
				} else {
						$front = new FrontClass(
								'nuclear-engagement',
								defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0',
								$settings,
								new Container()
						);
						( new Quiz_Shortcode( $settings, $front, $service ) )->register();
				}
		}
);
