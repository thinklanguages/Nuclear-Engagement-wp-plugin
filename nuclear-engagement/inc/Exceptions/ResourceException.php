<?php
/**
 * ResourceException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Exceptions
 */

declare(strict_types=1);

namespace NuclearEngagement\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception thrown when resource limits are exceeded
 */
class ResourceException extends BaseException {
	/**
	 * @var string Exception severity - always critical for resource issues
	 */
	protected string $severity = 'critical';

	/**
	 * Create exception for memory limit exceeded
	 *
	 * @param float $current_percent Current memory usage percentage
	 * @param float $limit_percent Maximum allowed percentage
	 * @return self
	 */
	public static function memoryLimitExceeded( float $current_percent, float $limit_percent ): self {
		$memory_usage = memory_get_usage( true );
		$memory_peak  = memory_get_peak_usage( true );

		return new self(
			sprintf( 'Memory usage too high: %.1f%% (limit: %.0f%%)', $current_percent, $limit_percent ),
			500,
			null,
			array(
				'current_percent'    => $current_percent,
				'limit_percent'      => $limit_percent,
				'memory_usage'       => $memory_usage,
				'memory_peak'        => $memory_peak,
				'memory_usage_human' => size_format( $memory_usage ),
				'memory_peak_human'  => size_format( $memory_peak ),
			)
		);
	}

	/**
	 * Create exception for execution time exceeded
	 *
	 * @param float $time_percent Current execution time percentage
	 * @param float $limit_percent Maximum allowed percentage
	 * @return self
	 */
	public static function executionTimeExceeded( float $time_percent, float $limit_percent ): self {
		$max_execution = (int) ini_get( 'max_execution_time' );
		$elapsed       = microtime( true ) - ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );

		return new self(
			sprintf( 'Execution time limit approaching: %.1f%% used (limit: %.0f%%)', $time_percent, $limit_percent ),
			500,
			null,
			array(
				'time_percent'       => $time_percent,
				'limit_percent'      => $limit_percent,
				'elapsed_seconds'    => $elapsed,
				'max_execution_time' => $max_execution,
			)
		);
	}

	/**
	 * Create exception for concurrent request limit exceeded
	 *
	 * @param int $current_requests Current number of concurrent requests
	 * @param int $max_requests Maximum allowed concurrent requests
	 * @return self
	 */
	public static function concurrentRequestLimitExceeded( int $current_requests, int $max_requests ): self {
		return new self(
			sprintf( 'Too many concurrent requests: %d (limit: %d)', $current_requests, $max_requests ),
			503,
			null,
			array(
				'current_requests' => $current_requests,
				'max_requests'     => $max_requests,
				'should_retry'     => true,
				'retry_after'      => 30, // seconds
			)
		);
	}

	/**
	 * Create exception for disk space limit
	 *
	 * @param int $required_bytes Required disk space in bytes
	 * @param int $available_bytes Available disk space in bytes
	 * @return self
	 */
	public static function diskSpaceInsufficient( int $required_bytes, int $available_bytes ): self {
		return new self(
			sprintf(
				'Insufficient disk space: %s required, %s available',
				size_format( $required_bytes ),
				size_format( $available_bytes )
			),
			507,
			null,
			array(
				'required_bytes'  => $required_bytes,
				'available_bytes' => $available_bytes,
				'required_human'  => size_format( $required_bytes ),
				'available_human' => size_format( $available_bytes ),
			)
		);
	}

	/**
	 * Create exception for database connection limit
	 *
	 * @param string $error_message Database error message
	 * @return self
	 */
	public static function databaseConnectionLimitExceeded( string $error_message ): self {
		return new self(
			'Database connection limit exceeded',
			503,
			null,
			array(
				'db_error'     => $error_message,
				'should_retry' => true,
				'retry_after'  => 60, // seconds
			)
		);
	}

	/**
	 * Get user-friendly message
	 *
	 * @return string
	 */
	public function getUserMessage(): string {
		if ( ! empty( $this->userMessage ) ) {
			return $this->userMessage;
		}

		// Provide user-friendly messages based on error type
		if ( strpos( $this->getMessage(), 'Memory usage' ) !== false ) {
			return __( 'The system is running low on memory. Please try again with fewer items or contact support.', 'nuclear-engagement' );
		}

		if ( strpos( $this->getMessage(), 'Execution time' ) !== false ) {
			return __( 'The operation is taking too long. Please try with fewer items or contact support.', 'nuclear-engagement' );
		}

		if ( strpos( $this->getMessage(), 'concurrent requests' ) !== false ) {
			return __( 'Too many operations in progress. Please wait a moment and try again.', 'nuclear-engagement' );
		}

		if ( strpos( $this->getMessage(), 'disk space' ) !== false ) {
			return __( 'Insufficient disk space available. Please contact your administrator.', 'nuclear-engagement' );
		}

		if ( strpos( $this->getMessage(), 'Database connection' ) !== false ) {
			return __( 'Database is temporarily unavailable. Please try again in a minute.', 'nuclear-engagement' );
		}

		return __( 'System resources are temporarily limited. Please try again later.', 'nuclear-engagement' );
	}
}
