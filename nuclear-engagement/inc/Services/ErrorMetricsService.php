<?php
/**
 * ErrorMetricsService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\BaseService;
use NuclearEngagement\Core\UnifiedErrorHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for collecting and monitoring error metrics
 */
class ErrorMetricsService extends BaseService {

	/** Metrics storage key */
	private const METRICS_OPTION = 'nuclen_error_metrics';

	/** Metrics retention period in days */
	private const RETENTION_DAYS = 30;

	/** Alert thresholds */
	private const THRESHOLDS = array(
		'error_rate'     => 0.05, // 5% error rate
		'critical_count' => 10, // 10 critical errors per hour
		'api_failures'   => 5, // 5 API failures per hour
	);

	/**
	 * @var array Current metrics
	 */
	private array $metrics = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->load_metrics();
		$this->register_hooks();

		// Set cache TTL for metrics
		$this->cache_ttl = 300; // 5 minutes
	}

	/**
	 * Get service name
	 *
	 * @return string
	 */
	protected function get_service_name(): string {
		return 'error_metrics_service';
	}

	/**
	 * Register hooks for error tracking
	 */
	private function register_hooks(): void {
		// Hook into error events
		add_action( 'nuclen_error_logged', array( $this, 'track_error' ), 10, 2 );
		add_action( 'nuclen_recovery_success', array( $this, 'track_recovery' ), 10, 2 );
		add_action( 'nuclen_security_event', array( $this, 'track_security_event' ) );

		// Schedule cleanup
		if ( ! wp_next_scheduled( 'nuclen_cleanup_error_metrics' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_error_metrics' );
		}
		add_action( 'nuclen_cleanup_error_metrics', array( $this, 'cleanup_old_metrics' ) );

		// Admin dashboard widget
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Track an error event
	 *
	 * @param array  $error_data Error information
	 * @param string $service Service name
	 */
	public function track_error( array $error_data, string $service ): void {
		$hour_key = $this->get_hour_key();
		$category = $error_data['category'] ?? 'general';
		$severity = $error_data['severity'] ?? 'medium';

		// Initialize hour metrics if needed
		if ( ! isset( $this->metrics['hourly'][ $hour_key ] ) ) {
			$this->metrics['hourly'][ $hour_key ] = $this->get_empty_hour_metrics();
		}

		// Update counters
		++$this->metrics['hourly'][ $hour_key ]['total_errors'];

		// Initialize category counter if needed
		if ( ! isset( $this->metrics['hourly'][ $hour_key ]['by_category'][ $category ] ) ) {
			$this->metrics['hourly'][ $hour_key ]['by_category'][ $category ] = 0;
		}
		++$this->metrics['hourly'][ $hour_key ]['by_category'][ $category ];

		// Initialize severity counter if needed
		if ( ! isset( $this->metrics['hourly'][ $hour_key ]['by_severity'][ $severity ] ) ) {
			$this->metrics['hourly'][ $hour_key ]['by_severity'][ $severity ] = 0;
		}
		++$this->metrics['hourly'][ $hour_key ]['by_severity'][ $severity ];

		// Initialize service counter if needed
		if ( ! isset( $this->metrics['hourly'][ $hour_key ]['by_service'][ $service ] ) ) {
			$this->metrics['hourly'][ $hour_key ]['by_service'][ $service ] = 0;
		}
		++$this->metrics['hourly'][ $hour_key ]['by_service'][ $service ];

		// Update daily totals
		$day_key = $this->get_day_key();
		if ( ! isset( $this->metrics['daily'][ $day_key ] ) ) {
			$this->metrics['daily'][ $day_key ] = $this->get_empty_day_metrics();
		}

		++$this->metrics['daily'][ $day_key ]['total_errors'];

		// Initialize daily category counter if needed
		if ( ! isset( $this->metrics['daily'][ $day_key ]['by_category'][ $category ] ) ) {
			$this->metrics['daily'][ $day_key ]['by_category'][ $category ] = 0;
		}
		++$this->metrics['daily'][ $day_key ]['by_category'][ $category ];

		// Track specific error types
		if ( isset( $error_data['context']['exception_class'] ) ) {
			$exception                                 = $error_data['context']['exception_class'];
			$this->metrics['exceptions'][ $exception ] = ( $this->metrics['exceptions'][ $exception ] ?? 0 ) + 1;
		}

		// Check thresholds
		$this->check_alert_thresholds();

		// Save metrics
		$this->save_metrics();
	}

	/**
	 * Track successful recovery
	 *
	 * @param string $recovery_id Recovery ID
	 * @param string $strategy Strategy name
	 */
	public function track_recovery( string $recovery_id, string $strategy ): void {
		$hour_key = $this->get_hour_key();

		if ( ! isset( $this->metrics['hourly'][ $hour_key ] ) ) {
			$this->metrics['hourly'][ $hour_key ] = $this->get_empty_hour_metrics();
		}

		++$this->metrics['hourly'][ $hour_key ]['recoveries'];
		$this->metrics['recovery_strategies'][ $strategy ] = ( $this->metrics['recovery_strategies'][ $strategy ] ?? 0 ) + 1;

		$this->save_metrics();
	}

	/**
	 * Track security event
	 *
	 * @param array $event_data Security event data
	 */
	public function track_security_event( array $event_data ): void {
		$hour_key = $this->get_hour_key();

		if ( ! isset( $this->metrics['hourly'][ $hour_key ] ) ) {
			$this->metrics['hourly'][ $hour_key ] = $this->get_empty_hour_metrics();
		}

		++$this->metrics['hourly'][ $hour_key ]['security_events'];

		// Track by IP if available
		if ( isset( $event_data['context']['ip_address'] ) ) {
			$ip                                   = $event_data['context']['ip_address'];
			$this->metrics['security_ips'][ $ip ] = ( $this->metrics['security_ips'][ $ip ] ?? 0 ) + 1;
		}

		$this->save_metrics();

		// Immediate alert for critical security events
		if ( $event_data['severity'] === 'critical' ) {
			$this->send_security_alert( $event_data );
		}
	}

	/**
	 * Get current error rate
	 *
	 * @return float Error rate (0-1)
	 */
	public function get_error_rate(): float {
		$hour_key     = $this->get_hour_key();
		$hour_metrics = $this->metrics['hourly'][ $hour_key ] ?? null;

		if ( ! $hour_metrics || $hour_metrics['total_requests'] === 0 ) {
			return 0.0;
		}

		return $hour_metrics['total_errors'] / $hour_metrics['total_requests'];
	}

	/**
	 * Get metrics summary
	 *
	 * @param string $period Period: 'hour', 'day', 'week', 'month'
	 * @return array Metrics summary
	 */
	public function get_metrics_summary( string $period = 'day' ): array {
		$summary = array(
			'period'           => $period,
			'total_errors'     => 0,
			'total_recoveries' => 0,
			'error_rate'       => 0.0,
			'top_categories'   => array(),
			'top_services'     => array(),
			'critical_errors'  => 0,
			'api_failures'     => 0,
		);

		// Calculate based on period
		$hours_to_include = $this->get_hours_for_period( $period );
		$total_requests   = 0;

		foreach ( $hours_to_include as $hour_key ) {
			if ( ! isset( $this->metrics['hourly'][ $hour_key ] ) ) {
				continue;
			}

			$hour_data                    = $this->metrics['hourly'][ $hour_key ];
			$summary['total_errors']     += $hour_data['total_errors'];
			$summary['total_recoveries'] += $hour_data['recoveries'];
			$total_requests              += $hour_data['total_requests'];

			// Aggregate categories
			foreach ( $hour_data['by_category'] as $category => $count ) {
				$summary['top_categories'][ $category ] = ( $summary['top_categories'][ $category ] ?? 0 ) + $count;
			}

			// Aggregate services
			foreach ( $hour_data['by_service'] as $service => $count ) {
				$summary['top_services'][ $service ] = ( $summary['top_services'][ $service ] ?? 0 ) + $count;
			}

			// Count critical errors
			$summary['critical_errors'] += $hour_data['by_severity']['critical'] ?? 0;

			// Count API failures
			$summary['api_failures'] += $hour_data['by_category']['network'] ?? 0;
		}

		// Calculate error rate
		if ( $total_requests > 0 ) {
			$summary['error_rate'] = $summary['total_errors'] / $total_requests;
		}

		// Sort top items
		arsort( $summary['top_categories'] );
		arsort( $summary['top_services'] );

		// Limit to top 5
		$summary['top_categories'] = array_slice( $summary['top_categories'], 0, 5, true );
		$summary['top_services']   = array_slice( $summary['top_services'], 0, 5, true );

		return $summary;
	}

	/**
	 * Get health status
	 *
	 * @return array Health status
	 */
	public function get_health_status(): array {
		$summary = $this->get_metrics_summary( 'hour' );

		$status = array(
			'healthy'  => true,
			'warnings' => array(),
			'alerts'   => array(),
		);

		// Check error rate
		if ( $summary['error_rate'] > self::THRESHOLDS['error_rate'] ) {
			$status['healthy']  = false;
			$status['alerts'][] = sprintf(
				'High error rate: %.1f%% (threshold: %.1f%%)',
				$summary['error_rate'] * 100,
				self::THRESHOLDS['error_rate'] * 100
			);
		}

		// Check critical errors
		if ( $summary['critical_errors'] > self::THRESHOLDS['critical_count'] ) {
			$status['healthy']  = false;
			$status['alerts'][] = sprintf(
				'Too many critical errors: %d (threshold: %d)',
				$summary['critical_errors'],
				self::THRESHOLDS['critical_count']
			);
		}

		// Check API failures
		if ( $summary['api_failures'] > self::THRESHOLDS['api_failures'] ) {
			$status['warnings'][] = sprintf(
				'High API failure rate: %d failures',
				$summary['api_failures']
			);
		}

		return $status;
	}

	/**
	 * Export metrics for analysis
	 *
	 * @param string $format Export format: 'json', 'csv'
	 * @return string Exported data
	 */
	public function export_metrics( string $format = 'json' ): string {
		$data = array(
			'export_date' => current_time( 'mysql' ),
			'metrics'     => $this->metrics,
			'summary'     => array(
				'hourly' => $this->get_metrics_summary( 'hour' ),
				'daily'  => $this->get_metrics_summary( 'day' ),
				'weekly' => $this->get_metrics_summary( 'week' ),
			),
		);

		if ( $format === 'csv' ) {
			return $this->export_as_csv( $data );
		}

		return wp_json_encode( $data, JSON_PRETTY_PRINT );
	}

	/**
	 * Add dashboard widget
	 */
	public function add_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'nuclen_error_metrics',
			__( 'Nuclear Engagement Error Metrics', 'nuclear-engagement' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget
	 */
	public function render_dashboard_widget(): void {
		$health  = $this->get_health_status();
		$summary = $this->get_metrics_summary( 'day' );

		?>
		<div class="nuclen-error-metrics">
			<div class="health-status <?php echo $health['healthy'] ? 'healthy' : 'unhealthy'; ?>">
				<h4><?php echo $health['healthy'] ? __( '✓ System Healthy', 'nuclear-engagement' ) : __( '⚠ Issues Detected', 'nuclear-engagement' ); ?></h4>
			</div>
			
			<?php if ( ! empty( $health['alerts'] ) ) : ?>
				<div class="alerts">
					<h5><?php esc_html_e( 'Alerts', 'nuclear-engagement' ); ?></h5>
					<ul>
						<?php foreach ( $health['alerts'] as $alert ) : ?>
							<li class="alert"><?php echo esc_html( $alert ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			
			<div class="metrics-summary">
				<h5><?php esc_html_e( '24 Hour Summary', 'nuclear-engagement' ); ?></h5>
				<ul>
					<li><?php printf( __( 'Total Errors: %d', 'nuclear-engagement' ), $summary['total_errors'] ); ?></li>
					<li><?php printf( __( 'Error Rate: %.1f%%', 'nuclear-engagement' ), $summary['error_rate'] * 100 ); ?></li>
					<li><?php printf( __( 'Recoveries: %d', 'nuclear-engagement' ), $summary['total_recoveries'] ); ?></li>
				</ul>
			</div>
			
			<?php if ( ! empty( $summary['top_categories'] ) ) : ?>
				<div class="top-errors">
					<h5><?php esc_html_e( 'Top Error Categories', 'nuclear-engagement' ); ?></h5>
					<ul>
						<?php foreach ( array_slice( $summary['top_categories'], 0, 3, true ) as $category => $count ) : ?>
							<li><?php printf( '%s: %d', esc_html( $category ), $count ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			
			<p class="view-full">
				<a href="<?php echo admin_url( 'admin.php?page=nuclear-engagement-metrics' ); ?>">
					<?php esc_html_e( 'View Full Metrics →', 'nuclear-engagement' ); ?>
				</a>
			</p>
		</div>
		
		<style>
			.nuclen-error-metrics .health-status {
				padding: 10px;
				margin-bottom: 15px;
				border-radius: 4px;
			}
			.nuclen-error-metrics .health-status.healthy {
				background: #d4edda;
				color: #155724;
			}
			.nuclen-error-metrics .health-status.unhealthy {
				background: #f8d7da;
				color: #721c24;
			}
			.nuclen-error-metrics .alerts {
				margin-bottom: 15px;
			}
			.nuclen-error-metrics .alert {
				color: #856404;
				background: #fff3cd;
				padding: 5px 10px;
				margin: 5px 0;
				border-radius: 3px;
			}
			.nuclen-error-metrics h5 {
				margin-top: 15px;
				margin-bottom: 10px;
			}
		</style>
		<?php
	}

	/**
	 * Load metrics from storage
	 */
	private function load_metrics(): void {
		$stored = get_option( self::METRICS_OPTION, array() );

		$this->metrics = wp_parse_args(
			$stored,
			array(
				'hourly'              => array(),
				'daily'               => array(),
				'exceptions'          => array(),
				'recovery_strategies' => array(),
				'security_ips'        => array(),
			)
		);
	}

	/**
	 * Save metrics to storage
	 */
	private function save_metrics(): void {
		update_option( self::METRICS_OPTION, $this->metrics, false );
	}

	/**
	 * Get current hour key
	 */
	private function get_hour_key(): string {
		return gmdate( 'Y-m-d-H' );
	}

	/**
	 * Get current day key
	 */
	private function get_day_key(): string {
		return gmdate( 'Y-m-d' );
	}

	/**
	 * Get empty hour metrics structure
	 */
	private function get_empty_hour_metrics(): array {
		return array(
			'total_errors'    => 0,
			'total_requests'  => 0,
			'recoveries'      => 0,
			'security_events' => 0,
			'by_category'     => array(),
			'by_severity'     => array(),
			'by_service'      => array(),
		);
	}

	/**
	 * Get empty day metrics structure
	 */
	private function get_empty_day_metrics(): array {
		return array(
			'total_errors' => 0,
			'by_category'  => array(),
		);
	}

	/**
	 * Get hour keys for period
	 */
	private function get_hours_for_period( string $period ): array {
		$hours        = array();
		$current_hour = time();

		$hours_count = match ( $period ) {
			'hour' => 1,
			'day' => 24,
			'week' => 168,
			'month' => 720,
			default => 24,
		};

		for ( $i = 0; $i < $hours_count; $i++ ) {
			$hours[] = gmdate( 'Y-m-d-H', $current_hour - ( $i * 3600 ) );
		}

		return $hours;
	}

	/**
	 * Check alert thresholds
	 */
	private function check_alert_thresholds(): void {
		$health = $this->get_health_status();

		if ( ! $health['healthy'] && ! get_transient( 'nuclen_metrics_alert_sent' ) ) {
			// Send alert email to admin
			$admin_email = get_option( 'admin_email' );
			$subject     = __( '[Nuclear Engagement] Error Threshold Alert', 'nuclear-engagement' );
			$message     = __( 'Nuclear Engagement has detected error thresholds have been exceeded:', 'nuclear-engagement' ) . "\n\n";

			foreach ( $health['alerts'] as $alert ) {
				$message .= '• ' . $alert . "\n";
			}

			$message .= "\n" . __( 'Please check your site for issues.', 'nuclear-engagement' );

			wp_mail( $admin_email, $subject, $message );

			// Prevent spam - only send once per hour
			set_transient( 'nuclen_metrics_alert_sent', true, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Send security alert
	 */
	private function send_security_alert( array $event_data ): void {
		// Log to security log
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			LoggingService::log(
				sprintf(
					'[SECURITY] %s | IP: %s | User: %d',
					$event_data['message'] ?? 'Security event',
					$event_data['context']['ip_address'] ?? 'unknown',
					$event_data['context']['user_id'] ?? 0
				)
			);
		}

		// Trigger action for external monitoring
		do_action( 'nuclen_critical_security_alert', $event_data );
	}

	/**
	 * Export as CSV
	 */
	private function export_as_csv( array $data ): string {
		$csv   = array();
		$csv[] = array( 'Date', 'Hour', 'Total Errors', 'Error Rate', 'Recoveries', 'Critical', 'API Failures' );

		foreach ( $this->metrics['hourly'] as $hour => $metrics ) {
			$parts    = explode( '-', $hour );
			$date     = implode( '-', array_slice( $parts, 0, 3 ) );
			$hour_num = $parts[3] ?? '00';

			$csv[] = array(
				$date,
				$hour_num,
				$metrics['total_errors'],
				$metrics['total_requests'] > 0 ? round( $metrics['total_errors'] / $metrics['total_requests'] * 100, 2 ) : 0,
				$metrics['recoveries'],
				$metrics['by_severity']['critical'] ?? 0,
				$metrics['by_category']['network'] ?? 0,
			);
		}

		// Convert to CSV string
		$output = '';
		foreach ( $csv as $row ) {
			$output .= implode( ',', $row ) . "\n";
		}

		return $output;
	}

	/**
	 * Cleanup old metrics
	 */
	public function cleanup_old_metrics(): void {
		$cutoff_hour = gmdate( 'Y-m-d-H', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );
		$cutoff_day  = gmdate( 'Y-m-d', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );

		// Clean hourly metrics
		foreach ( $this->metrics['hourly'] as $hour => $data ) {
			if ( $hour < $cutoff_hour ) {
				unset( $this->metrics['hourly'][ $hour ] );
			}
		}

		// Clean daily metrics
		foreach ( $this->metrics['daily'] as $day => $data ) {
			if ( $day < $cutoff_day ) {
				unset( $this->metrics['daily'][ $day ] );
			}
		}

		// Clean old security IPs (keep last 100)
		if ( count( $this->metrics['security_ips'] ) > 100 ) {
			arsort( $this->metrics['security_ips'] );
			$this->metrics['security_ips'] = array_slice( $this->metrics['security_ips'], 0, 100, true );
		}

		$this->save_metrics();
	}

	/**
	 * Track request for error rate calculation
	 */
	public function track_request(): void {
		$hour_key = $this->get_hour_key();

		if ( ! isset( $this->metrics['hourly'][ $hour_key ] ) ) {
			$this->metrics['hourly'][ $hour_key ] = $this->get_empty_hour_metrics();
		}

		++$this->metrics['hourly'][ $hour_key ]['total_requests'];

		// Save periodically (every 10 requests)
		if ( $this->metrics['hourly'][ $hour_key ]['total_requests'] % 10 === 0 ) {
			$this->save_metrics();
		}
	}
}
