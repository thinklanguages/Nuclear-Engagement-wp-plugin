<?php
/**
 * HealthCheckService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\CircuitBreakerService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for monitoring plugin health and status
 */
class HealthCheckService {

	/** @var SettingsRepository */
	private SettingsRepository $settings;

	/** @var CircuitBreakerService */
	private CircuitBreakerService $circuit_breaker_service;


	/** @var array */
	private array $checks = array();

	/** @var string */
	private const HEALTH_STATUS_OPTION = 'nuclen_health_status';

	/** @var int */
	private const CACHE_DURATION = 300; // 5 minutes

	public function __construct(
		SettingsRepository $settings,
		CircuitBreakerService $circuit_breaker_service
	) {
		$this->settings                = $settings;
		$this->circuit_breaker_service = $circuit_breaker_service;
		$this->register_default_checks();
	}

	/**
	 * Register default health checks
	 */
	private function register_default_checks(): void {
		// API connectivity check
		$this->register_check(
			'api_connectivity',
			function () {
				$api_key = $this->settings->get_string( 'api_key', '' );

				if ( empty( $api_key ) ) {
					return array(
						'status'  => 'warning',
						'message' => 'API key not configured',
					);
				}

				// Check circuit breaker status
				$circuit_breaker = new CircuitBreaker( 'remote_api' );
				$cb_status       = $circuit_breaker->get_status();

				if ( $cb_status['is_open'] ) {
					return array(
						'status'  => 'error',
						'message' => sprintf( 'API circuit breaker is open. Retry in %d seconds.', $cb_status['time_until_retry'] ),
					);
				}

				return array(
					'status'  => 'ok',
					'message' => 'API connectivity is healthy',
				);
			}
		);

		// Database tables check
		$this->register_check(
			'database_tables',
			function () {
				if ( ! class_exists( '\NuclearEngagement\OptinData' ) ) {
					return array(
						'status'  => 'error',
						'message' => 'OptinData class not found',
					);
				}

				if ( ! \NuclearEngagement\OptinData::table_exists() ) {
					return array(
						'status'  => 'error',
						'message' => 'Required database tables are missing',
					);
				}

				return array(
					'status'  => 'ok',
					'message' => 'Database tables are properly configured',
				);
			}
		);

		// Background processing check
		$this->register_check(
			'background_processing',
			function () {
				$processor        = new BulkGenerationBatchProcessor( $this->settings );
				$processing_count = 0;

				// Check for stuck batches
				global $wpdb;
				$stuck_batches = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $wpdb->options 
					WHERE option_name LIKE %s 
					AND (option_value LIKE %s OR option_value LIKE %s)
					AND option_value LIKE %s",
						'_transient_nuclen_batch_%',
						'%"status":"running"%',
						'%"status":"processing"%',
						'%"updated_at":%'
					)
				);

				if ( $stuck_batches > 0 ) {
					// Check if any are actually stuck (not updated in last hour)
					$one_hour_ago = time() - 3600;
					$transients   = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT option_value FROM $wpdb->options 
						WHERE option_name LIKE %s 
						AND (option_value LIKE %s OR option_value LIKE %s)
						LIMIT 10",
							'_transient_nuclen_batch_%',
							'%"status":"running"%',
							'%"status":"processing"%'
						)
					);

					$stuck_count = 0;
					foreach ( $transients as $transient ) {
						$data = maybe_unserialize( $transient->option_value );
						if ( is_array( $data ) && isset( $data['updated_at'] ) && $data['updated_at'] < $one_hour_ago ) {
							$stuck_count++;
						}
					}

