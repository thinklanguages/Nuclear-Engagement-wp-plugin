<?php
/**
 * BatchConfiguration.php - Centralized batch processing configuration.
 *
 * This file documents all batch processing constants with their rationale.
 * Values can be overridden via WordPress constants or filters.
 *
 * @package NuclearEngagement\Services\Batch
 */

declare(strict_types=1);

namespace NuclearEngagement\Services\Batch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized configuration for batch processing operations.
 *
 * WHY THIS EXISTS:
 * Previously, magic numbers were scattered throughout BulkGenerationBatchProcessor.
 * This class:
 * 1. Documents WHY each value was chosen
 * 2. Provides override mechanisms for different hosting environments
 * 3. Validates configuration to prevent invalid states
 *
 * @since 2.2.0
 */
final class BatchConfiguration {

	/**
	 * Maximum posts per batch.
	 *
	 * WHY 50: Balances API throughput vs. memory usage.
	 * - Lower values (10-20): Safer for shared hosting with limited memory
	 * - Higher values (100+): Risk of timeout on slow connections
	 * - 50 is optimal for most VPS/dedicated hosting with 256MB+ PHP memory
	 *
	 * OVERRIDE: Define NUCLEN_MAX_POSTS_PER_BATCH constant or use filter.
	 */
	public const MAX_POSTS_PER_BATCH = 50;

	/**
	 * Maximum concurrent batches.
	 *
	 * WHY 12: Prevents overwhelming the SaaS API while maintaining throughput.
	 * - Each batch = 1 API call in flight
	 * - SaaS backend rate limits at ~20 concurrent requests
	 * - 12 provides buffer for retries without hitting limits
	 * - Lower values slow down bulk operations significantly
	 *
	 * OVERRIDE: Define NUCLEN_MAX_CONCURRENT_BATCHES constant or use filter.
	 */
	public const MAX_CONCURRENT_BATCHES = 12;

	/**
	 * Lock timeout in seconds.
	 *
	 * WHY 300 (5 minutes): Accounts for worst-case API response times.
	 * - Average API call: 10-30 seconds
	 * - Slow API call (large posts): up to 120 seconds
	 * - 300s provides margin for network issues and retries
	 * - Prevents deadlocks if process crashes mid-operation
	 *
	 * OVERRIDE: Define NUCLEN_LOCK_TIMEOUT constant or use filter.
	 */
	public const LOCK_TIMEOUT = 300;

	/**
	 * Maximum retry attempts for failed batches.
	 *
	 * WHY 3: Balance between persistence and giving up on truly broken batches.
	 * - 1 retry: Often catches transient network errors
	 * - 2-3 retries: Handles temporary API unavailability
	 * - More than 3: Wastes resources on permanent failures
	 *
	 * OVERRIDE: Define NUCLEN_MAX_RETRIES constant or use filter.
	 */
	public const MAX_RETRIES = 3;

	/**
	 * Delay between retries in seconds.
	 *
	 * WHY 300 (5 minutes): Allows transient issues to resolve.
	 * - API rate limits typically reset in 60 seconds
	 * - Network issues often resolve within minutes
	 * - 300s prevents hammering a struggling service
	 * - Uses exponential backoff: 300, 600, 900 seconds
	 *
	 * OVERRIDE: Define NUCLEN_RETRY_DELAY constant or use filter.
	 */
	public const RETRY_DELAY = 300;

	/**
	 * Batch size for auto-generation feature.
	 *
	 * WHY 20: Optimized for background cron processing.
	 * - Cron has shorter timeouts than admin operations
	 * - 20 posts = ~60-90 seconds typical processing
	 * - Leaves headroom for other cron tasks
	 * - Can be increased for dedicated cron workers
	 *
	 * OVERRIDE: Define NUCLEN_AUTO_BATCH_SIZE constant or use filter.
	 */
	public const AUTO_BATCH_SIZE = 20;

	/**
	 * Stale batch threshold in seconds.
	 *
	 * WHY 3600 (1 hour): Identifies abandoned batches.
	 * - Normal batch completes in < 10 minutes
	 * - 1 hour accounts for very slow processing
	 * - Batches older than this are likely orphaned
	 *
	 * OVERRIDE: Define NUCLEN_STALE_BATCH_THRESHOLD constant or use filter.
	 */
	public const STALE_BATCH_THRESHOLD = 3600;

	/**
	 * Memory threshold percentage for pausing operations.
	 *
	 * WHY 80%: Prevents PHP memory exhaustion.
	 * - PHP OOM kills are hard to recover from
	 * - 80% leaves buffer for cleanup operations
	 * - Some hosts have unpredictable memory behavior
	 *
	 * OVERRIDE: Define NUCLEN_MAX_MEMORY_PERCENT constant or use filter.
	 */
	public const MAX_MEMORY_PERCENT = 80;

