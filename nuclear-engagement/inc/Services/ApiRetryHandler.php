<?php
/**
 * ApiRetryHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Exceptions\ApiException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles API calls with retry logic and circuit breaker pattern
 */
class ApiRetryHandler {

	/** @var int */
	private int $max_retries = 3;

	/** @var array */
	private array $retry_delays = array( 1000, 2000, 4000 ); // milliseconds

	/** @var array */
	private static array $circuit_breakers = array();

	/** @var int */
	private const CIRCUIT_FAILURE_THRESHOLD = 5;

	/** @var int */
	private const CIRCUIT_TIMEOUT = 300; // 5 minutes

	/** @var int */
	private const CIRCUIT_HALF_OPEN_REQUESTS = 1;

	/**
	 * Constructor
	 *
	 * @param int   $max_retries Maximum number of retries
	 * @param array $retry_delays Delays between retries in milliseconds
	 */
	public function __construct( int $max_retries = 3, array $retry_delays = array() ) {
		$this->max_retries = $max_retries;
		if ( ! empty( $retry_delays ) ) {
			$this->retry_delays = $retry_delays;
		}
	}

	/**
	 * Execute API call with retry logic and circuit breaker
	 *
	 * @param callable $api_call The API call to execute
	 * @param string   $service_name Name of the service for circuit breaker
	 * @param array    $context Additional context for logging
	 * @return mixed API response
	 * @throws ApiException When all retries fail or circuit is open
	 */
	public function execute( callable $api_call, string $service_name, array $context = array() ) {
		// Check circuit breaker
		$circuit_state = $this->get_circuit_state( $service_name );

		if ( $circuit_state === 'open' ) {
			throw ApiException::httpError(
				$service_name,
				503,
				array( 'error' => 'Service temporarily unavailable (circuit open)' )
			)->setUserMessage( __( 'Service is temporarily unavailable. Please try again later.', 'nuclear-engagement' ) );
		}

		$last_exception = null;
		$attempts       = 0;
		$max_attempts   = ( $circuit_state === 'half-open' ) ? 1 : $this->max_retries + 1;

		while ( $attempts < $max_attempts ) {
			++$attempts;

			try {
				// Log attempt
				LoggingService::log(
					sprintf(
						'API call attempt %d/%d for %s',
						$attempts,
						$max_attempts,
						$service_name
					),
					'info',
					$context
				);

				// Execute the API call - pass the attempt number
				$result = $api_call( $attempts - 1 ); // Zero-based attempt index

				// Success - record it
				$this->record_success( $service_name );

				return $result;

			} catch ( \Throwable $e ) {
				$last_exception = $e;

				// Record failure
				$this->record_failure( $service_name );

				// Convert to ApiException if needed
				if ( ! ( $e instanceof ApiException ) ) {
					$last_exception = ApiException::networkError( $service_name, $e->getMessage() );
				}

				// Check if error is retryable
				if ( ! $this->is_retryable( $last_exception ) ) {
					throw $last_exception;
				}

				// Log retry
				LoggingService::log(
					sprintf(
						'API call failed (attempt %d/%d): %s',
						$attempts,
						$max_attempts,
						$e->getMessage()
					),
					'warning',
					array_merge(
						$context,
						array(
							'exception_class' => get_class( $e ),
							'is_retryable'    => true,
						)
					)
				);

				// Don't retry if circuit is half-open
				if ( $circuit_state === 'half-open' ) {
					break;
				}

				// Wait before retry (except on last attempt)
				if ( $attempts < $max_attempts ) {
					$delay = $this->get_retry_delay( $attempts - 1 );
					usleep( $delay * 1000 ); // Convert to microseconds
				}
			}
		}

		// All attempts failed
		if ( $last_exception ) {
			throw $last_exception;
		}

		throw ApiException::networkError( $service_name, 'All retry attempts failed' );
	}

	/**
	 * Execute multiple API calls in parallel with retry logic
	 *
	 * @param array  $api_calls Array of callables
	 * @param string $service_name Service name for circuit breaker
	 * @return array Results indexed by array keys
	 * @throws ApiException When any call fails after retries
	 */
	public function execute_batch( array $api_calls, string $service_name ): array {
		$results = array();
		$errors  = array();

		foreach ( $api_calls as $key => $api_call ) {
			try {
				$results[ $key ] = $this->execute( $api_call, $service_name, array( 'batch_key' => $key ) );
			} catch ( ApiException $e ) {
				$errors[ $key ] = $e;
			}
		}

		// If any errors occurred, throw a batch exception
		if ( ! empty( $errors ) ) {
			$error_count = count( $errors );
			$total_count = count( $api_calls );

			throw new ApiException(
				sprintf( '%d of %d API calls failed', $error_count, $total_count ),
				0,
				array(
					'errors'  => $errors,
					'results' => $results,
				),
				false
			);
		}

		return $results;
	}

	/**
	 * Check if exception is retryable
	 */
	private function is_retryable( \Throwable $e ): bool {
		if ( ! ( $e instanceof ApiException ) ) {
			return true; // Network errors are retryable
		}

		return $e->is_retryable();
	}

