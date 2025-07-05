<?php
/**
 * SecurityErrorHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security-aware error handling system.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class SecurityErrorHandler {
	/**
	 * Security event types.
	 */
	public const EVENT_BRUTE_FORCE         = 'brute_force';
	public const EVENT_INJECTION_ATTEMPT   = 'injection_attempt';
	public const EVENT_UNAUTHORIZED_ACCESS = 'unauthorized_access';
	public const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';
	public const EVENT_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
	public const EVENT_MALICIOUS_REQUEST   = 'malicious_request';
	public const EVENT_DATA_BREACH_ATTEMPT = 'data_breach_attempt';

	/**
	 * Security monitoring thresholds.
	 *
	 * @var array<string, array{threshold: int, window: int, action: string}>
	 */
	private static array $security_thresholds = array(
		self::EVENT_BRUTE_FORCE         => array(
			'threshold' => 5,
			'window'    => 300,     // 5 minutes.
			'action'    => 'block_ip',
		),
		self::EVENT_INJECTION_ATTEMPT   => array(
			'threshold' => 3,
			'window'    => 600,     // 10 minutes.
			'action'    => 'block_ip',
		),
		self::EVENT_UNAUTHORIZED_ACCESS => array(
			'threshold' => 10,
			'window'    => 900,     // 15 minutes.
			'action'    => 'notify_admin',
		),
		self::EVENT_SUSPICIOUS_ACTIVITY => array(
			'threshold' => 20,
			'window'    => 1800,    // 30 minutes.
			'action'    => 'increase_monitoring',
		),
	);

	/**
	 * Security event tracking.
	 *
	 * @var array<string, array<string, array>>
	 */
	private static array $security_events = array();

	/**
	 * Blocked IPs and their expiration times.
	 *
	 * @var array<string, int>
	 */
	private static array $blocked_ips = array();

	/**
	 * Rate limiting data.
	 *
	 * @var array<string, array{count: int, window_start: int, blocked_until: int}>
	 */
	private static array $rate_limits = array();

	/**
	 * Suspicious patterns for detection.
	 *
	 * @var array<string, array<string>>
	 */
	private static array $suspicious_patterns = array(
		'sql_injection'     => array(
			'/(\bUNION\s+SELECT\b)|(\bSELECT\s+\*\s+FROM\b)|(\bDROP\s+TABLE\b)/i',
			'/(\bINSERT\s+INTO\b)|(\bUPDATE\s+SET\b)|(\bDELETE\s+FROM\b)/i',
			'/(\b(OR|AND)\s+\d+\s*=\s*\d+\b)|(\b(OR|AND)\s+["\'].*["\']\s*=\s*["\'].*["\'])/i',
		),
		'xss_attempt'       => array(
			'/<script[^>]*>.*?<\/script>/i',
			'/javascript\s*:/i',
			'/on\w+\s*=\s*["\'][^"\']*["\']/',
		),
		'path_traversal'    => array(
			'/\.\.\//',
			'/\.\.\\\\/',
			'/\/(etc|proc|dev|sys)\//i',
		),
		'command_injection' => array(
			'/[\|;&`]/',
			'/\b(cat|ls|pwd|whoami|id|uname)\b/i',
		),
	);

	/**
	 * Initialize security error handler.
	 */
	public static function init(): void {
		// Load existing security data.
		self::load_security_data();

		// Register security monitoring hooks.
		add_action( 'wp_login_failed', array( self::class, 'handle_login_failure' ) );
		add_action( 'wp_login', array( self::class, 'handle_successful_login' ), 10, 2 );
		add_filter( 'authenticate', array( self::class, 'check_ip_before_auth' ), 1, 3 );

		// Monitor specific WordPress events.
		add_action( 'wp_ajax_nopriv_*', array( self::class, 'monitor_ajax_requests' ), 1 );
		add_action( 'wp_ajax_*', array( self::class, 'monitor_ajax_requests' ), 1 );
		add_action( 'rest_api_init', array( self::class, 'monitor_rest_requests' ) );

		// Clean up old security data.
		if ( ! wp_next_scheduled( 'nuclen_cleanup_security_data' ) ) {
			wp_schedule_event( time(), 'hourly', 'nuclen_cleanup_security_data' );
		}
		add_action( 'nuclen_cleanup_security_data', array( self::class, 'cleanup_security_data' ) );

		// Register shutdown handler to save security data.
		register_shutdown_function( array( self::class, 'save_security_data' ) );
	}

	/**
	 * Handle security-related error with threat analysis.
	 *
	 * @param string $event_type Security event type.
	 * @param array  $context    Security context.
	 * @return ErrorContext Error context with security analysis.
	 */
	public static function handle_security_error( string $event_type, array $context = array() ): ErrorContext {
		$client_ip   = self::get_client_ip();
		$user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Enhanced security context.
		$security_context = array_merge(
			$context,
			array(
				'event_type'     => $event_type,
				'client_ip'      => $client_ip,
				'user_agent'     => $user_agent,
				'request_uri'    => $request_uri,
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
				'referer'        => $_SERVER['HTTP_REFERER'] ?? '',
				'threat_level'   => self::assess_threat_level( $event_type, $context ),
				'geolocation'    => self::get_ip_geolocation( $client_ip ),
				'is_known_bot'   => self::is_known_bot( $user_agent ),
				'similar_events' => self::count_similar_events( $event_type, $client_ip ),
			)
		);

		// Track security event.
		self::track_security_event( $event_type, $client_ip, $security_context );

		// Create error context.
		$error_context = ErrorManager::handle_error(
			"Security event detected: {$event_type}",
			ErrorManager::SEVERITY_HIGH,
			ErrorManager::CATEGORY_SECURITY,
			$security_context
		);

		// Apply automatic security measures.
		self::apply_security_measures( $event_type, $client_ip, $error_context );

		// Check for patterns that require immediate response.
		if ( self::requires_immediate_response( $event_type, $security_context ) ) {
			self::trigger_immediate_response( $error_context );
		}

		return $error_context;
	}

	/**
	 * Detect and handle suspicious request patterns.
	 *
	 * @param array $request_data Request data to analyze.
	 * @return bool Whether suspicious activity was detected.
	 */
	public static function detect_suspicious_activity( array $request_data ): bool {
		$suspicious_detected = false;
		$detected_patterns   = array();

		// Check each category of suspicious patterns.
		foreach ( self::$suspicious_patterns as $category => $patterns ) {
			foreach ( $patterns as $pattern ) {
				foreach ( $request_data as $key => $value ) {
					if ( is_string( $value ) && preg_match( $pattern, $value ) ) {
						$suspicious_detected = true;
						$detected_patterns[] = array(
							'category' => $category,
							'pattern'  => $pattern,
							'field'    => $key,
							'value'    => self::sanitize_for_logging( $value ),
						);
					}
				}
			}
		}

		if ( $suspicious_detected ) {
			self::handle_security_error(
				self::EVENT_SUSPICIOUS_ACTIVITY,
				array(
					'detected_patterns' => $detected_patterns,
					'request_data_keys' => array_keys( $request_data ),
				)
			);
		}

		return $suspicious_detected;
	}

	/**
	 * Check if IP should be blocked.
	 *
	 * @param string $ip IP address to check.
	 * @return bool Whether IP is blocked.
	 */
	public static function is_ip_blocked( string $ip ): bool {
		if ( ! isset( self::$blocked_ips[ $ip ] ) ) {
			return false;
		}

		// Check if block has expired.
		if ( self::$blocked_ips[ $ip ] < time() ) {
			unset( self::$blocked_ips[ $ip ] );
			return false;
		}

		return true;
	}

	/**
	 * Block IP address for security reasons.
	 *
	 * @param string $ip       IP address to block.
	 * @param int    $duration Block duration in seconds.
	 * @param string $reason   Block reason.
	 */
	public static function block_ip( string $ip, int $duration = 3600, string $reason = 'security_violation' ): void {
		$expires                  = time() + $duration;
		self::$blocked_ips[ $ip ] = $expires;

		// Log the block.
		ErrorManager::handle_error(
			"IP address blocked: {$ip}",
			ErrorManager::SEVERITY_MEDIUM,
			ErrorManager::CATEGORY_SECURITY,
			array(
				'blocked_ip' => $ip,
				'duration'   => $duration,
				'expires'    => $expires,
				'reason'     => $reason,
			)
		);

		// Notify administrators for long blocks.
		if ( $duration > 86400 ) { // More than 24 hours.
			self::notify_admin_of_block( $ip, $duration, $reason );
		}
	}

	/**
	 * Apply rate limiting to prevent abuse.
	 *
	 * @param string $identifier Rate limit identifier (IP, user, etc.).
	 * @param int    $limit      Request limit per window.
	 * @param int    $window     Time window in seconds.
	 * @return bool Whether request should be allowed.
	 */
	public static function apply_rate_limit( string $identifier, int $limit = 60, int $window = 3600 ): bool {
		$now = time();

		if ( ! isset( self::$rate_limits[ $identifier ] ) ) {
			self::$rate_limits[ $identifier ] = array(
				'count'         => 1,
				'window_start'  => $now,
				'blocked_until' => 0,
			);
			return true;
		}

		$rate_data = &self::$rate_limits[ $identifier ];

		// Check if currently blocked.
		if ( $rate_data['blocked_until'] > $now ) {
			return false;
		}

		// Reset window if expired.
		if ( $now - $rate_data['window_start'] > $window ) {
			$rate_data['count']         = 1;
			$rate_data['window_start']  = $now;
			$rate_data['blocked_until'] = 0;
			return true;
		}

		// Increment counter.
		++$rate_data['count'];

		// Check if limit exceeded.
		if ( $rate_data['count'] > $limit ) {
			// Block for progressively longer periods.
			$block_duration             = min( 3600, 60 * pow( 2, floor( $rate_data['count'] / $limit ) - 1 ) );
			$rate_data['blocked_until'] = $now + $block_duration;

			// Track rate limit violation.
			self::handle_security_error(
				self::EVENT_RATE_LIMIT_EXCEEDED,
				array(
					'identifier'     => $identifier,
					'limit'          => $limit,
					'window'         => $window,
					'count'          => $rate_data['count'],
					'block_duration' => $block_duration,
				)
			);

			return false;
		}

		return true;
	}

	/**
	 * Sanitize user input with security considerations.
	 *
	 * @param mixed $input Input to sanitize.
	 * @return mixed Sanitized input.
	 */
	public static function sanitize_input( $input ) {
		if ( is_array( $input ) ) {
			return array_map( array( self::class, 'sanitize_input' ), $input );
		}

		if ( ! is_string( $input ) ) {
			return $input;
		}

		// Check for suspicious patterns before sanitization.
		foreach ( self::$suspicious_patterns as $category => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $input ) ) {
					self::handle_security_error(
						self::EVENT_INJECTION_ATTEMPT,
						array(
							'category' => $category,
							'input'    => self::sanitize_for_logging( $input ),
							'pattern'  => $pattern,
						)
					);
				}
			}
		}

		// Sanitize the input.
		$sanitized = wp_kses( $input, array() ); // Remove all HTML.
		$sanitized = sanitize_text_field( $sanitized );

		return $sanitized;
	}

	/**
	 * Get security dashboard data.
	 *
	 * @param int $time_window Time window in seconds.
	 * @return array Security dashboard data.
	 */
	public static function get_security_dashboard( int $time_window = 86400 ): array {
		$cutoff    = time() - $time_window;
		$dashboard = array(
			'total_events'       => 0,
			'events_by_type'     => array(),
			'top_threat_ips'     => array(),
			'blocked_ips'        => count( self::$blocked_ips ),
			'active_rate_limits' => 0,
			'threat_level'       => 'low',
		);

		// Analyze security events.
		foreach ( self::$security_events as $event_type => $events ) {
			$recent_events = array_filter(
				$events,
				function ( $event ) use ( $cutoff ) {
					return $event['timestamp'] > $cutoff;
				}
			);

			$event_count                                = count( $recent_events );
			$dashboard['total_events']                 += $event_count;
			$dashboard['events_by_type'][ $event_type ] = $event_count;
		}

		// Count active rate limits.
		$now = time();
		foreach ( self::$rate_limits as $rate_data ) {
			if ( $rate_data['blocked_until'] > $now ) {
				++$dashboard['active_rate_limits'];
			}
		}

		// Determine overall threat level.
		$dashboard['threat_level'] = self::calculate_overall_threat_level( $dashboard );

		return $dashboard;
	}

	/**
	 * Handle login failure for brute force detection.
	 *
	 * @param string $username Failed login username.
	 */
	public static function handle_login_failure( string $username ): void {
		$client_ip = self::get_client_ip();

		self::handle_security_error(
			self::EVENT_BRUTE_FORCE,
			array(
				'username'     => $username,
				'failed_login' => true,
			)
		);
	}

	/**
	 * Handle successful login.
	 *
	 * @param string   $user_login User login.
	 * @param \WP_User $user       User object.
	 */
	public static function handle_successful_login( string $user_login, \WP_User $user ): void {
		$client_ip = self::get_client_ip();

		// Clear any brute force tracking for this IP on successful login.
		if ( isset( self::$security_events[ self::EVENT_BRUTE_FORCE ] ) ) {
			self::$security_events[ self::EVENT_BRUTE_FORCE ] = array_filter(
				self::$security_events[ self::EVENT_BRUTE_FORCE ],
				function ( $event ) use ( $client_ip ) {
					return $event['client_ip'] !== $client_ip;
				}
			);
		}
	}

	/**
	 * Check IP before authentication.
	 *
	 * @param \WP_User|\WP_Error|null $user     User object or error.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null User object or error.
	 */
	public static function check_ip_before_auth( $user, string $username, string $password ) {
		$client_ip = self::get_client_ip();

		if ( self::is_ip_blocked( $client_ip ) ) {
			return new \WP_Error(
				'ip_blocked',
				__( 'Access denied due to security restrictions.', 'nuclear-engagement' )
			);
		}

		return $user;
	}

	/**
	 * Monitor AJAX requests for suspicious activity.
	 */
	public static function monitor_ajax_requests(): void {
		if ( ! self::apply_rate_limit( 'ajax_' . self::get_client_ip(), 100, 3600 ) ) {
			wp_die( __( 'Too many requests. Please try again later.', 'nuclear-engagement' ), 429 );
		}

		// Check request data for suspicious patterns.
		$request_data = array_merge( $_GET, $_POST );
		self::detect_suspicious_activity( $request_data );
	}

	/**
	 * Monitor REST API requests.
	 */
	public static function monitor_rest_requests(): void {
		if ( ! self::apply_rate_limit( 'rest_' . self::get_client_ip(), 200, 3600 ) ) {
			status_header( 429 );
			wp_die( __( 'Too many requests. Please try again later.', 'nuclear-engagement' ) );
		}
	}

	/**
	 * Private helper methods for security operations.
	 */
	private static function get_client_ip(): string {
		// Implementation similar to ErrorManager::get_client_ip().
		return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	}

	private static function assess_threat_level( string $event_type, array $context ): string {
		// Implement threat level assessment logic.
		return 'medium';
	}

	private static function get_ip_geolocation( string $ip ): array {
		// Implement IP geolocation lookup.
		return array(
			'country' => 'unknown',
			'city'    => 'unknown',
		);
	}

	private static function is_known_bot( string $user_agent ): bool {
		// Implement bot detection logic.
		return false;
	}

	private static function count_similar_events( string $event_type, string $ip ): int {
		// Implement similar event counting.
		return 0;
	}

	private static function track_security_event( string $event_type, string $ip, array $context ): void {
		// Implement security event tracking.
	}

	private static function apply_security_measures( string $event_type, string $ip, ErrorContext $context ): void {
		// Implement security measure application.
	}

	private static function requires_immediate_response( string $event_type, array $context ): bool {
		// Implement immediate response requirement logic.
		return false;
	}

	private static function trigger_immediate_response( ErrorContext $context ): void {
		// Implement immediate response logic.
	}

	private static function sanitize_for_logging( string $value ): string {
		// Implement safe logging sanitization.
		return substr( $value, 0, 100 ) . ( strlen( $value ) > 100 ? '...' : '' );
	}

	private static function notify_admin_of_block( string $ip, int $duration, string $reason ): void {
		// Implement admin notification.
	}

	private static function calculate_overall_threat_level( array $dashboard ): string {
		// Implement threat level calculation.
		return 'low';
	}

	private static function load_security_data(): void {
		// Implement security data loading.
	}

	private static function save_security_data(): void {
		// Implement security data saving.
	}

	public static function cleanup_security_data(): void {
		// Implement security data cleanup.
	}
}
