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
	 * @var SettingsRepository
	 */
	private $settings_repository;

	/**
	 * Constructor – hooks assets only; the heavy lifting lives in the traits.
	 */
	public function __construct() {
		$this->settings_repository = \NuclearEngagement\SettingsRepository::get_instance();
		add_action( 'admin_enqueue_scripts', array( $this, 'nuclen_enqueue_color_picker' ) );
	}

	/**
	 * Get the settings repository instance.
	 *
	 * @return SettingsRepository
	 */
	public function nuclen_get_settings_repository() {
		return $this->settings_repository;
	}
}
