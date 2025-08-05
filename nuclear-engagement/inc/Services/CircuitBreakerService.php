<?php
/**
 * CircuitBreakerService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Circuit breaker pattern implementation for resource protection
 */
class CircuitBreakerService {

	/**
	 * Initialize circuit breaker service
	 */
	public static function init(): void {
		// Schedule daily cleanup
		if ( ! wp_next_scheduled( 'nuclen_circuit_breaker_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_circuit_breaker_cleanup' );
		}

		add_action( 'nuclen_circuit_breaker_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	/** Option prefix for circuit breaker state */
	private const OPTION_PREFIX = 'nuclen_circuit_breaker_';

	/** Default threshold for failures before opening circuit */
	private const DEFAULT_FAILURE_THRESHOLD = 5;

	/** Default timeout in seconds before attempting to close circuit */
	private const DEFAULT_TIMEOUT = 300; // 5 minutes

	/** Default success threshold to close circuit */
	private const DEFAULT_SUCCESS_THRESHOLD = 3;

	/**
	 * Circuit breaker states
	 */
	private const STATE_CLOSED    = 'closed';
	private const STATE_OPEN      = 'open';
	private const STATE_HALF_OPEN = 'half_open';

	/**
	 * Check if circuit is open for a service
	 *
	 * @param string $service_name Service identifier
	 * @return bool True if circuit is open (service should be blocked)
	 */
	public function is_open( string $service_name ): bool {
		$state = $this->get_state( $service_name );

		if ( $state['state'] === self::STATE_OPEN ) {
			// Check if timeout has passed
			if ( time() - $state['opened_at'] > $state['timeout'] ) {
				// Move to half-open state
				$this->set_state( $service_name, self::STATE_HALF_OPEN );
				return false;
			}
			return true;
		}

		return false;
	}

	/**
	 * Record a successful operation
	 *
	 * @param string $service_name Service identifier
	 */
	public function record_success( string $service_name ): void {
		$state = $this->get_state( $service_name );

		if ( $state['state'] === self::STATE_HALF_OPEN ) {
			++$state['success_count'];

			// Close circuit if success threshold reached
			if ( $state['success_count'] >= $state['success_threshold'] ) {
				$this->reset( $service_name );
			} else {
				$this->update_state( $service_name, $state );
			}
		} elseif ( $state['state'] === self::STATE_CLOSED ) {
			// Reset failure count on success
			$state['failure_count'] = 0;
			$this->update_state( $service_name, $state );
		}
	}

	/**
	 * Record a failed operation
	 *
	 * @param string $service_name Service identifier
	 * @param string $error_message Optional error message
	 */
	public function record_failure( string $service_name, string $error_message = '' ): void {
		$state = $this->get_state( $service_name );

		if ( $state['state'] === self::STATE_HALF_OPEN ) {
			// Immediately open circuit on failure in half-open state
			$this->open_circuit( $service_name, $error_message );
		} elseif ( $state['state'] === self::STATE_CLOSED ) {
			++$state['failure_count'];
			$state['last_error'] = $error_message;

			// Open circuit if failure threshold reached
			if ( $state['failure_count'] >= $state['failure_threshold'] ) {
				$this->open_circuit( $service_name, $error_message );
			} else {
				$this->update_state( $service_name, $state );
			}
		}
	}

	/**
	 * Get current state of circuit breaker
	 *
	 * @param string $service_name Service identifier
	 * @return array Circuit breaker state
	 */
	private function get_state( string $service_name ): array {
		$option_name = self::OPTION_PREFIX . $service_name;
		$default     = array(
			'state'             => self::STATE_CLOSED,
			'failure_count'     => 0,
			'success_count'     => 0,
			'failure_threshold' => self::DEFAULT_FAILURE_THRESHOLD,
			'success_threshold' => self::DEFAULT_SUCCESS_THRESHOLD,
			'timeout'           => self::DEFAULT_TIMEOUT,
			'opened_at'         => 0,
			'last_error'        => '',
		);

		$state = get_option( $option_name, $default );

		// Apply filters for customization
		$state['failure_threshold'] = apply_filters(
			'nuclen_circuit_breaker_failure_threshold',
			$state['failure_threshold'],
			$service_name
		);

		$state['timeout'] = apply_filters(
			'nuclen_circuit_breaker_timeout',
			$state['timeout'],
			$service_name
		);

		return $state;
	}

	/**
	 * Update circuit breaker state
	 *
	 * @param string $service_name Service identifier
	 * @param array  $state New state
	 */
	private function update_state( string $service_name, array $state ): void {
		$option_name = self::OPTION_PREFIX . $service_name;
		update_option( $option_name, $state, false );
	}

	/**
	 * Set circuit breaker to specific state
	 *
	 * @param string $service_name Service identifier
	 * @param string $new_state New state
	 */
	private function set_state( string $service_name, string $new_state ): void {
		$state          = $this->get_state( $service_name );
		$state['state'] = $new_state;

		if ( $new_state === self::STATE_HALF_OPEN ) {
			$state['success_count'] = 0;
		}

		$this->update_state( $service_name, $state );
	}

	/**
	 * Open the circuit breaker
	 *
	 * @param string $service_name Service identifier
	 * @param string $error_message Error message
	 */
	private function open_circuit( string $service_name, string $error_message ): void {
		$state                  = $this->get_state( $service_name );
		$state['state']         = self::STATE_OPEN;
		$state['opened_at']     = time();
		$state['last_error']    = $error_message;
		$state['failure_count'] = 0;
		$state['success_count'] = 0;

		$this->update_state( $service_name, $state );

		// Log circuit opening
		LoggingService::log(
			sprintf(
				'Circuit breaker opened for service "%s". Last error: %s',
				$service_name,
				$error_message
			)
		);

		// Trigger action for monitoring
		do_action( 'nuclen_circuit_breaker_opened', $service_name, $error_message );
	}

	/**
	 * Reset circuit breaker to closed state
	 *
	 * @param string $service_name Service identifier
	 */
	public function reset( string $service_name ): void {
		$option_name = self::OPTION_PREFIX . $service_name;
		delete_option( $option_name );

		// Log circuit reset
		// Circuit breaker reset

		// Trigger action for monitoring
		do_action( 'nuclen_circuit_breaker_closed', $service_name );
	}

	/**
	 * Get circuit breaker status for all services
	 *
	 * @return array Status of all circuit breakers
	 */
	public function get_all_statuses(): array {
		global $wpdb;

		$prefix  = self::OPTION_PREFIX;
		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value 
				FROM {$wpdb->options} 
				WHERE option_name LIKE %s",
				$prefix . '%'
			)
		);

		$statuses = array();

		// Cache date/time formats to avoid repeated get_option calls
		$date_format     = get_option( 'date_format' );
		$time_format     = get_option( 'time_format' );
		$datetime_format = $date_format . ' ' . $time_format;

		foreach ( $options as $option ) {
			$service_name = str_replace( $prefix, '', $option->option_name );
			$state        = maybe_unserialize( $option->option_value );

			if ( is_array( $state ) ) {
				$statuses[ $service_name ] = array(
					'state'         => $state['state'] ?? 'unknown',
					'failure_count' => $state['failure_count'] ?? 0,
					'last_error'    => $state['last_error'] ?? '',
					'opened_at'     => isset( $state['opened_at'] ) && $state['opened_at'] > 0
						? date_i18n( $datetime_format, $state['opened_at'] )
						: '',
				);
			}
		}

		return $statuses;
	}

	/**
	 * Clean up old circuit breaker states
	 */
	public static function cleanup(): void {
		global $wpdb;

		$prefix   = self::OPTION_PREFIX;
		$week_ago = time() - WEEK_IN_SECONDS;
		$cleaned  = 0;

		// Get all circuit breaker options
		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value 
				FROM {$wpdb->options} 
				WHERE option_name LIKE %s",
				$prefix . '%'
			)
		);

		if ( ! empty( $wpdb->last_error ) ) {
			LoggingService::log( 'Circuit breaker cleanup query error: ' . $wpdb->last_error );
			return;
		}

		foreach ( $options as $option ) {
			$state = maybe_unserialize( $option->option_value );

			// Remove if closed and no recent activity
			if ( is_array( $state ) &&
				$state['state'] === self::STATE_CLOSED &&
				$state['failure_count'] === 0 &&
				( ! isset( $state['opened_at'] ) || $state['opened_at'] < $week_ago )
			) {
				if ( delete_option( $option->option_name ) ) {
					++$cleaned;
				}
			}
		}

		// Cleanup completed
	}

	/**
	 * Deactivation cleanup
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'nuclen_circuit_breaker_cleanup' );
	}
}
