<?php
declare(strict_types=1);
// File: admin/partials/settings/display/attribution.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- Attribution -->
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
