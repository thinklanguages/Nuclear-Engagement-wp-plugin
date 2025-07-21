<?php
/**
 * TaskTransientManager.php - Manages task transients with fallback to direct DB storage
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

/**
 * Manages task transients ensuring they are properly stored even with object caching
 */
class TaskTransientManager {

	const TRANSIENT_PREFIX = 'nuclen_bulk_job_';
	const BATCH_PREFIX     = 'nuclen_batch_';

	/**
	 * Set a task transient with fallback to direct DB storage
	 *
	 * @param string $task_id The task ID
	 * @param array  $data    The task data
	 * @param int    $expiry  Expiry time in seconds
	 * @return bool Success status
	 */
	public static function set_task_transient( string $task_id, array $data, int $expiry = DAY_IN_SECONDS ): bool {
		$transient_key = self::TRANSIENT_PREFIX . $task_id;

		// If object caching is enabled, also store directly in DB
		if ( wp_using_ext_object_cache() ) {
			return self::direct_db_set( $transient_key, $data, $expiry );
		}

		// For non-cached sites, use standard transient API
		return set_transient( $transient_key, $data, $expiry );
	}

	/**
	 * Get a task transient with fallback to direct DB retrieval
	 *
	 * @param string $task_id The task ID
	 * @return array|false Task data or false if not found
	 */
	public static function get_task_transient( string $task_id ) {
		$transient_key = self::TRANSIENT_PREFIX . $task_id;

		// Try standard transient API first
		$data = get_transient( $transient_key );
		if ( false !== $data ) {
			return $data;
		}

		// If object caching is enabled, try direct DB
		if ( wp_using_ext_object_cache() ) {
			return self::direct_db_get( $transient_key );
		}

		return false;
	}

	/**
	 * Delete a task transient
	 *
	 * @param string $task_id The task ID
	 * @return bool Success status
	 */
	public static function delete_task_transient( string $task_id ): bool {
		$transient_key = self::TRANSIENT_PREFIX . $task_id;

		// Delete from both transient API and direct DB
		delete_transient( $transient_key );

		if ( wp_using_ext_object_cache() ) {
			global $wpdb;
			$wpdb->delete(
				$wpdb->options,
				array( 'option_name' => '_transient_' . $transient_key )
			);
			$wpdb->delete(
				$wpdb->options,
				array( 'option_name' => '_transient_timeout_' . $transient_key )
			);
		}

		return true;
	}

	/**
	 * Get all task transients
	 *
	 * @param int $limit  Maximum number of tasks to return
	 * @param int $offset Offset for pagination
	 * @return array Array of task data
	 */
	public static function get_all_task_transients( int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		// Always query database directly for listing
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s
				ORDER BY option_id DESC
				LIMIT %d OFFSET %d",
				'_transient_' . self::TRANSIENT_PREFIX . '%',
				'_transient_timeout_' . self::TRANSIENT_PREFIX . '%',
				$limit,
				$offset
			)
		);

		$tasks = array();
		foreach ( $results as $result ) {
			$task_id = str_replace( '_transient_' . self::TRANSIENT_PREFIX, '', $result->option_name );
			$data    = maybe_unserialize( $result->option_value );
			if ( is_array( $data ) ) {
				$data['task_id'] = $task_id;
				$tasks[]         = $data;
			}
		}

		return $tasks;
	}

	/**
	 * Count all task transients
	 *
	 * @return int Total count
	 */
	public static function count_task_transients(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s",
				'_transient_' . self::TRANSIENT_PREFIX . '%',
				'_transient_timeout_' . self::TRANSIENT_PREFIX . '%'
			)
		);
	}

	/**
	 * Set a batch transient
	 *
	 * @param string $batch_id The batch ID
	 * @param array  $data     The batch data
	 * @param int    $expiry   Expiry time in seconds
	 * @return bool Success status
	 */
	public static function set_batch_transient( string $batch_id, array $data, int $expiry = DAY_IN_SECONDS ): bool {
		$transient_key = self::BATCH_PREFIX . $batch_id;

		if ( wp_using_ext_object_cache() ) {
			return self::direct_db_set( $transient_key, $data, $expiry );
		}

		return set_transient( $transient_key, $data, $expiry );
	}

	/**
	 * Get a batch transient
	 *
	 * @param string $batch_id The batch ID
	 * @return array|false Batch data or false if not found
	 */
	public static function get_batch_transient( string $batch_id ) {
		$transient_key = self::BATCH_PREFIX . $batch_id;

		$data = get_transient( $transient_key );
		if ( false !== $data ) {
			return $data;
		}

		if ( wp_using_ext_object_cache() ) {
			return self::direct_db_get( $transient_key );
		}

		return false;
	}

	/**
	 * Directly set transient in database
	 *
	 * @param string $key    Transient key (without prefix)
	 * @param mixed  $value  Value to store
	 * @param int    $expiry Expiry time in seconds
	 * @return bool Success status
	 */
	private static function direct_db_set( string $key, $value, int $expiry ): bool {
		global $wpdb;

		$transient   = '_transient_' . $key;
		$timeout     = '_transient_timeout_' . $key;
		$value       = maybe_serialize( $value );
		$expiry_time = time() + $expiry;

		// Delete existing to avoid duplicates
		$wpdb->delete( $wpdb->options, array( 'option_name' => $transient ) );
		$wpdb->delete( $wpdb->options, array( 'option_name' => $timeout ) );

		// Insert new values
		$result = $wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => $transient,
				'option_value' => $value,
				'autoload'     => 'no',
			),
			array( '%s', '%s', '%s' )
		);

		if ( $result ) {
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => $timeout,
					'option_value' => $expiry_time,
					'autoload'     => 'no',
				),
				array( '%s', '%d', '%s' )
			);
		}

		// Also set in transient API for cache consistency
		set_transient( $key, maybe_unserialize( $value ), $expiry );

		return (bool) $result;
	}

	/**
	 * Directly get transient from database
	 *
	 * @param string $key Transient key (without prefix)
	 * @return mixed|false Value or false if not found/expired
	 */
	private static function direct_db_get( string $key ) {
		global $wpdb;

		$transient = '_transient_' . $key;
		$timeout   = '_transient_timeout_' . $key;

		// Check timeout first
		$expiry = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
				$timeout
			)
		);

		if ( $expiry && $expiry < time() ) {
			// Expired, clean up
			$wpdb->delete( $wpdb->options, array( 'option_name' => $transient ) );
			$wpdb->delete( $wpdb->options, array( 'option_name' => $timeout ) );
			return false;
		}

		// Get value
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
				$transient
			)
		);

		return $value ? maybe_unserialize( $value ) : false;
	}
}
