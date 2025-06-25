<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_nonce_field( 'nuclen_summary_data_nonce', 'nuclen_summary_data_nonce' );
?>
<div><label>
    <input type="checkbox" name="nuclen_summary_protected" value="1" <?php checked( $summary_protected, 1 ); ?> />
    Protected? <span nuclen-tooltip="Tick this box and save post to prevent overwriting during bulk generation.">🛈</span>
</label></div>
<div>
    <button type="button"
            id="nuclen-generate-summary-single"
            class="button nuclen-generate-single"
            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
            data-workflow="summary">
        Generate Summary with AI
    </button>
    <span nuclen-tooltip="(re)Generate. Data will be stored automatically (no need to save post).">🛈</span>
</div>
<p><strong>Date</strong><br>
    <input type="text" name="nuclen_summary_data[date]" value="<?php echo esc_attr( $date ); ?>" readonly class="nuclen-meta-date-input" />
</p>
<p><strong>Summary</strong><br>
<?php
wp_editor(
    $summary,
    'nuclen_summary_data_summary',
    array(
        'textarea_name' => 'nuclen_summary_data[summary]',
        'textarea_rows' => 5,
        'media_buttons' => false,
        'teeny'         => true,
    )
);
?>
</p>
