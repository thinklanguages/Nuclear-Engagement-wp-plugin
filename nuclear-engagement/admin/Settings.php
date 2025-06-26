<?php
declare(strict_types=1);
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
	public function __construct( SettingsRepository $settings_repository ) {
			$this->settings_repository = $settings_repository;
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
