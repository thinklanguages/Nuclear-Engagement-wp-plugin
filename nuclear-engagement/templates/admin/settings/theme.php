<?php
/**
 * theme.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

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
				<input type="radio" name="nuclen_theme" value="light" <?php checked( empty( $settings['theme'] ) || $settings['theme'] === 'light' || $settings['theme'] === 'bright' ); ?> />
				<?php esc_html_e( 'Light Theme', 'nuclear-engagement' ); ?>
			</label><br>
			
			<label>
				<input type="radio" name="nuclen_theme" value="dark" <?php checked( isset( $settings['theme'] ) && $settings['theme'] === 'dark' ); ?> />
				<?php esc_html_e( 'Dark Theme', 'nuclear-engagement' ); ?>
			</label><br>
			
			<label>
				<input type="radio" name="nuclen_theme" value="custom" <?php checked( isset( $settings['theme'] ) && $settings['theme'] === 'custom' ); ?> />
				<?php esc_html_e( 'Custom Theme', 'nuclear-engagement' ); ?>
			</label>
		</div>

		<!-- Custom Theme Section -->
		<?php $custom_theme_class = ( isset( $settings['theme'] ) && $settings['theme'] === 'custom' ) ? '' : 'nuclen-hidden'; ?>
		<div id="nuclen-custom-theme-section" class="nuclen-form-group <?php echo esc_attr( $custom_theme_class ); ?>" style="margin-top:20px;">
			<h3><?php esc_html_e( 'Custom Theme Settings', 'nuclear-engagement' ); ?></h3>
			
			<?php
			// Include theme customization files - whitelist allowed files for security.
			$theme_dir     = realpath( plugin_dir_path( __FILE__ ) . 'theme/' );
			$allowed_files = array(
				'quiz-container.php',
				'quiz-buttons.php',
				'progress-bar.php',
				'summary-container.php',
				'toc-container.php',
			);

			foreach ( $allowed_files as $file ) {
				$file_path = $theme_dir . DIRECTORY_SEPARATOR . $file;
				// Verify path is within theme directory and file exists.
				if ( $theme_dir &&
					strpos( realpath( $file_path ), $theme_dir ) === 0 &&
					file_exists( $file_path ) ) {
					require $file_path;
				}
			}
			?>
		</div>

</div><!-- /#theme -->