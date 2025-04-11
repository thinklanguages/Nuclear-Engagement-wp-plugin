<?php
/**
 * File: admin/Setup.php
 *
 * Implementation of changes required by WordPress.org guidelines and your recent SaaS/credits updates.
 * - Renders the Setup page with two steps (API key + WP app password).
 * - Includes a “Your Credits” section that fetches and displays the user’s remaining credits.
 * - References the SetupHandlersTrait for form-submission logic (nuclen_handle_connect_app, etc.).
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

	/**
	 * We'll store a Utils instance so we can do $this->nuclen_get_utils() if needed.
	 */
	private $utils;

	public function __construct() {
		$this->utils = new \NuclearEngagement\Utils();
	}

	public function nuclen_get_utils() {
		return $this->utils;
	}

	/**
	 * Add the Setup submenu page under “Nuclear Engagement”.
	 */
	public function nuclen_add_setup_page() {
		add_submenu_page(
			'nuclear-engagement',
			esc_html__( 'Nuclear Engagement - Setup', 'nuclear-engagement' ),
			esc_html__( 'Setup', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-setup',
			array( $this, 'nuclen_render_setup_page' )
		);
	}

	/**
	 * Render the plugin Setup page.
	 * Steps:
	 *   1) Enter API key (Gold Code).
	 *   2) Generate WP App Password (if not done yet).
	 * Also includes a “Your Credits” section.
	 */
	public function nuclen_render_setup_page() {
		// Check if there's any ?nuclen_error or ?nuclen_success in the URL
		$nuclen_error   = isset( $_GET['nuclen_error'] ) ? sanitize_text_field( wp_unslash( $_GET['nuclen_error'] ) ) : '';
		$nuclen_success = isset( $_GET['nuclen_success'] ) ? sanitize_text_field( wp_unslash( $_GET['nuclen_success'] ) ) : '';
		$nonce          = isset( $_GET['_wpnonce'] ) ? sanitize_key( $_GET['_wpnonce'] ) : '';

		if ( $nuclen_error && wp_verify_nonce( $nonce, 'nuclear-engagement-setup' ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $nuclen_error ); ?></p>
			</div>
			<?php
		}
		if ( $nuclen_success && wp_verify_nonce( $nonce, 'nuclear-engagement-setup' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $nuclen_success ); ?></p>
			</div>
			<?php
		}

		// Retrieve the plugin’s stored setup info (api_key, connected, etc.).
		$app_setup = get_option(
			'nuclear_engagement_setup',
			array(
				'api_key'             => '',
				'connected'           => false,
				'wp_app_pass_created' => false,
				'wp_app_pass_uuid'    => '',
			)
		);

		// If we believe a WP app password is created, confirm it actually exists
		if ( $app_setup['wp_app_pass_created'] ) {
			$current_user = wp_get_current_user();
			if ( class_exists( 'WP_Application_Passwords' ) && $current_user->ID ) {
				$app_passwords = \WP_Application_Passwords::get_user_application_passwords( $current_user->ID );
				$exists        = false;
				foreach ( $app_passwords as $ap ) {
					if ( $ap['uuid'] === $app_setup['wp_app_pass_uuid'] ) {
						$exists = true;
						break;
					}
				}
				if ( ! $exists ) {
					// Means the password was deleted behind our back, so reset local flags
					$app_setup['wp_app_pass_created'] = false;
					$app_setup['wp_app_pass_uuid']    = '';
					update_option( 'nuclear_engagement_setup', $app_setup );
				}
			}
		}

		// Check if WP application passwords are actually available
		global $wp_version;
		$wp_app_passwords_available = (
			version_compare( $wp_version, '5.6', '>=' ) &&
			class_exists( 'WP_Application_Passwords' ) &&
			function_exists( 'wp_is_application_passwords_available' ) &&
			wp_is_application_passwords_available()
		);

		// Branding header
		$this->utils->display_nuclen_page_header();
		?>
		<div class="wrap nuclen-container">
			<h1 class="nuclen-heading"><?php esc_html_e( 'Setup', 'nuclear-engagement' ); ?></h1>
			<p><?php esc_html_e( 'Two steps are needed to generate post sections with AI via Nuclear Engagement.', 'nuclear-engagement' ); ?></p>

			<!-- STEP 1: API Key -->
			<?php if ( ! $app_setup['connected'] ) : ?>
				<div id="nuclen-setup-step-1" class="nuclen-section">
					<span class="dashicons dashicons-admin-plugins"></span>
					<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 1 - Authorize your site', 'nuclear-engagement' ); ?></h2>
					<p class="nuclen-paragraph">
						<?php esc_html_e( 'Enter your Gold Code (API key) to connect your site. Without this, you can only create content manually.', 'nuclear-engagement' ); ?>
					</p>
					<p class="nuclen-paragraph">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to the 'API keys' page on nuclearengagement.com */
								__(
									'If you don\'t have a gold code yet, get one on the Nuclear Engagement web app (create a free account %s).',
									'nuclear-engagement'
								),
								array(
									'a' => array(
										'href'   => array(),
										'target' => array(),
									),
								)
							),
							'<a href="https://app.nuclearengagement.com/api-keys" target="_blank">'
								. esc_html__( 'here', 'nuclear-engagement' )
								. '</a>'
						);
						?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'nuclen_connect_app_action', 'nuclen_connect_app_nonce' ); ?>
						<input type="hidden" name="action" value="nuclen_connect_app" />

						<label for="nuclen_api_key" class="nuclen-label">
							<?php esc_html_e( 'Gold Code', 'nuclear-engagement' ); ?>
						</label><br/>
						<input
							type="text"
							name="nuclen_api_key"
							id="nuclen_api_key"
							style="width: 350px;"
							value=""
						/><br/><br/>

						<button type="submit" class="button button-primary nuclen-button nuclen-button-primary">
							<?php esc_html_e( 'Authorize Site', 'nuclear-engagement' ); ?>
						</button>
					</form>
				</div>
			<?php else : ?>
				<div id="nuclen-setup-step-1" class="nuclen-section">
					<span class="dashicons dashicons-plugins-checked"></span>
					<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 1 completed - Site authorized', 'nuclear-engagement' ); ?></h2>
					<p class="nuclen-paragraph" style="color: green;">
						<?php esc_html_e( 'Your site is authorized to generate content.', 'nuclear-engagement' ); ?>
					</p>
					<?php
					$short_key = ( ! empty( $app_setup['api_key'] ) ) ? substr( $app_setup['api_key'], 0, 6 ) : '';
					?>
					<p class="nuclen-paragraph">
						<?php esc_html_e( 'Current Gold Code:', 'nuclear-engagement' ); ?>
						<input type="text" readonly style="width: 80px; color: #888;"
								value="<?php echo esc_attr( $short_key ); ?>" />
					</p>

					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<form
							method="post"
							action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
							onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset the Gold Code?', 'nuclear-engagement' ) ); ?>');"
							style="margin-top: 10px;"
						>
							<?php wp_nonce_field( 'nuclen_reset_api_key_action', 'nuclen_reset_api_key_nonce' ); ?>
							<input type="hidden" name="action" value="nuclen_reset_api_key" />
							<button type="submit" class="button button-secondary">
								<?php esc_html_e( 'Reset Gold Code', 'nuclear-engagement' ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- STEP 2 - WP App Password -->
			<?php if ( $app_setup['connected'] ) : ?>
				<?php if ( ! $wp_app_passwords_available ) : ?>
					<!-- Show just one error if WP app passwords not available or WP version < 5.6 -->
					<div class="notice notice-error" style="margin:10px 0;">
						<p>
							<strong><?php esc_html_e( 'WP Application Passwords not available.', 'nuclear-engagement' ); ?></strong><br/>
							<?php esc_html_e( 'This can happen if WordPress is older than 5.6, or a security plugin / WP config is disabling them. Please enable app passwords if you want to push content automatically.', 'nuclear-engagement' ); ?>
						</p>
					</div>
				<?php else : ?>
					<div id="nuclen-setup-step-2" class="nuclen-section" style="margin-top:30px;">
						<?php if ( ! $app_setup['wp_app_pass_created'] ) : ?>
							<span class="dashicons dashicons-admin-plugins"></span>
							<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 2 - Allow data push', 'nuclear-engagement' ); ?></h2>
							<p class="nuclen-paragraph">
								<?php esc_html_e( 'Click below to allow the Nuclear Engagement app to send generated content into your site.', 'nuclear-engagement' ); ?>
							</p>

							<form
								method="post"
								action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								onsubmit="this.querySelector('button').disabled = true;"
							>
								<?php wp_nonce_field( 'nuclen_generate_app_password_action', 'nuclen_generate_app_password_nonce' ); ?>
								<input type="hidden" name="action" value="nuclen_generate_app_password" />
								<button type="submit" class="button button-primary nuclen-button nuclen-button-primary">
									<?php esc_html_e( 'Allow', 'nuclear-engagement' ); ?>
								</button>
							</form>
						<?php else : ?>
							<span class="dashicons dashicons-plugins-checked"></span>
							<h2 class="nuclen-subheading"><?php esc_html_e( 'Step 2 completed - Access granted', 'nuclear-engagement' ); ?></h2>
							<p class="nuclen-paragraph" style="color: green;">
								<?php esc_html_e( 'Nuclear Engagement can now push generated content to your site.', 'nuclear-engagement' ); ?>
							</p>

							<?php if ( current_user_can( 'manage_options' ) ) : ?>
								<form
									method="post"
									action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
									onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to revoke access?', 'nuclear-engagement' ) ); ?>');"
									style="margin-top: 10px;"
								>
									<?php wp_nonce_field( 'nuclen_reset_wp_app_action', 'nuclen_reset_wp_app_nonce' ); ?>
									<input type="hidden" name="action" value="nuclen_reset_wp_app_connection" />
									<button type="submit" class="button button-secondary">
										<?php esc_html_e( 'Revoke Access', 'nuclear-engagement' ); ?>
									</button>
								</form>
							<?php endif; ?>
						<?php endif; ?>
					</div><!-- #nuclen-setup-step-2 -->
				<?php endif; ?>
			<?php endif; ?>

			<!-- "Your Credits" Section -->
			<?php
			$fully_setup = ( ! empty( $app_setup['connected'] ) && ! empty( $app_setup['wp_app_pass_created'] ) );
			if ( $fully_setup ) :
				?>
				<h2 style="margin-top:30px;"><?php esc_html_e( 'Your Credits', 'nuclear-engagement' ); ?></h2>
				<p id="nuclen-setup-credits-msg"><?php esc_html_e( 'Loading credits...', 'nuclear-engagement' ); ?></p>
				<script>
				document.addEventListener('DOMContentLoaded', async () => {
				  const msgEl = document.getElementById('nuclen-setup-credits-msg');
				  if (!msgEl) return;

				  try {
				    // We'll reuse the same “nuclen_fetch_app_updates” action with no generation_id
				    const formData = new FormData();
				    formData.append('action', 'nuclen_fetch_app_updates');
				    formData.append('security', '<?php echo esc_js( wp_create_nonce("nuclen_admin_ajax_nonce") ); ?>');

				    const resp = await fetch("<?php echo esc_url( admin_url('admin-ajax.php') ); ?>", {
				      method: 'POST',
				      body: formData
				    });
				    const data = await resp.json();
				    if (!data.success) {
				      throw new Error(data.data?.message || 'Failed to fetch credits');
				    }
				    // data is wrapped in data.data
				    const remoteData = data.data;
				    if (remoteData && typeof remoteData.remaining_credits !== 'undefined') {
				      msgEl.textContent = '<?php echo esc_js( __("You have", "nuclear-engagement") ); ?> '
				        + remoteData.remaining_credits
				        + ' <?php echo esc_js( __("credits left.", "nuclear-engagement") ); ?>';
				    } else {
				      msgEl.textContent = '<?php echo esc_js( __("No credits info returned.", "nuclear-engagement") ); ?>';
				    }
				  } catch (err) {
				    msgEl.textContent = 'Error: ' + err;
				  }
				});
				</script>
				<?php
			else :
				?>
				<!-- If not fully setup, hide "Your Credits" section -->
				<!-- e.g. do nothing here -->
				<?php
			endif;
			?>

			<h2><?php esc_html_e( 'Support', 'nuclear-engagement' ); ?></h2>
			<p>
				<?php esc_html_e( 'This is a free beta version. Some details might have been overlooked, functionalities might be missing or broken, or there might be plugin conflicts on some websites.', 'nuclear-engagement' ); ?>
			</p>
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: 1: link to contact form, 2: link to stefano@nuclearengagement.com */
						__(
							'To report bugs, suggest features, or just any question or comment, please %1$s or drop Stefano an email at %2$s. I\'m constantly developing this service and will respond within 24 hours.',
							'nuclear-engagement'
						),
						array(
							'a' => array(
								'href'   => array(),
								'target' => array(),
								'rel'    => array(),
							),
						)
					),
					'<a href="https://www.nuclearengagement.com/contact" target="_blank" rel="noopener noreferrer">'
						. esc_html__( 'submit the contact form', 'nuclear-engagement' )
						. '</a>',
					'<a href="mailto:stefano@nuclearengagement.com">stefano@nuclearengagement.com</a>'
				);
				?>
			</p>

			<?php
				$info     = \NuclearEngagement\Utils::nuclen_get_log_file_info();
				$log_file = $info['path'];
				$log_url  = $info['url'];

			if ( file_exists( $log_file ) ) :
				?>
			<p style="margin-top: 20px;">
				<?php
				printf(
					esc_html__( 'For faster results, attach this %1$slog file%2$s to your support request.', 'nuclear-engagement' ),
					'<a href="' . esc_url( $log_url ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
				?>
			</p>
			<?php endif; ?>

			<p>
			<?php
			printf(
				wp_kses(
					/* translators: 1: link to Google, 2: link to TrustPilot, 3: link to Facebook, 4: link to TrustIndex, 5: link to WordPress.org */
					__(
						'If you like what I\'m doing, please leave me a review on %1$s, %2$s, %3$s, %4$s, or %5$s. It takes 1 minute to drop a 1-line review and it helps me immensely.',
						'nuclear-engagement'
					),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				),
				// Google
				'<a href="https://www.google.com/search?q=Nuclear+Engagement" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'Google', 'nuclear-engagement' )
					. '</a>',
				// TrustPilot
				'<a href="https://www.trustpilot.com/evaluate/nuclearengagement.com?stars=5" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'TrustPilot', 'nuclear-engagement' )
					. '</a>',
				// Facebook
				'<a href="https://www.facebook.com/nuclearengagement/reviews" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'Facebook', 'nuclear-engagement' )
					. '</a>',
				// TrustIndex
				'<a href="https://public.trustindex.io/review/write/slug/www.nuclearengagement.com" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'TrustIndex', 'nuclear-engagement' )
					. '</a>',
				// WordPress.org
				'<a href="https://wordpress.org/support/plugin/nuclear-engagement/reviews/#new-post" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'WordPress.org', 'nuclear-engagement' )
					. '</a>'
			);
			?>
			</p>

		</div>
		<?php
	}
}
