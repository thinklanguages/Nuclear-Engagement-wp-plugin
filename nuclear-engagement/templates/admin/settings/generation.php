<?php
declare(strict_types=1);
// File: admin/partials/settings-tabs/generation.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generation tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- GENERATION TAB -->
<div id="generation" class="nuclen-tab-content nuclen-section" style="display:none;">
	<h2 class="nuclen-subheading"><?php esc_html_e( 'Generation', 'nuclear-engagement' ); ?></h2>

	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="update_last_modified" class="nuclen-label"><?php esc_html_e( 'Update "Last Modified" date', 'nuclear-engagement' ); ?>
				<span nuclen-tooltip="<?php esc_attr_e( 'Check to update the post’s native "last modified" date whenever NE generation runs.', 'nuclear-engagement' ); ?>">🛈</span>
			</label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="checkbox" class="nuclen-checkbox" name="update_last_modified" id="update_last_modified" value="1" <?php checked( $settings['update_last_modified'], 1 ); ?>>
		</div>
	</div>

	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="auto_generate_quiz_on_publish" class="nuclen-label"><?php esc_html_e( 'Auto-generate Quiz on publish', 'nuclear-engagement' ); ?>
				<span nuclen-tooltip="<?php esc_attr_e( 'If checked, a quiz is automatically generated or updated whenever the post is published or republished.', 'nuclear-engagement' ); ?>">🛈</span>
			</label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="checkbox" class="nuclen-checkbox" name="auto_generate_quiz_on_publish" id="auto_generate_quiz_on_publish" value="1" <?php checked( $settings['auto_generate_quiz_on_publish'], 1 ); ?>>
		</div>
	</div>

	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="auto_generate_summary_on_publish" class="nuclen-label"><?php esc_html_e( 'Auto-generate Summary on publish', 'nuclear-engagement' ); ?>
				<span nuclen-tooltip="<?php esc_attr_e( 'If checked, a summary is automatically generated or updated whenever the post is published or republished.', 'nuclear-engagement' ); ?>">🛈</span>
			</label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="checkbox" class="nuclen-checkbox" name="auto_generate_summary_on_publish" id="auto_generate_summary_on_publish" value="1" <?php checked( $settings['auto_generate_summary_on_publish'], 1 ); ?>>
		</div>
	</div>

	<h2 class="nuclen-subheading"><?php esc_html_e( 'Allowed Post Types', 'nuclear-engagement' ); ?></h2>
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_generation_post_types" class="nuclen-label"><?php esc_html_e( 'Select Post Types', 'nuclear-engagement' ); ?>
				<span nuclen-tooltip="<?php esc_attr_e( 'By default, NE only processes posts. Select additional post types if you want quizzes/summaries for them as well. Use Ctrl/Cmd to select multiple.', 'nuclear-engagement' ); ?>">🛈</span>
			</label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<?php
			$all_post_types   = get_post_types( array( 'public' => true ), 'objects' );
			$excluded         = array(
				'attachment',
				'revision',
				'nav_menu_item',
				'custom_css',
				'customize_changeset',
				'oembed_cache',
				'user_request',
				'wp_block',
				'wp_template',
				'wp_template_part',
			);
			$saved_post_types = $settings['generation_post_types'] ?? array( 'post' );
			echo '<select name="nuclen_generation_post_types[]" id="nuclen_generation_post_types" multiple style="height:6em;">';
			foreach ( $all_post_types as $pt_key => $pt_obj ) {
				if ( in_array( $pt_key, $excluded ) ) {
					continue;
				}
				echo '<option value="' . esc_attr( $pt_key ) . '" ' . selected( in_array( $pt_key, $saved_post_types ), true, false ) . '>'
					. esc_html( $pt_obj->labels->name ) . '</option>';
			}
			echo '</select>';
			?>
		</div>
	</div>
</div><!-- /#generation -->
