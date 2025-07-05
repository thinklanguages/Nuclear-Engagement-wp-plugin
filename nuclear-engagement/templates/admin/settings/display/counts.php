<?php
/**
 * counts.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
// File: admin/partials/settings/display/counts.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 class="nuclen-subheading"><?php esc_html_e( 'Number of Quiz Questions, Answers', 'nuclear-engagement' ); ?></h2>
<p>
	<?php esc_html_e( 'Choose how many questions and answers to display per quiz.', 'nuclear-engagement' ); ?>
	<span nuclen-tooltip="<?php esc_attr_e( 'NE always generates 10 questions and 4 answers. These settings only control how many you show. You can change them at any time.', 'nuclear-engagement' ); ?>">🛈</span>
</p>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="nuclen_questions_per_quiz" class="nuclen-label"><?php esc_html_e( 'Number of Questions per Quiz', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="number" class="nuclen-input" name="nuclen_questions_per_quiz" id="nuclen_questions_per_quiz" value="<?php echo esc_attr( $settings['questions_per_quiz'] ); ?>" min="3" max="10">
	</div>
</div>
<div class="nuclen-form-group nuclen-row">
	<div class="nuclen-column nuclen-label-col">
		<label for="nuclen_answers_per_question" class="nuclen-label"><?php esc_html_e( 'Number of Answers per Question', 'nuclear-engagement' ); ?></label>
	</div>
	<div class="nuclen-column nuclen-input-col">
		<input type="number" class="nuclen-input" name="nuclen_answers_per_question" id="nuclen_answers_per_question" value="<?php echo esc_attr( $settings['answers_per_question'] ); ?>" min="2" max="4">
	</div>
</div>
