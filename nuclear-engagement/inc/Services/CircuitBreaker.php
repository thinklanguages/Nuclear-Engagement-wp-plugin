<?php
/**
 * CircuitBreaker.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple circuit breaker implementation for API calls
 */
class CircuitBreaker {

	/**
	 * Option name for circuit breaker state
	 */
	private const STATE_OPTION = 'nuclen_circuit_breaker_state';

	/**
	 * Circuit states
	 */
	private const STATE_CLOSED    = 'closed';
	private const STATE_OPEN      = 'open';
	private const STATE_HALF_OPEN = 'half_open';

	/**
	 * Default thresholds
	 */
	private const DEFAULT_FAILURE_THRESHOLD = 5;
	private const DEFAULT_TIMEOUT           = 300; // 5 minutes
	private const DEFAULT_SUCCESS_THRESHOLD = 2;
	private const HEALTH_CHECK_INTERVAL     = 600; // 10 minutes
	private const MAX_OPEN_DURATION         = 3600; // 1 hour max open time

	/**
	 * @var int Failure threshold
	 */
	private int $failure_threshold;

	/**
	 * @var int Timeout in seconds
	 */
	private int $timeout;

	/**
	 * @var int Success threshold for half-open state
	 */
	private int $success_threshold;

	/**
	 * @var string Service identifier
	 */
	private string $service_id;

	/**
	 * Constructor
	 *
	 * @param string $service_id Service identifier
	 * @param int    $failure_threshold Number of failures before opening circuit
	 * @param int    $timeout Seconds before trying half-open state
	 * @param int    $success_threshold Successes needed to close circuit
	 */
	public function __construct(
		string $service_id = 'api',
		int $failure_threshold = self::DEFAULT_FAILURE_THRESHOLD,
		int $timeout = self::DEFAULT_TIMEOUT,
		int $success_threshold = self::DEFAULT_SUCCESS_THRESHOLD
	) {
		$this->service_id        = $service_id;
		$this->failure_threshold = $failure_threshold;
		$this->timeout           = $timeout;
		$this->success_threshold = $success_threshold;
	}

	/**
	 * Check if request is allowed
	 *
	 * @return bool
	 */
	public function is_request_allowed(): bool {
		$state = $this->get_state();

		switch ( $state['status'] ) {
			case self::STATE_CLOSED:
				return true;

			case self::STATE_OPEN:
				// Check if timeout has passed
				if ( time() - $state['opened_at'] > $this->timeout ) {
					$this->transition_to_half_open();
					return true;
				}

				// Force reset if circuit has been open too long
				if ( time() - $state['opened_at'] > self::MAX_OPEN_DURATION ) {
					$this->force_reset();
					return true;
				}

				// Check if we should perform a health check
				if ( $this->should_perform_health_check( $state ) ) {
					$this->schedule_health_check();
				}

				return false;

			case self::STATE_HALF_OPEN:
				// Allow limited requests in half-open state
				return $state['half_open_attempts'] < $this->success_threshold;

			default:
				return true;
		}
	}

	/**
	 * Record successful request
	 */
	public function record_success(): void {
		$state = $this->get_state();

		switch ( $state['status'] ) {
			case self::STATE_HALF_OPEN:
				++$state['half_open_successes'];
				if ( $state['half_open_successes'] >= $this->success_threshold ) {
					$this->close_circuit();
				} else {
					$this->update_state( $state );
				}
				break;

			case self::STATE_OPEN:
				// Shouldn't happen, but close circuit if it does
				$this->close_circuit();
				break;

			case self::STATE_CLOSED:
				// Reset failure count on success
				if ( $state['consecutive_failures'] > 0 ) {
					$state['consecutive_failures'] = 0;
					$this->update_state( $state );
				}
				break;
		}
	}

	/**
	 * Record failed request
	 */
	public function record_failure(): void {
		$state = $this->get_state();

		switch ( $state['status'] ) {
			case self::STATE_CLOSED:
				++$state['consecutive_failures'];
				if ( $state['consecutive_failures'] >= $this->failure_threshold ) {
					$this->open_circuit();
				} else {
					$this->update_state( $state );
				}
				break;

			case self::STATE_HALF_OPEN:
				// Single failure in half-open returns to open
				$this->open_circuit();
				break;

			case self::STATE_OPEN:
				// Already open, update last failure time
				$state['last_failure'] = time();
				$this->update_state( $state );
				break;
		}
	}

