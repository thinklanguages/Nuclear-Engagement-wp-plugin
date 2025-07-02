<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

use NuclearEngagement\Modules\Summary\Summary_Service;

wp_nonce_field( 'nuclen_summary_data_nonce', 'nuclen_summary_data_nonce' );
?>
<div><label>
		<input type="checkbox" name="<?php echo esc_attr( Summary_Service::PROTECTED_KEY ); ?>" value="1" <?php checked( $summary_protected, 1 ); ?> />
	<?php esc_html_e( 'Protected?', 'nuclear-engagement' ); ?>
	<span nuclen-tooltip="<?php esc_attr_e( 'Tick this box and save post to prevent overwriting during bulk generation.', 'nuclear-engagement' ); ?>">🛈</span>
</label></div>
<div>
	<button type="button"
			id="nuclen-generate-summary-single"
			class="button nuclen-generate-single"
			data-post-id="<?php echo esc_attr( $post->ID ); ?>"
			data-workflow="summary">
		<?php esc_html_e( 'Generate Summary with AI', 'nuclear-engagement' ); ?>
	</button>
	<span nuclen-tooltip="<?php esc_attr_e( '(re)Generate. Data will be stored automatically (no need to save post).', 'nuclear-engagement' ); ?>">🛈</span>
</div>
<p><strong><?php esc_html_e( 'Date', 'nuclear-engagement' ); ?></strong><br>
	<input type="text" name="nuclen_summary_data[date]" value="<?php echo esc_attr( $date ); ?>" readonly class="nuclen-meta-date-input" />
</p>
<p><strong><?php esc_html_e( 'Summary', 'nuclear-engagement' ); ?></strong><br>
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
