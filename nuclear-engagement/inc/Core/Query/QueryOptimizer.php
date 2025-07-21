<?php
/**
 * QueryOptimizer.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core_Query
 */

declare(strict_types=1);

namespace NuclearEngagement\Core\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optimized database query handler with proper caching.
 *
 * @package NuclearEngagement\Core\Query
 */
final class QueryOptimizer {
	private const CACHE_GROUP = 'nuclen_queries';
	private const CACHE_TTL   = 300; // 5 minutes.

	private static ?self $instance     = null;
	private array $query_cache         = array();
	private array $prepared_statements = array();

	private function __construct() {}

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Execute optimized query with caching.
	 */
	public function query( string $sql, array $params = array(), int $cache_ttl = self::CACHE_TTL ): array {
		global $wpdb;

		$cache_key = $this->generateCacheKey( $sql, $params );

		// Try cache first.
		$cached_result = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $cached_result !== false ) {
			return $cached_result;
		}

		// Prepare and execute query.
		$prepared_sql = $this->prepareQuery( $sql, $params );
		$start_time   = microtime( true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results( $prepared_sql, ARRAY_A );

		$execution_time = microtime( true ) - $start_time;

		// Log slow queries.
		if ( $execution_time > 1.0 ) { // > 1 second.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[Nuclear Engagement] Slow query detected ({$execution_time}s): {$prepared_sql}" );
		}

		// Cache results.
		if ( empty( $wpdb->last_error ) && $cache_ttl > 0 ) {
			wp_cache_set( $cache_key, $results, self::CACHE_GROUP, $cache_ttl );
		}

		return $results ?: array();
	}

	/**
	 * Execute optimized query returning single row.
	 */
	public function queryRow( string $sql, array $params = array(), int $cache_ttl = self::CACHE_TTL ): ?array {
		$results = $this->query( $sql, $params, $cache_ttl );
		return $results[0] ?? null;
	}

	/**
	 * Execute optimized query returning single value.
	 */
	public function queryVar( string $sql, array $params = array(), int $cache_ttl = self::CACHE_TTL ) {
		global $wpdb;

		$cache_key = $this->generateCacheKey( $sql, $params );

		$cached_result = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $cached_result !== false ) {
			return $cached_result;
		}

		$prepared_sql = $this->prepareQuery( $sql, $params );
		$result       = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_var( $prepared_sql );

		if ( empty( $wpdb->last_error ) && $cache_ttl > 0 ) {
			wp_cache_set( $cache_key, $result, self::CACHE_GROUP, $cache_ttl );
		}

		return $result;
	}

	/**
	 * Batch insert with proper preparation.
	 */
	public function batchInsert( string $table, array $data, array $format = array() ): bool {
		global $wpdb;

		if ( empty( $data ) ) {
			return true;
		}

		$table = esc_sql( $table );

		// Get column names from first row.
		$columns     = array_keys( $data[0] );
		$columns_sql = '`' . implode( '`, `', array_map( 'esc_sql', $columns ) ) . '`';

		// Build values string.
		$values = array();
		foreach ( $data as $row ) {
			$row_values = array();
			foreach ( $columns as $column ) {
				$value = $row[ $column ] ?? null;
				if ( $value === null ) {
					$row_values[] = 'NULL';
				} else {
					$row_values[] = $wpdb->prepare( '%s', $value );
				}
			}
			$values[] = '(' . implode( ', ', $row_values ) . ')';
		}

		$values_sql = implode( ', ', $values );
		$sql        = "INSERT INTO {$table} ({$columns_sql}) VALUES {$values_sql}";

		$result = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query( $sql );

		// Invalidate related caches.
		$this->invalidateTableCache( $table );

		return $result !== false;
	}

	/**
	 * Safely prepare query with parameters.
	 */
	private function prepareQuery( string $sql, array $params ): string {
		global $wpdb;

		if ( empty( $params ) ) {
			return $sql;
		}

		// Cache prepared statements for reuse.
		$prep_key = md5( $sql );
		if ( ! isset( $this->prepared_statements[ $prep_key ] ) ) {
			$this->prepared_statements[ $prep_key ] = $sql;
		}

		return $wpdb->prepare( $sql, ...$params );
	}

	/**
	 * Generate cache key from SQL and parameters.
	 */
	private function generateCacheKey( string $sql, array $params ): string {
		$key_data = array(
			'sql'     => $sql,
			'params'  => $params,
			'blog_id' => get_current_blog_id(),
			'user_id' => get_current_user_id(),
		);

		return 'query_' . md5( maybe_serialize( $key_data ) );
	}

	/**
	 * Invalidate cache for specific table.
	 */
	public function invalidateTableCache( string $table ): void {
		// For WordPress object cache that supports groups.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			// Fallback: increment cache version.
			$version_key = self::CACHE_GROUP . '_version';
			$version     = (int) wp_cache_get( $version_key, 'options' );
			wp_cache_set( $version_key, $version + 1, 'options', 0 );
		}
	}

	/**
	 * Get query statistics for monitoring.
	 */
	public function getQueryStats(): array {
		global $wpdb;

		return array(
			'total_queries'              => $wpdb->num_queries,
			'prepared_statements_cached' => count( $this->prepared_statements ),
			'cache_hits'                 => $this->getCacheHits(),
			'slow_queries'               => $this->getSlowQueryCount(),
		);
	}

	private function getCacheHits(): int {
		// This would need to be tracked if detailed stats are needed.
		return 0;
	}

	private function getSlowQueryCount(): int {
		// This would need to be tracked if detailed stats are needed.
		return 0;
	}

	/**
	 * Clear all query caches.
	 */
	public function clearCache(): void {
		wp_cache_flush_group( self::CACHE_GROUP );
		$this->query_cache = array();
	}
}
