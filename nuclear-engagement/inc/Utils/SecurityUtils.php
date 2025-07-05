<?php
/**
 * SecurityUtils.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Utils
 */

declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecurityUtils {
	public static function generate_secure_password( int $length = 32, bool $special_chars = true ): string {
		return wp_generate_password( $length, $special_chars, true );
	}

	public static function generate_uuid(): string {
		return wp_generate_uuid4();
	}

	public static function hash_password( string $password ): string {
		return wp_hash_password( $password );
	}

	public static function verify_password( string $password, string $hash ): bool {
		return wp_check_password( $password, $hash );
	}

	public static function generate_nonce( string $action ): string {
		return wp_create_nonce( $action );
	}

	public static function verify_nonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	public static function sanitize_input( $input ) {
		if ( is_string( $input ) ) {
			return sanitize_text_field( $input );
		}

		if ( is_array( $input ) ) {
			return array_map( array( self::class, 'sanitize_input' ), $input );
		}

		return $input;
	}

	public static function rate_limit_check( string $key, int $limit = 10, int $window = 300 ): bool {
		$transient_key = 'nuclen_rate_limit_' . md5( $key );
		$attempts      = get_transient( $transient_key );

		if ( $attempts === false ) {
			set_transient( $transient_key, 1, $window );
			return true;
		}

		if ( $attempts >= $limit ) {
			return false;
		}

		set_transient( $transient_key, $attempts + 1, $window );
		return true;
	}
}
