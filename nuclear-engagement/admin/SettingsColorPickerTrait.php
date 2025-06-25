<?php
declare(strict_types=1);
/**
 * File: admin/SettingsColorPickerTrait.php
 *
 * Handles colour-picker assets.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

trait SettingsColorPickerTrait {

        /**
         * Previously enqueued the jQuery-based WP color picker.
         *
         * The settings page now uses native <input type="color"> elements,
         * so no additional scripts are required. This method remains to
         * preserve the public API but performs no actions.
         *
         * @param string $hook_suffix Current admin screen.
         */
    public function nuclen_enqueue_color_picker( $hook_suffix ) {
            // No-op: color inputs rely on native browser widgets.
    }
}
