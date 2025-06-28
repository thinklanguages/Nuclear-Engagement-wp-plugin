<?php
namespace NuclearEngagement\Admin;

use NuclearEngagement\Core\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApiConfigurationPage {
	private SettingsRepository $settings_repository;
	
	public function __construct( SettingsRepository $settings_repository ) {
		$this->settings_repository = $settings_repository;
	}
	
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'settings_init' ] );
		add_action( 'admin_post_nuclear_engagement_save_api_config', [ $this, 'handle_form_submission' ] );
	}
	
	public function add_admin_menu(): void {
		add_submenu_page(
			'nuclear-engagement-setup',
			'API Configuration',
			'API Config',
			'manage_options',
			'nuclear-engagement-api-config',
			[ $this, 'render_page' ]
		);
	}
	
	public function settings_init(): void {
		register_setting( 'nuclear_engagement_api_config', 'nuclear_engagement_api_settings' );
	}
	
	public function render_page(): void {
		$current_url = $this->settings_repository->get( 'api_base_url', 'https://app.nuclearengagement.com/api' );
		?>
		<div class="wrap">
			<h1>Nuclear Engagement API Configuration</h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'nuclear_engagement_api_config', 'api_config_nonce' ); ?>
				<input type="hidden" name="action" value="nuclear_engagement_save_api_config">
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="api_base_url">API Base URL</label>
						</th>
						<td>
							<input 
								type="url" 
								id="api_base_url" 
								name="api_base_url" 
								value="<?php echo esc_attr( $current_url ); ?>" 
								class="regular-text"
								required
							>
							<p class="description">
								The base URL for the Nuclear Engagement API. Default: https://app.nuclearengagement.com/api
							</p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( 'Save Configuration' ); ?>
			</form>
		</div>
		<?php
	}
	
	public function handle_form_submission(): void {
		if ( ! wp_verify_nonce( $_POST['api_config_nonce'] ?? '', 'nuclear_engagement_api_config' ) ) {
			wp_die( 'Security check failed' );
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}
		
		$api_url = sanitize_url( $_POST['api_base_url'] ?? '' );
		
		if ( empty( $api_url ) || ! filter_var( $api_url, FILTER_VALIDATE_URL ) ) {
			wp_redirect( add_query_arg( [ 'page' => 'nuclear-engagement-api-config', 'error' => 'invalid_url' ], admin_url( 'admin.php' ) ) );
			exit;
		}
		
		$this->settings_repository->set( 'api_base_url', $api_url )->save();
		
		wp_redirect( add_query_arg( [ 'page' => 'nuclear-engagement-api-config', 'success' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}
}