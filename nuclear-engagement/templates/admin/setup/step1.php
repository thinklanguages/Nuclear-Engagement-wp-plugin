<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Setup - Step 1 (Gold Code) – both states
 *
 * Variables available:
 *   – $app_setup (array)
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- ───── STEP 1 – Gold Code ───── -->
<?php if ( empty( $app_setup['connected'] ) ) : ?>
	<div id="nuclen-setup-step-1" class="nuclen-section">
		<span class="dashicons dashicons-admin-plugins"></span>
		<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 1 – Authorise your site', 'nuclear-engagement' ); ?></h2>
		<p class="nuclen-paragraph">
			<?php esc_html_e( 'Enter your Gold Code (API key) to connect this site to Nuclear Engagement.', 'nuclear-engagement' ); ?>
		</p>
		<p class="nuclen-paragraph">
			<?php
			printf(
				wp_kses(
					/* translators: %s: link */
					__( 'Need a Gold Code? Create a free account %s.', 'nuclear-engagement' ),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
						),
					)
				),
				'<a href="https://app.nuclearengagement.com/api-keys" target="_blank"> '
				. esc_html__( 'here', 'nuclear-engagement' )
				. '</a>'
			);
			?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'nuclen_connect_app_action', 'nuclen_connect_app_nonce' ); ?>
			<input type="hidden" name="action" value="nuclen_connect_app">

			<label for="nuclen_api_key" class="nuclen-label"><?php esc_html_e( 'Gold Code', 'nuclear-engagement' ); ?></label><br>
			<input type="text" id="nuclen_api_key" name="nuclen_api_key" style="width:350px;"><br><br>

			<button type="submit" class="button button-primary nuclen-button nuclen-button-primary">
				<?php esc_html_e( 'Authorise Site', 'nuclear-engagement' ); ?>
			</button>
		</form>
	</div>
<?php else : ?>
	<div id="nuclen-setup-step-1" class="nuclen-section">
		<span class="dashicons dashicons-plugins-checked"></span>
		<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 1 complete – Site authorised', 'nuclear-engagement' ); ?></h2>
		<p class="nuclen-paragraph" style="color:green;"><?php esc_html_e( 'Your site is connected.', 'nuclear-engagement' ); ?></p>
				<?php $short_key = isset( $app_setup['api_key'] ) ? substr( $app_setup['api_key'], 0, 6 ) : ''; ?>
		<p class="nuclen-paragraph">
			<?php esc_html_e( 'Current Gold Code:', 'nuclear-engagement' ); ?>
			<input type="text" readonly style="width:80px;color:#888;" value="<?php echo esc_attr( $short_key ); ?>">
		</p>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<form method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					onsubmit="return confirm('<?php echo esc_js( __( 'Reset Gold Code?', 'nuclear-engagement' ) ); ?>');"
					style="margin-top:10px;">
				<?php wp_nonce_field( 'nuclen_reset_api_key_action', 'nuclen_reset_api_key_nonce' ); ?>
				<input type="hidden" name="action" value="nuclen_reset_api_key">
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Reset Gold Code', 'nuclear-engagement' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
<?php endif; ?>
