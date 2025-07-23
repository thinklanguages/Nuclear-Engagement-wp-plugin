<?php
/**
 * ProcessIdentifier.php - Utility for generating unique process identifiers.
 *
 * @package NuclearEngagement_Utils
 */

declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process identifier utility for consistent process identification.
 *
 * Provides unique process identification for distributed locks and background
 * processing using standard PHP functions that work on all hosting environments.
 *
 * @package NuclearEngagement\Utils
 * @since 1.0.0
 */
final class ProcessIdentifier {

	/**
	 * Static cache for the process identifier to maintain consistency
	 * within a single request/process.
	 *
	 * @var string|null
	 */
	private static ?string $cached_identifier = null;

	/**
	 * Get a unique process identifier.
	 *
	 * Generates a unique identifier using available PHP functions. 
	 * The identifier is cached for the duration of the request to ensure consistency.
	 *
	 * @return string The process identifier.
	 */
	public static function get(): string {
		// Return cached identifier if available
		if ( self::$cached_identifier !== null ) {
			return self::$cached_identifier;
		}

		// Generate and cache unique identifier
		self::$cached_identifier = self::generate_unique_identifier();
		return self::$cached_identifier;
	}

	/**
	 * Generate a unique process identifier without using system functions.
	 *
	 * Creates a unique identifier combining:
	 * - High-precision timestamp
	 * - Unique ID with entropy
	 * - Random bytes for additional uniqueness
	 * - Request context when available
	 *
	 * @return string A unique process identifier.
	 */
	private static function generate_unique_identifier(): string {
		$components = array();

		// High-precision timestamp
		$components[] = sprintf( '%.4f', microtime( true ) );

		// Unique ID with more entropy
		$components[] = uniqid( '', true );

		// Random component for additional uniqueness
		try {
			$components[] = bin2hex( random_bytes( 4 ) );
		} catch ( \Exception $e ) {
			// Fallback to less secure randomness if random_bytes fails
			$components[] = substr( md5( (string) mt_rand() ), 0, 8 );
		}

		// Add request-specific context if available
		if ( php_sapi_name() !== 'cli' && isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
			$components[] = (string) $_SERVER['REQUEST_TIME_FLOAT'];
		}

		return implode( '_', $components );
	}

	/**
	 * Get process information for debugging.
	 *
	 * Returns an array with process identification details.
	 *
	 * @return array Process information.
	 */
	public static function get_debug_info(): array {
		$identifier = self::get();
		
		return array(
			'identifier' => $identifier,
			'type' => 'generated',
			'sapi' => php_sapi_name(),
			'time' => time(),
			'server' => gethostname() ?: 'unknown',
		);
	}

	/**
	 * Reset the cached identifier.
	 *
	 * Useful for testing or when forking processes.
	 */
	public static function reset(): void {
		self::$cached_identifier = null;
	}
}