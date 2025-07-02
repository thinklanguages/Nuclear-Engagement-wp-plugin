<?php
declare(strict_types=1);
// File: admin/partials/settings/theme.php
if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

/**
 * Theme tab - Simplified version
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- THEME TAB -->
<div id="theme" class="nuclen-tab-content nuclen-section" style="display:none;">
		<h2 class="nuclen-subheading"><?php esc_html_e( 'Theme', 'nuclear-engagement' ); ?></h2>
		<p><?php esc_html_e( 'Customize the appearance of your quizzes, summaries, and table of contents.', 'nuclear-engagement' ); ?></p>

		<!-- Theme Selection -->
		<div class="nuclen-form-group">
			<label>
				<input type="radio" name="nuclen_theme" value="light" <?php checked( empty($settings['theme']) || $settings['theme'] === 'light' || $settings['theme'] === 'bright' ); ?> />
				<?php esc_html_e( 'Light Theme', 'nuclear-engagement' ); ?>
			</label><br>
			
			<label>
				<input type="radio" name="nuclen_theme" value="dark" <?php checked( isset($settings['theme']) && $settings['theme'] === 'dark' ); ?> />
				<?php esc_html_e( 'Dark Theme', 'nuclear-engagement' ); ?>
			</label><br>
			
			<label>
				<input type="radio" name="nuclen_theme" value="custom" <?php checked( isset($settings['theme']) && $settings['theme'] === 'custom' ); ?> />
				<?php esc_html_e( 'Custom Theme', 'nuclear-engagement' ); ?>
			</label>
		</div>

		<!-- Custom Theme Section -->
		<?php $custom_theme_class = ( isset($settings['theme']) && $settings['theme'] === 'custom' ) ? '' : 'nuclen-hidden'; ?>
		<div id="nuclen-custom-theme-section" class="nuclen-form-group <?php echo esc_attr( $custom_theme_class ); ?>" style="margin-top:20px;">
			<h3><?php esc_html_e( 'Custom Theme Settings', 'nuclear-engagement' ); ?></h3>
			
			<?php
			// Include theme customization files
			$theme_dir = plugin_dir_path( __FILE__ ) . 'theme/';
			if ( file_exists( $theme_dir . 'quiz-container.php' ) ) {
				require $theme_dir . 'quiz-container.php';
			}
			if ( file_exists( $theme_dir . 'quiz-buttons.php' ) ) {
				require $theme_dir . 'quiz-buttons.php';
			}
			if ( file_exists( $theme_dir . 'progress-bar.php' ) ) {
				require $theme_dir . 'progress-bar.php';
			}
			if ( file_exists( $theme_dir . 'summary-container.php' ) ) {
				require $theme_dir . 'summary-container.php';
			}
			if ( file_exists( $theme_dir . 'toc-container.php' ) ) {
				require $theme_dir . 'toc-container.php';
			}
			?>
		</div>

</div><!-- /#theme -->