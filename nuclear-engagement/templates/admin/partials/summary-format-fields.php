<?php
/**
 * Summary format fields partial - Part of the Nuclear Engagement plugin.
 *
 * Common template for summary format selection fields used in both bulk generation and single post metabox.
 *
 * @package Nuclear_Engagement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set default values if not provided
$summary_format          = isset( $summary_format ) ? $summary_format : 'paragraph';
$summary_length          = isset( $summary_length ) ? $summary_length : 30;
$summary_number_of_items = isset( $summary_number_of_items ) ? $summary_number_of_items : 5;
$field_prefix            = isset( $field_prefix ) ? $field_prefix : '';
$id_prefix               = isset( $id_prefix ) ? $id_prefix : $field_prefix;
$show_labels             = isset( $show_labels ) ? $show_labels : true;
$use_array_notation      = isset( $use_array_notation ) ? $use_array_notation : true;
?>

<div class="nuclen-summary-format-fields">
	<?php if ( $show_labels ) : ?>
		<p><strong><?php esc_html_e( 'Summary Format', 'nuclear-engagement' ); ?></strong></p>
	<?php endif; ?>
	<select name="<?php echo esc_attr( $field_prefix ); ?><?php echo $use_array_notation ? '[format]' : 'format'; ?>" id="<?php echo esc_attr( $id_prefix ); ?>format" class="nuclen-input nuclen-summary-format-select">
		<option value="paragraph" <?php selected( $summary_format, 'paragraph' ); ?>><?php esc_html_e( 'Paragraph', 'nuclear-engagement' ); ?></option>
		<option value="bullet_list" <?php selected( $summary_format, 'bullet_list' ); ?>><?php esc_html_e( 'Bullet List', 'nuclear-engagement' ); ?></option>
	</select>

	<div class="nuclen-summary-paragraph-options" <?php echo ( $summary_format !== 'paragraph' ) ? 'style="display:none;"' : ''; ?>>
		<?php if ( $show_labels ) : ?>
			<p><strong><?php esc_html_e( 'Paragraph length (words)', 'nuclear-engagement' ); ?></strong></p>
		<?php endif; ?>
		<select name="<?php echo esc_attr( $field_prefix ); ?><?php echo $use_array_notation ? '[length]' : 'length'; ?>" id="<?php echo esc_attr( $id_prefix ); ?>length" class="nuclen-input">
			<option value="20" <?php selected( $summary_length, 20 ); ?>>20 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
			<option value="30" <?php selected( $summary_length, 30 ); ?>>30 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
			<option value="40" <?php selected( $summary_length, 40 ); ?>>40 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
			<option value="50" <?php selected( $summary_length, 50 ); ?>>50 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
		</select>
	</div>

	<div class="nuclen-summary-bullet-options" <?php echo ( $summary_format !== 'bullet_list' ) ? 'style="display:none;"' : ''; ?>>
		<?php if ( $show_labels ) : ?>
			<p><strong><?php esc_html_e( 'Number of bullet items', 'nuclear-engagement' ); ?></strong></p>
		<?php endif; ?>
		<select name="<?php echo esc_attr( $field_prefix ); ?><?php echo $use_array_notation ? '[number_of_items]' : 'number_of_items'; ?>" id="<?php echo esc_attr( $id_prefix ); ?>number_of_items" class="nuclen-input">
			<option value="3" <?php selected( $summary_number_of_items, 3 ); ?>>3 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
			<option value="4" <?php selected( $summary_number_of_items, 4 ); ?>>4 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
			<option value="5" <?php selected( $summary_number_of_items, 5 ); ?>>5 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
			<option value="6" <?php selected( $summary_number_of_items, 6 ); ?>>6 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
			<option value="7" <?php selected( $summary_number_of_items, 7 ); ?>>7 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
		</select>
	</div>
</div>
