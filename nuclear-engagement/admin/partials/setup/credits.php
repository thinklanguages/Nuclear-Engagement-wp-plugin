<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Setup - Credits section (only when fully set up)
 *
 * Variables:
 *   – $app_setup   (array)
 *   – $fully_setup (bool)
 *
 * @package NuclearEngagement\Admin
 */
?>
<?php if ( $fully_setup ) : ?>
	<h2 style="margin-top:30px;"><?php esc_html_e( 'Your Credits', 'nuclear-engagement' ); ?></h2>
	<p id="nuclen-setup-credits-msg"><?php esc_html_e( 'Loading credits…', 'nuclear-engagement' ); ?></p>
	<script>
	document.addEventListener('DOMContentLoaded', async () => {
		const msgEl = document.getElementById('nuclen-setup-credits-msg');
		if (!msgEl) return;

		try {
			const formData = new FormData();
			formData.append('action', 'nuclen_fetch_app_updates');
			formData.append('security', '<?php echo esc_js( wp_create_nonce( 'nuclen_admin_ajax_nonce' ) ); ?>');

			const resp = await fetch("<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>", { method:'POST', body:formData });
			const data = await resp.json();
			if (!data.success) throw new Error(data.data?.message || 'Failed');

			const remote = data.data;
			if (typeof remote.remaining_credits !== 'undefined') {
				msgEl.textContent = '<?php echo esc_js( __( 'You have', 'nuclear-engagement' ) ); ?> '
					+ remote.remaining_credits
					+ ' <?php echo esc_js( __( 'credits left.', 'nuclear-engagement' ) ); ?>';
			} else {
				msgEl.textContent = '<?php echo esc_js( __( 'No credits info returned.', 'nuclear-engagement' ) ); ?>';
			}
		} catch (err) {
			msgEl.textContent = 'Error: ' + err;
		}
	});
	</script>
<?php endif; ?>
