<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Setup - Step 2 (App Password) – both states
 *
 * Variables:
 *   – $app_setup (array)
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- ───── STEP 2 – Plugin App Password ───── -->
<?php if ( ! empty( $app_setup['connected'] ) ) : ?>
	<div id="nuclen-setup-step-2" class="nuclen-section" style="margin-top:30px;">
		<?php if ( empty( $app_setup['wp_app_pass_created'] ) ) : ?>
			<span class="dashicons dashicons-admin-plugins"></span>
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 2 – Allow data-push', 'nuclear-engagement' ); ?></h2>
			<p class="nuclen-paragraph">
				<?php esc_html_e( 'Click the button below to let Nuclear Engagement push generated content into WordPress. A secure password will be created automatically – you don’t have to copy or store it anywhere.', 'nuclear-engagement' ); ?>
			</p>

			<form method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					onsubmit="this.querySelector('button').disabled = true;">
				<?php wp_nonce_field( 'nuclen_generate_app_password_action', 'nuclen_generate_app_password_nonce' ); ?>
				<input type="hidden" name="action" value="nuclen_generate_app_password">
				<button type="submit" class="button button-primary nuclen-button nuclen-button-primary">
					<?php esc_html_e( 'Allow', 'nuclear-engagement' ); ?>
				</button>
			</form>
		<?php else : ?>
			<span class="dashicons dashicons-plugins-checked"></span>
			<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 2 complete – Access granted', 'nuclear-engagement' ); ?></h2>
			<p class="nuclen-paragraph" style="color:green;">
				<?php esc_html_e( 'Nuclear Engagement can now push content into this site.', 'nuclear-engagement' ); ?>
			</p>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<form method="post"
						action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						onsubmit="return confirm('<?php echo esc_js( __( 'Revoke access?', 'nuclear-engagement' ) ); ?>');"
						style="margin-top:10px;">
					<?php wp_nonce_field( 'nuclen_reset_wp_app_action', 'nuclen_reset_wp_app_nonce' ); ?>
					<input type="hidden" name="action" value="nuclen_reset_wp_app_connection">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Revoke Access', 'nuclear-engagement' ); ?></button>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>
