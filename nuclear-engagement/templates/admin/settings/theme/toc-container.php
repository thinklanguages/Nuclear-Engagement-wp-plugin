<?php
/**
 * toc-container.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
// File: admin/partials/settings/theme/toc-container.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- ─────────── TOC container ─────────── -->
<h3 class="nuclen-subheading" style="margin-top:30px;"><?php esc_html_e( 'TOC Container', 'nuclear-engagement' ); ?></h3>

<h4><?php esc_html_e( 'Font & Background', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_font_size" class="nuclen-label"><?php esc_html_e( 'Font Size (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_toc_font_size" id="nuclen_toc_font_size" value="<?php echo esc_attr( $settings['toc_font_size'] ); ?>" min="10" max="50"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_font_color" class="nuclen-label"><?php esc_html_e( 'Font Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_toc_font_color" id="nuclen_toc_font_color" value="<?php echo esc_attr( $settings['toc_font_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_bg_color" class="nuclen-label"><?php esc_html_e( 'TOC Background Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_toc_bg_color" id="nuclen_toc_bg_color" value="<?php echo esc_attr( $settings['toc_bg_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_link_color" class="nuclen-label"><?php esc_html_e( 'TOC Link Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_toc_link_color" id="nuclen_toc_link_color" value="<?php echo esc_attr( $settings['toc_link_color'] ); ?>"></div>
</div>

<h4><?php esc_html_e( 'Border Lines', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_border_color" class="nuclen-label"><?php esc_html_e( 'TOC Border Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_toc_border_color" id="nuclen_toc_border_color" value="<?php echo esc_attr( $settings['toc_border_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_border_style" class="nuclen-label"><?php esc_html_e( 'TOC Border Style', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col">
		<?php $styles = array( 'none', 'solid', 'dashed', 'dotted', 'double' ); ?>
		<select name="nuclen_toc_border_style" id="nuclen_toc_border_style" class="nuclen-input">
			<?php foreach ( $styles as $s ) : ?>
				<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $settings['toc_border_style'], $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_border_width" class="nuclen-label"><?php esc_html_e( 'TOC Border Width (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_toc_border_width" id="nuclen_toc_border_width" value="<?php echo esc_attr( $settings['toc_border_width'] ); ?>" min="0" max="10"></div>
</div>

<h4><?php esc_html_e( 'Border Radius &amp; Shadow', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_border_radius" class="nuclen-label"><?php esc_html_e( 'TOC Border Radius (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_toc_border_radius" id="nuclen_toc_border_radius" value="<?php echo esc_attr( $settings['toc_border_radius'] ); ?>" min="0" max="100"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_shadow_color" class="nuclen-label"><?php esc_html_e( 'TOC Shadow Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_toc_shadow_color" id="nuclen_toc_shadow_color" value="<?php echo esc_attr( $settings['toc_shadow_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_shadow_blur" class="nuclen-label"><?php esc_html_e( 'TOC Shadow Blur (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_toc_shadow_blur" id="nuclen_toc_shadow_blur" value="<?php echo esc_attr( $settings['toc_shadow_blur'] ); ?>" min="0" max="100"></div>
</div>
