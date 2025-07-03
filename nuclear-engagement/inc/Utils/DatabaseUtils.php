<?php
declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class for secure database operations.
 *
 * This class provides secure methods for database table names,
 * query building, and other database-related security concerns.
 *
 * @package NuclearEngagement\Utils
 * @since   1.0.0
 */
final class DatabaseUtils {

	/**
	 * Allowed table suffixes for this plugin.
	 */
	private const ALLOWED_TABLE_SUFFIXES = [
		'nuclear_themes',
		'nuclear_errors',
		'nuclear_jobs',
		'nuclear_performance',
		'nuclear_optins',
	];

	/**
	 * Get a validated and secure table name.
	 *
	 * @param string $suffix Table suffix (without prefix).
	 * @return string Validated table name.
	 * @throws \InvalidArgumentException If suffix is not allowed.
	 */
	public static function get_table_name( string $suffix ): string {
		// Validate suffix is in allowed list
		if ( ! in_array( $suffix, self::ALLOWED_TABLE_SUFFIXES, true ) ) {
			throw new \InvalidArgumentException( "Table suffix '{$suffix}' is not allowed" );
		}

		global $wpdb;
		
		// Validate and sanitize prefix
		$prefix = self::sanitize_table_prefix( $wpdb->prefix );
		
		return $prefix . $suffix;
	}

	/**
	 * Sanitize table prefix to prevent injection.
	 *
	 * @param string $prefix Table prefix to sanitize.
	 * @return string Sanitized prefix.
	 */
	public static function sanitize_table_prefix( string $prefix ): string {
		// Allow only alphanumeric characters and underscores
		$sanitized = preg_replace( '/[^a-zA-Z0-9_]/', '', $prefix );
		
		// Ensure it's not empty and doesn't start with a number
		if ( empty( $sanitized ) || is_numeric( $sanitized[0] ) ) {
			$sanitized = 'wp_';
		}
		
		// Limit length
		return substr( $sanitized, 0, 64 );
	}

	/**
	 * Escape table name for use in queries.
	 *
	 * @param string $table_name Table name to escape.
	 * @return string Escaped table name.
	 */
	public static function escape_table_name( string $table_name ): string {
		global $wpdb;
		
		// Validate the table name format
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
			throw new \InvalidArgumentException( 'Invalid table name format' );
		}
		
