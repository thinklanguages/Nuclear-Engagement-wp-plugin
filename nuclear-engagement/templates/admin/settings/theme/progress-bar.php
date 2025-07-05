<?php
/**
 * progress-bar.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
// File: admin/partials/settings/theme/progress-bar.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- ─────────── Progress bar ─────────── -->
<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Progress Bar', 'nuclear-engagement' ); ?></h3>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_progress_bar_fg_color" class="nuclen-label"><?php esc_html_e( 'Progress Foreground Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_quiz_progress_bar_fg_color" id="nuclen_quiz_progress_bar_fg_color" value="<?php echo esc_attr( $settings['quiz_progress_bar_fg_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_progress_bar_bg_color" class="nuclen-label"><?php esc_html_e( 'Progress Background Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_quiz_progress_bar_bg_color" id="nuclen_quiz_progress_bar_bg_color" value="<?php echo esc_attr( $settings['quiz_progress_bar_bg_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_progress_bar_height" class="nuclen-label"><?php esc_html_e( 'Progress Bar Height (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_progress_bar_height" id="nuclen_quiz_progress_bar_height" value="<?php echo esc_attr( $settings['quiz_progress_bar_height'] ); ?>" min="1" max="50"></div>
</div>
