<?php
/**
 * ErrorAnalytics.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error analytics and tracking functionality.
 *
 * Handles error statistics, tracking, and trend analysis.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ErrorAnalytics {
	/**
	 * Error tracking data.
	 *
	 * @var array<string, array>
	 */
	private static array $error_tracking = array();

	/**
	 * Rate limiting for error responses.
	 *
	 * @var array<string, array{count: int, window_start: int}>
	 */
	private static array $rate_limits = array();

	/**
	 * Initialize analytics.
	 */
	public static function init(): void {
		// Clean up old analytics data periodically.
		if ( ! wp_next_scheduled( 'nuclen_cleanup_analytics_data' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_analytics_data' );
		}
		add_action( 'nuclen_cleanup_analytics_data', array( self::class, 'cleanup_analytics_data' ) );
	}

	/**
	 * Track error occurrence.
	 *
	 * @param array $error_data Error data from ErrorHandler.
	 */
	public static function track_error( array $error_data ): void {
		$key = $error_data['category'] . ':' . $error_data['severity'];

		if ( ! isset( self::$error_tracking[ $key ] ) ) {
			self::$error_tracking[ $key ] = array(
				'count'      => 0,
				'first_seen' => $error_data['timestamp'],
				'last_seen'  => $error_data['timestamp'],
				'examples'   => array(),
			);
		}

		++self::$error_tracking[ $key ]['count'];
		self::$error_tracking[ $key ]['last_seen'] = $error_data['timestamp'];

		// Store up to 5 recent examples.
		if ( count( self::$error_tracking[ $key ]['examples'] ) < 5 ) {
			self::$error_tracking[ $key ]['examples'][] = array(
				'id'        => $error_data['id'],
				'message'   => $error_data['message'],
				'timestamp' => $error_data['timestamp'],
			);
		}

		// Persist tracking data.
		self::save_tracking_data();
	}

	/**
	 * Check if error should be rate limited.
	 *
	 * @param string $category Error category.
	 * @param string $severity Error severity.
	 * @param int    $limit Maximum errors per window.
	 * @param int    $window Window duration in seconds.
	 * @return bool True if rate limited, false otherwise.
	 */
	public static function is_rate_limited( string $category, string $severity, int $limit = 10, int $window = 300 ): bool {
		$key = $category . ':' . $severity;
		$now = time();

		if ( ! isset( self::$rate_limits[ $key ] ) ) {
			self::$rate_limits[ $key ] = array(
				'count'        => 1,
				'window_start' => $now,
			);
			return false;
		}

		$rate_data = self::$rate_limits[ $key ];

		// Reset window if expired.
		if ( $now - $rate_data['window_start'] >= $window ) {
			self::$rate_limits[ $key ] = array(
				'count'        => 1,
				'window_start' => $now,
			);
			return false;
		}

		// Check if limit exceeded.
		if ( $rate_data['count'] >= $limit ) {
			return true;
		}

		// Increment counter.
		++self::$rate_limits[ $key ]['count'];
		return false;
	}

	/**
	 * Get error statistics.
	 *
	 * @param int $since_timestamp Get stats since this timestamp.
	 * @return array Error statistics.
	 */
	public static function get_error_stats( int $since_timestamp = 0 ): array {
		$stats = array(
			'total_errors'  => 0,
			'by_category'   => array(),
			'by_severity'   => array(),
			'recent_errors' => array(),
			'error_rate'    => 0,
		);

		foreach ( self::$error_tracking as $key => $data ) {
			if ( $data['last_seen'] < $since_timestamp ) {
				continue;
			}

			[ $category, $severity ] = explode( ':', $key );

			$stats['total_errors']            += $data['count'];
			$stats['by_category'][ $category ] = ( $stats['by_category'][ $category ] ?? 0 ) + $data['count'];
			$stats['by_severity'][ $severity ] = ( $stats['by_severity'][ $severity ] ?? 0 ) + $data['count'];

			// Add recent examples.
			foreach ( $data['examples'] as $example ) {
				if ( $example['timestamp'] >= $since_timestamp ) {
					$stats['recent_errors'][] = $example;
				}
			}
		}

		// Calculate error rate (errors per hour).
		$time_span           = max( 1, time() - $since_timestamp );
		$stats['error_rate'] = round( ( $stats['total_errors'] / $time_span ) * 3600, 2 );

		// Sort recent errors by timestamp.
		usort(
			$stats['recent_errors'],
			function ( $a, $b ) {
				return $b['timestamp'] - $a['timestamp'];
			}
		);

		// Limit recent errors to 20.
		$stats['recent_errors'] = array_slice( $stats['recent_errors'], 0, 20 );

		return $stats;
	}

	/**
	 * Get error trends.
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Error trend data.
	 */
	public static function get_error_trends( int $days = 7 ): array {
		$since  = time() - ( $days * DAY_IN_SECONDS );
		$trends = array(
			'daily_counts'  => array(),
			'trending_up'   => array(),
			'trending_down' => array(),
		);

		// Group errors by day.
		foreach ( self::$error_tracking as $key => $data ) {
			foreach ( $data['examples'] as $example ) {
				if ( $example['timestamp'] < $since ) {
					continue;
				}

				$day                            = date( 'Y-m-d', $example['timestamp'] );
				$trends['daily_counts'][ $day ] = ( $trends['daily_counts'][ $day ] ?? 0 ) + 1;
			}
		}

		return $trends;
	}

	/**
	 * Clean up old analytics data.
	 */
	public static function cleanup_analytics_data(): void {
		$cutoff = time() - ( 30 * DAY_IN_SECONDS ); // Keep 30 days.

		foreach ( self::$error_tracking as $key => $data ) {
			// Remove old examples.
			$data['examples'] = array_filter(
				$data['examples'],
				function ( $example ) use ( $cutoff ) {
					return $example['timestamp'] >= $cutoff;
				}
			);

			// Remove empty tracking entries.
			if ( empty( $data['examples'] ) && $data['last_seen'] < $cutoff ) {
				unset( self::$error_tracking[ $key ] );
			} else {
				self::$error_tracking[ $key ] = $data;
			}
		}

		// Clean up rate limits.
		$rate_cutoff = time() - 3600; // Clean up rate limits older than 1 hour.
		foreach ( self::$rate_limits as $key => $data ) {
			if ( $data['window_start'] < $rate_cutoff ) {
				unset( self::$rate_limits[ $key ] );
			}
		}

		self::save_tracking_data();
	}

	/**
	 * Save tracking data to database.
	 */
	private static function save_tracking_data(): void {
		update_option( 'nuclen_error_tracking', self::$error_tracking );
		update_option( 'nuclen_rate_limits', self::$rate_limits );
	}

	/**
	 * Load tracking data from database.
	 */
	public static function load_tracking_data(): void {
		self::$error_tracking = get_option( 'nuclen_error_tracking', array() );
		self::$rate_limits    = get_option( 'nuclen_rate_limits', array() );
	}

	/**
	 * Reset all analytics data.
	 */
	public static function reset_analytics(): void {
		self::$error_tracking = array();
		self::$rate_limits    = array();
		delete_option( 'nuclen_error_tracking' );
		delete_option( 'nuclen_rate_limits' );
	}
}
