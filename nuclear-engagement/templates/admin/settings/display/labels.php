<?php
declare(strict_types=1);
// File: admin/partials/settings/display/labels.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="nuclen-subheading"><?php esc_html_e( 'Quiz Labels', 'nuclear-engagement' ); ?></h2>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="quiz_label_retake_test" class="nuclen-label"><?php esc_html_e( 'Retake Button', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="text" class="nuclen-input" name="quiz_label_retake_test" id="quiz_label_retake_test" value="<?php echo esc_attr( $settings['quiz_label_retake_test'] ); ?>" />
	</div>
</div>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="quiz_label_your_score" class="nuclen-label"><?php esc_html_e( 'Your Score Title', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="text" class="nuclen-input" name="quiz_label_your_score" id="quiz_label_your_score" value="<?php echo esc_attr( $settings['quiz_label_your_score'] ); ?>" />
	</div>
</div>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="quiz_label_perfect" class="nuclen-label"><?php esc_html_e( 'Perfect Score Message', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="text" class="nuclen-input" name="quiz_label_perfect" id="quiz_label_perfect" value="<?php echo esc_attr( $settings['quiz_label_perfect'] ); ?>" />
	</div>
</div>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="quiz_label_well_done" class="nuclen-label"><?php esc_html_e( 'Above Average Message', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="text" class="nuclen-input" name="quiz_label_well_done" id="quiz_label_well_done" value="<?php echo esc_attr( $settings['quiz_label_well_done'] ); ?>" />
	</div>
</div>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="quiz_label_retake_prompt" class="nuclen-label"><?php esc_html_e( 'Try Again Message', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="text" class="nuclen-input" name="quiz_label_retake_prompt" id="quiz_label_retake_prompt" value="<?php echo esc_attr( $settings['quiz_label_retake_prompt'] ); ?>" />
	</div>
</div>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="quiz_label_correct" class="nuclen-label"><?php esc_html_e( 'Correct Label', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="text" class="nuclen-input" name="quiz_label_correct" id="quiz_label_correct" value="<?php echo esc_attr( $settings['quiz_label_correct'] ); ?>" />
	</div>
</div>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="quiz_label_your_answer" class="nuclen-label"><?php esc_html_e( 'Your Answer Label', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="text" class="nuclen-input" name="quiz_label_your_answer" id="quiz_label_your_answer" value="<?php echo esc_attr( $settings['quiz_label_your_answer'] ); ?>" />
	</div>
</div>
