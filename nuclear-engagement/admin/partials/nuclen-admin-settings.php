<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * File: admin/partials/nuclen-admin-settings.php
 *
 * Changes:
 * - Removed color help text
 * - Using .nuclen-row, .nuclen-column, .nuclen-label-col, .nuclen-input-col for neat horizontal alignment
 * - Kept H3/H4/H5 headings for hierarchical grouping
 * - Full code with no omissions
 *
 * @package NuclearEngagement\Admin
 */
?>

<div class="wrap nuclen-container">
	<h1><?php esc_html_e( 'Nuclear Engagement Settings', 'nuclear-engagement' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( 'nuclen_settings_nonce', 'nuclen_settings_nonce_field' ); ?>

		<div class="nav-tab-wrapper">
			<a id="placement-tab" href="#placement" class="nav-tab nav-tab-active"><?php esc_html_e( 'Placement', 'nuclear-engagement' ); ?></a>
			<a id="theme-tab" href="#theme" class="nav-tab"><?php esc_html_e( 'Theme', 'nuclear-engagement' ); ?></a>
			<a id="display-tab" href="#display" class="nav-tab"><?php esc_html_e( 'Display', 'nuclear-engagement' ); ?></a>
			<a id="optin-tab" href="#optin" class="nav-tab"><?php esc_html_e( 'Opt-In', 'nuclear-engagement' ); ?></a>
			<a id="generation-tab" href="#generation" class="nav-tab"><?php esc_html_e( 'Generation', 'nuclear-engagement' ); ?></a>
		</div>

		<!-- PLACEMENT TAB -->
		<div id="placement" class="nuclen-tab-content nuclen-section" style="display:block;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Placement', 'nuclear-engagement' ); ?></h2>
			<p><?php esc_html_e( 'Choose how and where to display quizzes and summaries.', 'nuclear-engagement' ); ?></p>

			<!-- Display Summary -->
			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="nuclen_display_summary" class="nuclen-label"><?php esc_html_e( 'Display Summary', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<select name="nuclen_display_summary" id="nuclen_display_summary" class="nuclen-input">
						<option value="manual" <?php selected( $settings['display_summary'], 'manual' ); ?>><?php esc_html_e( 'Manually via shortcode', 'nuclear-engagement' ); ?></option>
						<option value="before" <?php selected( $settings['display_summary'], 'before' ); ?>><?php esc_html_e( 'Before post content', 'nuclear-engagement' ); ?></option>
						<option value="after" <?php selected( $settings['display_summary'], 'after' ); ?>><?php esc_html_e( 'After post content', 'nuclear-engagement' ); ?></option>
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
						<option value="after" <?php selected( $settings['display_quiz'], 'after' ); ?>><?php esc_html_e( 'After post content', 'nuclear-engagement' ); ?></option>
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
		</div>

		<!-- THEME TAB -->
		<div id="theme" class="nuclen-tab-content nuclen-section" style="display:none;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Theme', 'nuclear-engagement' ); ?></h2>
			<p><?php esc_html_e( 'Select a theme preset, or choose custom to override individual settings.', 'nuclear-engagement' ); ?></p>

			<div class="nuclen-form-group">
				<label>
					<input type="radio" name="nuclen_theme" value="bright" <?php checked( $settings['theme'], 'bright' ); ?>>
					<?php esc_html_e( 'Bright Theme (black on white)', 'nuclear-engagement' ); ?>
				</label>
				<br>
				<label>
					<input type="radio" name="nuclen_theme" value="dark" <?php checked( $settings['theme'], 'dark' ); ?>>
					<?php esc_html_e( 'Dark Theme (white on black)', 'nuclear-engagement' ); ?>
				</label>
				<br>
				<label>
					<input type="radio" name="nuclen_theme" value="custom" <?php checked( $settings['theme'], 'custom' ); ?>>
					<?php esc_html_e( 'Custom', 'nuclear-engagement' ); ?>
				</label>
				<br>
				<label>
					<input type="radio" name="nuclen_theme" value="none" <?php checked( $settings['theme'], 'none' ); ?>>
					<?php esc_html_e( 'No Theme (only base CSS)', 'nuclear-engagement' ); ?>
				</label>
			</div>

			<?php $custom_theme_class = ( $settings['theme'] === 'custom' ) ? '' : 'nuclen-hidden'; ?>
			<div id="nuclen-custom-theme-section" class="nuclen-form-group <?php echo esc_attr( $custom_theme_class ); ?>" style="margin-top:20px;">

				<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Container', 'nuclear-engagement' ); ?></h3>

				<h4><?php esc_html_e( 'Font & Background', 'nuclear-engagement' ); ?></h4>

				<!-- Quiz Font Size -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_font_size" class="nuclen-label"><?php esc_html_e( 'Quiz Font Size (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_font_size" id="nuclen_font_size"
								value="<?php echo esc_attr( $settings['font_size'] ); ?>" min="10" max="50">
					</div>
				</div>

				<!-- Quiz Font Color -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_font_color" class="nuclen-label"><?php esc_html_e( 'Quiz Font Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_font_color" id="nuclen_font_color"
								value="<?php echo esc_attr( $settings['font_color'] ); ?>">
					</div>
				</div>

				<!-- Quiz BG Color -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_bg_color" class="nuclen-label"><?php esc_html_e( 'Quiz Background Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_bg_color" id="nuclen_bg_color"
								value="<?php echo esc_attr( $settings['bg_color'] ); ?>">
					</div>
				</div>

				<h4><?php esc_html_e( 'Border Lines', 'nuclear-engagement' ); ?></h4>

				<!-- Quiz Border Color -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_border_color" class="nuclen-label"><?php esc_html_e( 'Quiz Border Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_border_color" id="nuclen_quiz_border_color"
								value="<?php echo esc_attr( $settings['quiz_border_color'] ); ?>">
					</div>
				</div>

				<!-- Quiz Border Style -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_border_style" class="nuclen-label"><?php esc_html_e( 'Quiz Border Style', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<?php $styles = array( 'none', 'solid', 'dashed', 'dotted', 'double' ); ?>
						<select name="nuclen_quiz_border_style" id="nuclen_quiz_border_style" class="nuclen-input">
							<?php foreach ( $styles as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $settings['quiz_border_style'], $s ); ?>>
									<?php echo esc_html( ucfirst( $s ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<!-- Quiz Border Width -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_border_width" class="nuclen-label"><?php esc_html_e( 'Quiz Border Width (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_quiz_border_width" id="nuclen_quiz_border_width"
								value="<?php echo esc_attr( $settings['quiz_border_width'] ); ?>" min="0" max="10">
					</div>
				</div>

				<h4><?php esc_html_e( 'Border Radius & Shadow', 'nuclear-engagement' ); ?></h4>

				<!-- Quiz Border Radius -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_border_radius" class="nuclen-label"><?php esc_html_e( 'Quiz Border Radius (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_quiz_border_radius" id="nuclen_quiz_border_radius"
								value="<?php echo esc_attr( $settings['quiz_border_radius'] ); ?>" min="0" max="100">
					</div>
				</div>

				<!-- Quiz Shadow Color -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_shadow_color" class="nuclen-label"><?php esc_html_e( 'Quiz Shadow Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_shadow_color" id="nuclen_quiz_shadow_color"
								value="<?php echo esc_attr( $settings['quiz_shadow_color'] ); ?>">
					</div>
				</div>

				<!-- Quiz Shadow Blur -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_shadow_blur" class="nuclen-label"><?php esc_html_e( 'Quiz Shadow Blur (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_quiz_shadow_blur" id="nuclen_quiz_shadow_blur"
								value="<?php echo esc_attr( $settings['quiz_shadow_blur'] ); ?>" min="0" max="100">
					</div>
				</div>

				<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Answer Buttons', 'nuclear-engagement' ); ?></h3>

				<!-- quiz button BG color -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_answer_button_bg_color" class="nuclen-label"><?php esc_html_e( 'Button BG Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_answer_button_bg_color" id="nuclen_quiz_answer_button_bg_color"
								value="<?php echo esc_attr( $settings['quiz_answer_button_bg_color'] ); ?>">
					</div>
				</div>

				<!-- quiz button Border Color -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_answer_button_border_color" class="nuclen-label"><?php esc_html_e( 'Button Border Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_answer_button_border_color" id="nuclen_quiz_answer_button_border_color"
								value="<?php echo esc_attr( $settings['quiz_answer_button_border_color'] ); ?>">
					</div>
				</div>

				<!-- quiz button Border Width -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_answer_button_border_width" class="nuclen-label"><?php esc_html_e( 'Button Border Width (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_quiz_answer_button_border_width" id="nuclen_quiz_answer_button_border_width"
								value="<?php echo esc_attr( $settings['quiz_answer_button_border_width'] ); ?>" min="0" max="10">
					</div>
				</div>

				<!-- quiz button Border Radius -->
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_answer_button_border_radius" class="nuclen-label"><?php esc_html_e( 'Button Border Radius (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_quiz_answer_button_border_radius" id="nuclen_quiz_answer_button_border_radius"
								value="<?php echo esc_attr( $settings['quiz_answer_button_border_radius'] ); ?>" min="0" max="100">
					</div>
				</div>

				<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Progress Bar', 'nuclear-engagement' ); ?></h3>

				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_progress_bar_fg_color" class="nuclen-label">
							<?php esc_html_e( 'Progress Foreground Color', 'nuclear-engagement' ); ?>
						</label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_progress_bar_fg_color" id="nuclen_quiz_progress_bar_fg_color"
								value="<?php echo esc_attr( $settings['quiz_progress_bar_fg_color'] ); ?>">
					</div>
				</div>

				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_progress_bar_bg_color" class="nuclen-label"><?php esc_html_e( 'Progress Background Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_quiz_progress_bar_bg_color" id="nuclen_quiz_progress_bar_bg_color"
								value="<?php echo esc_attr( $settings['quiz_progress_bar_bg_color'] ); ?>">
					</div>
				</div>

				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_quiz_progress_bar_height" class="nuclen-label"><?php esc_html_e( 'Progress Bar Height (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_quiz_progress_bar_height" id="nuclen_quiz_progress_bar_height"
								value="<?php echo esc_attr( $settings['quiz_progress_bar_height'] ); ?>" min="1" max="50">
					</div>
				</div>

				<h3 class="nuclen-subheading"><?php esc_html_e( 'Summary Container', 'nuclear-engagement' ); ?></h3>

				<h4><?php esc_html_e( 'Font & Background', 'nuclear-engagement' ); ?></h4>
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_font_size" class="nuclen-label"><?php esc_html_e( 'Summary Font Size (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_summary_font_size" id="nuclen_summary_font_size"
								value="<?php echo esc_attr( $settings['summary_font_size'] ); ?>" min="10" max="50">
					</div>
				</div>

				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_font_color" class="nuclen-label"><?php esc_html_e( 'Summary Font Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_summary_font_color" id="nuclen_summary_font_color"
								value="<?php echo esc_attr( $settings['summary_font_color'] ); ?>">
					</div>
				</div>

				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_bg_color" class="nuclen-label"><?php esc_html_e( 'Summary Background Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_summary_bg_color" id="nuclen_summary_bg_color"
								value="<?php echo esc_attr( $settings['summary_bg_color'] ); ?>">
					</div>
				</div>

				<h4><?php esc_html_e( 'Border Lines', 'nuclear-engagement' ); ?></h4>
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_border_color" class="nuclen-label"><?php esc_html_e( 'Summary Border Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_summary_border_color" id="nuclen_summary_border_color"
								value="<?php echo esc_attr( $settings['summary_border_color'] ); ?>">
					</div>
				</div>

				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_border_style" class="nuclen-label"><?php esc_html_e( 'Summary Border Style', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<?php $styles = array( 'none', 'solid', 'dashed', 'dotted', 'double' ); ?>
						<select name="nuclen_summary_border_style" id="nuclen_summary_border_style" class="nuclen-input">
							<?php foreach ( $styles as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $settings['summary_border_style'], $s ); ?>>
									<?php echo esc_html( ucfirst( $s ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_border_width" class="nuclen-label"><?php esc_html_e( 'Summary Border Width (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_summary_border_width" id="nuclen_summary_border_width"
								value="<?php echo esc_attr( $settings['summary_border_width'] ); ?>" min="0" max="10">
					</div>
				</div>

				<h4><?php esc_html_e( 'Border Radius & Shadow', 'nuclear-engagement' ); ?></h4>
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_border_radius" class="nuclen-label"><?php esc_html_e( 'Summary Border Radius (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_summary_border_radius" id="nuclen_summary_border_radius"
								value="<?php echo esc_attr( $settings['summary_border_radius'] ); ?>" min="0" max="100">
					</div>
				</div>
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_shadow_color" class="nuclen-label"><?php esc_html_e( 'Shadow Color', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="text" class="nuclen-input wp-color-picker-field" name="nuclen_summary_shadow_color" id="nuclen_summary_shadow_color"
								value="<?php echo esc_attr( $settings['summary_shadow_color'] ); ?>">
					</div>
				</div>
				<div class="nuclen-row">
					<div class="nuclen-column nuclen-label-col">
						<label for="nuclen_summary_shadow_blur" class="nuclen-label"><?php esc_html_e( 'Shadow Blur (px)', 'nuclear-engagement' ); ?></label>
					</div>
					<div class="nuclen-column nuclen-input-col">
						<input type="number" class="nuclen-input" name="nuclen_summary_shadow_blur" id="nuclen_summary_shadow_blur"
								value="<?php echo esc_attr( $settings['summary_shadow_blur'] ); ?>" min="0" max="100">
					</div>
				</div>
			</div>
		</div>

		<!-- DISPLAY TAB -->
		<div id="display" class="nuclen-tab-content nuclen-section" style="display:none;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Number of Quiz Questions, Answers', 'nuclear-engagement' ); ?></h2>
			<p><?php esc_html_e( 'Choose how many questions and answers to display per quiz.', 'nuclear-engagement' ); ?></p>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="nuclen_questions_per_quiz" class="nuclen-label"><?php esc_html_e( 'Number of Questions per Quiz', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="number" class="nuclen-input" name="nuclen_questions_per_quiz" id="nuclen_questions_per_quiz"
							value="<?php echo esc_attr( $settings['questions_per_quiz'] ); ?>" min="3" max="10">
				</div>
			</div>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="nuclen_answers_per_question" class="nuclen-label"><?php esc_html_e( 'Number of Answers per Question', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="number" class="nuclen-input" name="nuclen_answers_per_question" id="nuclen_answers_per_question"
							value="<?php echo esc_attr( $settings['answers_per_question'] ); ?>" min="2" max="4">
				</div>
			</div>

			<h2 class="nuclen-subheading"><?php esc_html_e( 'Quiz Custom Text', 'nuclear-engagement' ); ?></h2>

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
					<label for="quiz_title" class="nuclen-label"><?php esc_html_e( 'Quiz Title', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="text" class="nuclen-input" name="quiz_title" id="quiz_title"
							value="<?php echo esc_attr( $settings['quiz_title'] ); ?>">
				</div>
			</div>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="summary_title" class="nuclen-label"><?php esc_html_e( 'Summary Title', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="text" class="nuclen-input" name="summary_title" id="summary_title"
							value="<?php echo esc_attr( $settings['summary_title'] ); ?>">
				</div>
			</div>

			<!-- Show Attribution -->
			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="nuclen_show_attribution" class="nuclen-label"><?php esc_html_e( 'Display Attribution Link', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="checkbox" name="show_attribution" id="nuclen_show_attribution" value="1"
							<?php checked( $settings['show_attribution'], true ); ?>>
				</div>
			</div>
		</div>

		<!-- OPT-IN TAB -->
		<div id="optin" class="nuclen-tab-content nuclen-section" style="display:none;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Email Opt-In Form', 'nuclear-engagement' ); ?></h2>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="enable_optin" class="nuclen-label"><?php esc_html_e( 'Enable Opt-In', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="checkbox" class="nuclen-checkbox" name="enable_optin" id="enable_optin" value="1"
							<?php checked( $settings['enable_optin'], true ); ?>>
				</div>
			</div>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="optin_webhook" class="nuclen-label"><?php esc_html_e( 'Webhook URL', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="url" class="nuclen-input" name="optin_webhook" id="optin_webhook"
							value="<?php echo esc_attr( $settings['optin_webhook'] ); ?>">
				</div>
			</div>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="optin_success_message" class="nuclen-label"><?php esc_html_e( 'Success Message', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="text" class="nuclen-input" name="optin_success_message" id="optin_success_message"
							value="<?php echo esc_attr( $settings['optin_success_message'] ); ?>">
				</div>
			</div>
		</div>

		<!-- GENERATION TAB -->
		<div id="generation" class="nuclen-tab-content nuclen-section" style="display:none;">
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Generation', 'nuclear-engagement' ); ?></h2>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="update_last_modified" class="nuclen-label"><?php esc_html_e( 'Update "Last Modified" date', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="checkbox" class="nuclen-checkbox" name="update_last_modified" id="update_last_modified" value="1"
							<?php checked( $settings['update_last_modified'], 1 ); ?>>
				</div>
			</div>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="auto_generate_quiz_on_publish" class="nuclen-label"><?php esc_html_e( 'Auto-generate Quiz on publish', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="checkbox" class="nuclen-checkbox" name="auto_generate_quiz_on_publish" id="auto_generate_quiz_on_publish" value="1"
							<?php checked( $settings['auto_generate_quiz_on_publish'], 1 ); ?>>
				</div>
			</div>

			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="auto_generate_summary_on_publish" class="nuclen-label"><?php esc_html_e( 'Auto-generate Summary on publish', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<input type="checkbox" class="nuclen-checkbox" name="auto_generate_summary_on_publish" id="auto_generate_summary_on_publish" value="1"
							<?php checked( $settings['auto_generate_summary_on_publish'], 1 ); ?>>
				</div>
			</div>

			<h2 class="nuclen-subheading"><?php esc_html_e( 'Allowed Post Types', 'nuclear-engagement' ); ?></h2>
			<div class="nuclen-form-group nuclen-row">
				<div class="nuclen-column nuclen-label-col">
					<label for="nuclen_generation_post_types" class="nuclen-label"><?php esc_html_e( 'Select Post Types', 'nuclear-engagement' ); ?></label>
				</div>
				<div class="nuclen-column nuclen-input-col">
					<?php
					$all_post_types   = get_post_types( array( 'public' => true ), 'objects' );
					$excluded         = array(
						'attachment',
						'revision',
						'nav_menu_item',
						'custom_css',
						'customize_changeset',
						'oembed_cache',
						'user_request',
						'wp_block',
						'wp_template',
						'wp_template_part',
					);
					$saved_post_types = $settings['generation_post_types'] ?? array( 'post' );
					echo '<select name="nuclen_generation_post_types[]" id="nuclen_generation_post_types" multiple style="height:6em;">';
					foreach ( $all_post_types as $pt_key => $pt_obj ) {
						if ( in_array( $pt_key, $excluded ) ) {
							continue;
						}
						echo '<option value="' . esc_attr( $pt_key ) . '" ' . selected( in_array( $pt_key, $saved_post_types ), true, false ) . '>'
							. esc_html( $pt_obj->labels->name ) . '</option>';
					}
					echo '</select>';
					?>
				</div>
			</div>
		</div>

		<!-- Save Button -->
		<div>
			<?php
			submit_button(
				esc_html__( 'Save Settings', 'nuclear-engagement' ),
				'primary nuclen-button nuclen-button-primary',
				'nuclen_save_settings'
			);
			?>
		</div>
	</form>
</div>
