<?php
declare(strict_types=1);
// File: admin/partials/settings/display/toc.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- ────────── Table of Contents settings ────────── -->
<h2 class="nuclen-subheading"><?php esc_html_e( 'Table of Contents', 'nuclear-engagement' ); ?></h2>

<!-- Heading levels -->
<h4><?php esc_html_e( 'Heading Levels', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-form-group nuclen-row">
    <div class="nuclen-column nuclen-label-col">
        <label class="nuclen-label"><?php esc_html_e( 'Include in TOC', 'nuclear-engagement' ); ?></label>
    </div>
    <div class="nuclen-column nuclen-input-col">
        <?php
        $selected_levels = isset( $settings['toc_heading_levels'] ) ? (array) $settings['toc_heading_levels'] : range( 2, 6 );
        $selected_levels = array_map( 'intval', $selected_levels );
        $selected_levels = array_filter( $selected_levels, static fn ( $l ) => $l >= 2 && $l <= 6 );
        if ( empty( $selected_levels ) ) {
            $selected_levels = range( 2, 6 );
        }
        for ( $i = 2; $i <= 6; $i++ ) :
            $checked = in_array( $i, $selected_levels, true ) ? 'checked="checked"' : '';
        ?>
            <label style="display:inline-block;margin-right:15px;margin-bottom:5px;">
                <input type="checkbox"
                       name="nuclear_engagement_settings[toc_heading_levels][]"
                       value="<?php echo esc_attr( $i ); ?>"
                       <?php echo $checked; ?>>
                <?php printf( 'H%d', $i ); ?>
            </label>
        <?php endfor; ?>
        <p class="description" style="margin-top:5px;">
            <?php esc_html_e( 'Select which heading levels to include in the Table of Contents.', 'nuclear-engagement' ); ?>
        </p>
    </div>
</div>

<!-- Toggle button -->
<h4><?php esc_html_e( 'Display Options', 'nuclear-engagement' ); ?></h4>
<div class="nuclen-form-group nuclen-row">
    <div class="nuclen-column nuclen-label-col">
        <label for="nuclen_toc_show_toggle" class="nuclen-label"><?php esc_html_e( 'Show Toggle Button', 'nuclear-engagement' ); ?></label>
    </div>
    <div class="nuclen-column nuclen-input-col">
        <label class="nuclen-switch">
            <input type="checkbox" name="nuclen_toc_show_toggle" id="nuclen_toc_show_toggle" value="1" <?php checked( ! empty( $settings['toc_show_toggle'] ) ); ?>>
            <span class="nuclen-slider round"></span>
        </label>
        <p class="description"><?php esc_html_e( 'When enabled, a toggle button will be shown to show/hide the Table of Contents.', 'nuclear-engagement' ); ?></p>
    </div>
</div>

<!-- Show TOC content by default -->
<div class="nuclen-form-group nuclen-row">
    <div class="nuclen-column nuclen-label-col">
        <label for="nuclen_toc_show_content" class="nuclen-label"><?php esc_html_e( 'Show TOC Content by Default', 'nuclear-engagement' ); ?></label>
    </div>
    <div class="nuclen-column nuclen-input-col">
        <label class="nuclen-switch">
            <input type="checkbox" name="nuclen_toc_show_content" id="nuclen_toc_show_content" value="1" <?php checked( empty( $settings['toc_show_toggle'] ) || ! empty( $settings['toc_show_content'] ) ); ?><?php echo empty( $settings['toc_show_toggle'] ) ? ' disabled' : ''; ?>>
            <span class="nuclen-slider round"></span>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, the Table of Contents content will be visible by default.', 'nuclear-engagement' ); ?>
            <?php if ( empty( $settings['toc_show_toggle'] ) ) : ?>
            <br><em><?php esc_html_e( 'This option is only available when the toggle button is enabled.', 'nuclear-engagement' ); ?></em>
            <?php endif; ?>
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleEl = document.getElementById('nuclen_toc_show_toggle');
    const showContentEl = document.getElementById('nuclen_toc_show_content');
    if (!toggleEl || !showContentEl) {
        return;
    }

    const updateTocToggleState = () => {
        const showToggle = toggleEl.checked;
        showContentEl.disabled = !showToggle;
        if (!showToggle) {
            showContentEl.checked = true;
        }
    };

    toggleEl.addEventListener('change', updateTocToggleState);
    updateTocToggleState();
});
</script>
