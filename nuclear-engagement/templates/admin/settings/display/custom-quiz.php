<?php
declare(strict_types=1);
// File: admin/partials/settings/display/custom-quiz.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
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
