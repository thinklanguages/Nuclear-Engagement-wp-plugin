<?php
// File: admin/partials/settings/theme/quiz-container.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- ─────────── Quiz container ─────────── -->
<h3 class="nuclen-subheading"><?php esc_html_e( 'Quiz Container', 'nuclear-engagement' ); ?></h3>

<h4><?php esc_html_e( 'Font & Background', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_font_size" class="nuclen-label"><?php esc_html_e( 'Quiz Font Size (px)', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_font_size" id="nuclen_font_size" value="<?php echo esc_attr( $settings['font_size'] ); ?>" min="10" max="50"></div>
</div>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_font_color" class="nuclen-label"><?php esc_html_e( 'Quiz Font Color', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_font_color" id="nuclen_font_color" value="<?php echo esc_attr( $settings['font_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_bg_color" class="nuclen-label"><?php esc_html_e( 'Quiz Background Color', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_bg_color" id="nuclen_bg_color" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></div>
</div>

<h4><?php esc_html_e( 'Border Lines', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_border_color" class="nuclen-label"><?php esc_html_e( 'Quiz Border Color', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_quiz_border_color" id="nuclen_quiz_border_color" value="<?php echo esc_attr( $settings['quiz_border_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_border_style" class="nuclen-label"><?php esc_html_e( 'Quiz Border Style', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col">
        <?php $styles = array( 'none', 'solid', 'dashed', 'dotted', 'double' ); ?>
        <select name="nuclen_quiz_border_style" id="nuclen_quiz_border_style" class="nuclen-input">
            <?php foreach ( $styles as $s ) : ?>
                <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $settings['quiz_border_style'], $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_border_width" class="nuclen-label"><?php esc_html_e( 'Quiz Border Width (px)', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_border_width" id="nuclen_quiz_border_width" value="<?php echo esc_attr( $settings['quiz_border_width'] ); ?>" min="0" max="10"></div>
</div>

<h4><?php esc_html_e( 'Border Radius &amp; Shadow', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_border_radius" class="nuclen-label"><?php esc_html_e( 'Quiz Border Radius (px)', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_border_radius" id="nuclen_quiz_border_radius" value="<?php echo esc_attr( $settings['quiz_border_radius'] ); ?>" min="0" max="100"></div>
</div>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_shadow_color" class="nuclen-label"><?php esc_html_e( 'Quiz Shadow Color', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col"><input type="color" class="nuclen-input" name="nuclen_quiz_shadow_color" id="nuclen_quiz_shadow_color" value="<?php echo esc_attr( $settings['quiz_shadow_color'] ); ?>"></div>
</div>
<div class="nuclen-row">
    <div class="nuclen-column nuclen-label-col"><label for="nuclen_quiz_shadow_blur" class="nuclen-label"><?php esc_html_e( 'Quiz Shadow Blur (px)', 'nuclear-engagement' ); ?></label></div>
    <div class="nuclen-column nuclen-input-col"><input type="number" class="nuclen-input" name="nuclen_quiz_shadow_blur" id="nuclen_quiz_shadow_blur" value="<?php echo esc_attr( $settings['quiz_shadow_blur'] ); ?>" min="0" max="100"></div>
</div>
