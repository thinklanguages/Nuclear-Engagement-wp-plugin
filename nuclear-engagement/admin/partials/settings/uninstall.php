<?php
declare(strict_types=1);
// File: admin/partials/settings/uninstall.php
if ( ! defined( 'ABSPATH' ) ) {
		exit;
}
/**
 * Uninstall tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- UNINSTALL TAB -->
<div id="uninstall" class="nuclen-tab-content nuclen-section" style="display:none;">
		<h2 class="nuclen-subheading"><?php esc_html_e( 'Uninstall Options', 'nuclear-engagement' ); ?></h2>
		<p><?php esc_html_e( 'Choose what to remove when the plugin is deleted.', 'nuclear-engagement' ); ?></p>

		<div class="nuclen-form-group nuclen-row">
				<label class="nuclen-label-col" for="delete_settings_on_uninstall">
						<?php esc_html_e( 'Delete plugin settings', 'nuclear-engagement' ); ?>
				</label>
				<div class="nuclen-input-col">
						<input type="checkbox" name="delete_settings_on_uninstall" id="delete_settings_on_uninstall" value="1" <?php checked( $settings['delete_settings_on_uninstall'], true ); ?> />
				</div>
		</div>

		<div class="nuclen-form-group nuclen-row">
				<label class="nuclen-label-col" for="delete_generated_content_on_uninstall">
						<?php esc_html_e( 'Delete generated post data', 'nuclear-engagement' ); ?>
				</label>
				<div class="nuclen-input-col">
						<input type="checkbox" name="delete_generated_content_on_uninstall" id="delete_generated_content_on_uninstall" value="1" <?php checked( $settings['delete_generated_content_on_uninstall'], true ); ?> />
				</div>
		</div>

		<div class="nuclen-form-group nuclen-row">
				<label class="nuclen-label-col" for="delete_optin_data_on_uninstall">
						<?php esc_html_e( 'Delete email opt-in data', 'nuclear-engagement' ); ?>
				</label>
				<div class="nuclen-input-col">
						<input type="checkbox" name="delete_optin_data_on_uninstall" id="delete_optin_data_on_uninstall" value="1" <?php checked( $settings['delete_optin_data_on_uninstall'], true ); ?> />
				</div>
		</div>

		<div class="nuclen-form-group nuclen-row">
				<label class="nuclen-label-col" for="delete_log_file_on_uninstall">
						<?php esc_html_e( 'Delete log file', 'nuclear-engagement' ); ?>
				</label>
				<div class="nuclen-input-col">
						<input type="checkbox" name="delete_log_file_on_uninstall" id="delete_log_file_on_uninstall" value="1" <?php checked( $settings['delete_log_file_on_uninstall'], true ); ?> />
				</div>
		</div>

		<div class="nuclen-form-group nuclen-row">
				<label class="nuclen-label-col" for="delete_custom_css_on_uninstall">
						<?php esc_html_e( 'Delete custom theme file', 'nuclear-engagement' ); ?>
				</label>
				<div class="nuclen-input-col">
						<input type="checkbox" name="delete_custom_css_on_uninstall" id="delete_custom_css_on_uninstall" value="1" <?php checked( $settings['delete_custom_css_on_uninstall'], true ); ?> />
				</div>
		</div>
</div><!-- /#uninstall -->
