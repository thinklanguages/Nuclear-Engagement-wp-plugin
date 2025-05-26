<?php
// File: admin/partials/settings/placement.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Placement tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- PLACEMENT TAB -->
<div id="placement" class="nuclen-tab-content nuclen-section" style="display:block;">

	<h2 class="nuclen-subheading"><?php esc_html_e( 'Placement', 'nuclear-engagement' ); ?></h2>
	<p>
		<?php esc_html_e( 'Choose how and where to display quizzes, summaries and the Table of Contents.', 'nuclear-engagement' ); ?>
		<span nuclen-tooltip="<?php esc_attr_e( 'Shortcodes are the most versatile method. If your theme or page-builder lacks suitable slots you can append sections automatically.', 'nuclear-engagement' ); ?>">🛈</span>
	</p>

	<!-- ───────────── Display positions ───────────── -->

	<!-- SUMMARY -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_display_summary" class="nuclen-label"><?php esc_html_e( 'Display Summary', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<select name="nuclen_display_summary" id="nuclen_display_summary" class="nuclen-input">
				<option value="manual" <?php selected( $settings['display_summary'], 'manual' ); ?>><?php esc_html_e( 'Manually via shortcode', 'nuclear-engagement' ); ?></option>
				<option value="before" <?php selected( $settings['display_summary'], 'before' ); ?>><?php esc_html_e( 'Before post content',     'nuclear-engagement' ); ?></option>
				<option value="after"  <?php selected( $settings['display_summary'], 'after'  ); ?>><?php esc_html_e( 'After post content',      'nuclear-engagement' ); ?></option>
			</select>
			<p class="description"><?php printf( wp_kses( __( 'Shortcode: <b>%s</b>.', 'nuclear-engagement' ), array( 'b' => array() ) ), '[nuclear_engagement_summary]' ); ?></p>
		</div>
	</div>

	<!-- QUIZ -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_display_quiz" class="nuclen-label"><?php esc_html_e( 'Display Quiz', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<select name="nuclen_display_quiz" id="nuclen_display_quiz" class="nuclen-input">
				<option value="manual" <?php selected( $settings['display_quiz'], 'manual' ); ?>><?php esc_html_e( 'Manually via shortcode', 'nuclear-engagement' ); ?></option>
				<option value="before" <?php selected( $settings['display_quiz'], 'before' ); ?>><?php esc_html_e( 'Before post content',     'nuclear-engagement' ); ?></option>
				<option value="after"  <?php selected( $settings['display_quiz'], 'after'  ); ?>><?php esc_html_e( 'After post content',      'nuclear-engagement' ); ?></option>
			</select>
			<p class="description"><?php printf( wp_kses( __( 'Shortcode: <b>%s</b>.', 'nuclear-engagement' ), array( 'b' => array() ) ), '[nuclear_engagement_quiz]' ); ?></p>
		</div>
	</div>

	<!-- TOC -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_display_toc" class="nuclen-label"><?php esc_html_e( 'Display Table of Contents', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<select name="nuclen_display_toc" id="nuclen_display_toc" class="nuclen-input">
				<option value="manual" <?php selected( $settings['display_toc'] ?? 'manual', 'manual' ); ?>><?php esc_html_e( 'Manually via shortcode', 'nuclear-engagement' ); ?></option>
				<option value="before" <?php selected( $settings['display_toc'] ?? 'manual', 'before' ); ?>><?php esc_html_e( 'Before post content',     'nuclear-engagement' ); ?></option>
				<option value="after"  <?php selected( $settings['display_toc'] ?? 'manual', 'after'  ); ?>><?php esc_html_e( 'After post content',      'nuclear-engagement' ); ?></option>
			</select>
			<p class="description"><?php printf( wp_kses( __( 'Shortcode: <b>%s</b>.', 'nuclear-engagement' ), array( 'b' => array() ) ), '[nuclear_engagement_toc]' ); ?></p>
		</div>
	</div>

	<!-- ───────────── Sticky TOC & advanced ───────────── -->

	<!-- Sticky toggle -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_sticky" class="nuclen-label"><?php esc_html_e( 'Sticky TOC', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<label class="nuclen-checkbox-label">
				<input type="checkbox" name="toc_sticky" id="nuclen_toc_sticky" value="1" <?php checked( '1', $settings['toc_sticky'] ?? '0' ); ?> />
				<?php esc_html_e( 'Make Table of Contents sticky when scrolling', 'nuclear-engagement' ); ?>
			</label>
		</div>
	</div>

	<!-- Z-index -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_zindex" class="nuclen-label"><?php esc_html_e( 'TOC Z-Index', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="number"
			       name="toc_zindex"
			       id="nuclen_toc_zindex"
			       class="small-text"
			       min="0"
			       step="1"
			       value="<?php echo esc_attr( $settings['toc_z_index'] ?? '100' ); ?>" />
			<p class="description"><?php esc_html_e( 'Higher numbers keep the sticky TOC above other elements.', 'nuclear-engagement' ); ?></p>
		</div>
	</div>

	<!-- Offset X -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_sticky_offset_x" class="nuclen-label"><?php esc_html_e( 'Sticky Offset X (px)', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="number"
			       name="toc_sticky_offset_x"
			       id="nuclen_toc_sticky_offset_x"
			       class="small-text"
			       min="0"
			       step="1"
			       value="<?php echo esc_attr( $settings['toc_sticky_offset_x'] ?? '20' ); ?>" />
		</div>
	</div>

	<!-- Offset Y -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_sticky_offset_y" class="nuclen-label"><?php esc_html_e( 'Sticky Offset Y (px)', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="number"
			       name="toc_sticky_offset_y"
			       id="nuclen_toc_sticky_offset_y"
			       class="small-text"
			       min="0"
			       step="1"
			       value="<?php echo esc_attr( $settings['toc_sticky_offset_y'] ?? '20' ); ?>" />
		</div>
	</div>

	<!-- Max width -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_sticky_max_width" class="nuclen-label"><?php esc_html_e( 'Sticky Max-width (px)', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="number"
			       name="toc_sticky_max_width"
			       id="nuclen_toc_sticky_max_width"
			       class="small-text"
			       min="200"
			       step="1"
			       value="<?php echo esc_attr( $settings['toc_sticky_max_width'] ?? '300' ); ?>" />
		</div>
	</div>

</div><!-- /#placement -->
