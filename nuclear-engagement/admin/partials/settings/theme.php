<?php
// File: admin/partials/settings/theme.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Theme tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- THEME TAB -->
<div id="theme" class="nuclen-tab-content nuclen-section" style="display:none;">
	<h2 class="nuclen-subheading"><?php esc_html_e( 'Theme', 'nuclear-engagement' ); ?></h2>
	<p><?php esc_html_e( 'Select a theme preset, or choose custom to override individual settings.', 'nuclear-engagement' ); ?></p>

	<!-- Preset selector -->
	<div class="nuclen-form-group">
		<label><input type="radio" name="nuclen_theme" value="bright" <?php checked( $settings['theme'], 'bright' ); ?> /> <?php esc_html_e( 'Bright Theme (black on white)', 'nuclear-engagement' ); ?></label><br>
		<label><input type="radio" name="nuclen_theme" value="dark"   <?php checked( $settings['theme'], 'dark'   ); ?> /> <?php esc_html_e( 'Dark Theme (white on black)',   'nuclear-engagement' ); ?></label><br>
		<label><input type="radio" name="nuclen_theme" value="custom" <?php checked( $settings['theme'], 'custom' ); ?> /> <?php esc_html_e( 'Custom',                         'nuclear-engagement' ); ?></label><br>
		<label><input type="radio" name="nuclen_theme" value="none"   <?php checked( $settings['theme'], 'none'   ); ?> /> <?php esc_html_e( 'No Theme (only base CSS)',        'nuclear-engagement' ); ?></label>
	</div>

	<?php $custom_theme_class = ( $settings['theme'] === 'custom' ) ? '' : 'nuclen-hidden'; ?>
	<div id="nuclen-custom-theme-section" class="nuclen-form-group <?php echo esc_attr( $custom_theme_class ); ?>" style="margin-top:20px;">

		<!-- ─────────── Quiz container ─────────── -->
		<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Container', 'nuclear-engagement' ); ?></h3>

		<h4><?php esc_html_e( 'Font & Background', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_font_size" class="nuclen-label"><?php esc_html_e( 'Quiz Font Size (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_font_size" id="nuclen_font_size" value="<?php echo esc_attr( $settings['font_size'] ); ?>" min="10" max="50"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_font_color" class="nuclen-label"><?php esc_html_e( 'Quiz Font Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_font_color" id="nuclen_font_color" value="<?php echo esc_attr( $settings['font_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_bg_color" class="nuclen-label"><?php esc_html_e( 'Quiz Background Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_bg_color" id="nuclen_bg_color" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></div>
		</div>

		<h4><?php esc_html_e( 'Border Lines', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_border_color" class="nuclen-label"><?php esc_html_e( 'Quiz Border Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_border_color" id="nuclen_quiz_border_color" value="<?php echo esc_attr( $settings['quiz_border_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_border_style" class="nuclen-label"><?php esc_html_e( 'Quiz Border Style', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col">
				<?php $styles = array( 'none', 'solid', 'dashed', 'dotted', 'double' ); ?>
				<select name="nuclen_quiz_border_style" id="nuclen_quiz_border_style" class="nuclen-input">
					<?php foreach ( $styles as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $settings['quiz_border_style'], $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_border_width" class="nuclen-label"><?php esc_html_e( 'Quiz Border Width (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_border_width" id="nuclen_quiz_border_width" value="<?php echo esc_attr( $settings['quiz_border_width'] ); ?>" min="0" max="10"></div>
		</div>

		<h4><?php esc_html_e( 'Border Radius & Shadow', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_border_radius" class="nuclen-label"><?php esc_html_e( 'Quiz Border Radius (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_border_radius" id="nuclen_quiz_border_radius" value="<?php echo esc_attr( $settings['quiz_border_radius'] ); ?>" min="0" max="100"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_shadow_color" class="nuclen-label"><?php esc_html_e( 'Quiz Shadow Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_shadow_color" id="nuclen_quiz_shadow_color" value="<?php echo esc_attr( $settings['quiz_shadow_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_shadow_blur" class="nuclen-label"><?php esc_html_e( 'Quiz Shadow Blur (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_shadow_blur" id="nuclen_quiz_shadow_blur" value="<?php echo esc_attr( $settings['quiz_shadow_blur'] ); ?>" min="0" max="100"></div>
		</div>

		<!-- ─────────── Quiz answer buttons ─────────── -->
		<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Answer Buttons', 'nuclear-engagement' ); ?></h3>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_answer_button_bg_color" class="nuclen-label"><?php esc_html_e( 'Button BG Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_answer_button_bg_color" id="nuclen_quiz_answer_button_bg_color" value="<?php echo esc_attr( $settings['quiz_answer_button_bg_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_answer_button_border_color" class="nuclen-label"><?php esc_html_e( 'Button Border Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_answer_button_border_color" id="nuclen_quiz_answer_button_border_color" value="<?php echo esc_attr( $settings['quiz_answer_button_border_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_answer_button_border_width" class="nuclen-label"><?php esc_html_e( 'Button Border Width (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_answer_button_border_width" id="nuclen_quiz_answer_button_border_width" value="<?php echo esc_attr( $settings['quiz_answer_button_border_width'] ); ?>" min="0" max="10"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_answer_button_border_radius" class="nuclen-label"><?php esc_html_e( 'Button Border Radius (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_answer_button_border_radius" id="nuclen_quiz_answer_button_border_radius" value="<?php echo esc_attr( $settings['quiz_answer_button_border_radius'] ); ?>" min="0" max="100"></div>
		</div>

		<!-- ─────────── Progress bar ─────────── -->
		<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Progress Bar', 'nuclear-engagement' ); ?></h3>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_progress_bar_fg_color" class="nuclen-label"><?php esc_html_e( 'Progress Foreground Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_progress_bar_fg_color" id="nuclen_quiz_progress_bar_fg_color" value="<?php echo esc_attr( $settings['quiz_progress_bar_fg_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_progress_bar_bg_color" class="nuclen-label"><?php esc_html_e( 'Progress Background Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_progress_bar_bg_color" id="nuclen_quiz_progress_bar_bg_color" value="<?php echo esc_attr( $settings['quiz_progress_bar_bg_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_progress_bar_height" class="nuclen-label"><?php esc_html_e( 'Progress Bar Height (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_progress_bar_height" id="nuclen_quiz_progress_bar_height" value="<?php echo esc_attr( $settings['quiz_progress_bar_height'] ); ?>" min="1" max="50"></div>
		</div>

		<!-- ─────────── Summary container ─────────── -->
		<h3 class="nuclen-subheading"><?php esc_html_e( 'Summary Container', 'nuclear-engagement' ); ?></h3>

		<h4><?php esc_html_e( 'Font & Background', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_font_size" class="nuclen-label"><?php esc_html_e( 'Summary Font Size (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_summary_font_size" id="nuclen_summary_font_size" value="<?php echo esc_attr( $settings['summary_font_size'] ); ?>" min="10" max="50"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_font_color" class="nuclen-label"><?php esc_html_e( 'Summary Font Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_summary_font_color" id="nuclen_summary_font_color" value="<?php echo esc_attr( $settings['summary_font_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_bg_color" class="nuclen-label"><?php esc_html_e( 'Summary Background Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_summary_bg_color" id="nuclen_summary_bg_color" value="<?php echo esc_attr( $settings['summary_bg_color'] ); ?>"></div>
		</div>

		<h4><?php esc_html_e( 'Border Lines', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_border_color" class="nuclen-label"><?php esc_html_e( 'Summary Border Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_summary_border_color" id="nuclen_summary_border_color" value="<?php echo esc_attr( $settings['summary_border_color'] ); ?>"></div>
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

		<h4><?php esc_html_e( 'Border Radius & Shadow', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_border_radius" class="nuclen-label"><?php esc_html_e( 'Summary Border Radius (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_summary_border_radius" id="nuclen_summary_border_radius" value="<?php echo esc_attr( $settings['summary_border_radius'] ); ?>" min="0" max="100"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_shadow_color" class="nuclen-label"><?php esc_html_e( 'Shadow Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_summary_shadow_color" id="nuclen_summary_shadow_color" value="<?php echo esc_attr( $settings['summary_shadow_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_summary_shadow_blur" class="nuclen-label"><?php esc_html_e( 'Shadow Blur (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_summary_shadow_blur" id="nuclen_summary_shadow_blur" value="<?php echo esc_attr( $settings['summary_shadow_blur'] ); ?>" min="0" max="100"></div>
		</div>

		<!-- ─────────── TOC container ─────────── -->
		<h3 class="nuclen-subheading" style="margin-top:30px;"><?php esc_html_e( 'TOC Container', 'nuclear-engagement' ); ?></h3>

		<h4><?php esc_html_e( 'Font & Background', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_font_size" class="nuclen-label"><?php esc_html_e( 'Font Size (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_toc_font_size" id="nuclen_toc_font_size" value="<?php echo esc_attr( $settings['toc_font_size'] ); ?>" min="10" max="50"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_font_color" class="nuclen-label"><?php esc_html_e( 'Font Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_toc_font_color" id="nuclen_toc_font_color" value="<?php echo esc_attr( $settings['toc_font_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_bg_color" class="nuclen-label"><?php esc_html_e( 'TOC Background Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_toc_bg_color" id="nuclen_toc_bg_color" value="<?php echo esc_attr( $settings['toc_bg_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_link_color" class="nuclen-label"><?php esc_html_e( 'TOC Link Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_toc_link_color" id="nuclen_toc_link_color" value="<?php echo esc_attr( $settings['toc_link_color'] ); ?>"></div>
		</div>

		<h4><?php esc_html_e( 'Border Lines', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_border_color" class="nuclen-label"><?php esc_html_e( 'TOC Border Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_toc_border_color" id="nuclen_toc_border_color" value="<?php echo esc_attr( $settings['toc_border_color'] ); ?>"></div>
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

		<h4><?php esc_html_e( 'Border Radius & Shadow', 'nuclear-engagement' ); ?></h4>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_border_radius" class="nuclen-label"><?php esc_html_e( 'TOC Border Radius (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_toc_border_radius" id="nuclen_toc_border_radius" value="<?php echo esc_attr( $settings['toc_border_radius'] ); ?>" min="0" max="100"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_shadow_color" class="nuclen-label"><?php esc_html_e( 'TOC Shadow Color', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_toc_shadow_color" id="nuclen_toc_shadow_color" value="<?php echo esc_attr( $settings['toc_shadow_color'] ); ?>"></div>
		</div>
		<div class="nuclen-row">
			<div class="nuclen-column nuclen-label-col"><label for="nuclen_toc_shadow_blur" class="nuclen-label"><?php esc_html_e( 'TOC Shadow Blur (px)', 'nuclear-engagement' ); ?></label></div>
			<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_toc_shadow_blur" id="nuclen_toc_shadow_blur" value="<?php echo esc_attr( $settings['toc_shadow_blur'] ); ?>" min="0" max="100"></div>
		</div>

	</div><!-- /#nuclen-custom-theme-section -->
</div><!-- /#theme -->
