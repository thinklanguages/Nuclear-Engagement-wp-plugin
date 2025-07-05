<?php
/**
 * summary-container.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
// File: admin/partials/settings/theme/summary-container.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- ─────────── Summary container ─────────── -->
<h3 class="nuclen-subheading"><?php esc_html_e( 'Summary Container', 'nuclear-engagement' ); ?></h3>

<h4><?php esc_html_e( 'Font & Background', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_font_size" class="nuclen-label"><?php esc_html_e( 'Summary Font Size (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_summary_font_size" id="nuclen_summary_font_size" value="<?php echo esc_attr( $settings['summary_font_size'] ); ?>" min="10" max="50"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_font_color" class="nuclen-label"><?php esc_html_e( 'Summary Font Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_summary_font_color" id="nuclen_summary_font_color" value="<?php echo esc_attr( $settings['summary_font_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_bg_color" class="nuclen-label"><?php esc_html_e( 'Summary Background Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_summary_bg_color" id="nuclen_summary_bg_color" value="<?php echo esc_attr( $settings['summary_bg_color'] ); ?>"></div>
</div>

<h4><?php esc_html_e( 'Border Lines', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_border_color" class="nuclen-label"><?php esc_html_e( 'Summary Border Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_summary_border_color" id="nuclen_summary_border_color" value="<?php echo esc_attr( $settings['summary_border_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_border_style" class="nuclen-label"><?php esc_html_e( 'Summary Border Style', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col">
		<?php $styles = array( 'none', 'solid', 'dashed', 'dotted', 'double' ); ?>
		<select name="nuclen_summary_border_style" id="nuclen_summary_border_style" class="nuclen-input">
			<?php foreach ( $styles as $s ) : ?>
				<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $settings['summary_border_style'], $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_border_width" class="nuclen-label"><?php esc_html_e( 'Summary Border Width (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_summary_border_width" id="nuclen_summary_border_width" value="<?php echo esc_attr( $settings['summary_border_width'] ); ?>" min="0" max="10"></div>
</div>

<h4><?php esc_html_e( 'Border Radius &amp; Shadow', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_border_radius" class="nuclen-label"><?php esc_html_e( 'Summary Border Radius (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_summary_border_radius" id="nuclen_summary_border_radius" value="<?php echo esc_attr( $settings['summary_border_radius'] ); ?>" min="0" max="100"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_shadow_color" class="nuclen-label"><?php esc_html_e( 'Shadow Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_summary_shadow_color" id="nuclen_summary_shadow_color" value="<?php echo esc_attr( $settings['summary_shadow_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_shadow_blur" class="nuclen-label"><?php esc_html_e( 'Shadow Blur (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_summary_shadow_blur" id="nuclen_summary_shadow_blur" value="<?php echo esc_attr( $settings['summary_shadow_blur'] ); ?>" min="0" max="100"></div>
</div>
