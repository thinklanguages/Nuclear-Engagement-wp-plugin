<?php
/**
 * DistributedLock.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Utils\ProcessIdentifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Distributed lock implementation for multi-server environments.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class DistributedLock {

	/**
	 * Lock storage mechanism.
	 */
	private const STORAGE_OPTION = 'option';
	private const STORAGE_CACHE = 'cache';
	private const STORAGE_DB = 'database';

	/**
	 * Default lock timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 300; // 5 minutes

	/**
	 * Maximum retry attempts.
	 */
	private const MAX_RETRIES = 10;

	/**
	 * Retry delay in microseconds.
	 */
	private const RETRY_DELAY = 100000; // 100ms

	/**
	 * Lock storage type.
	 *
	 * @var string
	 */
	private static string $storage_type = self::STORAGE_OPTION;

	/**
	 * Set storage type for locks.
	 *
	 * @param string $type Storage type.
	 */
	public static function set_storage_type( string $type ): void {
		if ( in_array( $type, array( self::STORAGE_OPTION, self::STORAGE_CACHE, self::STORAGE_DB ), true ) ) {
			self::$storage_type = $type;
		}
	}

	/**
	 * Acquire a distributed lock.
	 *
	 * @param string $lock_name Lock name.
	 * @param string $lock_value Unique lock value.
	 * @param int    $timeout Lock timeout in seconds.
	 * @return bool True if lock acquired.
	 */
	public static function acquire( string $lock_name, string $lock_value, int $timeout = self::DEFAULT_TIMEOUT ): bool {
		$lock_key = self::get_lock_key( $lock_name );
		$lock_data = array(
			'value' => $lock_value,
			'expires' => time() + $timeout,
			'server' => gethostname() ?: 'unknown',
			'process_id' => ProcessIdentifier::get(),
		);

		// Try to acquire lock with retries
		for ( $i = 0; $i < self::MAX_RETRIES; $i++ ) {
			if ( self::try_acquire_lock( $lock_key, $lock_data ) ) {
				return true;
			}

			// Check if existing lock is expired
			$existing = self::get_lock_data( $lock_key );
			if ( $existing && isset( $existing['expires'] ) && $existing['expires'] < time() ) {
				// Try to take over expired lock
				if ( self::takeover_expired_lock( $lock_key, $existing, $lock_data ) ) {
					return true;
				}
			}

			// Wait before retry with exponential backoff
			usleep( self::RETRY_DELAY * ( $i + 1 ) );
		}

		return false;
	}

	/**
	 * Release a distributed lock.
	 *
	 * @param string $lock_name Lock name.
	 * @param string $lock_value Lock value to verify ownership.
	 * @return bool True if lock released.
	 */
	public static function release( string $lock_name, string $lock_value ): bool {
		$lock_key = self::get_lock_key( $lock_name );
		$existing = self::get_lock_data( $lock_key );

		// Only release if we own the lock
		if ( $existing && isset( $existing['value'] ) && $existing['value'] === $lock_value ) {
			return self::delete_lock( $lock_key );
		}

		return false;
	}

	/**
	 * Check if a lock is held.
	 *
	 * @param string $lock_name Lock name.
	 * @return bool True if lock is held.
	 */
	public static function is_locked( string $lock_name ): bool {
		$lock_key = self::get_lock_key( $lock_name );
		$lock_data = self::get_lock_data( $lock_key );

		if ( ! $lock_data ) {
			return false;
		}

		// Check if lock is expired
		if ( isset( $lock_data['expires'] ) && $lock_data['expires'] < time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Extend lock timeout.
	 *
	 * @param string $lock_name Lock name.
	 * @param string $lock_value Lock value to verify ownership.
	 * @param int    $extend_by Seconds to extend by.
	 * @return bool True if extended.
	 */
	public static function extend( string $lock_name, string $lock_value, int $extend_by ): bool {
		$lock_key = self::get_lock_key( $lock_name );
		$existing = self::get_lock_data( $lock_key );

		// Only extend if we own the lock
		if ( $existing && isset( $existing['value'] ) && $existing['value'] === $lock_value ) {
			$existing['expires'] = time() + $extend_by;
			return self::update_lock( $lock_key, $existing );
		}

		return false;
	}

	/**
	 * Get lock information.
	 *
	 * @param string $lock_name Lock name.
	 * @return array|null Lock information or null.
	 */
	public static function get_info( string $lock_name ): ?array {
		$lock_key = self::get_lock_key( $lock_name );
		$lock_data = self::get_lock_data( $lock_key );

		if ( ! $lock_data ) {
			return null;
		}

		// Add calculated fields
		$lock_data['is_expired'] = isset( $lock_data['expires'] ) && $lock_data['expires'] < time();
		$lock_data['remaining_time'] = isset( $lock_data['expires'] ) ? max( 0, $lock_data['expires'] - time() ) : 0;

		return $lock_data;
	}

	/**
	 * Try to acquire lock atomically.
	 *
	 * @param string $lock_key Lock key.
	 * @param array  $lock_data Lock data.
	 * @return bool True if acquired.
	 */
	private static function try_acquire_lock( string $lock_key, array $lock_data ): bool {
		switch ( self::$storage_type ) {
			case self::STORAGE_CACHE:
				// Use cache add for atomic operation
				if ( function_exists( 'wp_cache_add' ) && wp_using_ext_object_cache() ) {
					return wp_cache_add( $lock_key, $lock_data, 'nuclen_locks', $lock_data['expires'] - time() );
				}
				// Fall back to option storage
				self::$storage_type = self::STORAGE_OPTION;

			case self::STORAGE_OPTION:
				// Use add_option for atomic operation
				return add_option( $lock_key, $lock_data, '', 'no' );

			case self::STORAGE_DB:
				// Direct database insert for true atomicity
				global $wpdb;
				$table = $wpdb->prefix . 'nuclen_locks';
				
				// Create table if not exists
				self::ensure_lock_table();
				
				$result = $wpdb->insert(
					$table,
					array(
						'lock_key' => $lock_key,
						'lock_data' => serialize( $lock_data ),
						'expires_at' => $lock_data['expires'],
					),
					array( '%s', '%s', '%d' )
				);
				
				return $result !== false;

			default:
				return false;
		}
	}

	/**
	 * Get lock data.
	 *
	 * @param string $lock_key Lock key.
	 * @return array|null Lock data or null.
	 */
	private static function get_lock_data( string $lock_key ): ?array {
		switch ( self::$storage_type ) {
			case self::STORAGE_CACHE:
				if ( function_exists( 'wp_cache_get' ) && wp_using_ext_object_cache() ) {
					$data = wp_cache_get( $lock_key, 'nuclen_locks' );
					return is_array( $data ) ? $data : null;
				}
				// Fall back to option storage
				self::$storage_type = self::STORAGE_OPTION;

			case self::STORAGE_OPTION:
				$data = get_option( $lock_key );
				return is_array( $data ) ? $data : null;

			case self::STORAGE_DB:
				global $wpdb;
				$table = $wpdb->prefix . 'nuclen_locks';
				
				$result = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT lock_data FROM $table WHERE lock_key = %s AND expires_at > %d",
						$lock_key,
						time()
					)
				);
				
				return $result ? unserialize( $result ) : null;

			default:
				return null;
		}
	}

	/**
	 * Update lock data.
	 *
	 * @param string $lock_key Lock key.
	 * @param array  $lock_data Lock data.
	 * @return bool True if updated.
	 */
	private static function update_lock( string $lock_key, array $lock_data ): bool {
		switch ( self::$storage_type ) {
			case self::STORAGE_CACHE:
				if ( function_exists( 'wp_cache_set' ) && wp_using_ext_object_cache() ) {
					return wp_cache_set( $lock_key, $lock_data, 'nuclen_locks', $lock_data['expires'] - time() );
				}
				// Fall back to option storage
				self::$storage_type = self::STORAGE_OPTION;

			case self::STORAGE_OPTION:
				return update_option( $lock_key, $lock_data, 'no' );

			case self::STORAGE_DB:
				global $wpdb;
				$table = $wpdb->prefix . 'nuclen_locks';
				
				$result = $wpdb->update(
					$table,
					array(
						'lock_data' => serialize( $lock_data ),
						'expires_at' => $lock_data['expires'],
					),
					array( 'lock_key' => $lock_key ),
					array( '%s', '%d' ),
					array( '%s' )
				);
				
				return $result !== false;

			default:
				return false;
		}
	}

	/**
	 * Delete lock.
	 *
	 * @param string $lock_key Lock key.
	 * @return bool True if deleted.
	 */
	private static function delete_lock( string $lock_key ): bool {
		switch ( self::$storage_type ) {
			case self::STORAGE_CACHE:
				if ( function_exists( 'wp_cache_delete' ) && wp_using_ext_object_cache() ) {
					return wp_cache_delete( $lock_key, 'nuclen_locks' );
				}
				// Fall back to option storage
				self::$storage_type = self::STORAGE_OPTION;

			case self::STORAGE_OPTION:
				return delete_option( $lock_key );

			case self::STORAGE_DB:
				global $wpdb;
				$table = $wpdb->prefix . 'nuclen_locks';
				
				$result = $wpdb->delete(
					$table,
					array( 'lock_key' => $lock_key ),
					array( '%s' )
				);
				
				return $result !== false;

			default:
				return false;
		}
	}

	/**
	 * Try to takeover expired lock.
	 *
	 * @param string $lock_key Lock key.
	 * @param array  $existing Existing lock data.
	 * @param array  $new_data New lock data.
	 * @return bool True if takeover successful.
	 */
	private static function takeover_expired_lock( string $lock_key, array $existing, array $new_data ): bool {
		// Atomic compare-and-swap operation
		if ( self::$storage_type === self::STORAGE_DB ) {
			global $wpdb;
			$table = $wpdb->prefix . 'nuclen_locks';
			
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $table SET lock_data = %s, expires_at = %d 
					WHERE lock_key = %s AND lock_data = %s",
					serialize( $new_data ),
					$new_data['expires'],
					$lock_key,
					serialize( $existing )
				)
			);
			
			return $result > 0;
		}
		
		// For option storage, delete and re-add
		if ( self::delete_lock( $lock_key ) ) {
			return self::try_acquire_lock( $lock_key, $new_data );
		}
		
		return false;
	}

	/**
	 * Get lock key with prefix.
	 *
	 * @param string $lock_name Lock name.
	 * @return string Lock key.
	 */
	private static function get_lock_key( string $lock_name ): string {
		return 'nuclen_lock_' . sanitize_key( $lock_name );
	}

	/**
	 * Ensure lock table exists for database storage.
	 */
	private static function ensure_lock_table(): void {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'nuclen_locks';
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			lock_key varchar(255) NOT NULL,
			lock_data text NOT NULL,
			expires_at int(11) NOT NULL,
			PRIMARY KEY (lock_key),
			KEY expires_at (expires_at)
		) $charset_collate;";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Clean up expired locks.
	 *
	 * @return int Number of locks cleaned.
	 */
	public static function cleanup_expired(): int {
		if ( self::$storage_type === self::STORAGE_DB ) {
			global $wpdb;
			$table = $wpdb->prefix . 'nuclen_locks';
			
			return $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table WHERE expires_at < %d",
					time()
				)
			);
		}
		
		// For option storage, we'd need to scan all options
		// This is expensive, so it should be done in a scheduled task
		return 0;
	}
}