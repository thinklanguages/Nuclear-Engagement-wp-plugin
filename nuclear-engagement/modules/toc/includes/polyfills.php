<?php
declare(strict_types=1);
/**
 * File: modules/toc/includes/polyfills.php
 *
 * Poly‑fills for PHP 7.4 and name‑spaced helpers.
 * All helpers are prefixed `nuclen_` to avoid collisions in large stacks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'nuclen_str_contains' ) ) {
	/**
	 * Prefixed back‑port of PHP 8 str_contains().
	 */
	function nuclen_str_contains( string $haystack, string $needle ): bool {
		return $needle === '' || strpos( $haystack, $needle ) !== false;
	}
}

if ( ! function_exists( 'nuclen_str_ends_with' ) ) {
	/**
	 * Prefixed back‑port of PHP 8 str_ends_with().
	 */
	function nuclen_str_ends_with( string $haystack, string $needle ): bool {
		return $needle === '' || substr( $haystack, -strlen( $needle ) ) === $needle;
	}
}