	/**
	 * Get retry delay for attempt with jitter
	 */
	private function get_retry_delay( int $attempt ): int {
		if ( isset( $this->retry_delays[ $attempt ] ) ) {
			$base_delay = $this->retry_delays[ $attempt ];
		} else {
			// Exponential backoff: 1s, 2s, 4s, 8s, 16s, 30s (max)
			$base_delay = min( 1000 * pow( 2, $attempt ), 30000 );
		}

		// Add jitter (±25%) to prevent thundering herd
		$jitter = $base_delay * 0.25;
		$delay  = $base_delay + mt_rand( (int) ( -$jitter ), (int) $jitter );

		LoggingService::log(
			sprintf(
				'Retry delay for attempt %d: %dms (base: %dms)',
				$attempt + 1,
				$delay,
				$base_delay
			)
		);

		return max( 100, $delay ); // Minimum 100ms
	}

	/**
	 * Get circuit breaker state
	 */
	private function get_circuit_state( string $service_name ): string {
		$circuit_key  = 'nuclen_circuit_' . md5( $service_name );
		$circuit_data = get_transient( $circuit_key );

		if ( ! $circuit_data ) {
			return 'closed';
		}

		// Check if circuit should transition from open to half-open
		if ( $circuit_data['state'] === 'open' &&
			time() - $circuit_data['opened_at'] >= self::CIRCUIT_TIMEOUT ) {

			$circuit_data['state']              = 'half-open';
			$circuit_data['half_open_requests'] = 0;
			set_transient( $circuit_key, $circuit_data, self::CIRCUIT_TIMEOUT * 2 );
		}

		self::$circuit_breakers[ $service_name ] = $circuit_data;

		return $circuit_data['state'];
	}

	/**
	 * Record successful API call
	 */
	private function record_success( string $service_name ): void {
		$circuit_key  = 'nuclen_circuit_' . md5( $service_name );
		$circuit_data = get_transient( $circuit_key );

		if ( ! $circuit_data ) {
			return;
		}

		if ( $circuit_data['state'] === 'half-open' ) {
			// Success in half-open state - close the circuit
			delete_transient( $circuit_key );
			unset( self::$circuit_breakers[ $service_name ] );

			LoggingService::log(
				sprintf(
					'Circuit breaker closed for %s after successful half-open request',
					$service_name
				)
			);
		} elseif ( $circuit_data['state'] === 'closed' ) {
			// Reset failure count on success
			$circuit_data['failure_count'] = 0;
			set_transient( $circuit_key, $circuit_data, self::CIRCUIT_TIMEOUT * 2 );
		}
	}

	/**
	 * Record failed API call
	 */
	private function record_failure( string $service_name ): void {
		$circuit_key  = 'nuclen_circuit_' . md5( $service_name );
		$circuit_data = get_transient( $circuit_key );

		if ( ! $circuit_data ) {
			$circuit_data = array(
				'state'         => 'closed',
				'failure_count' => 0,
				'last_failure'  => 0,
			);
		}

		++$circuit_data['failure_count'];
		$circuit_data['last_failure'] = time();

		// Check if we should open the circuit
		if ( $circuit_data['state'] === 'closed' &&
			$circuit_data['failure_count'] >= self::CIRCUIT_FAILURE_THRESHOLD ) {

			$circuit_data['state']     = 'open';
			$circuit_data['opened_at'] = time();

			LoggingService::log(
				sprintf(
					'Circuit breaker opened for %s after %d failures',
					$service_name,
					$circuit_data['failure_count']
				),
				'error'
			);

			// Notify admins
			do_action( 'nuclen_circuit_breaker_opened', $service_name, $circuit_data );
		} elseif ( $circuit_data['state'] === 'half-open' ) {
			// Failure in half-open state - reopen the circuit
			$circuit_data['state']     = 'open';
			$circuit_data['opened_at'] = time();

			LoggingService::log(
				sprintf(
					'Circuit breaker reopened for %s after half-open failure',
					$service_name
				)
			);
		}

		set_transient( $circuit_key, $circuit_data, self::CIRCUIT_TIMEOUT * 2 );
		self::$circuit_breakers[ $service_name ] = $circuit_data;
	}

	/**
	 * Reset circuit breaker for a service
	 */
	public static function reset_circuit( string $service_name ): void {
		$circuit_key = 'nuclen_circuit_' . md5( $service_name );
		delete_transient( $circuit_key );
		unset( self::$circuit_breakers[ $service_name ] );

		LoggingService::log( sprintf( 'Circuit breaker manually reset for %s', $service_name ) );
	}

	/**
	 * Get circuit breaker status for all services
	 */
	public static function get_circuit_status(): array {
		global $wpdb;

		$prefix = $wpdb->prefix . 'options';
		$like   = $wpdb->esc_like( '_transient_nuclen_circuit_' ) . '%';

		$circuits = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $prefix WHERE option_name LIKE %s",
				$like
			)
		);

		$status = array();

		foreach ( $circuits as $circuit ) {
			$data = maybe_unserialize( $circuit->option_value );
			if ( is_array( $data ) && isset( $data['state'] ) ) {
				$service_name            = str_replace( '_transient_nuclen_circuit_', '', $circuit->option_name );
				$status[ $service_name ] = $data;
			}
		}

		return $status;
	}
}