	/**
	 * Get circuit state
	 *
	 * @return array
	 */
	private function get_state(): array {
		$option_name = self::STATE_OPTION . '_' . $this->service_id;
		$state       = get_option( $option_name, array() );

		if ( empty( $state ) ) {
			$state = array(
				'status'               => self::STATE_CLOSED,
				'consecutive_failures' => 0,
				'opened_at'            => 0,
				'last_failure'         => 0,
				'half_open_attempts'   => 0,
				'half_open_successes'  => 0,
			);
		}

		return $state;
	}

	/**
	 * Update circuit state
	 *
	 * @param array $state New state
	 */
	private function update_state( array $state ): void {
		$option_name = self::STATE_OPTION . '_' . $this->service_id;
		update_option( $option_name, $state, 'no' );
	}

	/**
	 * Open the circuit
	 */
	private function open_circuit(): void {
		$state = array(
			'status'               => self::STATE_OPEN,
			'consecutive_failures' => $this->failure_threshold,
			'opened_at'            => time(),
			'last_failure'         => time(),
			'half_open_attempts'   => 0,
			'half_open_successes'  => 0,
		);

		$this->update_state( $state );

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'Circuit breaker OPENED for service %s after %d failures',
				$this->service_id,
				$this->failure_threshold
			)
		);
	}

	/**
	 * Close the circuit
	 */
	private function close_circuit(): void {
		$state = array(
			'status'               => self::STATE_CLOSED,
			'consecutive_failures' => 0,
			'opened_at'            => 0,
			'last_failure'         => 0,
			'half_open_attempts'   => 0,
			'half_open_successes'  => 0,
		);

		$this->update_state( $state );

		\NuclearEngagement\Services\LoggingService::log(
			sprintf( 'Circuit breaker CLOSED for service %s', $this->service_id )
		);
	}

	/**
	 * Transition to half-open state
	 */
	private function transition_to_half_open(): void {
		$state                        = $this->get_state();
		$state['status']              = self::STATE_HALF_OPEN;
		$state['half_open_attempts']  = 1; // Count this attempt
		$state['half_open_successes'] = 0;

		$this->update_state( $state );

		\NuclearEngagement\Services\LoggingService::log(
			sprintf( 'Circuit breaker HALF-OPEN for service %s', $this->service_id )
		);
	}

	/**
	 * Get human-readable status
	 *
	 * @return array
	 */
	public function get_status(): array {
		$state = $this->get_state();

		return array(
			'status'           => $state['status'],
			'is_open'          => $state['status'] === self::STATE_OPEN,
			'failures'         => $state['consecutive_failures'],
			'time_until_retry' => $state['status'] === self::STATE_OPEN ?
				max( 0, $this->timeout - ( time() - $state['opened_at'] ) ) : 0,
		);
	}

	/**
	 * Force reset the circuit breaker
	 */
	public function force_reset(): void {
		$this->close_circuit();

		\NuclearEngagement\Services\LoggingService::log(
			sprintf( 'Circuit breaker FORCE RESET for service %s', $this->service_id )
		);
	}

	/**
	 * Check if we should perform a health check
	 *
	 * @param array $state Current state
	 * @return bool
	 */
	private function should_perform_health_check( array $state ): bool {
		$last_check = $state['last_health_check'] ?? 0;
		return ( time() - $last_check ) > self::HEALTH_CHECK_INTERVAL;
	}

	/**
	 * Schedule a health check
	 */
	private function schedule_health_check(): void {
		$hook = 'nuclen_circuit_breaker_health_check_' . $this->service_id;

		// Only schedule if not already scheduled
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( time() + 60, $hook );

			// Update last health check time
			$state                      = $this->get_state();
			$state['last_health_check'] = time();
			$this->update_state( $state );
		}
	}

	/**
	 * Perform health check (to be called by cron)
	 */
	public function perform_health_check(): void {
		$state = $this->get_state();

		// Only check if circuit is open
		if ( $state['status'] !== self::STATE_OPEN ) {
			return;
		}

		// Transition to half-open to allow a test request
		$this->transition_to_half_open();

		\NuclearEngagement\Services\LoggingService::log(
			sprintf( 'Circuit breaker health check initiated for service %s', $this->service_id )
		);
	}

	/**
	 * Register health check handler
	 */
	public static function register_health_check_handlers(): void {
		// Register for the main API service
		$hook = 'nuclen_circuit_breaker_health_check_api';
		add_action(
			$hook,
			function () {
				$circuit_breaker = new self( 'api' );
				$circuit_breaker->perform_health_check();
			}
		);
	}
}