					if ( $stuck_count > 0 ) {
						return array(
							'status'  => 'warning',
							'message' => sprintf( '%d batches appear to be stuck in processing', $stuck_count ),
						);
					}
				}

				return array(
					'status'  => 'ok',
					'message' => 'Background processing is working normally',
				);
			}
		);


		// Memory usage check
		$this->register_check(
			'memory_usage',
			function () {
				$memory_limit = $this->parse_memory_limit( ini_get( 'memory_limit' ) );
				$memory_usage = memory_get_usage( true );
				$memory_peak  = memory_get_peak_usage( true );

				$usage_percent = ( $memory_usage / $memory_limit ) * 100;
				$peak_percent  = ( $memory_peak / $memory_limit ) * 100;

				if ( $peak_percent > 90 ) {
					return array(
						'status'  => 'error',
						'message' => sprintf( 'Peak memory usage is very high: %.1f%%', $peak_percent ),
					);
				}

				if ( $usage_percent > 80 ) {
					return array(
						'status'  => 'warning',
						'message' => sprintf( 'Current memory usage is high: %.1f%%', $usage_percent ),
					);
				}

				return array(
					'status'  => 'ok',
					'message' => sprintf( 'Memory usage: %.1f%% (Peak: %.1f%%)', $usage_percent, $peak_percent ),
				);
			}
		);

		// Scheduled tasks check
		$this->register_check(
			'scheduled_tasks',
			function () {
				$required_hooks = array(
					'nuclen_cleanup_old_batches',
					'nuclen_theme_migration',
				);

				$missing_hooks = array();
				foreach ( $required_hooks as $hook ) {
					if ( ! wp_next_scheduled( $hook ) ) {
						$missing_hooks[] = $hook;
					}
				}

				if ( ! empty( $missing_hooks ) ) {
					return array(
						'status'  => 'warning',
						'message' => 'Some scheduled tasks are not registered: ' . implode( ', ', $missing_hooks ),
					);
				}

				return array(
					'status'  => 'ok',
					'message' => 'All scheduled tasks are properly registered',
				);
			}
		);
	}

	/**
	 * Register a health check
	 *
	 * @param string   $name Check name
	 * @param callable $callback Check callback
	 */
	public function register_check( string $name, callable $callback ): void {
		$this->checks[ $name ] = $callback;
	}

	/**
	 * Run all health checks
	 *
	 * @param bool $use_cache Whether to use cached results
	 * @return array Health check results
	 */
	public function run_checks( bool $use_cache = true ): array {
		if ( $use_cache ) {
			$cached = get_transient( self::HEALTH_STATUS_OPTION );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$results        = array();
		$overall_status = 'ok';

		foreach ( $this->checks as $name => $callback ) {
			try {
				$result = $callback();

				if ( ! is_array( $result ) || ! isset( $result['status'] ) || ! isset( $result['message'] ) ) {
					$result = array(
						'status'  => 'error',
						'message' => 'Invalid check result',
					);
				}

				$results[ $name ] = $result;

				// Update overall status
				if ( $result['status'] === 'error' && $overall_status !== 'error' ) {
					$overall_status = 'error';
				} elseif ( $result['status'] === 'warning' && $overall_status === 'ok' ) {
					$overall_status = 'warning';
				}
			} catch ( \Throwable $e ) {
				$results[ $name ] = array(
					'status'  => 'error',
					'message' => 'Check failed: ' . $e->getMessage(),
				);
				$overall_status   = 'error';
			}
		}

		$health_data = array(
			'timestamp'      => time(),
			'overall_status' => $overall_status,
			'checks'         => $results,
		);

		// Cache results
		set_transient( self::HEALTH_STATUS_OPTION, $health_data, self::CACHE_DURATION );

		return $health_data;
	}

	/**
	 * Get quick status
	 *
	 * @return string Status: ok, warning, error
	 */
	public function get_status(): string {
		$health_data = $this->run_checks();
		return $health_data['overall_status'];
	}

	/**
	 * Check if plugin is healthy
	 *
	 * @return bool
	 */
	public function is_healthy(): bool {
		return $this->get_status() !== 'error';
	}

	/**
	 * Parse memory limit string to bytes
	 *
	 * @param string $limit Memory limit string
	 * @return int Bytes
	 */
	private function parse_memory_limit( string $limit ): int {
		$unit  = strtolower( substr( $limit, -1 ) );
		$value = (int) $limit;

		switch ( $unit ) {
			case 'g':
				$value *= 1024;
			case 'm':
				$value *= 1024;
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * Register REST API endpoint for health checks
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'nuclear-engagement/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_health_check' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * REST API callback for health check
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function rest_health_check( \WP_REST_Request $request ): \WP_REST_Response {
		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		$health_service = $container->get( 'health_check_service' );

		$use_cache = $request->get_param( 'refresh' ) !== '1';
		$results   = $health_service->run_checks( $use_cache );

		return new \WP_REST_Response( $results, 200 );
	}

	/**
	 * Display health status in admin bar
	 */
	public static function add_admin_bar_status( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		$health_service = $container->get( 'health_check_service' );
		$status         = $health_service->get_status();

		$icon  = '⬤';
		$color = '#46b450'; // Green

		if ( $status === 'warning' ) {
			$color = '#ffb900'; // Yellow
		} elseif ( $status === 'error' ) {
			$color = '#dc3232'; // Red
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'nuclen-health-status',
				'title' => sprintf(
					'<span style="color: %s">%s</span> Nuclear Engagement',
					$color,
					$icon
				),
				'href'  => admin_url( 'admin.php?page=nuclear-engagement&tab=health' ),
				'meta'  => array(
					'title' => 'Nuclear Engagement Health Status: ' . ucfirst( $status ),
				),
			)
		);
	}
}
