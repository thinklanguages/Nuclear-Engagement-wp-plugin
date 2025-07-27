<?php
/**
 * PerformanceMonitor.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance monitoring system.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class PerformanceMonitor {
	/**
	 * Performance metrics storage.
	 *
	 * @var array<string, array{start: float, end?: float, memory_start: int, memory_end?: int, queries_start: int, queries_end?: int}>
	 */
	private static array $metrics = array();

	/**
	 * Current profiling stack.
	 *
	 * @var array<string>
	 */
	private static array $stack = array();

	/**
	 * Whether monitoring is enabled.
	 *
	 * @var bool
	 */
	private static bool $enabled = false;

	/**
	 * Performance thresholds.
	 *
	 * @var array{time: float, memory: int, queries: int}
	 */
	private static array $thresholds = array(
		'time'    => 1.0,    // 1 second.
		'memory'  => 5242880, // 5MB.
		'queries' => 50,     // 50 queries.
	);

	/**
	 * Initialize the performance monitor.
	 */
	public static function init(): void {
		self::$enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( ! self::$enabled ) {
			return;
		}

		// Hook into WordPress to track overall page performance.
		add_action( 'init', array( self::class, 'start_page_monitoring' ), 1 );
		add_action( 'wp_footer', array( self::class, 'end_page_monitoring' ), 999 );
		add_action( 'admin_footer', array( self::class, 'end_page_monitoring' ), 999 );

		// Track WordPress hooks if in development mode.
		if ( defined( 'NUCLEN_DEV_MODE' ) && NUCLEN_DEV_MODE ) {
			self::trackWordPressHooks();
		}
	}

	/**
	 * Start monitoring a specific operation.
	 *
	 * @param string $operation Operation identifier.
	 */
	public static function start( string $operation ): void {
		if ( ! self::$enabled ) {
			return;
		}

		self::$stack[]               = $operation;
		self::$metrics[ $operation ] = array(
			'start'         => microtime( true ),
			'memory_start'  => memory_get_usage( true ),
			'queries_start' => self::getQueryCount(),
		);
	}

	/**
	 * Stop monitoring an operation.
	 *
	 * @param string $operation Operation identifier.
	 */
	public static function stop( string $operation ): void {
		if ( ! self::$enabled || ! isset( self::$metrics[ $operation ] ) ) {
			return;
		}

		self::$metrics[ $operation ]['end']         = microtime( true );
		self::$metrics[ $operation ]['memory_end']  = memory_get_usage( true );
		self::$metrics[ $operation ]['queries_end'] = self::getQueryCount();

		// Remove from stack.
		$key = array_search( $operation, self::$stack, true );
		if ( $key !== false ) {
			unset( self::$stack[ $key ] );
		}

		// Check thresholds and log warnings.
		self::checkThresholds( $operation );
	}

	/**
	 * Profile a callable and return its result.
	 *
	 * @param string   $operation Operation name.
	 * @param callable $callback  Function to profile.
	 * @return mixed
	 */
	public static function profile( string $operation, callable $callback ) {
		self::start( $operation );

		try {
			return call_user_func( $callback );
		} finally {
			self::stop( $operation );
		}
	}

	/**
	 * Get performance metrics for an operation.
	 *
	 * @param string $operation Operation identifier.
	 * @return array{duration: float, memory_usage: int, query_count: int}|null
	 */
	public static function getMetrics( string $operation ): ?array {
		if ( ! isset( self::$metrics[ $operation ] ) || ! isset( self::$metrics[ $operation ]['end'] ) ) {
			return null;
		}

		$metric = self::$metrics[ $operation ];

		return array(
			'duration'     => $metric['end'] - $metric['start'],
			'memory_usage' => $metric['memory_end'] - $metric['memory_start'],
			'query_count'  => $metric['queries_end'] - $metric['queries_start'],
		);
	}

	/**
	 * Get all performance metrics.
	 *
	 * @return array<string, array{duration: float, memory_usage: int, query_count: int}>
	 */
	public static function getAllMetrics(): array {
		$results = array();

		foreach ( self::$metrics as $operation => $metric ) {
			if ( isset( $metric['end'] ) ) {
				$results[ $operation ] = array(
					'duration'     => $metric['end'] - $metric['start'],
					'memory_usage' => $metric['memory_end'] - $metric['memory_start'],
					'query_count'  => $metric['queries_end'] - $metric['queries_start'],
				);
			}
		}

		return $results;
	}

	/**
	 * Get current memory usage.
	 *
	 * @return array{current: int, peak: int, limit: int, percentage: float}
	 */
	public static function getMemoryUsage(): array {
		$current = memory_get_usage( true );
		$peak    = memory_get_peak_usage( true );
		$limit   = (int) ini_get( 'memory_limit' ) !== -1 ? self::parseMemoryLimit() : -1;
		
		$percentage = 0.0;
		if ( $limit > 0 ) {
			$percentage = ( $current / $limit ) * 100;
		}
		
		return array(
			'current' => $current,
			'peak'    => $peak,
			'limit'   => $limit,
			'percentage' => $percentage,
		);
	}

	/**
	 * Check if memory usage is approaching limits.
	 *
	 * @param float $threshold Percentage threshold (0-100).
	 * @return bool True if memory usage exceeds threshold.
	 */
	public static function isMemoryUsageHigh( float $threshold = 80.0 ): bool {
		$usage = self::getMemoryUsage();
		return $usage['percentage'] > $threshold;
	}

	/**
	 * Get available memory.
	 *
	 * @return int Available memory in bytes, or -1 if unlimited.
	 */
	public static function getAvailableMemory(): int {
		$usage = self::getMemoryUsage();
		if ( $usage['limit'] < 0 ) {
			return -1;
		}
		return max( 0, $usage['limit'] - $usage['current'] );
	}

	/**
	 * Check if enough memory is available for an operation.
	 *
	 * @param int $required_bytes Required memory in bytes.
	 * @param float $safety_factor Safety factor (default 1.5).
	 * @return bool True if enough memory is available.
	 */
	public static function hasEnoughMemory( int $required_bytes, float $safety_factor = 1.5 ): bool {
		$available = self::getAvailableMemory();
		if ( $available < 0 ) {
			return true; // Unlimited memory
		}
		return $available > ( $required_bytes * $safety_factor );
	}

	/**
	 * Get query performance statistics.
	 *
	 * @return array{count: int, time: float, slow_queries: int}
	 */
	public static function getQueryStats(): array {
		global $wpdb;

		$slow_queries = 0;
		$total_time   = 0;

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $query ) {
				$query_time  = $query[1] ?? 0;
				$total_time += $query_time;

				if ( $query_time > 0.05 ) { // Queries taking more than 50ms.
					++$slow_queries;
				}
			}
		}

		return array(
			'count'        => self::getQueryCount(),
			'time'         => $total_time,
			'slow_queries' => $slow_queries,
		);
	}

	/**
	 * Start page-level monitoring.
	 */
	public static function start_page_monitoring(): void {
		self::start( 'page_load' );
	}

	/**
	 * End page-level monitoring and output debug info.
	 */
	public static function end_page_monitoring(): void {
		self::stop( 'page_load' );

		if ( current_user_can( 'manage_options' ) && isset( $_GET['nuclen_debug'] ) ) {
			self::outputDebugInfo();
		}
	}

	/**
	 * Set performance thresholds.
	 *
	 * @param array{time?: float, memory?: int, queries?: int} $thresholds New thresholds.
	 */
	public static function setThresholds( array $thresholds ): void {
		self::$thresholds = array_merge( self::$thresholds, $thresholds );
	}

	/**
	 * Track WordPress hooks performance.
	 */
	private static function trackWordPressHooks(): void {
		$hooks_to_track = array(
			'plugins_loaded',
			'init',
			'wp_loaded',
			'template_redirect',
			'wp_head',
			'wp_footer',
		);

		foreach ( $hooks_to_track as $hook ) {
			add_action(
				$hook,
				function () use ( $hook ) {
					self::start( "hook_{$hook}" );
				},
				-999
			);

			add_action(
				$hook,
				function () use ( $hook ) {
					self::stop( "hook_{$hook}" );
				},
				999
			);
		}
	}

	/**
	 * Check if operation exceeded thresholds.
	 *
	 * @param string $operation Operation identifier.
	 */
	private static function checkThresholds( string $operation ): void {
		$metrics = self::getMetrics( $operation );
		if ( ! $metrics ) {
			return;
		}

		$warnings = array();

		if ( $metrics['duration'] > self::$thresholds['time'] ) {
			$warnings[] = sprintf( 'Duration: %.2fs (threshold: %.2fs)', $metrics['duration'], self::$thresholds['time'] );
		}

		if ( $metrics['memory_usage'] > self::$thresholds['memory'] ) {
			$warnings[] = sprintf(
				'Memory: %s (threshold: %s)',
				size_format( $metrics['memory_usage'] ),
				size_format( self::$thresholds['memory'] )
			);
		}

		if ( $metrics['query_count'] > self::$thresholds['queries'] ) {
			$warnings[] = sprintf( 'Queries: %d (threshold: %d)', $metrics['query_count'], self::$thresholds['queries'] );
		}

		if ( ! empty( $warnings ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[WARNING] Performance Warning [%s]: %s',
					$operation,
					implode( ', ', $warnings )
				)
			);
		}
	}

	/**
	 * Output debug information.
	 */
	private static function outputDebugInfo(): void {
		$metrics = self::getAllMetrics();
		$memory  = self::getMemoryUsage();
		$queries = self::getQueryStats();

		echo '<div style="background: #f1f1f1; padding: 20px; margin: 20px; font-family: monospace; font-size: 12px; border: 1px solid #ddd;">';
		echo '<h3>Nuclear Engagement Performance Debug</h3>';

		echo '<h4>Memory Usage:</h4>';
		echo '<ul>';
		echo '<li>Current: ' . size_format( $memory['current'] ) . '</li>';
		echo '<li>Peak: ' . size_format( $memory['peak'] ) . '</li>';
		if ( $memory['limit'] > 0 ) {
			echo '<li>Limit: ' . size_format( $memory['limit'] ) . '</li>';
		}
		echo '</ul>';

		echo '<h4>Database Queries:</h4>';
		echo '<ul>';
		echo '<li>Total: ' . $queries['count'] . '</li>';
		echo '<li>Time: ' . number_format( $queries['time'], 4 ) . 's</li>';
		echo '<li>Slow (>50ms): ' . $queries['slow_queries'] . '</li>';
		echo '</ul>';

		if ( ! empty( $metrics ) ) {
			echo '<h4>Operation Metrics:</h4>';
			echo '<table style="width: 100%; border-collapse: collapse;">';
			echo '<tr><th style="border: 1px solid #ddd; padding: 8px;">Operation</th><th style="border: 1px solid #ddd; padding: 8px;">Duration</th><th style="border: 1px solid #ddd; padding: 8px;">Memory</th><th style="border: 1px solid #ddd; padding: 8px;">Queries</th></tr>';

			foreach ( $metrics as $operation => $metric ) {
				echo '<tr>';
				echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html( $operation ) . '</td>';
				echo '<td style="border: 1px solid #ddd; padding: 8px;">' . number_format( $metric['duration'], 4 ) . 's</td>';
				echo '<td style="border: 1px solid #ddd; padding: 8px;">' . size_format( $metric['memory_usage'] ) . '</td>';
				echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $metric['query_count'] . '</td>';
				echo '</tr>';
			}

			echo '</table>';
		}

		echo '</div>';
	}

	/**
	 * Get current query count.
	 *
	 * @return int
	 */
	private static function getQueryCount(): int {
		global $wpdb;
		return (int) $wpdb->num_queries;
	}

	/**
	 * Parse memory limit from ini setting.
	 *
	 * @return int
	 */
	private static function parseMemoryLimit(): int {
		$limit = ini_get( 'memory_limit' );

		if ( $limit === '-1' ) {
			return -1;
		}

		$limit = strtolower( trim( $limit ) );
		$bytes = (int) $limit;

		if ( strpos( $limit, 'g' ) !== false ) {
			$bytes *= 1024 * 1024 * 1024;
		} elseif ( strpos( $limit, 'm' ) !== false ) {
			$bytes *= 1024 * 1024;
		} elseif ( strpos( $limit, 'k' ) !== false ) {
			$bytes *= 1024;
		}

		return $bytes;
	}

	/**
	 * Enable or disable monitoring.
	 *
	 * @param bool $enabled Whether to enable monitoring.
	 */
	public static function setEnabled( bool $enabled ): void {
		self::$enabled = $enabled;
	}

	/**
	 * Check if monitoring is enabled.
	 *
	 * @return bool
	 */
	public static function isEnabled(): bool {
		return self::$enabled;
	}
}
