<?php
declare(strict_types=1);
// File: admin/partials/settings/theme/quiz-buttons.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- ─────────── Quiz answer buttons ─────────── -->
<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Answer Buttons', 'nuclear-engagement' ); ?></h3>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_answer_button_bg_color" class="nuclen-label"><?php esc_html_e( 'Button BG Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_quiz_answer_button_bg_color" id="nuclen_quiz_answer_button_bg_color" value="<?php echo esc_attr( $settings['quiz_answer_button_bg_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_answer_button_border_color" class="nuclen-label"><?php esc_html_e( 'Button Border Color', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_quiz_answer_button_border_color" id="nuclen_quiz_answer_button_border_color" value="<?php echo esc_attr( $settings['quiz_answer_button_border_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_answer_button_border_width" class="nuclen-label"><?php esc_html_e( 'Button Border Width (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_answer_button_border_width" id="nuclen_quiz_answer_button_border_width" value="<?php echo esc_attr( $settings['quiz_answer_button_border_width'] ); ?>" min="0" max="10"></div>
</div>
<div class="nuclen-row">
	<div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_answer_button_border_radius" class="nuclen-label"><?php esc_html_e( 'Button Border Radius (px)', 'nuclear-engagement' ); ?></label></div>
	<div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_answer_button_border_radius" id="nuclen_quiz_answer_button_border_radius" value="<?php echo esc_attr( $settings['quiz_answer_button_border_radius'] ); ?>" min="0" max="100"></div>
</div>
