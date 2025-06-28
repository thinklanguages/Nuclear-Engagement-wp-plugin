<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhanced error recovery and resilience system.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ErrorRecovery {
	/**
	 * Circuit breaker states.
	 */
	private const STATE_CLOSED = 'closed';
	private const STATE_OPEN = 'open';
	private const STATE_HALF_OPEN = 'half_open';

	/**
	 * Circuit breaker data.
	 *
	 * @var array<string, array{state: string, failures: int, last_failure: int, next_attempt: int}>
	 */
	private static array $circuit_breakers = [];

	/**
	 * Retry configurations.
	 *
	 * @var array<string, array{max_attempts: int, delay: int, backoff_multiplier: float}>
	 */
	private static array $retry_configs = [];

	/**
	 * Fallback handlers.
	 *
	 * @var array<string, callable>
	 */
	private static array $fallback_handlers = [];

	/**
	 * Error context storage.
	 *
	 * @var array<array{message: string, context: array, timestamp: int, level: string}>
	 */
	private static array $error_context = [];

	/**
	 * Default circuit breaker settings.
	 *
	 * @var array{failure_threshold: int, timeout: int, half_open_max_calls: int}
	 */
	private static array $default_breaker_config = [
		'failure_threshold'    => 5,
		'timeout'             => 60,
		'half_open_max_calls' => 3,
	];

	/**
	 * Initialize error recovery system.
	 */
	public static function init(): void {
		// Set up default retry configurations
		self::$retry_configs = [
			'default' => [
				'max_attempts'       => 3,
				'delay'             => 1000, // 1 second
				'backoff_multiplier' => 2.0,
			],
			'network' => [
				'max_attempts'       => 5,
				'delay'             => 500, // 0.5 seconds
				'backoff_multiplier' => 1.5,
			],
			'database' => [
				'max_attempts'       => 3,
				'delay'             => 2000, // 2 seconds
				'backoff_multiplier' => 2.5,
			],
		];

		// Register shutdown handler for emergency cleanup
		register_shutdown_function( [ self::class, 'handle_shutdown' ] );
	}

	/**
	 * Execute operation with circuit breaker protection.
	 *
	 * @param string   $service_id Service identifier.
	 * @param callable $operation  Operation to execute.
	 * @param array    $config     Circuit breaker configuration.
	 * @return mixed
	 * @throws \RuntimeException If circuit is open or operation fails.
	 */
	public static function executeWithCircuitBreaker( string $service_id, callable $operation, array $config = [] ) {
		$config = array_merge( self::$default_breaker_config, $config );
		
		if ( ! self::canExecute( $service_id, $config ) ) {
			$fallback = self::$fallback_handlers[$service_id] ?? null;
			if ( $fallback ) {
				return call_user_func( $fallback );
			}
			throw new \RuntimeException( "Circuit breaker is OPEN for service: {$service_id}" );
		}

		try {
			$result = call_user_func( $operation );
			self::recordSuccess( $service_id );
			return $result;
		} catch ( \Throwable $e ) {
			self::recordFailure( $service_id, $config );
			throw $e;
		}
	}

	/**
	 * Execute operation with retry logic.
	 *
	 * @param callable $operation Operation to execute.
	 * @param string   $type      Retry configuration type.
	 * @param array    $context   Additional context for logging.
	 * @return mixed
	 * @throws \RuntimeException If all retry attempts fail.
	 */
	public static function executeWithRetry( callable $operation, string $type = 'default', array $context = [] ) {
		$config = self::$retry_configs[$type] ?? self::$retry_configs['default'];
		$attempt = 0;
		$last_exception = null;

		while ( $attempt < $config['max_attempts'] ) {
			$attempt++;

			try {
				return call_user_func( $operation );
			} catch ( \Throwable $e ) {
				$last_exception = $e;
				
				self::logRetryAttempt( $attempt, $config['max_attempts'], $e, $context );

				if ( $attempt < $config['max_attempts'] ) {
					$delay = self::calculateDelay( $attempt, $config );
					usleep( $delay * 1000 ); // Convert to microseconds
				}
			}
		}

		throw new \RuntimeException( 
			"Operation failed after {$config['max_attempts']} attempts. Last error: " . $last_exception->getMessage(),
			0,
			$last_exception
		);
	}

	/**
	 * Execute operation with graceful degradation.
	 *
	 * @param callable      $primary_operation   Primary operation to try.
	 * @param callable|null $fallback_operation  Fallback operation if primary fails.
	 * @param array         $context            Context for logging.
	 * @return mixed
	 */
	public static function executeWithGracefulDegradation( callable $primary_operation, ?callable $fallback_operation = null, array $context = [] ) {
		try {
			return call_user_func( $primary_operation );
		} catch ( \Throwable $e ) {
			self::logDegradation( $e, $context );

			if ( $fallback_operation ) {
				try {
					return call_user_func( $fallback_operation );
				} catch ( \Throwable $fallback_error ) {
					self::logFallbackFailure( $fallback_error, $context );
					throw $fallback_error;
				}
			}

			throw $e;
		}
	}

	/**
	 * Register a fallback handler for a service.
	 *
	 * @param string   $service_id Service identifier.
	 * @param callable $handler    Fallback handler.
	 */
	public static function registerFallback( string $service_id, callable $handler ): void {
		self::$fallback_handlers[$service_id] = $handler;
	}

	/**
	 * Configure retry settings for a type.
	 *
	 * @param string $type   Retry type identifier.
	 * @param array  $config Retry configuration.
	 */
	public static function configureRetry( string $type, array $config ): void {
		self::$retry_configs[$type] = array_merge( self::$retry_configs['default'], $config );
	}

	/**
	 * Get circuit breaker status.
	 *
	 * @param string $service_id Service identifier.
	 * @return array{state: string, failures: int, last_failure: int}
	 */
	public static function getCircuitBreakerStatus( string $service_id ): array {
		$breaker = self::$circuit_breakers[$service_id] ?? [
			'state'        => self::STATE_CLOSED,
			'failures'     => 0,
			'last_failure' => 0,
			'next_attempt' => 0,
		];

		return [
			'state'        => $breaker['state'],
			'failures'     => $breaker['failures'],
			'last_failure' => $breaker['last_failure'],
		];
	}

	/**
	 * Reset circuit breaker.
	 *
	 * @param string $service_id Service identifier.
	 */
	public static function resetCircuitBreaker( string $service_id ): void {
		unset( self::$circuit_breakers[$service_id] );
	}

	/**
	 * Add error context for debugging.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context.
	 * @param string $level   Error level.
	 */
	public static function addErrorContext( string $message, array $context = [], string $level = 'error' ): void {
		self::$error_context[] = [
			'message'   => $message,
			'context'   => $context,
			'timestamp' => time(),
			'level'     => $level,
		];

		// Keep only last 100 entries
		if ( count( self::$error_context ) > 100 ) {
			self::$error_context = array_slice( self::$error_context, -100 );
		}
	}

	/**
	 * Get recent error context.
	 *
	 * @param int $limit Number of entries to return.
	 * @return array
	 */
	public static function getErrorContext( int $limit = 20 ): array {
		return array_slice( self::$error_context, -$limit );
	}

	/**
	 * Handle PHP shutdown and capture fatal errors.
	 */
	public static function handle_shutdown(): void {
		$error = error_get_last();
		
		if ( $error && in_array( $error['type'], [ E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ], true ) ) {
			self::addErrorContext( 
				'Fatal error detected during shutdown',
				[
					'error'    => $error,
					'context'  => self::getErrorContext( 5 ),
					'memory'   => memory_get_peak_usage( true ),
					'time'     => microtime( true ),
				],
				'critical'
			);

			// Try to log the fatal error
			if ( class_exists( 'NuclearEngagement\\Services\\LoggingService' ) ) {
				\NuclearEngagement\Services\LoggingService::log( 
					'Nuclear Engagement Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']
				);
			}
		}
	}

	/**
	 * Check if operation can be executed based on circuit breaker state.
	 *
	 * @param string $service_id Service identifier.
	 * @param array  $config     Circuit breaker configuration.
	 * @return bool
	 */
	private static function canExecute( string $service_id, array $config ): bool {
		if ( ! isset( self::$circuit_breakers[$service_id] ) ) {
			self::$circuit_breakers[$service_id] = [
				'state'        => self::STATE_CLOSED,
				'failures'     => 0,
				'last_failure' => 0,
				'next_attempt' => 0,
			];
		}

		$breaker = &self::$circuit_breakers[$service_id];
		$now = time();

		switch ( $breaker['state'] ) {
			case self::STATE_CLOSED:
				return true;

			case self::STATE_OPEN:
				if ( $now >= $breaker['next_attempt'] ) {
					$breaker['state'] = self::STATE_HALF_OPEN;
					return true;
				}
				return false;

			case self::STATE_HALF_OPEN:
				return true;

			default:
				return false;
		}
	}

	/**
	 * Record successful operation.
	 *
	 * @param string $service_id Service identifier.
	 */
	private static function recordSuccess( string $service_id ): void {
		if ( isset( self::$circuit_breakers[$service_id] ) ) {
			self::$circuit_breakers[$service_id] = [
				'state'        => self::STATE_CLOSED,
				'failures'     => 0,
				'last_failure' => 0,
				'next_attempt' => 0,
			];
		}
	}

	/**
	 * Record failed operation.
	 *
	 * @param string $service_id Service identifier.
	 * @param array  $config     Circuit breaker configuration.
	 */
	private static function recordFailure( string $service_id, array $config ): void {
		if ( ! isset( self::$circuit_breakers[$service_id] ) ) {
			self::$circuit_breakers[$service_id] = [
				'state'        => self::STATE_CLOSED,
				'failures'     => 0,
				'last_failure' => 0,
				'next_attempt' => 0,
			];
		}

		$breaker = &self::$circuit_breakers[$service_id];
		$breaker['failures']++;
		$breaker['last_failure'] = time();

		if ( $breaker['failures'] >= $config['failure_threshold'] ) {
			$breaker['state'] = self::STATE_OPEN;
			$breaker['next_attempt'] = time() + $config['timeout'];
		}
	}

	/**
	 * Calculate delay for retry attempt.
	 *
	 * @param int   $attempt Current attempt number.
	 * @param array $config  Retry configuration.
	 * @return int Delay in milliseconds.
	 */
	private static function calculateDelay( int $attempt, array $config ): int {
		return (int) ( $config['delay'] * pow( $config['backoff_multiplier'], $attempt - 1 ) );
	}

	/**
	 * Log retry attempt.
	 *
	 * @param int        $attempt     Current attempt.
	 * @param int        $max_attempts Maximum attempts.
	 * @param \Throwable $exception   Exception that occurred.
	 * @param array      $context     Additional context.
	 */
	private static function logRetryAttempt( int $attempt, int $max_attempts, \Throwable $exception, array $context ): void {
		self::addErrorContext(
			"Retry attempt {$attempt}/{$max_attempts} failed",
			array_merge( $context, [
				'exception' => $exception->getMessage(),
				'file'      => $exception->getFile(),
				'line'      => $exception->getLine(),
			] ),
			'warning'
		);
	}

	/**
	 * Log graceful degradation.
	 *
	 * @param \Throwable $exception Exception that occurred.
	 * @param array      $context   Additional context.
	 */
	private static function logDegradation( \Throwable $exception, array $context ): void {
		self::addErrorContext(
			'Primary operation failed, attempting graceful degradation',
			array_merge( $context, [
				'exception' => $exception->getMessage(),
				'file'      => $exception->getFile(),
				'line'      => $exception->getLine(),
			] ),
			'warning'
		);
	}

	/**
	 * Log fallback failure.
	 *
	 * @param \Throwable $exception Exception that occurred.
	 * @param array      $context   Additional context.
	 */
	private static function logFallbackFailure( \Throwable $exception, array $context ): void {
		self::addErrorContext(
			'Fallback operation also failed',
			array_merge( $context, [
				'exception' => $exception->getMessage(),
				'file'      => $exception->getFile(),
				'line'      => $exception->getLine(),
			] ),
			'critical'
		);
	}
}