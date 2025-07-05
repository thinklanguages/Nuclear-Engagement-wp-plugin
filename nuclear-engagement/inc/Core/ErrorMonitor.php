<?php
/**
 * ErrorMonitor.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error monitoring, analytics, and notifications.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ErrorMonitor {
	/**
	 * Error statistics storage.
	 *
	 * @var array<string, array{count: int, last_occurrence: int, categories: array}>
	 */
	private static array $error_stats = array();

	/**
	 * Security events tracking.
	 *
	 * @var array<string, array{count: int, last_occurrence: int, severity: string}>
	 */
	private static array $security_events = array();

	/**
	 * Rate limiting storage.
	 *
	 * @var array<string, array{count: int, window_start: int}>
	 */
	private static array $rate_limits = array();

	/**
	 * Notification thresholds.
	 *
	 * @var array<string, array{threshold: int, interval: int}>
	 */
	private static array $notification_thresholds = array(
		'critical' => array(
			'threshold' => 1,
			'interval'  => 300,
		),     // Immediate.
		'high'     => array(
			'threshold' => 5,
			'interval'  => 600,
		),         // 5 in 10 min.
		'medium'   => array(
			'threshold' => 10,
			'interval'  => 1800,
		),     // 10 in 30 min.
		'low'      => array(
			'threshold' => 20,
			'interval'  => 3600,
		),        // 20 in 1 hour.
	);

	/**
	 * Initialize error monitoring.
	 */
	public static function init(): void {
		// Schedule cleanup tasks.
		if ( ! wp_next_scheduled( 'nuclen_cleanup_error_data' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_error_data' );
		}
		add_action( 'nuclen_cleanup_error_data', array( self::class, 'cleanup_old_data' ) );

		// Schedule analytics generation.
		if ( ! wp_next_scheduled( 'nuclen_generate_error_analytics' ) ) {
			wp_schedule_event( time(), 'hourly', 'nuclen_generate_error_analytics' );
		}
		add_action( 'nuclen_generate_error_analytics', array( self::class, 'generate_analytics' ) );
	}

	/**
	 * Track error occurrence for analytics.
	 *
	 * @param ErrorContext $error_context Error context.
	 */
	public static function track_error( ErrorContext $error_context ): void {
		$category = $error_context->get_category();
		$severity = $error_context->get_severity();
		$key      = "{$category}:{$severity}";

		// Update statistics.
		if ( ! isset( self::$error_stats[ $key ] ) ) {
			self::$error_stats[ $key ] = array(
				'count'           => 0,
				'last_occurrence' => 0,
				'categories'      => array(),
			);
		}

		++self::$error_stats[ $key ]['count'];
		self::$error_stats[ $key ]['last_occurrence']         = time();
		self::$error_stats[ $key ]['categories'][ $category ] =
			( self::$error_stats[ $key ]['categories'][ $category ] ?? 0 ) + 1;

		// Store in database for persistence.
		self::store_error_stat( $error_context );

		// Check notification thresholds.
		self::check_notification_threshold( $error_context );
	}

	/**
	 * Track security event.
	 *
	 * @param ErrorContext $error_context Error context.
	 */
	public static function track_security_event( ErrorContext $error_context ): void {
		$event_type = self::determine_security_event_type( $error_context );

		if ( ! isset( self::$security_events[ $event_type ] ) ) {
			self::$security_events[ $event_type ] = array(
				'count'           => 0,
				'last_occurrence' => 0,
				'severity'        => $error_context->get_severity(),
			);
		}

		++self::$security_events[ $event_type ]['count'];
		self::$security_events[ $event_type ]['last_occurrence'] = time();

		// Store in database.
		self::store_security_event( $error_context, $event_type );

		// Send immediate notification for critical security events.
		if ( $error_context->get_severity() === 'critical' ) {
			self::send_security_alert( $error_context, $event_type );
		}
	}

	/**
	 * Check if error rate is limited.
	 *
	 * @param string $category Error category.
	 * @param int    $limit Rate limit.
	 * @param int    $window Time window in seconds.
	 * @return bool Whether rate is limited.
	 */
	public static function is_rate_limited( string $category, int $limit = 10, int $window = 300 ): bool {
		$key = "rate_limit_{$category}";
		$now = time();

		if ( ! isset( self::$rate_limits[ $key ] ) ) {
			self::$rate_limits[ $key ] = array(
				'count'        => 0,
				'window_start' => $now,
			);
		}

		$rate_data = &self::$rate_limits[ $key ];

		// Reset window if expired.
		if ( $now - $rate_data['window_start'] >= $window ) {
			$rate_data['count']        = 0;
			$rate_data['window_start'] = $now;
		}

		// Check limit.
		if ( $rate_data['count'] >= $limit ) {
			return true;
		}

		++$rate_data['count'];
		return false;
	}

	/**
	 * Get error statistics.
	 *
	 * @param int $hours Hours to look back (default 24).
	 * @return array Error statistics.
	 */
	public static function get_error_stats( int $hours = 24 ): array {
		global $wpdb;

		$since = time() - ( $hours * HOUR_IN_SECONDS );

		$stats = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT 
				category,
				severity,
				COUNT(*) as count,
				MIN(timestamp) as first_occurrence,
				MAX(timestamp) as last_occurrence
			FROM {$wpdb->prefix}nuclen_error_log 
			WHERE timestamp >= %d
			GROUP BY category, severity
			ORDER BY count DESC
		",
				$since
			),
			ARRAY_A
		);

		return $stats ?: array();
	}

	/**
	 * Get security event statistics.
	 *
	 * @param int $hours Hours to look back.
	 * @return array Security event statistics.
	 */
	public static function get_security_stats( int $hours = 24 ): array {
		global $wpdb;

		$since = time() - ( $hours * HOUR_IN_SECONDS );

		$stats = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT 
				event_type,
				severity,
				COUNT(*) as count,
				MAX(timestamp) as last_occurrence
			FROM {$wpdb->prefix}nuclen_security_events
			WHERE timestamp >= %d
			GROUP BY event_type, severity
			ORDER BY count DESC
		",
				$since
			),
			ARRAY_A
		);

		return $stats ?: array();
	}

	/**
	 * Generate error trend analysis.
	 *
	 * @param int $days Days to analyze.
	 * @return array Trend analysis.
	 */
	public static function analyze_trends( int $days = 7 ): array {
		global $wpdb;

		$since = time() - ( $days * DAY_IN_SECONDS );

		// Get daily error counts.
		$daily_stats = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT 
				DATE(FROM_UNIXTIME(timestamp)) as date,
				category,
				severity,
				COUNT(*) as count
			FROM {$wpdb->prefix}nuclen_error_log
			WHERE timestamp >= %d
			GROUP BY DATE(FROM_UNIXTIME(timestamp)), category, severity
			ORDER BY date DESC
		",
				$since
			),
			ARRAY_A
		);

		// Calculate trends.
		$trends = array();
		foreach ( $daily_stats as $stat ) {
			$key = "{$stat['category']}:{$stat['severity']}";
			if ( ! isset( $trends[ $key ] ) ) {
				$trends[ $key ] = array();
			}
			$trends[ $key ][ $stat['date'] ] = (int) $stat['count'];
		}

		// Calculate trend direction for each category/severity.
		$analysis = array();
		foreach ( $trends as $key => $data ) {
			$values           = array_values( $data );
			$analysis[ $key ] = array(
				'total'   => array_sum( $values ),
				'average' => array_sum( $values ) / count( $values ),
				'trend'   => self::calculate_trend( $values ),
				'data'    => $data,
			);
		}

		return $analysis;
	}

	/**
	 * Send error notification.
	 *
	 * @param ErrorContext $error_context Error context.
	 * @param string       $notification_type Type of notification.
	 */
	public static function send_notification( ErrorContext $error_context, string $notification_type = 'standard' ): void {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}

		$subject = self::format_notification_subject( $error_context, $notification_type );
		$message = self::format_notification_message( $error_context, $notification_type );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $admin_email, $subject, $message, $headers );

		// Log notification.
		self::log_notification( $error_context, $notification_type, $admin_email );
	}

	/**
	 * Generate periodic analytics report.
	 */
	public static function generate_analytics(): void {
		$stats          = self::get_error_stats( 24 );
		$security_stats = self::get_security_stats( 24 );
		$trends         = self::analyze_trends( 7 );

		$report = array(
			'timestamp'      => time(),
			'error_stats'    => $stats,
			'security_stats' => $security_stats,
			'trends'         => $trends,
			'summary'        => self::generate_summary( $stats, $security_stats ),
		);

		// Store report.
		update_option( 'nuclen_error_analytics_report', $report );

		// Send summary if significant activity.
		if ( self::should_send_summary_report( $report ) ) {
			self::send_analytics_summary( $report );
		}
	}

	/**
	 * Clean up old error data.
	 */
	public static function cleanup_old_data(): void {
		global $wpdb;

		$retention_days = apply_filters( 'nuclen_error_log_retention_days', 30 );
		$cutoff         = time() - ( $retention_days * DAY_IN_SECONDS );

		// Clean error logs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}nuclen_error_log WHERE timestamp < %d",
				$cutoff
			)
		);

		// Clean security events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}nuclen_security_events WHERE timestamp < %d",
				$cutoff
			)
		);

		// Clean old analytics reports.
		$old_reports     = get_option( 'nuclen_error_analytics_reports', array() );
		$cleaned_reports = array_filter(
			$old_reports,
			function ( $report ) use ( $cutoff ) {
				return $report['timestamp'] >= $cutoff;
			}
		);
		update_option( 'nuclen_error_analytics_reports', $cleaned_reports );
	}

	/**
	 * Check notification threshold and send if needed.
	 *
	 * @param ErrorContext $error_context Error context.
	 */
	private static function check_notification_threshold( ErrorContext $error_context ): void {
		$severity         = $error_context->get_severity();
		$threshold_config = self::$notification_thresholds[ $severity ] ?? null;

		if ( ! $threshold_config ) {
			return;
		}

		$key           = "notification_threshold_{$severity}";
		$current_count = (int) get_transient( $key );
		++$current_count;

		if ( $current_count >= $threshold_config['threshold'] ) {
			self::send_notification( $error_context, 'threshold_reached' );
			delete_transient( $key ); // Reset counter.
		} else {
			set_transient( $key, $current_count, $threshold_config['interval'] );
		}
	}

	/**
	 * Store error statistic in database.
	 *
	 * @param ErrorContext $error_context Error context.
	 */
	private static function store_error_stat( ErrorContext $error_context ): void {
		global $wpdb;

		self::maybe_create_error_tables();

		$wpdb->insert(
			$wpdb->prefix . 'nuclen_error_log',
			array(
				'message'   => $error_context->get_message(),
				'category'  => $error_context->get_category(),
				'severity'  => $error_context->get_severity(),
				'context'   => wp_json_encode( $error_context->get_context() ),
				'timestamp' => time(),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Store security event in database.
	 *
	 * @param ErrorContext $error_context Error context.
	 * @param string       $event_type Event type.
	 */
	private static function store_security_event( ErrorContext $error_context, string $event_type ): void {
		global $wpdb;

		self::maybe_create_error_tables();

		$context = $error_context->get_context();
		$wpdb->insert(
			$wpdb->prefix . 'nuclen_security_events',
			array(
				'event_type' => $event_type,
				'severity'   => $error_context->get_severity(),
				'user_ip'    => $context['user_ip'] ?? '',
				'user_id'    => $context['user_id'] ?? 0,
				'message'    => $error_context->get_message(),
				'context'    => wp_json_encode( $context ),
				'timestamp'  => time(),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
		);
	}

	/**
	 * Create database tables if they don't exist.
	 */
	private static function maybe_create_error_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Error log table.
		$error_log_table = $wpdb->prefix . 'nuclen_error_log';
		if ( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_var( "SHOW TABLES LIKE '{$error_log_table}'" ) !== $error_log_table ) {
			$sql = "CREATE TABLE {$error_log_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				message text NOT NULL,
				category varchar(50) NOT NULL,
				severity varchar(20) NOT NULL,
				context longtext DEFAULT NULL,
				timestamp int(11) NOT NULL,
				PRIMARY KEY (id),
				KEY category_severity (category, severity),
				KEY timestamp (timestamp)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		// Security events table.
		$security_table = $wpdb->prefix . 'nuclen_security_events';
		if ( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_var( "SHOW TABLES LIKE '{$security_table}'" ) !== $security_table ) {
			$sql = "CREATE TABLE {$security_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_type varchar(50) NOT NULL,
				severity varchar(20) NOT NULL,
				user_ip varchar(45) DEFAULT NULL,
				user_id bigint(20) unsigned DEFAULT NULL,
				message text NOT NULL,
				context longtext DEFAULT NULL,
				timestamp int(11) NOT NULL,
				PRIMARY KEY (id),
				KEY event_type (event_type),
				KEY user_ip (user_ip),
				KEY timestamp (timestamp)
			) {$charset_collate};";

			dbDelta( $sql );
		}
	}

	// Helper methods.
	private static function determine_security_event_type( ErrorContext $context ): string {
		$message = strtolower( $context->get_message() );
		if ( strpos( $message, 'login' ) !== false ) {
			return 'failed_login';
		}
		if ( strpos( $message, 'permission' ) !== false ) {
			return 'permission_denied';
		}
		if ( strpos( $message, 'injection' ) !== false ) {
			return 'injection_attempt';
		}
		return 'security_violation';
	}

	private static function send_security_alert( ErrorContext $context, string $event_type ): void {
		self::send_notification( $context, 'security_alert' );
	}

	private static function format_notification_subject( ErrorContext $context, string $type ): string {
		$site_name = get_bloginfo( 'name' );
		$severity  = ucfirst( $context->get_severity() );
		return "[{$site_name}] {$severity} Error Alert - {$context->get_category()}";
	}

	private static function format_notification_message( ErrorContext $context, string $type ): string {
		$message  = "<h2>Error Report</h2>\n";
		$message .= '<p><strong>Message:</strong> ' . esc_html( $context->get_message() ) . "</p>\n";
		$message .= '<p><strong>Category:</strong> ' . esc_html( $context->get_category() ) . "</p>\n";
		$message .= '<p><strong>Severity:</strong> ' . esc_html( $context->get_severity() ) . "</p>\n";
		$message .= '<p><strong>Time:</strong> ' . date( 'Y-m-d H:i:s' ) . "</p>\n";

		$context_data = $context->get_context();
		if ( ! empty( $context_data ) ) {
			$message .= "<h3>Context</h3>\n<pre>" . esc_html( wp_json_encode( $context_data, JSON_PRETTY_PRINT ) ) . "</pre>\n";
		}

		return $message;
	}

	private static function log_notification( ErrorContext $context, string $type, string $recipient ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "Nuclear Engagement: Notification sent - Type: {$type}, Recipient: {$recipient}, Error: {$context->get_message()}" );
	}

	private static function calculate_trend( array $values ): string {
		if ( count( $values ) < 2 ) {
			return 'stable';
		}

		$first_half  = array_slice( $values, 0, ceil( count( $values ) / 2 ) );
		$second_half = array_slice( $values, floor( count( $values ) / 2 ) );

		$first_avg  = array_sum( $first_half ) / count( $first_half );
		$second_avg = array_sum( $second_half ) / count( $second_half );

		$change = ( $second_avg - $first_avg ) / ( $first_avg ?: 1 );

		if ( $change > 0.2 ) {
			return 'increasing';
		}
		if ( $change < -0.2 ) {
			return 'decreasing';
		}
		return 'stable';
	}

	private static function generate_summary( array $error_stats, array $security_stats ): array {
		$total_errors   = array_sum( array_column( $error_stats, 'count' ) );
		$total_security = array_sum( array_column( $security_stats, 'count' ) );

		return array(
			'total_errors'          => $total_errors,
			'total_security_events' => $total_security,
			'most_common_error'     => $error_stats[0]['category'] ?? 'none',
			'critical_errors'       => count(
				array_filter(
					$error_stats,
					function ( $stat ) {
						return $stat['severity'] === 'critical';
					}
				)
			),
		);
	}

	private static function should_send_summary_report( array $report ): bool {
		$summary = $report['summary'];
		return $summary['total_errors'] > 50 || $summary['critical_errors'] > 0 || $summary['total_security_events'] > 10;
	}

	private static function send_analytics_summary( array $report ): void {
		// Implementation for sending analytics summary.
	}
}
