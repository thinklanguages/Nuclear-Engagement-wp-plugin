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

	/** Maximum total posts to prevent memory issues */
	private const MAX_POSTS = 2000;

	/**
	 * Fetch posts in memory-efficient batches.
	 *
	 * @param string $sql_clauses SQL WHERE/JOIN clauses.
	 * @return array Array of post IDs.
	 */
	public function fetch_posts_in_batches( string $sql_clauses ): array {
		global $wpdb;

		$post_ids = array();
		$offset = 0;
		$processed_total = 0;

		$initial_memory = memory_get_usage( true );
		$memory_limit = $this->get_memory_limit();

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
			$post_ids = array_merge( $post_ids, $batch_ids );

			$processed_total += count( $batch );
			$offset += $batch_size;

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

		if ( $memory_usage_percent > 70 ) {
			return max( 50, self::BATCH_SIZE / 4 );
		} elseif ( $memory_usage_percent > 50 ) {
			return max( 100, self::BATCH_SIZE / 2 );
		}

		return self::BATCH_SIZE;
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

		if ( $memory_usage_percent > 80 ) {
			LoggingService::log( "BatchProcessor: Memory usage at {$memory_usage_percent}%, stopping batch processing" );
			return true;
		}

		if ( $processed_total >= self::MAX_POSTS ) {
			LoggingService::log( 'BatchProcessor: Reached maximum post limit (' . self::MAX_POSTS . ')' );
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
		if ( $offset % ( self::BATCH_SIZE * 5 ) === 0 ) {
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}
		}
	}

	/**
	 * Log batch processing statistics.
	 *
	 * @param array $post_ids Final post IDs.
	 * @param int $offset Final offset.
	 * @param int $initial_memory Initial memory usage.
	 */
	private function log_batch_stats( array $post_ids, int $offset, int $initial_memory ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$final_memory = memory_get_usage( true );
		$memory_used = $final_memory - $initial_memory;

		LoggingService::log(
			sprintf(
				'BatchProcessor: Processed %d posts in %d batches, memory used: %s',
				count( $post_ids ),
				ceil( $offset / self::BATCH_SIZE ),
				size_format( $memory_used )
			)
		);
	}

	/**
	 * Get current memory usage percentage.
	 *
	 * @return float Memory usage percentage.
	 */
	private function get_memory_usage_percent(): float {
		$current_memory = memory_get_usage( true );
		$memory_limit = $this->get_memory_limit();
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
		$unit = strtolower( substr( $memory_limit, -1 ) );

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