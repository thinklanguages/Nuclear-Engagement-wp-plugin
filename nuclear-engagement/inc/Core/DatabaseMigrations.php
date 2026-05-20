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
	private const CURRENT_DB_VERSION = '1.3.0';

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

			// Add composite indexes for v1.2.0
			if ( version_compare( $current_version, '1.2.0', '<' ) ) {
				self::add_composite_indexes();
			}

			// Add constraints and additional indexes for v1.3.0
			if ( version_compare( $current_version, '1.3.0', '<' ) ) {
				self::add_constraints_and_indexes();
			}

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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- low-level DB management
			$existing_index = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = %s AND table_name = %s AND index_name = %s',
					DB_NAME,
					$wpdb->postmeta,
					$index_name
				)
			);

			if ( ! $existing_index ) {
				// Use manual identifier escaping for WP 6.1 compatibility (%i requires WP 6.2+).
				$escaped_index = '`' . esc_sql( $index_name ) . '`';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, identifiers are escaped
				$sql = "CREATE INDEX {$escaped_index} ON {$wpdb->postmeta} (meta_key, post_id) WHERE meta_key = '" . esc_sql( $meta_key ) . "'"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe identifier interpolation

				// For MySQL versions that don't support partial indexes, use regular index.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers manually escaped above
				$fallback_sql = "CREATE INDEX {$escaped_index} ON {$wpdb->postmeta} (meta_key(20), post_id)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe identifier interpolation

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management
				$result = $wpdb->query( $sql );
				if ( $result === false && $wpdb->last_error ) {
					// Try fallback approach.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management
					$result = $wpdb->query( $fallback_sql );
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
	 * Add composite indexes for better query performance.
	 */
	private static function add_composite_indexes(): void {
		global $wpdb;

		// Add composite index for themes table
		$themes_table = $wpdb->prefix . 'nuclen_themes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, table name is safe
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$themes_table'" ) === $themes_table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			// Index for is_active queries
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
			$wpdb->query( "ALTER TABLE $themes_table ADD INDEX idx_active_type (is_active, type)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			// Index for type queries
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
			$wpdb->query( "ALTER TABLE $themes_table ADD INDEX idx_type (type)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
		}

		// Add composite index for background jobs table
		$jobs_table = $wpdb->prefix . 'nuclen_background_jobs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, table name is safe
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$jobs_table'" ) === $jobs_table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			// Index for status and scheduled queries
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
			$wpdb->query( "ALTER TABLE $jobs_table ADD INDEX idx_status_scheduled (status, scheduled)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			// Index for job type queries
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
			$wpdb->query( "ALTER TABLE $jobs_table ADD INDEX idx_type_status (type, status)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
		}
	}

	/**
	 * Add constraints and additional indexes for v1.3.0.
	 */
	private static function add_constraints_and_indexes(): void {
		global $wpdb;

		// Add indexes for opt-in table
		$optin_table = $wpdb->prefix . 'nuclen_opt_ins';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, table name is safe
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$optin_table'" ) === $optin_table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			// Check if indexes already exist
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management
			$email_index_exists = $wpdb->get_var(
				"SHOW INDEX FROM $optin_table WHERE Key_name = 'idx_email'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			);

			if ( ! $email_index_exists ) {
				// Index for email lookups
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
				$wpdb->query( "ALTER TABLE $optin_table ADD INDEX idx_email (email)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
				// Index for post ID queries
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
				$wpdb->query( "ALTER TABLE $optin_table ADD INDEX idx_post_id (post_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
				// Index for created_at sorting
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
				$wpdb->query( "ALTER TABLE $optin_table ADD INDEX idx_created_at (created_at)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			}
		}

		// Add unique constraint for themes table
		$themes_table = $wpdb->prefix . 'nuclen_themes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, table name is safe
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$themes_table'" ) === $themes_table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			// Check if constraint exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management
			$constraint_exists = $wpdb->get_var(
				"SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = '$themes_table'
				AND CONSTRAINT_NAME = 'unique_theme_name'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			);

			if ( ! $constraint_exists ) {
				// Add unique constraint on theme name
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
				$wpdb->query( "ALTER TABLE $themes_table ADD CONSTRAINT unique_theme_name UNIQUE (name)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			}
		}

		// Add indexes for transient lookups (for plugins that use custom transient storage)
		$options_table = $wpdb->options;

		// Check if our plugin transient index exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- low-level DB management, table name is safe
		$transient_index_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW INDEX FROM $options_table WHERE Key_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
				'idx_nuclen_transients'
			)
		);

		if ( ! $transient_index_exists ) {
			// Index for our plugin's transients
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- low-level DB management, SchemaChange intentional
			$wpdb->query(
				"ALTER TABLE $options_table
				ADD INDEX idx_nuclen_transients (option_name(50))
				USING BTREE" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name interpolation
			);
		}

		LoggingService::log( 'Added database constraints and indexes for v1.3.0' );
	}

	/**
	 * Check if database needs migration.
	 */
	public static function needs_migration(): bool {
		$current_version = get_option( self::DB_VERSION_OPTION, '1.0.0' );
		return version_compare( $current_version, self::CURRENT_DB_VERSION, '<' );
	}
}
