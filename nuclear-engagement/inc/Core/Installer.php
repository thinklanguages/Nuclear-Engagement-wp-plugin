<?php
/**
 * Plugin installation, activation, and migration handler.
 *
 * This class manages the installation lifecycle of the Nuclear Engagement plugin,
 * including activation, deactivation, and data migration processes.
 *
 * @package NuclearEngagement\Core
 * @since   1.0.0
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Core\Defaults;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\Activator;
use NuclearEngagement\Core\Deactivator;
use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Services\LoggingService;
use NuclearEngagement\Security\ApiUserManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin installation, activation, and migration processes.
 *
 * The Installer class is responsible for:
 * - Plugin activation and setup
 * - Plugin deactivation and cleanup
 * - Database migrations and meta key updates
 * - Error handling during installation processes
 *
 * @since 1.0.0
 */
class Installer {

	/**
	 * Handle plugin activation.
	 *
	 * This method is called when the plugin is activated. It initializes
	 * default settings and delegates the actual activation process to the
	 * Activator class.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activate(): void {
		// Load default plugin settings.
		$defaults = Defaults::nuclen_get_default_settings();

		// Get or create settings repository instance with defaults.
		$settings = SettingsRepository::get_instance( $defaults );

		// Initialize secure API user management system.
		ApiUserManager::init();

		// Delegate activation to the Activator class.
		Activator::nuclen_activate( $settings );
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * This method is called when the plugin is deactivated. It performs
	 * cleanup operations while preserving user data and settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivate(): void {
		// Get current settings instance.
		$settings = SettingsRepository::get_instance();

		// Clean up API user management system.
		ApiUserManager::cleanup();

		// Delegate deactivation to the Deactivator class.
		Deactivator::nuclen_deactivate( $settings );
	}

	/**
	 * Migrate post meta keys from old format to new format.
	 *
	 * This method handles the migration of meta keys from the previous
	 * plugin version format to the current standardized format. It's
	 * idempotent and can be safely called multiple times.
	 *
	 * Migration mappings:
	 * - 'ne-summary-data' -> Summary_Service::META_KEY
	 * - 'ne-quiz-data' -> 'nuclen-quiz-data'
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function migrate_post_meta(): void {
		// Check if migration has already been completed.
		if ( get_option( 'nuclen_meta_migration_done' ) ) {
			return;
		}

		global $wpdb;

		/**
		 * Error checking closure for database operations.
		 *
		 * @return bool True if no error occurred, false otherwise.
		 */
		$check_error = static function () use ( $wpdb ) {
			if ( ! empty( $wpdb->last_error ) ) {
				// Log the error for debugging.
				LoggingService::log( 'Meta migration error: ' . $wpdb->last_error );

				// Store error details for admin review.
				update_option( 'nuclen_meta_migration_error', $wpdb->last_error );
				return false;
			}
			return true;
		};

		// Migrate summary meta keys from old to new format.
		// Old: 'ne-summary-data' -> New: Summary_Service::META_KEY.
       // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
   // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				Summary_Service::META_KEY,
				'ne-summary-data'
			)
		);

		// Check for database errors after summary migration.
		if ( ! $check_error() ) {
			return;
		}

		// Migrate quiz meta keys from old to new format.
		// Old: 'ne-quiz-data' -> New: 'nuclen-quiz-data'.
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
   // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				'nuclen-quiz-data',
				'ne-quiz-data'
			)
		);

		// Check for database errors after quiz migration.
		if ( ! $check_error() ) {
			return;
		}

		// Migration completed successfully - clean up and mark as done.
		delete_option( 'nuclen_meta_migration_error' );
		update_option( 'nuclen_meta_migration_done', true );
	}
}
