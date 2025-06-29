<?php
declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ValidationUtils {
	public static function validate_nonce( string $nonce_value, string $nonce_action ): bool {
		return wp_verify_nonce( $nonce_value, $nonce_action ) !== false;
	}

	public static function validate_capability( string $capability = 'manage_options' ): bool {
		return current_user_can( $capability );
	}

	public static function validate_ajax_request( string $nonce_action, string $capability = 'manage_options' ): bool {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			return false;
		}

		return self::validate_capability( $capability );
	}

	public static function sanitize_api_key( string $api_key ): string {
		return sanitize_text_field( trim( $api_key ) );
	}

	public static function is_valid_uuid( string $uuid ): bool {
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid );
	}

	public static function validate_url( string $url ): bool {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	public static function validate_email( string $email ): bool {
		return is_email( $email ) !== false;
	}
}