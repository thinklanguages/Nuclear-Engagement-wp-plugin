<?php
declare(strict_types=1);
namespace NuclearEngagement\Security;

use NuclearEngagement\Core\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TokenManager {
	private SettingsRepository $settings_repository;
	private string $encryption_key;
	
	public function __construct( SettingsRepository $settings_repository ) {
		$this->settings_repository = $settings_repository;
		$this->encryption_key = $this->get_or_create_encryption_key();
	}
	
	public function generate_secure_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}
	
	public function hash_token( string $token ): string {
		return hash_hmac( 'sha256', $token, $this->encryption_key );
	}
	
	public function encrypt_sensitive_data( string $data ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$encrypted = sodium_crypto_secretbox( $data, $nonce, $this->get_encryption_key_bytes() );
		return base64_encode( $nonce . $encrypted );
	}
	
	public function decrypt_sensitive_data( string $encrypted_data ): ?string {
		$decoded = base64_decode( $encrypted_data );
		if ( $decoded === false ) {
			return null;
		}
		
		$nonce = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		
		$decrypted = sodium_crypto_secretbox_open( $ciphertext, $nonce, $this->get_encryption_key_bytes() );
		return $decrypted !== false ? $decrypted : null;
	}
	
	public function verify_token( string $token, string $stored_hash ): bool {
		return hash_equals( $stored_hash, $this->hash_token( $token ) );
	}
	
	private function get_or_create_encryption_key(): string {
		$key = get_option( 'nuclear_engagement_encryption_key' );
		if ( empty( $key ) ) {
			$key = base64_encode( random_bytes( 32 ) );
			update_option( 'nuclear_engagement_encryption_key', $key, false );
		}
		return $key;
	}
	
	private function get_encryption_key_bytes(): string {
		return substr( hash( 'sha256', $this->encryption_key, true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}