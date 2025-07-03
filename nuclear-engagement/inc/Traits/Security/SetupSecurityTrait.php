<?php
namespace NuclearEngagement\Traits\Security;

use NuclearEngagement\Utils\ValidationUtils;
use NuclearEngagement\Utils\ResponseUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SetupSecurityTrait {
	protected function validate_nonce_and_permissions( string $nonce_field, string $nonce_action, string $capability = 'manage_options' ): void {
		if ( ! isset( $_POST[ $nonce_field ] ) ) {
			$this->redirect_with_error( 'Missing security token.' );
		}
		
		$nonce_value = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
		if ( ! ValidationUtils::validate_nonce( $nonce_value, $nonce_action ) ) {
			$this->redirect_with_error( 'Invalid security token.' );
		}
		
		if ( ! ValidationUtils::validate_capability( $capability ) ) {
			$this->redirect_with_error( 'Insufficient permissions.' );
		}
	}

	protected function redirect_with_error( string $msg ): void {
		ResponseUtils::redirect_with_message( 'nuclear-engagement-setup', $msg, true );
	}

	protected function redirect_with_success( string $msg ): void {
		ResponseUtils::redirect_with_message( 'nuclear-engagement-setup', $msg, false );
	}
}