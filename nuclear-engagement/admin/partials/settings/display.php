<?php
// File: admin/partials/settings/display.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Display tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- DISPLAY TAB -->
<div id="display" class="nuclen-tab-content nuclen-section" style="display:none;">
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

	<h2 class="nuclen-subheading"><?php esc_html_e( 'Quiz Custom Text', 'nuclear-engagement' ); ?>
		<span nuclen-tooltip="<?php esc_attr_e( 'Useful for coupons, disclaimers, etc.', 'nuclear-engagement' ); ?>">🛈</span>
	</h2>

	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="custom_quiz_html_before" class="nuclen-label"><?php esc_html_e( 'Message before quiz start', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<?php
			wp_editor(
				$settings['custom_quiz_html_before'] ?? '',
				'custom_quiz_html_before',
				array(
					'textarea_name' => 'custom_quiz_html_before',
					'textarea_rows' => 5,
				)
			);
			?>
		</div>
	</div>

	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="custom_quiz_html_after" class="nuclen-label"><?php esc_html_e( 'Message after quiz end', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<?php
			wp_editor(
				$settings['custom_quiz_html_after'] ?? '',
				'custom_quiz_html_after',
				array(
					'textarea_name' => 'custom_quiz_html_after',
					'textarea_rows' => 5,
				)
			);
			?>
		</div>
	</div>

	<h2 class="nuclen-subheading"><?php esc_html_e( 'Section Titles', 'nuclear-engagement' ); ?></h2>
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="quiz_title" class="nuclen-label"><?php esc_html_e( 'Quiz Title', 'nuclear-engagement' ); ?>
				<span nuclen-tooltip="<?php esc_attr_e( 'Examples: "Test your knowledge," "Can you pass this test?"', 'nuclear-engagement' ); ?>">🛈</span>
			</label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="text" class="nuclen-input" name="quiz_title" id="quiz_title" value="<?php echo esc_attr( $settings['quiz_title'] ); ?>">
		</div>
	</div>

	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="summary_title" class="nuclen-label"><?php esc_html_e( 'Summary Title', 'nuclear-engagement' ); ?>
				<span nuclen-tooltip="<?php esc_attr_e( 'Examples: "Summary," "Key Concepts."', 'nuclear-engagement' ); ?>">🛈</span>
			</label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="text" class="nuclen-input" name="summary_title" id="summary_title" value="<?php echo esc_attr( $settings['summary_title'] ); ?>">
		</div>
	</div>

	<!-- Show Attribution -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_show_attribution" class="nuclen-label"><?php esc_html_e( 'Display Attribution Link', 'nuclear-engagement' ); ?>
				<span nuclen-tooltip="<?php esc_attr_e( 'Help spread the word with a small link under the NE sections.', 'nuclear-engagement' ); ?>">🛈</span>
			</label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="checkbox" name="show_attribution" id="nuclen_show_attribution" value="1" <?php checked( $settings['show_attribution'], true ); ?>>
		</div>
	</div>
</div><!-- /#display -->
