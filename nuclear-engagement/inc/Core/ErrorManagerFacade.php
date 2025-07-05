<?php
/**
 * ErrorManagerFacade.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facade for the refactored error management system.
 *
 * Provides a unified interface to the separated error handling,
 * analytics, and notification components while maintaining
 * backward compatibility with existing code.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ErrorManagerFacade {
	/**
	 * Initialize all error management components.
	 */
	public static function init(): void {
		// Initialize analytics.
		ErrorAnalytics::init();
		ErrorAnalytics::load_tracking_data();

		// Initialize notifications.
		ErrorNotification::init();

		// Set up WordPress error hooks.
		add_action( 'wp_die_handler', array( self::class, 'handle_wp_die' ) );
		add_filter( 'wp_die_ajax_handler', array( self::class, 'handle_ajax_error' ) );
		add_filter( 'wp_die_json_handler', array( self::class, 'handle_json_error' ) );

		// Hook into PHP error handling.
		set_error_handler( array( ErrorHandler::class, 'handle_php_error' ) );
		set_exception_handler( array( ErrorHandler::class, 'handle_uncaught_exception' ) );

		// Register shutdown handler for fatal errors.
		register_shutdown_function( array( ErrorHandler::class, 'handle_shutdown_error' ) );

		// Clean up old error data periodically.
		if ( ! wp_next_scheduled( 'nuclen_cleanup_error_data' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_error_data' );
		}
		add_action( 'nuclen_cleanup_error_data', array( self::class, 'cleanup_error_data' ) );
	}

	/**
	 * Handle error with comprehensive processing.
	 *
	 * @param string          $message Error message.
	 * @param string          $severity Error severity level.
	 * @param string          $category Error category.
	 * @param array           $context Additional context data.
	 * @param \Throwable|null $exception Related exception if any.
	 * @return array Error data array.
	 */
	public static function handle_error(
		string $message,
		string $severity = ErrorHandler::SEVERITY_MEDIUM,
		string $category = ErrorHandler::CATEGORY_VALIDATION,
		array $context = array(),
		?\Throwable $exception = null
	): array {
		// Handle the error using ErrorHandler.
		$error_data = ErrorHandler::handle_error( $message, $severity, $category, $context, $exception );

		// Track analytics if not rate limited.
		if ( ! ErrorAnalytics::is_rate_limited( $category, $severity ) ) {
			ErrorAnalytics::track_error( $error_data );
		}

		// Process notifications.
		ErrorNotification::process_error_notification( $error_data );

		return $error_data;
	}

	/**
	 * Handle WordPress die errors.
	 *
	 * @param callable $handler Original handler.
	 * @return callable Modified handler.
	 */
	public static function handle_wp_die( $handler ) {
		return function ( $message, $title = '', $args = array() ) use ( $handler ) {
			// Log the wp_die error.
			self::handle_error(
				is_string( $message ) ? $message : 'WordPress die error',
				ErrorHandler::SEVERITY_HIGH,
				ErrorHandler::CATEGORY_CONFIGURATION,
				array(
					'title' => $title,
					'args'  => $args,
				)
			);

			// Call original handler.
			return call_user_func( $handler, $message, $title, $args );
		};
	}

	/**
	 * Handle AJAX errors.
	 *
	 * @param callable $handler Original handler.
	 * @return callable Modified handler.
	 */
	public static function handle_ajax_error( $handler ) {
		return function ( $message, $title = '', $args = array() ) use ( $handler ) {
			self::handle_error(
				is_string( $message ) ? $message : 'AJAX error',
				ErrorHandler::SEVERITY_MEDIUM,
				ErrorHandler::CATEGORY_NETWORK,
				array(
					'title' => $title,
					'args'  => $args,
					'ajax'  => true,
				)
			);

			return call_user_func( $handler, $message, $title, $args );
		};
	}

	/**
	 * Handle JSON errors.
	 *
	 * @param callable $handler Original handler.
	 * @return callable Modified handler.
	 */
	public static function handle_json_error( $handler ) {
		return function ( $message, $title = '', $args = array() ) use ( $handler ) {
			self::handle_error(
				is_string( $message ) ? $message : 'JSON error',
				ErrorHandler::SEVERITY_MEDIUM,
				ErrorHandler::CATEGORY_VALIDATION,
				array(
					'title' => $title,
					'args'  => $args,
					'json'  => true,
				)
			);

			return call_user_func( $handler, $message, $title, $args );
		};
	}

	/**
	 * Get error statistics.
	 *
	 * @param int $since_timestamp Get stats since this timestamp.
	 * @return array Error statistics.
	 */
	public static function get_error_stats( int $since_timestamp = 0 ): array {
		return ErrorAnalytics::get_error_stats( $since_timestamp );
	}

	/**
	 * Get error trends.
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Error trend data.
	 */
	public static function get_error_trends( int $days = 7 ): array {
		return ErrorAnalytics::get_error_trends( $days );
	}

	/**
	 * Get security events.
	 *
	 * @param int $since_timestamp Get events since this timestamp.
	 * @return array Security events summary.
	 */
	public static function get_security_events( int $since_timestamp = 0 ): array {
		return ErrorNotification::get_security_events( $since_timestamp );
	}

	/**
	 * Check if error type is rate limited.
	 *
	 * @param string $category Error category.
	 * @param string $severity Error severity.
	 * @param int    $limit Maximum errors per window.
	 * @param int    $window Window duration in seconds.
	 * @return bool True if rate limited, false otherwise.
	 */
	public static function is_rate_limited( string $category, string $severity, int $limit = 10, int $window = 300 ): bool {
		return ErrorAnalytics::is_rate_limited( $category, $severity, $limit, $window );
	}

	/**
	 * Clean up old error data.
	 */
	public static function cleanup_error_data(): void {
		ErrorAnalytics::cleanup_analytics_data();
		ErrorNotification::cleanup_security_events();
	}

	/**
	 * Reset all error management data.
	 */
	public static function reset_all_data(): void {
		ErrorAnalytics::reset_analytics();
		// Security events will be cleaned up during next cleanup cycle.
	}

	// Backward compatibility constants.
	public const SEVERITY_CRITICAL = ErrorHandler::SEVERITY_CRITICAL;
	public const SEVERITY_HIGH     = ErrorHandler::SEVERITY_HIGH;
	public const SEVERITY_MEDIUM   = ErrorHandler::SEVERITY_MEDIUM;
	public const SEVERITY_LOW      = ErrorHandler::SEVERITY_LOW;

	public const CATEGORY_AUTHENTICATION = ErrorHandler::CATEGORY_AUTHENTICATION;
	public const CATEGORY_DATABASE       = ErrorHandler::CATEGORY_DATABASE;
	public const CATEGORY_NETWORK        = ErrorHandler::CATEGORY_NETWORK;
	public const CATEGORY_VALIDATION     = ErrorHandler::CATEGORY_VALIDATION;
	public const CATEGORY_PERMISSIONS    = ErrorHandler::CATEGORY_PERMISSIONS;
	public const CATEGORY_RESOURCE       = ErrorHandler::CATEGORY_RESOURCE;
	public const CATEGORY_SECURITY       = ErrorHandler::CATEGORY_SECURITY;
	public const CATEGORY_CONFIGURATION  = ErrorHandler::CATEGORY_CONFIGURATION;
	public const CATEGORY_EXTERNAL_API   = ErrorHandler::CATEGORY_EXTERNAL_API;
	public const CATEGORY_FILE_SYSTEM    = ErrorHandler::CATEGORY_FILE_SYSTEM;
}
