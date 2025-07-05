<?php
/**
 * DatabaseMigrations.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);
/**
 * File: inc/Core/DatabaseMigrations.php
 *
 * Database migration helper for adding indexes and optimizations.
 *
 * @package NuclearEngagement\Core
 */

namespace NuclearEngagement\Core;

use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database schema migrations and optimizations.
 */
class DatabaseMigrations {

	/** Database version option name. */
	private const DB_VERSION_OPTION = 'nuclen_db_version';

	/** Current database version. */
	private const CURRENT_DB_VERSION = '1.1.0';

	/**
	 * Run all necessary database migrations.
	 */
	public static function migrate(): void {
		$current_version = get_option( self::DB_VERSION_OPTION, '1.0.0' );

		if ( version_compare( $current_version, self::CURRENT_DB_VERSION, '>=' ) ) {
			return; // Already up to date.
		}

		global $wpdb;

		try {
			// Add meta key indexes for performance.
			self::add_meta_indexes();

			// Update version.
			update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION, false );

			LoggingService::log( 'Nuclear Engagement: Database migrated to version ' . self::CURRENT_DB_VERSION );

		} catch ( \Throwable $e ) {
			LoggingService::log( 'Nuclear Engagement: Database migration failed - ' . $e->getMessage() );
		}
	}

	/**
	 * Add indexes for frequently queried meta keys.
	 */
	private static function add_meta_indexes(): void {
		global $wpdb;

		$meta_keys = array(
			'nuclen-quiz-data',
			'nuclen_quiz_protected',
			'nuclen-summary-data',
			'nuclen_summary_protected',
		);

		foreach ( $meta_keys as $meta_key ) {
			$index_name = 'idx_nuclen_' . str_replace( '-', '_', $meta_key );

			// Check if index already exists.
			$existing_index = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.statistics 
				 WHERE table_schema = %s 
				 AND table_name = %s 
				 AND index_name = %s',
					DB_NAME,
					$wpdb->postmeta,
					$index_name
				)
			);

			if ( ! $existing_index ) {
				$sql = $wpdb->prepare(
					"CREATE INDEX %i ON {$wpdb->postmeta} (meta_key, post_id) WHERE meta_key = %s",
					$index_name,
					$meta_key
				);

				// For MySQL versions that don't support partial indexes, use regular index.
				$fallback_sql = $wpdb->prepare(
					"CREATE INDEX %i ON {$wpdb->postmeta} (meta_key(20), post_id)",
					$index_name
				);

				$result = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery

				$wpdb->query( $sql );
				if ( $result === false && $wpdb->last_error ) {
					// Try fallback approach.
					$result = // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery

					$wpdb->query( $fallback_sql );
				}

				if ( $result === false ) {
					LoggingService::log( 'Failed to create index ' . $index_name . ': ' . $wpdb->last_error );
				} else {
					LoggingService::log( 'Created database index: ' . $index_name );
				}
			}
		}
	}

	/**
	 * Check if database needs migration.
	 */
	public static function needs_migration(): bool {
		$current_version = get_option( self::DB_VERSION_OPTION, '1.0.0' );
		return version_compare( $current_version, self::CURRENT_DB_VERSION, '<' );
	}
}
