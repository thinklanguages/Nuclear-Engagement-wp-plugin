<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * File: admin/partials/nuclen-admin-generate.php
 * Implementation of changes required by WordPress.org guidelines.
 * 
 * - Now includes a <p id="nuclen-credits-info"> in Step 2 to show “This will consume X credits. 
 *   You have Y left.” 
 * - All other code remains the same, except for that inserted line.
 *
 * @package NuclearEngagement\Admin
 */

// Retrieve the plugin settings
$settings = get_option( 'nuclear_engagement_settings', array() );

// The user-selected post types
$allowed_post_types = $settings['generation_post_types'] ?? array( 'post' );

$statuses   = get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' );
$categories = get_categories( array( 'hide_empty' => false ) );
$authors    = get_users( array( 'who' => 'authors' ) );

// We'll still fetch all public post types, but only show the allowed ones
$post_types = get_post_types( array( 'public' => true ), 'objects' );

$utils = new \NuclearEngagement\Utils();
$utils->display_nuclen_page_header();
?>

<div class="wrap nuclen-container">

	<div id="nuclen-progress-bar" class="nuclen-step-bar">
		<div id="nuclen-step-bar-1" class="nuclen-step-bar-step nuclen-step-todo"><?php esc_html_e( '1. Select', 'nuclear-engagement' ); ?></div>
		<div id="nuclen-step-bar-2" class="nuclen-step-bar-step nuclen-step-todo"><?php esc_html_e( '2. Confirm', 'nuclear-engagement' ); ?></div>
		<div id="nuclen-step-bar-3" class="nuclen-step-bar-step nuclen-step-todo"><?php esc_html_e( '3. Generate', 'nuclear-engagement' ); ?></div>
		<div id="nuclen-step-bar-4" class="nuclen-step-bar-step nuclen-step-todo"><?php esc_html_e( '4. Save', 'nuclear-engagement' ); ?></div>
	</div>

	<h1 class="nuclen-heading"><?php esc_html_e( 'Generate Content', 'nuclear-engagement' ); ?></h1>

	<!-- Step 1 Section -->
	<div id="nuclen-step-1" class="nuclen-section">
		<h2 class="nuclen-subheading"><?php esc_html_e( '1. Select filters for posts', 'nuclear-engagement' ); ?></h2>
		<form id="nuclen-filters-form" class="nuclen-section" method="post">
			<table class="form-table">
				<tr class="nuclen-form-group">
					<th><label for="nuclen_post_status" class="nuclen-label"><?php esc_html_e( 'Post Status', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_post_status" id="nuclen_post_status" class="nuclen-input">
							<option value="any"><?php esc_html_e( 'All', 'nuclear-engagement' ); ?></option>
							<?php
							foreach ( $statuses as $status_key => $st_obj ) :
								if ( ! in_array( $status_key, array( 'trash', 'private', 'inherit' ) ) ) :
									?>
									<option value="<?php echo esc_attr( $status_key ); ?>">
										<?php echo esc_html( $st_obj->label ); ?>
									</option>
									<?php
								endif;
							endforeach;
							?>
						</select>
					</td>
				</tr>

				<tr class="nuclen-form-group">
					<th><label for="nuclen_category" class="nuclen-label"><?php esc_html_e( 'Category', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_category" id="nuclen_category" class="nuclen-input">
							<option value=""><?php esc_html_e( 'All', 'nuclear-engagement' ); ?></option>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>">
									<?php echo esc_html( $cat->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr class="nuclen-form-group">
					<th><label for="nuclen_author" class="nuclen-label"><?php esc_html_e( 'Author', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_author" id="nuclen_author" class="nuclen-input">
							<option value=""><?php esc_html_e( 'All', 'nuclear-engagement' ); ?></option>
							<?php foreach ( $authors as $author ) : ?>
								<option value="<?php echo esc_attr( $author->ID ); ?>">
									<?php echo esc_html( $author->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<!-- Post Type row -->
				<tr class="nuclen-form-group">
					<th><label for="nuclen_post_type" class="nuclen-label"><?php esc_html_e( 'Post Type', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_post_type" id="nuclen_post_type" class="nuclen-input">
							<?php
							foreach ( $post_types as $pt_key => $pt_obj ) {
								if ( in_array( $pt_key, $allowed_post_types ) ) {
									echo '<option value="' . esc_attr( $pt_key ) . '">' . esc_html( $pt_obj->labels->name ) . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
				<!-- End Post Type row -->

				<tr class="nuclen-form-group">
					<th><label for="nuclen_generate_workflow" class="nuclen-label"><?php esc_html_e( 'Generate Type', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_generate_workflow" id="nuclen_generate_workflow" class="nuclen-input">
							<option value="quiz" selected><?php esc_html_e( 'Quiz', 'nuclear-engagement' ); ?></option>
							<option value="summary"><?php esc_html_e( 'Summary', 'nuclear-engagement' ); ?></option>
						</select>
					</td>
				</tr>

				<tr id="nuclen-summary-settings" class="nuclen-form-group nuclen-hidden">
					<th><label for="nuclen_summary_format" class="nuclen-label"><?php esc_html_e( 'Summary Format', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_summary_format" id="nuclen_summary_format" class="nuclen-input">
							<option value="paragraph"><?php esc_html_e( 'Paragraph', 'nuclear-engagement' ); ?></option>
							<option value="bullet_list"><?php esc_html_e( 'Bullet List', 'nuclear-engagement' ); ?></option>
						</select>
					</td>
				</tr>

				<tr id="nuclen-summary-paragraph-options" class="nuclen-form-group nuclen-hidden">
					<th><label for="nuclen_summary_length" class="nuclen-label"><?php esc_html_e( 'Paragraph length (words)', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_summary_length" id="nuclen_summary_length" class="nuclen-input">
							<option value="20">20 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
							<option value="30">30 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
							<option value="40">40 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
							<option value="50">50 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
						</select>
					</td>
				</tr>

				<tr id="nuclen-summary-bullet-options" class="nuclen-form-group nuclen-hidden">
					<th><label for="nuclen_summary_number_of_items" class="nuclen-label"><?php esc_html_e( 'Number of bullet items', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_summary_number_of_items" id="nuclen_summary_number_of_items" class="nuclen-input">
							<option value="3">3 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
							<option value="4">4 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
							<option value="5">5 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
							<option value="6">6 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
							<option value="7">7 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
						</select>
					</td>
				</tr>

			</table>

			<div class="nuclen-form-row">
				<div class="nuclen-form-field">
					<input
					type="checkbox"
					class="nuclen-checkbox"
					name="nuclen_allow_regenerate_data"
					id="nuclen_allow_regenerate_data"
					value="1"
					/>
					<label class="nuclen-checkbox-label" for="nuclen_allow_regenerate_data">
					<?php esc_html_e( 'Allow bulk regeneration of existing data', 'nuclear-engagement' ); ?>
					</label>
					<span nuclen-tooltip="<?php esc_attr_e( 'By default, the plugin only generates content for posts lacking it. If checked, existing data is overwritten.', 'nuclear-engagement' ); ?>">🛈</span>
				</div>
			</div>

			<div class="nuclen-form-row">
				<div class="nuclen-form-field">
					<input
					type="checkbox"
					class="nuclen-checkbox"
					name="nuclen_regenerate_protected_data"
					id="nuclen_regenerate_protected_data"
					value="1"
					/>
					<label class="nuclen-checkbox-label" for="nuclen_regenerate_protected_data">
					<?php esc_html_e( 'Allow bulk regeneration of protected data', 'nuclear-engagement' ); ?>
					</label>
					<span nuclen-tooltip="<?php esc_attr_e( 'By default, content marked protected is skipped. If checked, protected data can be overwritten too.', 'nuclear-engagement' ); ?>">🛈</span>
				</div>
			</div>

			<button type="button" id="nuclen-get-posts-btn" class="button button-primary nuclen-button-primary">
				<?php esc_html_e( 'Get Posts to Process', 'nuclear-engagement' ); ?>
			</button>
		</form>
	</div>

	<!-- Step 2 Section (hidden by default) -->
	<div id="nuclen-step-2" class="nuclen-section nuclen-hidden">
		<h2 class="nuclen-subheading"><?php esc_html_e( '2. Confirm and Generate', 'nuclear-engagement' ); ?></h2>

		<!-- New line to display credit info -->
		<p id="nuclen-credits-info" style="font-weight: bold; margin-bottom: 1em;"></p>

		<p id="nuclen-posts-count"></p>
		<form id="nuclen-generate-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php
			wp_nonce_field( 'nuclen_admin_ajax_nonce', 'security' );
			?>
			<input type="hidden" name="action" value="nuclen_trigger_generation" />
			<!-- Hidden fields to preserve user selections -->
			<input type="hidden" name="nuclen_selected_post_ids" id="nuclen_selected_post_ids" />
			<input type="hidden" name="nuclen_selected_generate_workflow" id="nuclen_selected_generate_workflow" />
			<input type="hidden" name="nuclen_selected_summary_format" id="nuclen_selected_summary_format" />
			<input type="hidden" name="nuclen_selected_summary_length" id="nuclen_selected_summary_length" />
			<input type="hidden" name="nuclen_selected_summary_number_of_items" id="nuclen_selected_summary_number_of_items" />
			<input type="hidden" name="nuclen_selected_post_status" id="nuclen_selected_post_status" />
			<input type="hidden" name="nuclen_selected_post_type" id="nuclen_selected_post_type" />

			<button type="submit" id="nuclen-submit-btn" class="button button-primary nuclen-button-primary">
				<?php esc_html_e( 'Generate Content', 'nuclear-engagement' ); ?>
			</button>
			<button type="button" id="nuclen-go-back-btn" class="button">
				<?php esc_html_e( 'Back to post selection', 'nuclear-engagement' ); ?>
			</button>
		</form>
	</div>

	<div id="nuclen-updates-section" class="nuclen-section nuclen-hidden" style="margin-top: 40px;">
		<h2 class="nuclen-subheading"><?php esc_html_e( 'Progress', 'nuclear-engagement' ); ?></h2>
		<div id="nuclen-updates-content" class="nuclen-progress"><?php esc_html_e( 'Processing posts...', 'nuclear-engagement' ); ?></div>
		<button id="nuclen-restart-btn" class="button button-primary nuclen-button-primary nuclen-hidden" style="margin-top: 20px;">
			<?php esc_html_e( 'Back to post selection', 'nuclear-engagement' ); ?>
		</button>
	</div>

</div>
