<?php
declare(strict_types=1);
// File: admin/partials/settings/theme.php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
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
                <label><input type="radio" name="nuclen_theme" value="bright" <?php checked( $settings['theme'], 'bright' ); ?> /> <?php esc_html_e( 'Bright Theme (black on white)', 'nuclear-engagement' ); ?></label><br>
                <label><input type="radio" name="nuclen_theme" value="dark"   <?php checked( $settings['theme'], 'dark'   ); ?> /> <?php esc_html_e( 'Dark Theme (white on black)',   'nuclear-engagement' ); ?></label><br>
                <label><input type="radio" name="nuclen_theme" value="custom" <?php checked( $settings['theme'], 'custom' ); ?> /> <?php esc_html_e( 'Custom',                       'nuclear-engagement' ); ?></label><br>
                <label><input type="radio" name="nuclen_theme" value="none"   <?php checked( $settings['theme'], 'none'   ); ?> /> <?php esc_html_e( 'No Theme (only base CSS)',        'nuclear-engagement' ); ?></label>
        </div>

        <?php $custom_theme_class = ( $settings['theme'] === 'custom' ) ? '' : 'nuclen-hidden'; ?>
        <div id="nuclen-custom-theme-section" class="nuclen-form-group <?php echo esc_attr( $custom_theme_class ); ?>" style="margin-top:20px;">
<?php
                $theme_dir = plugin_dir_path( __FILE__ ) . 'theme/';
                require $theme_dir . 'quiz-container.php';
                require $theme_dir . 'quiz-buttons.php';
                require $theme_dir . 'progress-bar.php';
                require $theme_dir . 'summary-container.php';
                require $theme_dir . 'toc-container.php';
?>
        </div><!-- /#nuclen-custom-theme-section -->
</div><!-- /#theme -->