		// Use WordPress escaping
		return esc_sql( $table_name );
	}

	/**
	 * Build a secure SELECT query with proper escaping.
	 *
	 * @param string $table_name Table name.
	 * @param array  $columns    Columns to select.
	 * @param array  $where      WHERE conditions.
	 * @param array  $order_by   ORDER BY clauses.
	 * @param int    $limit      LIMIT value.
	 * @return string Prepared SQL query.
	 */
	public static function build_select_query(
		string $table_name,
		array $columns = [ '*' ],
		array $where = [],
		array $order_by = [],
		int $limit = 0
	): string {
		global $wpdb;
		
		$escaped_table = self::escape_table_name( $table_name );
		
		// Validate and escape columns
		$escaped_columns = [];
		foreach ( $columns as $column ) {
			if ( $column === '*' ) {
				$escaped_columns[] = '*';
			} else {
				// Validate column name format
				if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $column ) ) {
					throw new \InvalidArgumentException( "Invalid column name: {$column}" );
				}
				$escaped_columns[] = esc_sql( $column );
			}
		}
		
		$sql = "SELECT " . implode( ', ', $escaped_columns ) . " FROM {$escaped_table}";
		
		// Add WHERE clause
		if ( ! empty( $where ) ) {
			$where_clauses = [];
			foreach ( $where as $column => $value ) {
				// Validate column name
				if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $column ) ) {
					throw new \InvalidArgumentException( "Invalid WHERE column: {$column}" );
				}
				
				$escaped_column = esc_sql( $column );
				if ( is_null( $value ) ) {
					$where_clauses[] = "{$escaped_column} IS NULL";
				} elseif ( is_array( $value ) ) {
					$placeholders = implode( ', ', array_fill( 0, count( $value ), '%s' ) );
					$where_clauses[] = $wpdb->prepare( "{$escaped_column} IN ({$placeholders})", ...$value );
				} else {
					$where_clauses[] = $wpdb->prepare( "{$escaped_column} = %s", $value );
				}
			}
			$sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}
		
		// Add ORDER BY clause
		if ( ! empty( $order_by ) ) {
			$order_clauses = [];
			foreach ( $order_by as $column => $direction ) {
				// Validate column name
				if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $column ) ) {
					throw new \InvalidArgumentException( "Invalid ORDER BY column: {$column}" );
				}
				
				// Validate direction
				$direction = strtoupper( $direction );
				if ( ! in_array( $direction, [ 'ASC', 'DESC' ], true ) ) {
					$direction = 'ASC';
				}
				
				$order_clauses[] = esc_sql( $column ) . ' ' . $direction;
			}
			$sql .= ' ORDER BY ' . implode( ', ', $order_clauses );
		}
		
		// Add LIMIT clause
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}
		
		return $sql;
	}

	/**
	 * Execute a query with proper error handling and logging.
	 *
	 * @param string $query SQL query to execute.
	 * @param string $operation Description of the operation for logging.
	 * @return mixed Query result or false on failure.
	 */
	public static function execute_query( string $query, string $operation = 'database_operation' ) {
		global $wpdb;
		
		$start_time = microtime( true );
		$result = $wpdb->query( $query );
		$execution_time = microtime( true ) - $start_time;
		
		// Log query execution
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'Nuclear Engagement DB Query [%s]: %s (%.4fs)',
				$operation,
				$query,
				$execution_time
			) );
		}
		
		// Check for errors
		if ( $wpdb->last_error ) {
			error_log( sprintf(
				'Nuclear Engagement DB Error [%s]: %s | Query: %s',
				$operation,
				$wpdb->last_error,
				$query
			) );
			return false;
		}
		
		// Log slow queries
		if ( $execution_time > 1.0 ) {
			error_log( sprintf(
				'Nuclear Engagement Slow Query [%s]: %.4fs | %s',
				$operation,
				$execution_time,
				$query
			) );
		}
		
		return $result;
	}

	/**
	 * Check if a table exists and is accessible.
	 *
	 * @param string $table_name Table name to check.
	 * @return bool True if table exists and is accessible.
	 */
	public static function table_exists( string $table_name ): bool {
		global $wpdb;
		
		$escaped_table = self::escape_table_name( $table_name );
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $escaped_table );
		
		$result = $wpdb->get_var( $query );
		
		return $result === $escaped_table;
	}

	/**
	 * Get table charset and collation.
	 *
	 * @return string WordPress charset and collation string.
	 */
	public static function get_charset_collate(): string {
		global $wpdb;
		return $wpdb->get_charset_collate();
	}

	/**
	 * Validate column name for security.
	 *
	 * @param string $column_name Column name to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_column_name( string $column_name ): bool {
		// Allow only alphanumeric characters and underscores
		// Must start with a letter or underscore
		// Length between 1 and 64 characters
		return preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $column_name ) === 1;
	}

	/**
	 * Validate SQL operator for WHERE clauses.
	 *
	 * @param string $operator Operator to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_operator( string $operator ): bool {
		$valid_operators = [
			'=', '!=', '<>', '<', '>', '<=', '>=',
			'LIKE', 'NOT LIKE', 'IN', 'NOT IN',
			'IS NULL', 'IS NOT NULL',
			'EXISTS', 'NOT EXISTS'
		];
		
		return in_array( strtoupper( $operator ), $valid_operators, true );
	}

	/**
	 * Get plugin table names for cleanup operations.
	 *
	 * @return array Array of plugin table names.
	 */
	public static function get_plugin_tables(): array {
		$tables = [];
		
		foreach ( self::ALLOWED_TABLE_SUFFIXES as $suffix ) {
			try {
				$tables[] = self::get_table_name( $suffix );
			} catch ( \InvalidArgumentException $e ) {
				// Skip invalid table names
				continue;
			}
		}
		
		return $tables;
	}
}