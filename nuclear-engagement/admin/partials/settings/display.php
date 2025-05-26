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

	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_title" class="nuclen-label"><?php esc_html_e( 'TOC Title', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<input type="text" class="nuclen-input" name="nuclen_toc_title" id="nuclen_toc_title" value="<?php echo esc_attr( $settings['toc_title'] ); ?>">
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

	<!-- Table of Contents Settings -->
	<h2 class="nuclen-subheading"><?php esc_html_e( 'Table of Contents', 'nuclear-engagement' ); ?></h2>

	<!-- Heading Levels -->
	<h4><?php esc_html_e( 'Heading Levels', 'nuclear-engagement' ); ?></h4>
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label class="nuclen-label"><?php esc_html_e( 'Include in TOC', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<?php 
			// Get the saved heading levels, default to all levels if not set
			$selected_levels = isset($settings['toc_heading_levels']) ? (array)$settings['toc_heading_levels'] : range(2, 6);
			
			// Ensure we have an array of integers between 2 and 6
			$selected_levels = array_map('intval', $selected_levels);
			$selected_levels = array_filter($selected_levels, function($level) {
				return $level >= 2 && $level <= 6;
			});
			
			// If no valid levels, use default (2-6)
			if (empty($selected_levels)) {
				$selected_levels = range(2, 6);
			}
			
			for ($i = 2; $i <= 6; $i++): 
				$checked = in_array($i, $selected_levels, true) ? 'checked="checked"' : '';
			?>
				<label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
					<input type="checkbox" 
					       name="nuclear_engagement_settings[toc_heading_levels][]" 
					       value="<?php echo esc_attr($i); ?>" 
					       <?php echo $checked; ?>>
					<?php echo sprintf(esc_html__('H%d', 'nuclear-engagement'), $i); ?>
				</label>
			<?php endfor; ?>
			<p class="description" style="margin-top: 5px;">
				<?php esc_html_e('Select which heading levels to include in the Table of Contents.', 'nuclear-engagement'); ?>
			</p>
		</div>
	</div>

	<!-- Toggle Button Settings -->
	<h4><?php esc_html_e( 'Display Options', 'nuclear-engagement' ); ?></h4>
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_show_toggle" class="nuclen-label"><?php esc_html_e( 'Show Toggle Button', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<label class="nuclen-switch">
				<input type="checkbox" name="nuclen_toc_show_toggle" id="nuclen_toc_show_toggle" value="1" <?php checked( ! empty( $settings['toc_show_toggle'] ) ); ?>>
				<span class="nuclen-slider round"></span>
			</label>
			<p class="description"><?php esc_html_e( 'When enabled, a toggle button will be shown to show/hide the Table of Contents content.', 'nuclear-engagement' ); ?></p>
		</div>
	</div>

	<!-- Show TOC Content by Default -->
	<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
			<label for="nuclen_toc_show_content" class="nuclen-label"><?php esc_html_e( 'Show TOC Content by Default', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
			<label class="nuclen-switch">
				<input type="checkbox" name="nuclen_toc_show_content" id="nuclen_toc_show_content" value="1" <?php checked( empty( $settings['toc_show_toggle'] ) || ! empty( $settings['toc_show_content'] ) ); ?><?php echo empty( $settings['toc_show_toggle'] ) ? ' disabled' : ''; ?>>
				<span class="nuclen-slider round"></span>
			</label>
			<p class="description">
				<?php esc_html_e( 'When enabled, the Table of Contents content will be visible by default.', 'nuclear-engagement' ); ?>
				<?php if ( empty( $settings['toc_show_toggle'] ) ) : ?>
				<br><em><?php esc_html_e( 'This option is only available when the toggle button is enabled.', 'nuclear-engagement' ); ?></em>
				<?php endif; ?>
			</p>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		function updateTocToggleState() {
			var showToggle = $('#nuclen_toc_show_toggle').is(':checked');
			var $showContent = $('#nuclen_toc_show_content');
			$showContent.prop('disabled', !showToggle);
			// If toggle is disabled, ensure the content is checked
			if (!showToggle) {
				$showContent.prop('checked', true);
			}
		}
		
		$('#nuclen_toc_show_toggle').on('change', updateTocToggleState);
		updateTocToggleState();
	});
	</script>
</div><!-- /#display -->
