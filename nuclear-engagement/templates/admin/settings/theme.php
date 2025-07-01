<?php
declare(strict_types=1);
// File: admin/partials/settings/theme.php
if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

use NuclearEngagement\Services\ThemeSettingsService;

$theme_service = new ThemeSettingsService();
$active_theme = $theme_service->get_active_theme();
$preset_themes = $theme_service->get_preset_themes();
$custom_themes = $theme_service->get_custom_themes();

/**
 * Theme tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- THEME TAB -->
<div id="theme" class="nuclen-tab-content nuclen-section" style="display:none;">
		<h2 class="nuclen-subheading"><?php esc_html_e( 'Theme', 'nuclear-engagement' ); ?></h2>
		<p><?php esc_html_e( 'Select a theme preset, or choose custom to override individual settings.', 'nuclear-engagement' ); ?></p>

		<!-- Preset selector -->
		<div class="nuclen-form-group">
			<?php foreach ($preset_themes as $theme): ?>
				<label>
					<input type="radio" name="nuclen_theme" value="<?php echo esc_attr($theme->id); ?>" 
						<?php checked($active_theme && $active_theme->id === $theme->id); ?> />
					<?php 
					if ($theme->name === 'light') {
						esc_html_e('Light Theme (clean and bright)', 'nuclear-engagement');
					} elseif ($theme->name === 'dark') {
						esc_html_e('Dark Theme (white on dark)', 'nuclear-engagement');
					} else {
						echo esc_html(ucfirst($theme->name) . ' Theme');
					}
					?>
				</label><br>
			<?php endforeach; ?>
			
			<?php if (!empty($custom_themes)): ?>
				<?php foreach ($custom_themes as $theme): ?>
					<label>
						<input type="radio" name="nuclen_theme" value="<?php echo esc_attr($theme->id); ?>" 
							<?php checked($active_theme && $active_theme->id === $theme->id); ?> />
						<?php echo esc_html($theme->name); ?> (Custom)
						<span style="margin-left: 10px;">
							<a href="#" class="nuclen-edit-custom-theme" data-theme-id="<?php echo esc_attr($theme->id); ?>">
								<?php esc_html_e('Edit', 'nuclear-engagement'); ?>
							</a>
							<?php if ($theme->name !== 'custom-migrated'): ?>
								| <a href="#" class="nuclen-delete-custom-theme" data-theme-id="<?php echo esc_attr($theme->id); ?>">
									<?php esc_html_e('Delete', 'nuclear-engagement'); ?>
								</a>
							<?php endif; ?>
						</span>
					</label><br>
				<?php endforeach; ?>
			<?php endif; ?>
			
			<label>
				<input type="radio" name="nuclen_theme" value="new-custom" 
					<?php checked(!$active_theme || ($active_theme && !in_array($active_theme->id, array_merge(array_column($preset_themes, 'id'), array_column($custom_themes, 'id'))))); ?> />
				<?php esc_html_e('Create New Custom Theme', 'nuclear-engagement'); ?>
			</label><br>
			
			<label>
				<input type="radio" name="nuclen_theme" value="none" 
					<?php checked($active_theme === null); ?> />
				<?php esc_html_e('No Theme (only base CSS)', 'nuclear-engagement'); ?>
			</label>
		</div>

		<?php 
		$show_custom_section = false;
		if ($active_theme && $active_theme->type === 'custom') {
			$show_custom_section = true;
		}
		$custom_theme_class = $show_custom_section ? '' : 'nuclen-hidden'; 
		?>
		<div id="nuclen-custom-theme-section" class="nuclen-form-group <?php echo esc_attr( $custom_theme_class ); ?>" style="margin-top:20px;">
			<h3><?php esc_html_e('Custom Theme Settings', 'nuclear-engagement'); ?></h3>
			<div id="nuclen-theme-editor">
				<?php if ($show_custom_section && $active_theme): ?>
					<input type="hidden" id="nuclen-editing-theme-id" value="<?php echo esc_attr($active_theme->id); ?>" />
					<div class="nuclen-theme-editor-wrapper">
						<?php
						// Load the theme configuration into legacy format for existing form components
						$legacy_config = $theme_service->get_theme_config_for_legacy($active_theme->name);
						$settings = array_merge($settings, $legacy_config);
						
						$theme_dir = plugin_dir_path( __FILE__ ) . 'theme/';
						require $theme_dir . 'quiz-container.php';
						require $theme_dir . 'quiz-buttons.php';
						require $theme_dir . 'progress-bar.php';
						require $theme_dir . 'summary-container.php';
						require $theme_dir . 'toc-container.php';
						?>
						
						<div class="nuclen-custom-theme-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
							<button type="button" class="button" id="nuclen-duplicate-theme" data-theme-id="<?php echo esc_attr($active_theme->id); ?>">
								<?php esc_html_e('Duplicate Theme', 'nuclear-engagement'); ?>
							</button>
							<button type="button" class="button" id="nuclen-export-theme" data-theme-id="<?php echo esc_attr($active_theme->id); ?>">
								<?php esc_html_e('Export Theme', 'nuclear-engagement'); ?>
							</button>
							<button type="button" class="button" id="nuclen-import-theme">
								<?php esc_html_e('Import Theme', 'nuclear-engagement'); ?>
							</button>
							<input type="file" id="nuclen-theme-import-file" accept=".json" style="display: none;" />
						</div>
					</div>
				<?php else: ?>
					<p><?php esc_html_e('Select "Create New Custom Theme" to customize theme settings.', 'nuclear-engagement'); ?></p>
				<?php endif; ?>
			</div>
		</div><!-- /#nuclen-custom-theme-section -->

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const themeRadios = document.querySelectorAll('input[name="nuclen_theme"]');
			const customSection = document.getElementById('nuclen-custom-theme-section');
			
			themeRadios.forEach(radio => {
				radio.addEventListener('change', function() {
					if (this.value === 'new-custom' || (this.dataset.isCustom === 'true')) {
						customSection.classList.remove('nuclen-hidden');
					} else {
						customSection.classList.add('nuclen-hidden');
					}
				});
			});

			// Mark custom themes for JS
			<?php foreach ($custom_themes as $theme): ?>
			const radio<?php echo $theme->id; ?> = document.querySelector('input[value="<?php echo $theme->id; ?>"]');
			if (radio<?php echo $theme->id; ?>) {
				radio<?php echo $theme->id; ?>.dataset.isCustom = 'true';
			}
			<?php endforeach; ?>
		});
		</script>
</div><!-- /#theme -->
