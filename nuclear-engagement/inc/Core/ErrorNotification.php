<?php
/**
 * ErrorNotification.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error notification and monitoring functionality.
 *
 * Handles error alerts, notifications, and monitoring integrations.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ErrorNotification {
	/**
	 * Security event monitoring.
	 *
	 * @var array<string, array{count: int, first_seen: int, last_seen: int, sources: array}>
	 */
	private static array $security_events = array();

	/**
	 * Notification thresholds.
	 */
	private const CRITICAL_THRESHOLD = 1;    // Immediate notification.
	private const HIGH_THRESHOLD     = 5;        // Notify after 5 occurrences.
	private const MEDIUM_THRESHOLD   = 10;     // Notify after 10 occurrences.
	private const LOW_THRESHOLD      = 20;        // Notify after 20 occurrences.

	/**
	 * Initialize notification system.
	 */
	public static function init(): void {
		// Load security events.
		self::$security_events = get_option( 'nuclen_security_events', array() );
	}

	/**
	 * Process error for notifications.
	 *
	 * @param array $error_data Error data from ErrorHandler.
	 */
	public static function process_error_notification( array $error_data ): void {
		// Track security events.
		if ( $error_data['category'] === ErrorHandler::CATEGORY_SECURITY ) {
			self::track_security_event( $error_data );
		}

		// Check if notification should be sent.
		if ( self::should_notify( $error_data ) ) {
			self::send_notification( $error_data );
		}
	}

	/**
	 * Track security events.
	 *
	 * @param array $error_data Error data.
	 */
	private static function track_security_event( array $error_data ): void {
		$event_key = md5( $error_data['message'] );
		$source    = self::get_request_source();

		if ( ! isset( self::$security_events[ $event_key ] ) ) {
			self::$security_events[ $event_key ] = array(
				'count'      => 0,
				'first_seen' => $error_data['timestamp'],
				'last_seen'  => $error_data['timestamp'],
				'sources'    => array(),
				'message'    => $error_data['message'],
				'severity'   => $error_data['severity'],
			);
		}

		++self::$security_events[ $event_key ]['count'];
		self::$security_events[ $event_key ]['last_seen'] = $error_data['timestamp'];

		if ( ! in_array( $source, self::$security_events[ $event_key ]['sources'], true ) ) {
			self::$security_events[ $event_key ]['sources'][] = $source;
		}

		// Save to database.
		update_option( 'nuclen_security_events', self::$security_events );
	}

	/**
	 * Determine if notification should be sent.
	 *
	 * @param array $error_data Error data.
	 * @return bool True if notification should be sent.
	 */
	private static function should_notify( array $error_data ): bool {
		$severity = $error_data['severity'];
		$category = $error_data['category'];

		// Always notify for critical errors.
		if ( $severity === ErrorHandler::SEVERITY_CRITICAL ) {
			return true;
		}

		// Check rate limiting.
		if ( ErrorAnalytics::is_rate_limited( $category, $severity, 5, 3600 ) ) {
			return false;
		}

		// Get error count for this type.
		$stats       = ErrorAnalytics::get_error_stats( time() - 3600 ); // Last hour.
		$error_count = $stats['by_severity'][ $severity ] ?? 0;

		// Check thresholds.
		switch ( $severity ) {
			case ErrorHandler::SEVERITY_HIGH:
				return $error_count >= self::HIGH_THRESHOLD;
			case ErrorHandler::SEVERITY_MEDIUM:
				return $error_count >= self::MEDIUM_THRESHOLD;
			case ErrorHandler::SEVERITY_LOW:
				return $error_count >= self::LOW_THRESHOLD;
			default:
				return false;
		}
	}

	/**
	 * Send error notification.
	 *
	 * @param array $error_data Error data.
	 */
	private static function send_notification( array $error_data ): void {
		// Email notification to admin.
		self::send_email_notification( $error_data );

		// Log high-priority notification.
		if ( in_array( $error_data['severity'], array( ErrorHandler::SEVERITY_CRITICAL, ErrorHandler::SEVERITY_HIGH ), true ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[NUCLEAR-ENGAGEMENT-ALERT] %s error in %s: %s (ID: %s)',
					strtoupper( $error_data['severity'] ),
					$error_data['category'],
					$error_data['message'],
					$error_data['id']
				)
			);
		}
	}

	/**
	 * Send email notification.
	 *
	 * @param array $error_data Error data.
	 */
	private static function send_email_notification( array $error_data ): void {
		$admin_email = get_option( 'admin_email' );
		if ( empty( $admin_email ) ) {
			return;
		}

		$subject = sprintf(
			'[%s] Nuclear Engagement Error Alert - %s',
			get_bloginfo( 'name' ),
			strtoupper( $error_data['severity'] )
		);

		$message = self::format_notification_message( $error_data );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . $admin_email . '>',
		);

		wp_mail( $admin_email, $subject, $message, $headers );
	}

	/**
	 * Format notification message.
	 *
	 * @param array $error_data Error data.
	 * @return string Formatted message.
	 */
	private static function format_notification_message( array $error_data ): string {
		$stats = ErrorAnalytics::get_error_stats( time() - 3600 );

		$message  = '<html><body>';
		$message .= '<h2>Nuclear Engagement Error Alert</h2>';
		$message .= '<table border="1" cellpadding="5" cellspacing="0">';
		$message .= '<tr><td><strong>Error ID:</strong></td><td>' . esc_html( $error_data['id'] ) . '</td></tr>';
		$message .= '<tr><td><strong>Severity:</strong></td><td>' . esc_html( strtoupper( $error_data['severity'] ) ) . '</td></tr>';
		$message .= '<tr><td><strong>Category:</strong></td><td>' . esc_html( $error_data['category'] ) . '</td></tr>';
		$message .= '<tr><td><strong>Message:</strong></td><td>' . esc_html( $error_data['message'] ) . '</td></tr>';
		$message .= '<tr><td><strong>Time:</strong></td><td>' . date( 'Y-m-d H:i:s T', $error_data['timestamp'] ) . '</td></tr>';
		$message .= '<tr><td><strong>Site:</strong></td><td>' . esc_html( get_site_url() ) . '</td></tr>';
		$message .= '</table>';

		if ( ! empty( $error_data['exception'] ) ) {
			$message .= '<h3>Exception Details</h3>';
			$message .= '<table border="1" cellpadding="5" cellspacing="0">';
			$message .= '<tr><td><strong>Class:</strong></td><td>' . esc_html( $error_data['exception']['class'] ) . '</td></tr>';
			$message .= '<tr><td><strong>File:</strong></td><td>' . esc_html( $error_data['exception']['file'] ) . '</td></tr>';
			$message .= '<tr><td><strong>Line:</strong></td><td>' . esc_html( $error_data['exception']['line'] ) . '</td></tr>';
			$message .= '</table>';
		}

		$message .= '<h3>Recent Error Summary (Last Hour)</h3>';
		$message .= '<p><strong>Total Errors:</strong> ' . $stats['total_errors'] . '</p>';
		$message .= '<p><strong>Error Rate:</strong> ' . $stats['error_rate'] . ' errors/hour</p>';

		if ( ! empty( $stats['by_category'] ) ) {
			$message .= '<p><strong>By Category:</strong><br>';
			foreach ( $stats['by_category'] as $category => $count ) {
				$message .= '• ' . esc_html( $category ) . ': ' . $count . '<br>';
			}
			$message .= '</p>';
		}

		$message .= '<hr>';
		$message .= '<p><small>This is an automated alert from Nuclear Engagement Plugin.</small></p>';
		$message .= '</body></html>';

		return $message;
	}

	/**
	 * Get security events summary.
	 *
	 * @param int $since_timestamp Get events since this timestamp.
	 * @return array Security events summary.
	 */
	public static function get_security_events( int $since_timestamp = 0 ): array {
		$events = array();

		foreach ( self::$security_events as $key => $event ) {
			if ( $event['last_seen'] >= $since_timestamp ) {
				$events[] = $event;
			}
		}

		// Sort by last seen (most recent first).
		usort(
			$events,
			function ( $a, $b ) {
				return $b['last_seen'] - $a['last_seen'];
			}
		);

		return $events;
	}

	/**
	 * Get request source for tracking.
	 *
	 * @return string Request source identifier.
	 */
	private static function get_request_source(): string {
		$ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

		return md5( $ip . $user_agent );
	}

	/**
	 * Clean up old security events.
	 */
	public static function cleanup_security_events(): void {
		$cutoff = time() - ( 30 * DAY_IN_SECONDS ); // Keep 30 days.

		foreach ( self::$security_events as $key => $event ) {
			if ( $event['last_seen'] < $cutoff ) {
				unset( self::$security_events[ $key ] );
			}
		}

		update_option( 'nuclen_security_events', self::$security_events );
	}
}
