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
                $this->settings_repository = \NuclearEngagement\Container::getInstance()->get('settings');
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
