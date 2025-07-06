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
use NuclearEngagement\Core\ServiceContainer;

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
$settings = SettingsRepository::get_instance();
$service  = new Quiz_Service();

// IMPORTANT: Quiz shortcode registration happens in two places:
// 1. Here in the module loader (for module-based approach)
// 2. In Plugin.php via FrontClass->nuclen_register_quiz_shortcode()
// Currently using the Plugin.php approach, so this is admin-only
if ( is_admin() ) {
				( new Quiz_Admin( $settings, $service ) )->register_hooks();
} else {
				// NOTE: Shortcode registration is handled by Plugin class
				// via FrontClass->nuclen_register_quiz_shortcode()
				// If you enable this, disable the one in Plugin.php to avoid conflicts
				$front = new FrontClass(
					'nuclear-engagement',
					defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0',
					$settings,
					ServiceContainer::getInstance()
				);
				( new Quiz_Shortcode( $settings, $front, $service ) )->register();
}
