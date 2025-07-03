<?php
declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class for safely accessing server variables.
 *
 * This class provides secure methods to access $_SERVER variables
 * with proper validation and sanitization to prevent security issues.
 *
 * @package NuclearEngagement\Utils
 * @since   1.0.0
 */
final class ServerUtils {

	/**
	 * Get client IP address safely.
	 *
	 * @return string Sanitized IP address or 'unknown' if not available.
	 */
	public static function get_client_ip(): string {
		$ip_headers = [
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_X_FORWARDED_FOR',      // Load balancers/proxies
			'HTTP_X_FORWARDED',          // Proxies
			'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
			'HTTP_FORWARDED_FOR',        // Proxies
			'HTTP_FORWARDED',            // Proxies
			'REMOTE_ADDR'                // Standard
		];

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[$header] ) ) {
				$ip = sanitize_text_field( $_SERVER[$header] );
				
				// Handle comma-separated IPs (from proxies)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
				
				// Allow private IPs for development
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return 'unknown';
	}

	/**
	 * Get user agent safely.
	 *
	 * @return string Sanitized user agent or 'unknown' if not available.
	 */
	public static function get_user_agent(): string {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return 'unknown';
		}

		$user_agent = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
		
		// Limit length to prevent DoS
		$user_agent = substr( $user_agent, 0, 500 );
		
		// Remove potentially dangerous characters
		$user_agent = preg_replace( '/[<>"\']/', '', $user_agent );
		
		return $user_agent ?: 'unknown';
	}

	/**
	 * Get request URI safely.
	 *
	 * @return string Sanitized request URI or '/' if not available.
	 */
	public static function get_request_uri(): string {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '/';
		}

		$uri = sanitize_text_field( $_SERVER['REQUEST_URI'] );
		
		// Validate URI format
		if ( ! filter_var( $uri, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED ) && $uri[0] !== '/' ) {
			return '/';
		}
		
		// Limit length
		$uri = substr( $uri, 0, 2000 );
		
		return $uri;
	}

	/**
	 * Get request method safely.
	 *
	 * @return string Sanitized request method or 'GET' if not available.
	 */
	public static function get_request_method(): string {
		if ( empty( $_SERVER['REQUEST_METHOD'] ) ) {
			return 'GET';
		}

		$method = strtoupper( sanitize_text_field( $_SERVER['REQUEST_METHOD'] ) );
		
		// Validate against known HTTP methods
		$valid_methods = [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS' ];
		
		return in_array( $method, $valid_methods, true ) ? $method : 'GET';
	}

	/**
	 * Get HTTP host safely.
	 *
	 * @return string Sanitized HTTP host or 'localhost' if not available.
	 */
	public static function get_http_host(): string {
		if ( empty( $_SERVER['HTTP_HOST'] ) ) {
			return 'localhost';
		}

		$host = sanitize_text_field( $_SERVER['HTTP_HOST'] );
		
		// Remove port number for validation
		$host_without_port = preg_replace( '/:\d+$/', '', $host );
		
		// Validate hostname
		if ( ! filter_var( $host_without_port, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
			return 'localhost';
		}
		
		// Limit length
		$host = substr( $host, 0, 255 );
		
		return $host;
	}

	/**
	 * Get referrer safely.
	 *
	 * @return string Sanitized referrer or empty string if not available.
	 */
	public static function get_referrer(): string {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		$referrer = sanitize_text_field( $_SERVER['HTTP_REFERER'] );
		
		// Validate URL
		if ( ! filter_var( $referrer, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		
		// Limit length
		$referrer = substr( $referrer, 0, 2000 );
		
		return $referrer;
	}

	/**
	 * Check if request is HTTPS.
	 *
	 * @return bool True if HTTPS, false otherwise.
	 */
	public static function is_https(): bool {
		// Check multiple possible indicators
		$https_indicators = [
			! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off',
			! empty( $_SERVER['SERVER_PORT'] ) && (int) $_SERVER['SERVER_PORT'] === 443,
			! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https',
			! empty( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on',
		];

		return in_array( true, $https_indicators, true );
	}

	/**
	 * Get server software safely.
	 *
	 * @return string Sanitized server software or 'unknown' if not available.
	 */
	public static function get_server_software(): string {
		if ( empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
			return 'unknown';
		}

		$server = sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] );
		
		// Extract only the main server name, remove version details for security
		if ( preg_match( '/^(\w+)/', $server, $matches ) ) {
			return strtolower( $matches[1] );
		}
		
		return 'unknown';
	}

	/**
	 * Get anonymized client identifier for rate limiting.
	 *
	 * @return string Anonymized client identifier.
	 */
	public static function get_client_identifier(): string {
		$ip = self::get_client_ip();
		$user_agent = self::get_user_agent();
		
		// Create anonymous but unique identifier
		$identifier = hash( 'sha256', $ip . $user_agent . wp_salt() );
		
		return 'client_' . substr( $identifier, 0, 16 );
	}

	/**
	 * Get safe server context for logging.
	 *
	 * @return array Safe server context data.
	 */
	public static function get_safe_context(): array {
		return [
			'ip'           => self::get_client_ip(),
			'user_agent'   => self::get_user_agent(),
			'request_uri'  => self::get_request_uri(),
			'method'       => self::get_request_method(),
			'host'         => self::get_http_host(),
			'referrer'     => self::get_referrer(),
			'https'        => self::is_https(),
			'server'       => self::get_server_software(),
			'timestamp'    => time(),
		];
	}
}