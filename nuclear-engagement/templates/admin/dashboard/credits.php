<?php
/**
 * credits.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
// File: admin/partials/dashboard/credits.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( $fully_setup ) : // Only show credits if plugin setup is complete ?>
	<!-- Show the user’s current credits -->
	<h2 style="margin-top:30px;"><?php esc_html_e( 'Your Credits', 'nuclear-engagement' ); ?></h2>
	<p id="nuclen-credits-dashboard-msg"><?php esc_html_e( 'Loading your credits...', 'nuclear-engagement' ); ?></p>
	<script>
	document.addEventListener('DOMContentLoaded', async () => {
		const msgEl = document.getElementById('nuclen-credits-dashboard-msg');
		if (!msgEl) return;
		try {
		// We'll reuse the same "nuclen_fetch_app_updates" action with no generation_id.
		const formData = new FormData();
		formData.append('action', 'nuclen_fetch_app_updates');
		formData.append('security', '<?php echo esc_js( wp_create_nonce( 'nuclen_admin_ajax_nonce' ) ); ?>');
		// We do not append generation_id => let the SaaS interpret it as "just return credits".
		const resp = await fetch("<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>", {
			method: 'POST',
			body: formData
		});
		const data = await resp.json();
		if (!data.success) {
			throw new Error(data.data?.message || 'Failed to fetch credits');
		}
		const remoteData = data.data;
		if (remoteData && typeof remoteData.remaining_credits !== 'undefined') {
			msgEl.textContent = '<?php echo esc_js( __( 'You have', 'nuclear-engagement' ) ); ?> '
			+ remoteData.remaining_credits
			+ ' <?php echo esc_js( __( 'credits left.', 'nuclear-engagement' ) ); ?>';
		} else {
			msgEl.textContent = '<?php echo esc_js( __( 'No credits info returned.', 'nuclear-engagement' ) ); ?>';
		}
		} catch (err) {
		msgEl.textContent = 'Error: ' + err;
		}
	});
	</script>
<?php else : ?>
	<!-- Credits hidden: user has not completed plugin setup -->
<?php endif; ?>
