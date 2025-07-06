<!-- Display positions -->
<!-- SUMMARY -->
<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
				<label for="nuclen_display_summary" class="nuclen-label"><?php esc_html_e( 'Display Summary', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
				<select name="nuclen_display_summary" id="nuclen_display_summary" class="nuclen-input">
						<option value="manual" <?php selected( $settings['display_summary'], 'manual' ); ?>><?php esc_html_e( 'Shortcode / Block', 'nuclear-engagement' ); ?></option>
						<option value="before" <?php selected( $settings['display_summary'], 'before' ); ?>><?php esc_html_e( 'Before post content', 'nuclear-engagement' ); ?></option>
						<option value="after"  <?php selected( $settings['display_summary'], 'after' ); ?>><?php esc_html_e( 'After post content', 'nuclear-engagement' ); ?></option>
				</select>
				<p class="description"><?php printf( wp_kses( __( 'Shortcode: <b>%s</b>.', 'nuclear-engagement' ), array( 'b' => array() ) ), '[nuclear_engagement_summary]' ); ?></p>
		</div>
</div>

<!-- QUIZ -->
<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
				<label for="nuclen_display_quiz" class="nuclen-label"><?php esc_html_e( 'Display Quiz', 'nuclear-engagement' ); ?></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
				<select name="nuclen_display_quiz" id="nuclen_display_quiz" class="nuclen-input">
						<option value="manual" <?php selected( $settings['display_quiz'], 'manual' ); ?>><?php esc_html_e( 'Shortcode / Block', 'nuclear-engagement' ); ?></option>
						<option value="before" <?php selected( $settings['display_quiz'], 'before' ); ?>><?php esc_html_e( 'Before post content', 'nuclear-engagement' ); ?></option>
						<option value="after"  <?php selected( $settings['display_quiz'], 'after' ); ?>><?php esc_html_e( 'After post content', 'nuclear-engagement' ); ?></option>
				</select>
				<p class="description"><?php printf( wp_kses( __( 'Shortcode: <b>%s</b>.', 'nuclear-engagement' ), array( 'b' => array() ) ), '[nuclear_engagement_quiz]' ); ?></p>
		</div>
</div>

<!-- TOC -->
<div class="nuclen-form-group nuclen-row">
		<div class="nuclen-column nuclen-label-col">
				<label for="nuclen_display_toc" class="nuclen-label"><?php esc_html_e( 'Display Table of Contents', 'nuclear-engagement' ); ?> <span class="nuclen-tooltip" data-tooltip="<?php esc_html_e( 'The TOC is compiled from post content for free. There is no need to generate it with AI.', 'nuclear-engagement' ); ?>">?</span></label>
		</div>
		<div class="nuclen-column nuclen-input-col">
				<select name="nuclen_display_toc" id="nuclen_display_toc" class="nuclen-input">
						<option value="manual" <?php selected( $settings['display_toc'] ?? 'manual', 'manual' ); ?>><?php esc_html_e( 'Shortcode / Block', 'nuclear-engagement' ); ?></option>
						<option value="before" <?php selected( $settings['display_toc'] ?? 'manual', 'before' ); ?>><?php esc_html_e( 'Before post content', 'nuclear-engagement' ); ?></option>
						<option value="after"  <?php selected( $settings['display_toc'] ?? 'manual', 'after' ); ?>><?php esc_html_e( 'After post content', 'nuclear-engagement' ); ?></option>
				</select>
				<p class="description"><?php printf( wp_kses( __( 'Shortcode: <b>%s</b>.', 'nuclear-engagement' ), array( 'b' => array() ) ), '[nuclear_engagement_toc]' ); ?></p>
		</div>
</div>
