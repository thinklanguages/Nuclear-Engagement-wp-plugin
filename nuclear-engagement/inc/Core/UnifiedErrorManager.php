<?php
/**
 * UnifiedErrorManager.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified Error Manager
 *
 * Consolidates multiple error handling systems into a single interface.
 * Provides backward compatibility while centralizing error management.
 */
class UnifiedErrorManager {

	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Handle error with unified interface.
	 *
	 * @param string          $message Error message.
	 * @param string          $category Error category.
	 * @param string          $severity Error severity.
	 * @param array           $context Additional context.
	 * @param \Throwable|null $exception Optional exception.
	 * @return bool Whether error was handled successfully.
	 */
	public function handleError(
		string $message,
		string $category = 'general',
		string $severity = 'error',
		array $context = array(),
		?\Throwable $exception = null
	): bool {
		try {
			// Primary handler: ErrorHandler.
			if ( class_exists( 'NuclearEngagement\Core\ErrorHandler' ) ) {
				return ErrorHandler::handle_error( $message, $category, $severity, $context );
			}

			// Fallback: ErrorManager.
			if ( class_exists( 'NuclearEngagement\Core\ErrorManager' ) ) {
				$errorManager = new ErrorManager();
				$errorManager->logError( $message, $context );
				return true;
			}

			// Last resort: WordPress error log.
			$log_message = sprintf(
				'[%s][%s] %s %s',
				$category,
				$severity,
				$message,
				$context ? wp_json_encode( $context ) : ''
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $log_message );

			return true;

		} catch ( \Throwable $e ) {
			// Prevent infinite recursion.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'UnifiedErrorManager failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Handle exception with unified interface.
	 *
	 * @param \Throwable $exception Exception to handle.
	 * @param array      $context Additional context.
	 * @return bool Whether exception was handled successfully.
	 */
	public function handleException( \Throwable $exception, array $context = array() ): bool {
		$message = sprintf(
			'%s: %s in %s:%d',
			get_class( $exception ),
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine()
		);

		$context['exception'] = array(
			'class' => get_class( $exception ),
			'file'  => $exception->getFile(),
			'line'  => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		);

		return $this->handleError( $message, 'exception', 'error', $context, $exception );
	}

	/**
	 * Log informational message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function log( string $message, array $context = array() ): void {
		$this->handleError( $message, 'log', 'info', $context );
	}

	/**
	 * Log warning message.
	 *
	 * @param string $message Warning message.
	 * @param array  $context Additional context.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->handleError( $message, 'warning', 'warning', $context );
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Debug message.
	 * @param array  $context Additional context.
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->handleError( $message, 'debug', 'debug', $context );
		}
	}

	/**
	 * Initialize error handling for the plugin.
	 */
	public static function init(): void {
		$instance = self::getInstance();

		// Set up global exception handler if needed.
		if ( ! defined( 'NUCLEN_ERROR_HANDLER_INITIALIZED' ) ) {
			set_exception_handler( array( $instance, 'handleException' ) );

			// Register WordPress hooks for plugin-specific errors.
			add_action(
				'wp_die_handler',
				function ( $handler ) use ( $instance ) {
					return function ( $message, $title, $args ) use ( $instance, $handler ) {
						$instance->handleError(
							$message,
							'wp_die',
							'error',
							array(
								'title' => $title,
								'args'  => $args,
							)
						);
						return $handler( $message, $title, $args );
					};
				}
			);

			define( 'NUCLEN_ERROR_HANDLER_INITIALIZED', true );
		}
	}

	/**
	 * Get error statistics for monitoring.
	 *
	 * @return array Error statistics.
	 */
	public function getErrorStats(): array {
		// Try to get stats from primary error handler.
		if ( class_exists( 'NuclearEngagement\Core\ErrorHandler' ) &&
			method_exists( 'NuclearEngagement\Core\ErrorHandler', 'get_error_stats' ) ) {
			return ErrorHandler::get_error_stats();
		}

		return array(
			'total_errors'  => 0,
			'recent_errors' => 0,
			'error_rate'    => 0.0,
		);
	}

	/**
	 * Clear error tracking data.
	 */
	public function clearErrorStats(): void {
		if ( class_exists( 'NuclearEngagement\Core\ErrorHandler' ) &&
			method_exists( 'NuclearEngagement\Core\ErrorHandler', 'clear_error_stats' ) ) {
			ErrorHandler::clear_error_stats();
		}
	}
}
