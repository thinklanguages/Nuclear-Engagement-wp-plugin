<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ExceptionHandler {
	
	/**
	 * Handle plugin exceptions consistently
	 *
	 * @param \Throwable $exception The exception to handle.
	 * @param string     $context   Context where the exception occurred.
	 * @return array Error response array.
	 */
	public static function handle( \Throwable $exception, string $context = '' ): array {
		$error_data = [
			'success' => false,
			'error' => self::getUserFriendlyMessage( $exception ),
			'context' => $context,
			'timestamp' => current_time( 'mysql', true )
		];
		
		// Log the full exception details for debugging
		self::logException( $exception, $context );
		
		// Add debug info in development
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$error_data['debug'] = [
				'message' => $exception->getMessage(),
				'file' => $exception->getFile(),
				'line' => $exception->getLine(),
				'trace' => $exception->getTraceAsString()
			];
		}
		
		return $error_data;
	}
	
	/**
	 * Get user-friendly error message
	 *
	 * @param \Throwable $exception The exception.
	 * @return string User-friendly message.
	 */
	private static function getUserFriendlyMessage( \Throwable $exception ): string {
		$message = $exception->getMessage();
		
		// Convert technical errors to user-friendly messages
		$friendly_messages = [
			'Connection refused' => 'Unable to connect to the service. Please try again later.',
			'Timeout' => 'The request timed out. Please try again.',
			'Authentication failed' => 'Authentication failed. Please check your credentials.',
			'Permission denied' => 'You do not have permission to perform this action.',
			'Invalid input' => 'The provided input is invalid. Please check your data.',
			'Database error' => 'A database error occurred. Please try again later.',
		];
		
		foreach ( $friendly_messages as $technical => $friendly ) {
			if ( stripos( $message, $technical ) !== false ) {
				return $friendly;
			}
		}
		
		// Default fallback for unknown errors
		return 'An unexpected error occurred. Please try again later.';
	}
	
	/**
	 * Log exception details
	 *
	 * @param \Throwable $exception The exception to log.
	 * @param string     $context   Context information.
	 * @return void
	 */
	private static function logException( \Throwable $exception, string $context ): void {
		$log_message = sprintf(
			'[Nuclear Engagement] Exception in %s: %s in %s:%d',
			$context ?: 'Unknown context',
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine()
		);
		
		// Use WordPress error logging if available
		if ( function_exists( 'error_log' ) ) {
			error_log( $log_message );
		}
		
		// Also try to use the plugin's logging service if available
		if ( class_exists( '\NuclearEngagement\Services\LoggingService' ) ) {
			\NuclearEngagement\Services\LoggingService::log( $log_message );
		}
	}
	
	/**
	 * Create standardized error response
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @param array  $data    Additional error data.
	 * @return array Error response.
	 */
	public static function createErrorResponse( string $message, string $code = 'general_error', array $data = [] ): array {
		return [
			'success' => false,
			'error' => $message,
			'error_code' => $code,
			'data' => $data,
			'timestamp' => current_time( 'mysql', true )
		];
	}
	
	/**
	 * Create standardized success response
	 *
	 * @param mixed  $data    Response data.
	 * @param string $message Success message.
	 * @return array Success response.
	 */
	public static function createSuccessResponse( $data = null, string $message = 'Operation completed successfully' ): array {
		$response = [
			'success' => true,
			'message' => $message,
			'timestamp' => current_time( 'mysql', true )
		];
		
		if ( $data !== null ) {
			$response['data'] = $data;
		}
		
		return $response;
	}
}