<?php
/**
 * File: modules/toc/includes/polyfills.php
 *
 * Poly‑fills for PHP 7.4 and name‑spaced helpers.
 * All helpers are prefixed `nuclen_` to avoid collisions in large stacks.
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'nuclen_str_contains' ) ) {
        /**
         * Prefixed back‑port of PHP 8 str_contains().
         *
         * @param string $haystack String to search in.
         * @param string $needle   Substring to search for.
         *
         * @return bool Whether the needle exists in the haystack.
         */
        function nuclen_str_contains( string $haystack, string $needle ): bool {
                return '' === $needle || false !== strpos( $haystack, $needle );
        }
}

if ( ! function_exists( 'nuclen_str_ends_with' ) ) {
        /**
         * Prefixed back‑port of PHP 8 str_ends_with().
         *
         * @param string $haystack String to check.
         * @param string $needle   Expected ending.
         *
         * @return bool Whether the haystack ends with the needle.
         */
        function nuclen_str_ends_with( string $haystack, string $needle ): bool {
                return '' === $needle || substr( $haystack, -strlen( $needle ) ) === $needle;
        }
}