	/**
	 * Execution time threshold percentage.
	 *
	 * WHY 70%: Ensures graceful shutdown before timeout.
	 * - Allows time to save state and cleanup
	 * - Prevents partial operations on timeout
	 * - 30% buffer handles slow I/O operations
	 *
	 * OVERRIDE: Define NUCLEN_MAX_EXECUTION_PERCENT constant or use filter.
	 */
	public const MAX_EXECUTION_PERCENT = 70;

	/**
	 * Get configuration value with override support.
	 *
	 * @param string $key Configuration key (use class constants).
	 * @return int Configuration value.
	 */
	public static function get( string $key ): int {
		$constant_name = 'NUCLEN_' . $key;

		// Check for WordPress constant override.
		if ( defined( $constant_name ) ) {
			$value = constant( $constant_name );
			if ( is_numeric( $value ) ) {
				return (int) $value;
			}
		}

		// Check for filter override.
		$default  = self::get_default( $key );
		$filtered = apply_filters( 'nuclen_batch_config_' . strtolower( $key ), $default );

		return is_numeric( $filtered ) ? (int) $filtered : $default;
	}

	/**
	 * Get default value for a configuration key.
	 *
	 * @param string $key Configuration key.
	 * @return int Default value.
	 */
	private static function get_default( string $key ): int {
		$defaults = array(
			'MAX_POSTS_PER_BATCH'    => self::MAX_POSTS_PER_BATCH,
			'MAX_CONCURRENT_BATCHES' => self::MAX_CONCURRENT_BATCHES,
			'LOCK_TIMEOUT'           => self::LOCK_TIMEOUT,
			'MAX_RETRIES'            => self::MAX_RETRIES,
			'RETRY_DELAY'            => self::RETRY_DELAY,
			'AUTO_BATCH_SIZE'        => self::AUTO_BATCH_SIZE,
			'STALE_BATCH_THRESHOLD'  => self::STALE_BATCH_THRESHOLD,
			'MAX_MEMORY_PERCENT'     => self::MAX_MEMORY_PERCENT,
			'MAX_EXECUTION_PERCENT'  => self::MAX_EXECUTION_PERCENT,
		);

		return $defaults[ $key ] ?? 0;
	}

	/**
	 * Validate configuration values.
	 *
	 * @return array Array of validation errors, empty if valid.
	 */
	public static function validate(): array {
		$errors = array();

		$max_posts = self::get( 'MAX_POSTS_PER_BATCH' );
		if ( $max_posts < 1 || $max_posts > 500 ) {
			$errors[] = 'MAX_POSTS_PER_BATCH must be between 1 and 500';
		}

		$max_concurrent = self::get( 'MAX_CONCURRENT_BATCHES' );
		if ( $max_concurrent < 1 || $max_concurrent > 50 ) {
			$errors[] = 'MAX_CONCURRENT_BATCHES must be between 1 and 50';
		}

		$lock_timeout = self::get( 'LOCK_TIMEOUT' );
		if ( $lock_timeout < 60 || $lock_timeout > 1800 ) {
			$errors[] = 'LOCK_TIMEOUT must be between 60 and 1800 seconds';
		}

		$memory_percent = self::get( 'MAX_MEMORY_PERCENT' );
		if ( $memory_percent < 50 || $memory_percent > 95 ) {
			$errors[] = 'MAX_MEMORY_PERCENT must be between 50 and 95';
		}

		$exec_percent = self::get( 'MAX_EXECUTION_PERCENT' );
		if ( $exec_percent < 50 || $exec_percent > 90 ) {
			$errors[] = 'MAX_EXECUTION_PERCENT must be between 50 and 90';
		}

		return $errors;
	}

	/**
	 * Get all configuration values as an array.
	 *
	 * @return array All configuration values with their current (possibly overridden) values.
	 */
	public static function get_all(): array {
		return array(
			'max_posts_per_batch'    => self::get( 'MAX_POSTS_PER_BATCH' ),
			'max_concurrent_batches' => self::get( 'MAX_CONCURRENT_BATCHES' ),
			'lock_timeout'           => self::get( 'LOCK_TIMEOUT' ),
			'max_retries'            => self::get( 'MAX_RETRIES' ),
			'retry_delay'            => self::get( 'RETRY_DELAY' ),
			'auto_batch_size'        => self::get( 'AUTO_BATCH_SIZE' ),
			'stale_batch_threshold'  => self::get( 'STALE_BATCH_THRESHOLD' ),
			'max_memory_percent'     => self::get( 'MAX_MEMORY_PERCENT' ),
			'max_execution_percent'  => self::get( 'MAX_EXECUTION_PERCENT' ),
		);
	}
}
