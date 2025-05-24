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
		<?php esc_html_e( 'Choose how and where to display quizzes and summaries.', 'nuclear-engagement' ); ?>
		<span nuclen-tooltip="<?php esc_attr_e( 'Shortcodes are the most versatile method. If your theme or page builder lacks slots for custom HTML in the single post template, you can only automatically append sections to the post content.', 'nuclear-engagement' ); ?>">🛈</span>
	</p>

	<!-- Display Summary -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_display_summary" class="nuclen-label"><?php esc_html_e( 'Display Summary', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<select name="nuclen_display_summary" id="nuclen_display_summary" class="nuclen-input">
				<option value="manual" <?php selected( $settings['display_summary'], 'manual' ); ?>><?php esc_html_e( 'Manually via shortcode', 'nuclear-engagement' ); ?></option>
				<option value="before" <?php selected( $settings['display_summary'], 'before' ); ?>><?php esc_html_e( 'Before post content', 'nuclear-engagement' ); ?></option>
				<option value="after"  <?php selected( $settings['display_summary'], 'after'  ); ?>><?php esc_html_e( 'After post content',  'nuclear-engagement' ); ?></option>
			</select>
			<p class="description">
				<?php
				$allowed_html = array( 'b' => array() );
				$summary_text = sprintf(
					__( 'Shortcode: %s. If set to “before” or “after”, the summary is displayed automatically.', 'nuclear-engagement' ),
					'<b>[nuclear_engagement_summary]</b>'
				);
				echo wp_kses( $summary_text, $allowed_html );
				?>
			</p>
		</div>
	</div>

	<!-- Display Quiz -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_display_quiz" class="nuclen-label"><?php esc_html_e( 'Display Quiz', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<select name="nuclen_display_quiz" id="nuclen_display_quiz" class="nuclen-input">
				<option value="manual" <?php selected( $settings['display_quiz'], 'manual' ); ?>><?php esc_html_e( 'Manually via shortcode', 'nuclear-engagement' ); ?></option>
				<option value="before" <?php selected( $settings['display_quiz'], 'before' ); ?>><?php esc_html_e( 'Before post content', 'nuclear-engagement' ); ?></option>
				<option value="after"  <?php selected( $settings['display_quiz'], 'after'  ); ?>><?php esc_html_e( 'After post content',  'nuclear-engagement' ); ?></option>
			</select>
			<p class="description">
				<?php
				$quiz_text = sprintf(
					__( 'Shortcode: %s. If set to “before” or “after”, the quiz is displayed automatically.', 'nuclear-engagement' ),
					'<b>[nuclear_engagement_quiz]</b>'
				);
				echo wp_kses( $quiz_text, $allowed_html );
				?>
			</p>
		</div>
	</div>

	<!-- Display TOC -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_display_toc" class="nuclen-label"><?php esc_html_e( 'Display Table of Contents', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<select name="nuclen_display_toc" id="nuclen_display_toc" class="nuclen-input">
				<option value="manual" <?php selected( $settings['display_toc'] ?? 'manual', 'manual' ); ?>><?php esc_html_e( 'Manually via shortcode', 'nuclear-engagement' ); ?></option>
				<option value="before" <?php selected( $settings['display_toc'] ?? 'manual', 'before' ); ?>><?php esc_html_e( 'Before post content', 'nuclear-engagement' ); ?></option>
				<option value="after"  <?php selected( $settings['display_toc'] ?? 'manual', 'after'  ); ?>><?php esc_html_e( 'After post content', 'nuclear-engagement' ); ?></option>
			</select>
			<p class="description">
				<?php
				$toc_text = sprintf(
					__( 'Shortcode: %s. If set to "before" or "after", the table of contents is displayed automatically.', 'nuclear-engagement' ),
					'<b>[nuclear_engagement_toc]</b>'
				);
				echo wp_kses( $toc_text, $allowed_html );
				?>
			</p>
		</div>
	</div>

	<!-- Sticky TOC -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_sticky" class="nuclen-label"><?php esc_html_e( 'Sticky TOC', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<label class="nuclen-checkbox-label">
				<input type="checkbox" name="toc_sticky" id="nuclen_toc_sticky" value="1" <?php checked( '1', $settings['toc_sticky'] ?? '0' ); ?> />
				<?php esc_html_e( 'Make Table of Contents sticky when scrolling', 'nuclear-engagement' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'When enabled, the TOC will stick to the top of the viewport when scrolling down the page.', 'nuclear-engagement' ); ?>
			</p>
		</div>
	</div>
</div>
