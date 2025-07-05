<?php
/**
 * DatabaseRepository.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Repositories
 */

declare(strict_types=1);

namespace NuclearEngagement\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base database repository providing common database operations.
 *
 * Abstracts direct $wpdb usage and provides a consistent interface
 * for database operations throughout the plugin.
 *
 * @package NuclearEngagement\Repositories
 * @since 1.0.0
 */
abstract class DatabaseRepository {
	/**
	 * WordPress database object.
	 *
	 * @var \wpdb
	 */
	protected \wpdb $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Execute a prepared query.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param array  $params Parameters for the query.
	 * @return int|false Number of rows affected or false on error.
	 */
	protected function execute_query( string $query, array $params = array() ) {
		if ( empty( $params ) ) {
			return $this->wpdb->query( $query );
		}

		$prepared = $this->wpdb->prepare( $query, ...$params );
		return $this->wpdb->query( $prepared );
	}

	/**
	 * Get a single row from the database.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param array  $params Parameters for the query.
	 * @param string $output Output type (OBJECT, ARRAY_A, or ARRAY_N).
	 * @return mixed Single row data or null if not found.
	 */
	protected function get_row( string $query, array $params = array(), string $output = OBJECT ) {
		if ( empty( $params ) ) {
			return $this->wpdb->get_row( $query, $output );
		}

		$prepared = $this->wpdb->prepare( $query, ...$params );
		return $this->wpdb->get_row( $prepared, $output );
	}

	/**
	 * Get multiple rows from the database.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param array  $params Parameters for the query.
	 * @param string $output Output type (OBJECT, ARRAY_A, or ARRAY_N).
	 * @return array Array of rows or empty array if none found.
	 */
	protected function get_results( string $query, array $params = array(), string $output = OBJECT ): array {
		if ( empty( $params ) ) {
			return $this->wpdb->get_results( $query, $output ) ?: array();
		}

		$prepared = $this->wpdb->prepare( $query, ...$params );
		return $this->wpdb->get_results( $prepared, $output ) ?: array();
	}

	/**
	 * Get a single variable from the database.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param array  $params Parameters for the query.
	 * @param int    $col_offset Column offset (0-based).
	 * @param int    $row_offset Row offset (0-based).
	 * @return mixed Single value or null if not found.
	 */
	protected function get_var( string $query, array $params = array(), int $col_offset = 0, int $row_offset = 0 ) {
		if ( empty( $params ) ) {
			return $this->wpdb->get_var( $query, $col_offset, $row_offset );
		}

		$prepared = $this->wpdb->prepare( $query, ...$params );
		return $this->wpdb->get_var( $prepared, $col_offset, $row_offset );
	}

	/**
	 * Insert data into a table.
	 *
	 * @param string       $table Table name.
	 * @param array        $data Data to insert (column => value pairs).
	 * @param array|string $format Optional. Array of formats for the values.
	 * @return int|false Insert ID on success, false on error.
	 */
	protected function insert( string $table, array $data, $format = null ) {
		$result = $this->wpdb->insert( $table, $data, $format );

		if ( $result === false ) {
			return false;
		}

		return $this->wpdb->insert_id;
	}

	/**
	 * Update data in a table.
	 *
	 * @param string       $table Table name.
	 * @param array        $data Data to update (column => value pairs).
	 * @param array        $where WHERE clause data (column => value pairs).
	 * @param array|string $format Optional. Array of formats for the data values.
	 * @param array|string $where_format Optional. Array of formats for the WHERE clause values.
	 * @return int|false Number of rows updated or false on error.
	 */
	protected function update( string $table, array $data, array $where, $format = null, $where_format = null ) {
		return $this->wpdb->update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Delete data from a table.
	 *
	 * @param string       $table Table name.
	 * @param array        $where WHERE clause data (column => value pairs).
	 * @param array|string $where_format Optional. Array of formats for the WHERE clause values.
	 * @return int|false Number of rows deleted or false on error.
	 */
	protected function delete( string $table, array $where, $where_format = null ) {
		return $this->wpdb->delete( $table, $where, $where_format );
	}

	/**
	 * Check if a table exists.
	 *
	 * @param string $table_name Table name to check.
	 * @return bool True if table exists, false otherwise.
	 */
	protected function table_exists( string $table_name ): bool {
		$query  = 'SHOW TABLES LIKE %s';
		$result = $this->get_var( $query, array( $table_name ) );
		return $result === $table_name;
	}

	/**
	 * Get the last database error.
	 *
	 * @return string Last database error message.
	 */
	protected function get_last_error(): string {
		return $this->wpdb->last_error;
	}

	/**
	 * Start a database transaction.
	 */
	protected function start_transaction(): void {
		$this->wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commit a database transaction.
	 */
	protected function commit(): void {
		$this->wpdb->query( 'COMMIT' );
	}

	/**
	 * Rollback a database transaction.
	 */
	protected function rollback(): void {
		$this->wpdb->query( 'ROLLBACK' );
	}

	/**
	 * Get table prefix.
	 *
	 * @return string Table prefix.
	 */
	protected function get_table_prefix(): string {
		return $this->wpdb->prefix;
	}

	/**
	 * Escape data for use in SQL queries.
	 *
	 * @param string $data Data to escape.
	 * @return string Escaped data.
	 */
	protected function escape( string $data ): string {
		return $this->wpdb->_escape( $data );
	}

	/**
	 * Get full table name with prefix.
	 *
	 * @param string $table_name Base table name.
	 * @return string Full table name with prefix.
	 */
	protected function get_table_name( string $table_name ): string {
		return $this->wpdb->prefix . $table_name;
	}
}
