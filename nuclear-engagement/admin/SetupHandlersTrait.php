<?php
/**
 * File: admin/SetupHandlersTrait.php
 *
 * Contains all the form-handling methods for the Setup class.
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


trait SetupHandlersTrait {

	/**
	 * Handle the form submission for connecting the app (storing the API key).
	 */
	public function nuclen_handle_connect_app() {
		if (
			! isset( $_POST['nuclen_connect_app_nonce'] ) || ! isset( $_POST['nuclen_api_key'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_connect_app_nonce'] ) ), 'nuclen_connect_app_action' )
		) {
			$this->nuclen_redirect_with_error( 'Invalid nonce for connecting the app.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions to connect the app.' );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['nuclen_api_key'] ?? '' ) );
		if ( empty( $api_key ) ) {
			$this->nuclen_redirect_with_error( 'Missing API key. Please enter your Gold Code.' );
		}

		// 1) Validate the API key with our remote endpoint before marking "connected"
		if ( ! $this->nuclen_validate_api_key_with_app( $api_key ) ) {
			$this->nuclen_redirect_with_error( 'Invalid or unknown Gold Code. Please check and try again.' );
		}

		// 2) If remote check succeeded, store the API key & mark as connected
		$app_setup              = get_option( 'nuclear_engagement_setup', array() );
		$app_setup['api_key']   = $api_key;
		$app_setup['connected'] = true;
		update_option( 'nuclear_engagement_setup', $app_setup );

		// 3) Auto-generate WP App Password if we haven't already (this is Step 2)
		if ( empty( $app_setup['wp_app_pass_created'] ) ) {
			$this->nuclen_handle_generate_app_password( true );
			// nuclen_handle_generate_app_password() will redirect on success/failure, so just return here
			return;
		}

		// 4) If we already had a WP app password created, just redirect with success
		$this->nuclen_redirect_with_success( 'API key saved and your site is now connected.' );
	}

	/**
	 * Private helper to confirm the API key is recognized by the remote app.
	 */
	private function nuclen_validate_api_key_with_app( $api_key ) {
		$url = 'https://app.nuclearengagement.com/api/check-api-key';

		$response = wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( array( 'appApiKey' => $api_key ) ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->nuclen_get_utils()->nuclen_log( 'Failed to validate API key. ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$this->nuclen_get_utils()->nuclen_log( "Remote error validating API key: code $code, body $body" );
			return false;
		}

		return true;
	}

	/**
	 * Handle generating the WP application password.
	 *
	 * @param bool $bypass_nonce If true, skip the nonce check (internal call).
	 */
	public function nuclen_handle_generate_app_password( $bypass_nonce = false ) {
		if ( ! $bypass_nonce ) {
			if (
				! isset( $_POST['nuclen_generate_app_password_nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_generate_app_password_nonce'] ) ), 'nuclen_generate_app_password_action' )
			) {
				$this->nuclen_redirect_with_error( 'Invalid nonce for generating WP app password.' );
			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions to generate WP app password.' );
		}

		$app_setup = get_option( 'nuclear_engagement_setup', array() );
		if ( empty( $app_setup['connected'] ) || empty( $app_setup['api_key'] ) ) {
			$this->nuclen_redirect_with_error( 'Cannot create WP password: app is not connected or API key is missing.' );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-application-passwords.php';
		}
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			$this->nuclen_redirect_with_error( 'WP version may be too old to use WP_Application_Passwords.' );
		}

		// Check if application passwords are disabled
		if ( ! wp_is_application_passwords_available() ) {
			$message = 'Application passwords are currently disabled. To enable them: ';

			// Check if they're disabled via constant
			if ( defined( 'WP_APP_PASSWORDS_DISABLED' ) && WP_APP_PASSWORDS_DISABLED ) {
				$message .= 'Remove or set WP_APP_PASSWORDS_DISABLED to false in wp-config.php. ';
			}

			// Check if they're disabled via filter
			if ( ! wp_is_application_passwords_available() ) {
				$message .= 'Check for settings/plugins/themes that might be disabling them via the ';
				$message .= 'wp_is_application_passwords_available filter.';
			}

			$this->nuclen_redirect_with_error( $message );
		}

		$current_user = wp_get_current_user();
		if ( ! $current_user || 0 === $current_user->ID ) {
			$this->nuclen_redirect_with_error( 'Could not retrieve current user.' );
		}

		// 1. Remove any existing "Nuclear Engagement" app password for this user
		$app_passwords = \WP_Application_Passwords::get_user_application_passwords( $current_user->ID );
		foreach ( $app_passwords as $ap ) {
			// Ensure that item_id is set before using it
			if ( isset( $ap['item_id'] ) && $ap['name'] === 'Nuclear Engagement' ) {
				\WP_Application_Passwords::delete_application_password( $current_user->ID, $ap['item_id'] );
			}
		}

		// 2. Create a new app password
		$app_password = \WP_Application_Passwords::create_new_application_password(
			$current_user->ID,
			array( 'name' => 'Nuclear Engagement' )
		);
		if ( is_wp_error( $app_password ) ) {
			$this->nuclen_redirect_with_error( 'Failed to generate WP app password.' );
		}

		list($plaintext_password, $password_details) = $app_password;
		$uuid                                        = $password_details['uuid'];

		// 3. Try sending the new password to the NE app
		$ok = $this->nuclen_send_app_password_to_app(
			array(
				'appApiKey'     => $app_setup['api_key'],
				'siteUrl'       => get_site_url(),
				'wpUserLogin'   => $current_user->user_login,
				'wpAppPassword' => $plaintext_password,
				'wpAppPassUuid' => $uuid,
			)
		);
		if ( ! $ok ) {
			// Sending failed => delete the newly-created WP app password
			\WP_Application_Passwords::delete_application_password( $current_user->ID, $password_details['item_id'] );
			$this->nuclen_redirect_with_error(
				'Failed to send WP app password to the NE app. Creation aborted.'
			);
		}

		// 4. If remote send succeeded, update local plugin settings
		$app_setup['wp_app_pass_created'] = true;
		$app_setup['wp_app_pass_uuid']    = $uuid;
		update_option( 'nuclear_engagement_setup', $app_setup );

		$this->nuclen_redirect_with_success( 'Setup completed! You are ready to go.' );
	}

	/**
	 * Reset/empty the API key (and mark site as disconnected).
	 */
	public function nuclen_handle_reset_api_key() {
		if (
			! isset( $_POST['nuclen_reset_api_key_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_reset_api_key_nonce'] ) ), 'nuclen_reset_api_key_action' )
		) {
			$this->nuclen_redirect_with_error( 'Invalid nonce for resetting API key.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions to reset API key.' );
		}

		$app_setup = get_option( 'nuclear_engagement_setup', array() );
		// Clear fields
		$app_setup['api_key']             = '';
		$app_setup['connected']           = false;
		$app_setup['wp_app_pass_created'] = false;
		$app_setup['wp_app_pass_uuid']    = '';
		update_option( 'nuclear_engagement_setup', $app_setup );

		$this->nuclen_redirect_with_success( 'API key was reset. Your site is now disconnected.' );
	}

	/**
	 * Reset/empty the WP app password (removing it from WP if it exists).
	 */
	public function nuclen_handle_reset_wp_app_connection() {
		if (
			! isset( $_POST['nuclen_reset_wp_app_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nuclen_reset_wp_app_nonce'] ) ), 'nuclen_reset_wp_app_action' )
		) {
			$this->nuclen_redirect_with_error( 'Invalid nonce for resetting the WP App Password.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->nuclen_redirect_with_error( 'Insufficient permissions to reset the WP App connection.' );
		}

		$app_setup    = get_option( 'nuclear_engagement_setup', array() );
		$current_user = wp_get_current_user();

		// Remove the actual WP app password if it exists
		if ( class_exists( 'WP_Application_Passwords' ) && ! empty( $app_setup['wp_app_pass_uuid'] ) && $current_user->ID ) {
			$app_passwords = \WP_Application_Passwords::get_user_application_passwords( $current_user->ID );
			foreach ( $app_passwords as $ap ) {
				if ( isset( $ap['uuid'] ) && $ap['uuid'] === $app_setup['wp_app_pass_uuid'] ) {
					\WP_Application_Passwords::delete_application_password( $current_user->ID, $ap['item_id'] );
					break;
				}
			}
		}

		// Clear local plugin flags
		$app_setup['wp_app_pass_created'] = false;
		$app_setup['wp_app_pass_uuid']    = '';
		update_option( 'nuclear_engagement_setup', $app_setup );

		$this->nuclen_redirect_with_success( 'WP App Password was reset. The plugin no longer has a valid app password.' );
	}

	/**
	 * Send the WP app password data to an external app (optional).
	 */
	private function nuclen_send_app_password_to_app( $data ) {
		// Example endpoint
		$url = 'https://app.nuclearengagement.com/api/store-wp-creds';

		$response = wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->nuclen_get_utils()->nuclen_log( 'Failed to send WP app password. ' . $response->get_error_message() );
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$this->nuclen_get_utils()->nuclen_log( "Remote error sending WP app password: code $code, body $body" );
			return false;
		}
		return true;
	}

	/**
	 * Helper: redirect with an error message in the URL query params.
	 */
	private function nuclen_redirect_with_error( $message ) {
		wp_redirect(
			add_query_arg(
				array(
					'page'         => 'nuclear-engagement-setup',
					'nuclen_error' => urlencode( $message ),
					'_wpnonce'     => wp_create_nonce( 'nuclear-engagement-setup' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Helper: redirect with a success message in the URL query params.
	 */
	private function nuclen_redirect_with_success( $message ) {
		wp_redirect(
			add_query_arg(
				array(
					'page'           => 'nuclear-engagement-setup',
					'nuclen_success' => urlencode( $message ),
					'_wpnonce'       => wp_create_nonce( 'nuclear-engagement-setup' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
