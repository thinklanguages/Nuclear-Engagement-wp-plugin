<?php
// File: admin/partials/settings/display/titles.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2 class="nuclen-subheading"><?php esc_html_e( 'Section Titles', 'nuclear-engagement' ); ?></h2>
<div class="nuclen-form-group nuclen-row">
    <div class="nuclen-column nuclen-label-col">
        <label for="quiz_title" class="nuclen-label"><?php esc_html_e( 'Quiz Title', 'nuclear-engagement' ); ?>
            <span nuclen-tooltip="<?php esc_attr_e( 'Examples: \"Test your knowledge\", \"Can you pass this test?\"', 'nuclear-engagement' ); ?>">🛈</span>
        </label>
    </div>
    <div class="nuclen-column nuclen-input-col">
        <input type="text" class="nuclen-input" name="quiz_title" id="quiz_title" value="<?php echo esc_attr( $settings['quiz_title'] ); ?>">
    </div>
</div>
<div class="nuclen-form-group nuclen-row">
    <div class="nuclen-column nuclen-label-col">
        <label for="summary_title" class="nuclen-label"><?php esc_html_e( 'Summary Title', 'nuclear-engagement' ); ?>
            <span nuclen-tooltip="<?php esc_attr_e( 'Examples: \"Summary\", \"Key Concepts\".', 'nuclear-engagement' ); ?>">🛈</span>
        </label>
    </div>
    <div class="nuclen-column nuclen-input-col">
        <input type="text" class="nuclen-input" name="summary_title" id="summary_title" value="<?php echo esc_attr( $settings['summary_title'] ); ?>">
    </div>
</div>
<!-- **TOC title — name/id kept as nuclen_toc_title so it maps & persists** -->
<div class="nuclen-form-group nuclen-row">
    <div class="nuclen-column nuclen-label-col">
        <label for="nuclen_toc_title" class="nuclen-label"><?php esc_html_e( 'TOC Title', 'nuclear-engagement' ); ?></label>
    </div>
    <div class="nuclen-column nuclen-input-col">
        <input type="text" class="nuclen-input" name="nuclen_toc_title" id="nuclen_toc_title" value="<?php echo esc_attr( $settings['toc_title'] ); ?>">
    </div>
</div>
