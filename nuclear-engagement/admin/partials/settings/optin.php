<?php
// File: admin/partials/settings/optin.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Opt-In tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- OPT-IN TAB -->
<div id="optin" class="nuclen-tab-content nuclen-section" style="display:none;">
	<h2 class="nuclen-subheading"><?php esc_html_e( 'Email Opt-In Form', 'nuclear-engagement' ); ?></h2>

	<!-- Enable toggle -->
	<div class="nuclen-form-group nuclen-row">
		<label class="nuclen-label-col" for="enable_optin"><?php esc_html_e( 'Enable Opt-In', 'nuclear-engagement' ); ?></label>
		<div class="nuclen-input-col">
			<input type="checkbox" name="enable_optin" id="enable_optin" value="1" <?php checked( $settings['enable_optin'], true ); ?> />
		</div>
	</div>

	<!-- Position -->
	<div class="nuclen-form-group nuclen-row">
		<label class="nuclen-label-col"><?php esc_html_e( 'Display Position', 'nuclear-engagement' ); ?></label>
		<div class="nuclen-input-col">
			<label><input type="radio" name="nuclen_optin_position" value="with_results"  <?php checked( $settings['optin_position'], 'with_results' ); ?> /> <?php esc_html_e( 'With Results', 'nuclear-engagement' ); ?></label><br/>
			<label><input type="radio" name="nuclen_optin_position" value="before_results" <?php checked( $settings['optin_position'], 'before_results' ); ?> /> <?php esc_html_e( 'Before Results (after last question)', 'nuclear-engagement' ); ?></label>
		</div>
	</div>

	<!-- Mandatory -->
	<div class="nuclen-form-group nuclen-row">
		<label class="nuclen-label-col" for="optin_mandatory"><?php esc_html_e( 'Make Opt-In Mandatory', 'nuclear-engagement' ); ?></label>
		<div class="nuclen-input-col">
			<input type="checkbox" name="optin_mandatory" id="optin_mandatory" value="1" <?php checked( $settings['optin_mandatory'], true ); ?> />
			<p class="description"><?php esc_html_e( 'Applies to opt-in displayed before results. If checked, users must submit the form to see their quiz score.', 'nuclear-engagement' ); ?></p>
		</div>
	</div>

	<!-- Webhook URL -->
	<div class="nuclen-form-group nuclen-row">
		<label class="nuclen-label-col" for="optin_webhook"><?php esc_html_e( 'Webhook URL', 'nuclear-engagement' ); ?></label>
		<div class="nuclen-input-col">
			<input type="url" name="optin_webhook" id="optin_webhook" class="nuclen-input" value="<?php echo esc_attr( $settings['optin_webhook'] ); ?>" />
		</div>
	</div>

	<!-- Success message -->
	<div class="nuclen-form-group nuclen-row">
		<label class="nuclen-label-col" for="optin_success_message"><?php esc_html_e( 'Success Message', 'nuclear-engagement' ); ?></label>
		<div class="nuclen-input-col">
			<input type="text" name="optin_success_message" id="optin_success_message" class="nuclen-input" value="<?php echo esc_attr( $settings['optin_success_message'] ); ?>" />
		</div>
	</div>
</div><!-- /#optin -->
