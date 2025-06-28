<?php
/**
 * API User Management for secure REST API operations.
 *
 * This class manages a dedicated service account for API operations,
 * implementing the principle of least privilege and proper audit trails.
 *
 * @package NuclearEngagement\Security
 * @since   1.1.0
 */

declare(strict_types=1);

namespace NuclearEngagement\Security;

use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API User Manager for secure service account handling.
 *
 * This class provides secure API authentication by:
 * - Creating dedicated API user roles with minimal permissions
 * - Managing service accounts for API operations
 * - Implementing proper capability-based authorization
 * - Providing audit trails for API operations
 *
 * @since 1.1.0
 */
class ApiUserManager {
	
	/**
	 * API user role name.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const API_ROLE = 'nuclear_engagement_api';
	
	/**
	 * API service account username.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const SERVICE_ACCOUNT_USERNAME = 'nuclear_engagement_api_service';
	
	/**
	 * Option key for storing service account user ID.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const SERVICE_ACCOUNT_OPTION = 'nuclear_engagement_api_user_id';
	
	/**
	 * Initialize API user management.
	 *
	 * Sets up the API role and service account if they don't exist.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function init(): void {
		// Create API role if it doesn't exist
		if ( ! get_role( self::API_ROLE ) ) {
			self::create_api_role();
		}
		
		// Ensure service account exists
		self::ensure_service_account();
	}
	
	/**
	 * Create the API user role with minimal required capabilities.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private static function create_api_role(): void {
		$capabilities = array(
			'read'                              => true,
			'edit_posts'                        => true,
			'edit_published_posts'              => true,
			'publish_posts'                     => true,
			'upload_files'                      => true,
			'manage_nuclear_engagement_content' => true, // Custom capability
		);
		
		add_role(
			self::API_ROLE,
			__( 'Nuclear Engagement API', 'nuclear-engagement' ),
			$capabilities
		);
		
		LoggingService::log( 'Created Nuclear Engagement API user role with limited capabilities' );
	}
	
	/**
	 * Ensure the API service account exists.
	 *
	 * @since 1.1.0
	 * @return int|false Service account user ID or false on failure.
	 */
	public static function ensure_service_account() {
		$service_user_id = get_option( self::SERVICE_ACCOUNT_OPTION );
		
		// Check if stored user ID is valid
		if ( $service_user_id && get_user_by( 'id', $service_user_id ) ) {
			return (int) $service_user_id;
		}
		
		// Create new service account
		return self::create_service_account();
	}
	
	/**
	 * Create a new API service account.
	 *
	 * @since 1.1.0
	 * @return int|false Service account user ID or false on failure.
	 */
	private static function create_service_account() {
		$username = self::SERVICE_ACCOUNT_USERNAME;
		$email    = 'api@' . parse_url( home_url(), PHP_URL_HOST );
		$password = wp_generate_password( 32, true, true );
		
		// Create user with API role
		$user_id = wp_create_user( $username, $password, $email );
		
		if ( is_wp_error( $user_id ) ) {
			LoggingService::log( 'Failed to create API service account: ' . $user_id->get_error_message() );
			return false;
		}
		
		// Assign API role
		$user = new \WP_User( $user_id );
		$user->set_role( self::API_ROLE );
		
		// Store service account ID
		update_option( self::SERVICE_ACCOUNT_OPTION, $user_id );
		
		LoggingService::log( "Created API service account (User ID: {$user_id})" );
		
		return $user_id;
	}
	
	/**
	 * Get the API service account user.
	 *
	 * @since 1.1.0
	 * @return \WP_User|false Service account user or false if not found.
	 */
	public static function get_service_account() {
		$user_id = self::ensure_service_account();
		
		if ( ! $user_id ) {
			return false;
		}
		
		return get_user_by( 'id', $user_id );
	}
	
	/**
	 * Check if a user has the required API capabilities.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $user_id    User ID to check.
	 * @param string $capability Capability to check.
	 * @return bool True if user has capability, false otherwise.
	 */
	public static function user_can_api( int $user_id, string $capability ): bool {
		$user = get_user_by( 'id', $user_id );
		
		if ( ! $user ) {
			return false;
		}
		
		return $user->has_cap( $capability );
	}
	
	/**
	 * Log API operation for audit trail.
	 *
	 * @since 1.1.0
	 *
	 * @param string $operation Operation performed.
	 * @param array  $context   Additional context data.
	 * @return void
	 */
	public static function log_api_operation( string $operation, array $context = [] ): void {
		$log_data = array_merge(
			[
				'operation'    => $operation,
				'user_id'      => get_current_user_id(),
				'user_ip'      => self::sanitize_ip_address( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ),
				'user_agent'   => self::sanitize_user_agent( $_SERVER['HTTP_USER_AGENT'] ?? 'unknown' ),
				'timestamp'    => current_time( 'mysql', true ),
			],
			$context
		);
		
		LoggingService::log( 'API Operation: ' . wp_json_encode( $log_data ) );
	}
	
	/**
	 * Sanitize and validate IP address for logging
	 *
	 * @param string $ip Raw IP address.
	 * @return string Sanitized IP or 'unknown' if invalid.
	 */
	private static function sanitize_ip_address( string $ip ): string {
		if ( $ip === 'unknown' ) {
			return 'unknown';
		}
		
		// Remove any non-IP characters
		$ip = preg_replace( '/[^0-9a-fA-F:.]/', '', $ip );
		
		// Validate IPv4 or IPv6
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
			// Hash IP for privacy while maintaining uniqueness for rate limiting
			return 'ip_' . substr( hash( 'sha256', $ip . wp_salt() ), 0, 12 );
		}
		
		return 'unknown';
	}
	
	/**
	 * Sanitize user agent string for logging
	 *
	 * @param string $user_agent Raw user agent string.
	 * @return string Sanitized user agent.
	 */
	private static function sanitize_user_agent( string $user_agent ): string {
		if ( $user_agent === 'unknown' ) {
			return 'unknown';
		}
		
		// Remove potentially sensitive information and limit length
		$user_agent = sanitize_text_field( $user_agent );
		$user_agent = substr( $user_agent, 0, 200 ); // Limit length
		
		// Extract only browser/OS info, remove specific version details
		if ( preg_match( '/^(\w+)/', $user_agent, $matches ) ) {
			return $matches[1] . '_browser';
		}
		
		return 'generic_browser';
	}
	
	/**
	 * Clean up API user role and service account on plugin deactivation.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function cleanup(): void {
		// Get service account before cleanup
		$service_user_id = get_option( self::SERVICE_ACCOUNT_OPTION );
		
		// Remove service account user
		if ( $service_user_id ) {
			wp_delete_user( $service_user_id );
			delete_option( self::SERVICE_ACCOUNT_OPTION );
		}
		
		// Remove API role
		remove_role( self::API_ROLE );
		
		LoggingService::log( 'Cleaned up API user role and service account' );
	}
}