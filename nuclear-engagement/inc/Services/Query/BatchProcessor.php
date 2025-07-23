<?php
/**
 * BatchProcessor.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_Query
 */

declare(strict_types=1);

namespace NuclearEngagement\Services\Query;

use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles memory-efficient batch processing of posts queries
 */
class BatchProcessor {

	/** Maximum posts to process per batch */
	private const BATCH_SIZE = 200;

	/** Maximum total posts - can be overridden by filter */
	private const MAX_POSTS = 10000;

	/** Minimum batch size when memory is constrained */
	private const MIN_BATCH_SIZE = 25;

	/**
	 * Fetch posts in memory-efficient batches.
	 *
	 * @param string $sql_clauses SQL WHERE/JOIN clauses.
	 * @return array Array of post IDs.
	 */
	public function fetch_posts_in_batches( string $sql_clauses ): array {
		global $wpdb;

		$post_ids        = array();
		$offset          = 0;
		$processed_total = 0;

		$initial_memory = memory_get_usage( true );
		$memory_limit   = $this->get_memory_limit();

		do {
			$batch_size = $this->calculate_safe_batch_size( $processed_total );

			$query = $wpdb->prepare(
				"SELECT DISTINCT p.ID $sql_clauses ORDER BY p.ID ASC LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			);

			$batch = $wpdb->get_col( $query );

			if ( empty( $batch ) ) {
				break;
			}

			$batch_ids = array_map( 'intval', $batch );
			$post_ids  = array_merge( $post_ids, $batch_ids );

			$processed_total += count( $batch );
			$offset          += $batch_size;

			if ( $this->should_stop_processing( $processed_total, $memory_limit ) ) {
				break;
			}

			$this->maybe_cleanup_memory( $offset );

		} while ( count( $batch ) === $batch_size );

		$this->log_batch_stats( $post_ids, $offset, $initial_memory );

		return array_unique( $post_ids );
	}

	/**
	 * Calculate safe batch size based on memory usage.
	 *
	 * @param int $processed_so_far Number of posts processed so far.
	 * @return int Safe batch size.
	 */
	private function calculate_safe_batch_size( int $processed_so_far ): int {
		$memory_usage_percent = $this->get_memory_usage_percent();
		$batch_size           = self::BATCH_SIZE;

		// Adaptive batch sizing based on memory usage
		if ( $memory_usage_percent > 80 ) {
			$batch_size = self::MIN_BATCH_SIZE;
		} elseif ( $memory_usage_percent > 70 ) {
			$batch_size = (int) ( self::BATCH_SIZE * 0.25 );
		} elseif ( $memory_usage_percent > 60 ) {
			$batch_size = (int) ( self::BATCH_SIZE * 0.5 );
		} elseif ( $memory_usage_percent > 50 ) {
			$batch_size = (int) ( self::BATCH_SIZE * 0.75 );
		}

		// Allow filtering for specific environments
		$batch_size = apply_filters( 'nuclen_batch_processor_batch_size', $batch_size, $memory_usage_percent, $processed_so_far );

		return max( self::MIN_BATCH_SIZE, $batch_size );
	}

	/**
	 * Check if processing should stop due to limits.
	 *
	 * @param int $processed_total Total posts processed.
	 * @param int $memory_limit Memory limit in bytes.
	 * @return bool Whether to stop processing.
	 */
	private function should_stop_processing( int $processed_total, int $memory_limit ): bool {
		$memory_usage_percent = $this->get_memory_usage_percent();

		// Critical memory threshold - stop immediately
		if ( $memory_usage_percent > 85 ) {
			LoggingService::log( "[ERROR] BatchProcessor: Critical memory usage at {$memory_usage_percent}%, stopping batch processing" );
			return true;
		}

		// Check execution time limit
		$max_execution_time = (int) ini_get( 'max_execution_time' );
		if ( $max_execution_time > 0 ) {
			$elapsed_time = microtime( true ) - ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
			if ( $elapsed_time > ( $max_execution_time * 0.8 ) ) {
				LoggingService::log( '[WARNING] BatchProcessor: Approaching execution time limit, stopping batch processing' );
				return true;
			}
		}

		// Allow customization of max posts limit
		$max_posts = apply_filters( 'nuclen_batch_processor_max_posts', self::MAX_POSTS );
		if ( $processed_total >= $max_posts ) {
			// Reached max post limit
			return true;
		}

		return false;
	}

	/**
	 * Cleanup memory if needed.
	 *
	 * @param int $offset Current offset.
	 */
	private function maybe_cleanup_memory( int $offset ): void {
		// More aggressive memory cleanup based on usage
		$memory_usage_percent = $this->get_memory_usage_percent();

		if ( $memory_usage_percent > 60 || $offset % ( self::BATCH_SIZE * 3 ) === 0 ) {
			// Clear WordPress object cache for non-persistent caches
			if ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}

			// Force garbage collection
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}

			// Memory cleanup performed
		}
	}

	/**
	 * Log batch processing statistics.
	 *
	 * @param array $post_ids Final post IDs.
	 * @param int   $offset Final offset.
	 * @param int   $initial_memory Initial memory usage.
	 */
	private function log_batch_stats( array $post_ids, int $offset, int $initial_memory ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$final_memory = memory_get_usage( true );
		$memory_used  = $final_memory - $initial_memory;

		// Batch processing complete
	}

	/**
	 * Get current memory usage percentage.
	 *
	 * @return float Memory usage percentage.
	 */
	private function get_memory_usage_percent(): float {
		$current_memory = memory_get_usage( true );
		$memory_limit   = $this->get_memory_limit();
		return ( $current_memory / $memory_limit ) * 100;
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int Memory limit in bytes.
	 */
	private function get_memory_limit(): int {
		$memory_limit = ini_get( 'memory_limit' );

		if ( $memory_limit === -1 ) {
			return PHP_INT_MAX;
		}

		$value = (int) $memory_limit;
		$unit  = strtolower( substr( $memory_limit, -1 ) );

		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
	}
}
