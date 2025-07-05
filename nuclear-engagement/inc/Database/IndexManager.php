<?php
/**
 * IndexManager.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Database
 */

declare(strict_types=1);

namespace NuclearEngagement\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IndexManager {

	/**
	 * Add missing database indexes for performance optimization
	 *
	 * @return void
	 */
	public static function add_performance_indexes(): void {
		global $wpdb;

		// Get WordPress tables prefix.
		$posts_table    = $wpdb->posts;
		$postmeta_table = $wpdb->postmeta;
		$usermeta_table = $wpdb->usermeta;
		$options_table  = $wpdb->options;

		$indexes_to_add = array(
			// Posts table indexes for common Nuclear Engagement queries.
			array(
				'table'       => $posts_table,
				'name'        => 'ne_post_status_type_date',
				'columns'     => 'post_status, post_type, post_date',
				'description' => 'Optimize posts queries by status, type and date',
			),
			array(
				'table'       => $posts_table,
				'name'        => 'ne_post_type_status_modified',
				'columns'     => 'post_type, post_status, post_modified',
				'description' => 'Optimize recent posts queries',
			),

			// Postmeta table indexes for meta queries.
			array(
				'table'       => $postmeta_table,
				'name'        => 'ne_meta_key_value',
				'columns'     => 'meta_key, meta_value(100)',
				'description' => 'Optimize meta key-value searches',
			),
			array(
				'table'       => $postmeta_table,
				'name'        => 'ne_post_meta_key_value',
				'columns'     => 'post_id, meta_key, meta_value(100)',
				'description' => 'Optimize post meta queries with value search',
			),

			// Usermeta table indexes.
			array(
				'table'       => $usermeta_table,
				'name'        => 'ne_user_meta_key_value',
				'columns'     => 'user_id, meta_key, meta_value(100)',
				'description' => 'Optimize user meta queries',
			),

			// Options table index for Nuclear Engagement options.
			array(
				'table'       => $options_table,
				'name'        => 'ne_option_name_autoload',
				'columns'     => 'option_name, autoload',
				'description' => 'Optimize options queries by name and autoload status',
			),
		);

		foreach ( $indexes_to_add as $index ) {
			self::add_index_if_not_exists( $index );
		}
	}

	/**
	 * Add index if it doesn't already exist
	 *
	 * @param array $index_config Index configuration.
	 * @return void
	 */
	private static function add_index_if_not_exists( array $index_config ): void {
		global $wpdb;

		$table       = $index_config['table'];
		$index_name  = $index_config['name'];
		$columns     = $index_config['columns'];
		$description = $index_config['description'];

		// Check if index already exists.
		$existing_indexes = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results(
			$wpdb->prepare(
				'SHOW INDEX FROM `%s` WHERE Key_name = %s',
				$table,
				$index_name
			)
		);

		if ( empty( $existing_indexes ) ) {
			// Create the index.
			$sql = $wpdb->prepare(
				'ALTER TABLE `%s` ADD INDEX `%s` (%s)',
				$table,
				$index_name,
				$columns
			);

			$result = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$wpdb->query( $sql );

			if ( $result === false ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[Nuclear Engagement] Failed to create index %s on table %s: %s',
						$index_name,
						$table,
						$wpdb->last_error
					)
				);
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[Nuclear Engagement] Successfully created index %s on table %s: %s',
						$index_name,
						$table,
						$description
					)
				);
			}
		}
	}

	/**
	 * Remove Nuclear Engagement specific indexes (for cleanup)
	 *
	 * @return void
	 */
	public static function remove_performance_indexes(): void {
		global $wpdb;

		$tables_and_indexes = array(
			$wpdb->posts    => array( 'ne_post_status_type_date', 'ne_post_type_status_modified' ),
			$wpdb->postmeta => array( 'ne_meta_key_value', 'ne_post_meta_key_value' ),
			$wpdb->usermeta => array( 'ne_user_meta_key_value' ),
			$wpdb->options  => array( 'ne_option_name_autoload' ),
		);

		foreach ( $tables_and_indexes as $table => $indexes ) {
			foreach ( $indexes as $index_name ) {
				self::remove_index_if_exists( $table, $index_name );
			}
		}
	}

	/**
	 * Remove index if it exists
	 *
	 * @param string $table      Table name.
	 * @param string $index_name Index name.
	 * @return void
	 */
	private static function remove_index_if_exists( string $table, string $index_name ): void {
		global $wpdb;

		// Check if index exists.
		$existing_indexes = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results(
			$wpdb->prepare(
				'SHOW INDEX FROM `%s` WHERE Key_name = %s',
				$table,
				$index_name
			)
		);

		if ( ! empty( $existing_indexes ) ) {
			// Drop the index.
			$sql = $wpdb->prepare(
				'ALTER TABLE `%s` DROP INDEX `%s`',
				$table,
				$index_name
			);

			$result = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$wpdb->query( $sql );

			if ( $result === false ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[Nuclear Engagement] Failed to drop index %s from table %s: %s',
						$index_name,
						$table,
						$wpdb->last_error
					)
				);
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[Nuclear Engagement] Successfully dropped index %s from table %s',
						$index_name,
						$table
					)
				);
			}
		}
	}

	/**
	 * Analyze database tables for query performance
	 *
	 * @return array Performance analysis results.
	 */
	public static function analyze_table_performance(): array {
		global $wpdb;

		$analysis = array();
		$tables   = array( $wpdb->posts, $wpdb->postmeta, $wpdb->usermeta, $wpdb->options );

		foreach ( $tables as $table ) {
			$analysis[ $table ] = array(
				'row_count'  => // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `%s`', $table ) ),
				'table_size' => self::get_table_size( $table ),
				'indexes'    => self::get_table_indexes( $table ),
			);
		}

		return $analysis;
	}

	/**
	 * Get table size in MB
	 *
	 * @param string $table Table name.
	 * @return float Table size in MB.
	 */
	private static function get_table_size( string $table ): float {
		global $wpdb;

		$result = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_row(
			$wpdb->prepare(
				'SELECT 
				ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
			FROM information_schema.TABLES 
			WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table
			)
		);

		return $result ? (float) $result->size_mb : 0.0;
	}

	/**
	 * Get existing indexes for a table
	 *
	 * @param string $table Table name.
	 * @return array List of indexes.
	 */
	private static function get_table_indexes( string $table ): array {
		global $wpdb;

		$indexes = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results(
			$wpdb->prepare(
				'SHOW INDEX FROM `%s`',
				$table
			)
		);

		$index_list = array();
		foreach ( $indexes as $index ) {
			$index_list[] = array(
				'name'   => $index->Key_name,
				'column' => $index->Column_name,
				'unique' => $index->Non_unique == 0,
			);
		}

		return $index_list;
	}
}
