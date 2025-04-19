<?php
/**
 * File: admin/Setup.php
 *
 * Renders the Setup page for Nuclear Engagement.
 * Step 1 – enter Gold Code  Step 2 – generate the plugin App Password.
 *
 * This version fixes PHP warnings caused by undefined array keys when the
 * `nuclear_engagement_setup` option was previously stored in an unexpected
 * format.  All array accesses now use `empty()`/`! empty()` or `isset()`, and
 * we normalise the option with `wp_parse_args()` so the needed keys are always
 * present.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/SetupHandlersTrait.php';

class Setup {

	use SetupHandlersTrait;

	/** @var \NuclearEngagement\Utils */
	private $utils;

	public function __construct() {
		$this->utils = new \NuclearEngagement\Utils();
	}

	public function nuclen_get_utils() {
		return $this->utils;
	}

	/** Add the Setup submenu page. */
	public function nuclen_add_setup_page() {
		add_submenu_page(
			'nuclear-engagement',
			esc_html__( 'Nuclear Engagement – Setup', 'nuclear-engagement' ),
			esc_html__( 'Setup', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-setup',
			array( $this, 'nuclen_render_setup_page' )
		);
	}

	/** Render the Setup screen. */
	public function nuclen_render_setup_page() {

		/* ───── Notices ───── */
		$nuclen_error   = isset( $_GET['nuclen_error'] )
			? sanitize_text_field( wp_unslash( $_GET['nuclen_error'] ) )
			: '';
		$nuclen_success = isset( $_GET['nuclen_success'] )
			? sanitize_text_field( wp_unslash( $_GET['nuclen_success'] ) )
			: '';
		$nonce          = isset( $_GET['_wpnonce'] ) ? sanitize_key( $_GET['_wpnonce'] ) : '';

		if ( $nuclen_error && wp_verify_nonce( $nonce, 'nuclear-engagement-setup' ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $nuclen_error ) . '</p></div>';
		}
		if ( $nuclen_success && wp_verify_nonce( $nonce, 'nuclear-engagement-setup' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $nuclen_success ) . '</p></div>';
		}

		/* ───── Retrieve & normalise option ───── */
		$raw_setup = get_option( 'nuclear_engagement_setup', array() );

		// Ensure we always have an array with all keys, even if the option was malformed.
		if ( ! is_array( $raw_setup ) ) {
			$raw_setup = array();
		}
		$app_setup = wp_parse_args(
			$raw_setup,
			array(
				'api_key'             => '',
				'connected'           => false,
				'wp_app_pass_created' => false,
				'wp_app_pass_uuid'    => '',
				'plugin_password'     => '',
			)
		);

		/* ───── Branding header ───── */
		$this->utils->display_nuclen_page_header();
		?>
		<div class="wrap nuclen-container">
			<h1 class="nuclen-heading"><?php esc_html_e( 'Setup', 'nuclear-engagement' ); ?></h1>
			<p><?php esc_html_e( 'Two short steps are required before you can push AI‑generated content into WordPress.', 'nuclear-engagement' ); ?></p>

			<!-- ───── STEP 1 – Gold Code ───── -->
			<?php if ( empty( $app_setup['connected'] ) ) : ?>
				<div id="nuclen-setup-step-1" class="nuclen-section">
					<span class="dashicons dashicons-admin-plugins"></span>
					<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 1 – Authorise your site', 'nuclear-engagement' ); ?></h2>
					<p class="nuclen-paragraph">
						<?php esc_html_e( 'Enter your Gold Code (API key) to connect this site to Nuclear Engagement.', 'nuclear-engagement' ); ?>
					</p>
					<p class="nuclen-paragraph">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link */
								__( 'Need a Gold Code? Create a free account %s.', 'nuclear-engagement' ),
								array( 'a' => array( 'href' => array(), 'target' => array() ) )
							),
							'<a href="https://app.nuclearengagement.com/api-keys" target="_blank">'
							. esc_html__( 'here', 'nuclear-engagement' )
							. '</a>'
						);
						?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'nuclen_connect_app_action', 'nuclen_connect_app_nonce' ); ?>
						<input type="hidden" name="action" value="nuclen_connect_app">

						<label for="nuclen_api_key" class="nuclen-label"><?php esc_html_e( 'Gold Code', 'nuclear-engagement' ); ?></label><br>
						<input type="text" id="nuclen_api_key" name="nuclen_api_key" style="width:350px;"><br><br>

						<button type="submit" class="button button-primary nuclen-button nuclen-button-primary">
							<?php esc_html_e( 'Authorise Site', 'nuclear-engagement' ); ?>
						</button>
					</form>
				</div>
			<?php else : ?>
				<div id="nuclen-setup-step-1" class="nuclen-section">
					<span class="dashicons dashicons-plugins-checked"></span>
					<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 1 complete – Site authorised', 'nuclear-engagement' ); ?></h2>
					<p class="nuclen-paragraph" style="color:green;"><?php esc_html_e( 'Your site is connected.', 'nuclear-engagement' ); ?></p>
					<?php $short_key = substr( $app_setup['api_key'], 0, 6 ); ?>
					<p class="nuclen-paragraph">
						<?php esc_html_e( 'Current Gold Code:', 'nuclear-engagement' ); ?>
						<input type="text" readonly style="width:80px;color:#888;" value="<?php echo esc_attr( $short_key ); ?>">
					</p>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<form method="post"
							  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
							  onsubmit="return confirm('<?php echo esc_js( __( 'Reset Gold Code?', 'nuclear-engagement' ) ); ?>');"
							  style="margin-top:10px;">
							<?php wp_nonce_field( 'nuclen_reset_api_key_action', 'nuclen_reset_api_key_nonce' ); ?>
							<input type="hidden" name="action" value="nuclen_reset_api_key">
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'Reset Gold Code', 'nuclear-engagement' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- ───── STEP 2 – Plugin App Password ───── -->
			<?php if ( ! empty( $app_setup['connected'] ) ) : ?>
				<div id="nuclen-setup-step-2" class="nuclen-section" style="margin-top:30px;">
					<?php if ( empty( $app_setup['wp_app_pass_created'] ) ) : ?>
						<span class="dashicons dashicons-admin-plugins"></span>
						<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 2 – Allow data‑push', 'nuclear-engagement' ); ?></h2>
						<p class="nuclen-paragraph">
							<?php esc_html_e( 'Click the button below to let Nuclear Engagement push generated content into WordPress. A secure password will be created automatically – you don’t have to copy or store it anywhere.', 'nuclear-engagement' ); ?>
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
						<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 2 complete – Access granted', 'nuclear-engagement' ); ?></h2>
						<p class="nuclen-paragraph" style="color:green;">
							<?php esc_html_e( 'Nuclear Engagement can now push content into this site.', 'nuclear-engagement' ); ?>
						</p>

						<?php if ( current_user_can( 'manage_options' ) ) : ?>
							<form method="post"
								  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								  onsubmit="return confirm('<?php echo esc_js( __( 'Revoke access?', 'nuclear-engagement' ) ); ?>');"
								  style="margin-top:10px;">
								<?php wp_nonce_field( 'nuclen_reset_wp_app_action', 'nuclen_reset_wp_app_nonce' ); ?>
								<input type="hidden" name="action" value="nuclen_reset_wp_app_connection">
								<button type="submit" class="button button-secondary"><?php esc_html_e( 'Revoke Access', 'nuclear-engagement' ); ?></button>
							</form>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- ───── Credits (only when fully set up) ───── -->
			<?php
			$fully_setup = ( ! empty( $app_setup['connected'] ) && ! empty( $app_setup['wp_app_pass_created'] ) );
			if ( $fully_setup ) :
			?>
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

			<!-- ───── Support ───── -->
			<h2><?php esc_html_e( 'Support', 'nuclear-engagement' ); ?></h2>
			<p><?php esc_html_e( 'This is an early version – please report bugs or suggestions.', 'nuclear-engagement' ); ?></p>
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: 1: link, 2: email */
						__( 'Contact us via %1$s or e‑mail %2$s – we reply within 24 hours.', 'nuclear-engagement' ),
						array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
					),
					'<a href="https://www.nuclearengagement.com/contact" target="_blank" rel="noopener noreferrer">'
						. esc_html__( 'this form', 'nuclear-engagement' ) . '</a>',
					'<a href="mailto:stefano@nuclearengagement.com">stefano@nuclearengagement.com</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
