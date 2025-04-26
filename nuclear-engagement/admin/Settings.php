<?php
/**
 * File: admin/Settings.php
 *
 * Coordinates all Settings logic through lightweight traits.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load trait files – they live in the same folder for simple pathing.
require_once plugin_dir_path( __FILE__ ) . 'SettingsColorPickerTrait.php';
require_once plugin_dir_path( __FILE__ ) . 'SettingsSanitizeTrait.php';
require_once plugin_dir_path( __FILE__ ) . 'SettingsPageTrait.php';

class Settings {
	use SettingsColorPickerTrait;
	use SettingsSanitizeTrait;
	use SettingsPageTrait;

	/**
	 * Constructor – hooks assets only; the heavy lifting lives in the traits.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'nuclen_enqueue_color_picker' ) );
	}
}
