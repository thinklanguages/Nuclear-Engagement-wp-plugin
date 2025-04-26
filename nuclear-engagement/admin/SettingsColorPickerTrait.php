<?php
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
	 * Enqueue WP colour picker on admin pages.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function nuclen_enqueue_color_picker( $hook_suffix ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$inline_js = 'jQuery(document).ready(function($){ $(".wp-color-picker-field").wpColorPicker(); });';
		wp_add_inline_script( 'wp-color-picker', $inline_js );
	}
}
