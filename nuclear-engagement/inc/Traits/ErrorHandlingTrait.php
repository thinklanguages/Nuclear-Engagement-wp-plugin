<?php
/**
 * ErrorHandlingTrait.php - Standardized error handling for services
 *
 * @package NuclearEngagement_Traits
 */

declare(strict_types=1);

namespace NuclearEngagement\Traits;

use NuclearEngagement\Core\ExceptionHandler;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides standardized error handling methods for services.
 *
 * Usage:
 * ```php
 * class MyService {
 *     use ErrorHandlingTrait;
 *
 *     public function doSomething(): array {
 *         try {
 *             // risky operation
 *         } catch ( \Exception $e ) {
 *             return $this->handle_exception( $e, 'doing_something' );
 *         }
 *     }
 * }
 * ```
 */
trait ErrorHandlingTrait {

	/**
	 * Handle an exception and return a standardized error response.
	 *
	 * @param \Throwable $exception The exception to handle.
	 * @param string     $context   Context where the exception occurred.
	 * @param array      $meta      Additional metadata to include in logs.
	 * @return array Standardized error response.
	 */
	protected function handle_exception( \Throwable $exception, string $context = '', array $meta = array() ): array {
		$full_context = $this->build_context( $context, $meta );
		return ExceptionHandler::handle( $exception, $full_context );
	}

	/**
	 * Log an exception without returning an error response.
	 *
	 * Use this when you need to log an error but continue processing.
	 *
	 * @param \Throwable $exception The exception to log.
	 * @param string     $context   Context where the exception occurred.
	 * @param array      $meta      Additional metadata to include in logs.
	 */
	protected function log_exception( \Throwable $exception, string $context = '', array $meta = array() ): void {
		$full_context = $this->build_context( $context, $meta );
		$this->log_error(
			sprintf( '%s: %s', $full_context, $exception->getMessage() ),
			array(
				'file'  => $exception->getFile(),
				'line'  => $exception->getLine(),
				'trace' => $exception->getTraceAsString(),
			)
		);
	}

	/**
	 * Log an exception and rethrow it.
	 *
	 * Use this when you need to log an error but let it propagate.
	 *
	 * @param \Throwable $exception The exception to log and rethrow.
	 * @param string     $context   Context where the exception occurred.
	 * @param array      $meta      Additional metadata to include in logs.
	 * @throws \Throwable The original exception.
	 */
	protected function log_and_rethrow( \Throwable $exception, string $context = '', array $meta = array() ): void {
		$this->log_exception( $exception, $context, $meta );
		throw $exception;
	}

	/**
	 * Execute a callback with error handling.
	 *
	 * @param callable $callback       The callback to execute.
	 * @param string   $context        Context for error messages.
	 * @param mixed    $default_return Value to return on error (null returns error array).
	 * @return mixed Callback result or default/error response on failure.
	 */
	protected function with_error_handling( callable $callback, string $context = '', $default_return = null ) {
		try {
			return $callback();
		} catch ( \Throwable $e ) {
			if ( null === $default_return ) {
				return $this->handle_exception( $e, $context );
			}

			$this->log_exception( $e, $context );
			return $default_return;
		}
	}

	/**
	 * Log an error message with optional metadata.
	 *
	 * @param string $message Error message.
	 * @param array  $meta    Additional metadata.
	 */
	protected function log_error( string $message, array $meta = array() ): void {
		$log_message = '[' . $this->get_service_identifier() . '] ' . $message;

		if ( ! empty( $meta ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message .= ' | Meta: ' . wp_json_encode( $meta );
		}

		if ( class_exists( LoggingService::class ) ) {
			LoggingService::log( $log_message, 'error' );
		} elseif ( function_exists( 'error_log' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Nuclear Engagement] ' . $log_message );
		}
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Warning message.
	 * @param array  $meta    Additional metadata.
	 */
	protected function log_warning( string $message, array $meta = array() ): void {
		$log_message = '[' . $this->get_service_identifier() . '] ' . $message;

		if ( ! empty( $meta ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message .= ' | Meta: ' . wp_json_encode( $meta );
		}

		if ( class_exists( LoggingService::class ) ) {
			LoggingService::log( $log_message, 'warning' );
		}
	}

	/**
	 * Create an error response.
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @param array  $data    Additional error data.
	 * @return array Error response.
	 */
	protected function error_response( string $message, string $code = 'general_error', array $data = array() ): array {
		return ExceptionHandler::createErrorResponse( $message, $code, $data );
	}

	/**
	 * Create a success response.
	 *
	 * @param mixed  $data    Response data.
	 * @param string $message Success message.
	 * @return array Success response.
	 */
	protected function success_response( $data = null, string $message = 'Operation completed successfully' ): array {
		return ExceptionHandler::createSuccessResponse( $data, $message );
	}

	/**
	 * Build context string with service name and metadata.
	 *
	 * @param string $context Base context.
	 * @param array  $meta    Additional metadata.
	 * @return string Full context string.
	 */
	private function build_context( string $context, array $meta = array() ): string {
		$service_name = $this->get_service_identifier();
		$full_context = $service_name;

		if ( ! empty( $context ) ) {
			$full_context .= '::' . $context;
		}

		if ( ! empty( $meta ) ) {
			$meta_parts = array();
			foreach ( $meta as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$meta_parts[] = $key . '=' . $value;
				}
			}
			if ( ! empty( $meta_parts ) ) {
				$full_context .= ' (' . implode( ', ', $meta_parts ) . ')';
			}
		}

		return $full_context;
	}

	/**
	 * Get service identifier for logging.
	 *
	 * If the using class has a get_service_name() method (e.g., from BaseService),
	 * it will be used. Otherwise, falls back to class name.
	 *
	 * @return string Service identifier.
	 */
	protected function get_service_identifier(): string {
		// Check if the class has get_service_name (e.g., BaseService subclasses).
		if ( method_exists( $this, 'get_service_name' ) && is_callable( array( $this, 'get_service_name' ) ) ) {
			return $this->get_service_name();
		}

		// Use reflection to get class short name if not overridden.
		$class_name = static::class;
		$parts      = explode( '\\', $class_name );
		return end( $parts );
	}
}
