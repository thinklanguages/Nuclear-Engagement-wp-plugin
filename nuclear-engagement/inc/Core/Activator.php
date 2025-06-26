<?php
declare(strict_types=1);
// Activator.php

namespace NuclearEngagement\Core;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\OptinData;
use NuclearEngagement\Core\AssetVersions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	/**
	 * Plugin activation hook
	 *
	 * @param SettingsRepository|null $settings Optional settings repository instance
	 */
	public static function nuclen_activate( ?SettingsRepository $settings = null ) {
		// Set transient for activation redirect
		set_transient( 'nuclen_plugin_activation_redirect', true, NUCLEN_ACTIVATION_REDIRECT_TTL );

		// Get default settings
		$default_settings = Defaults::nuclen_get_default_settings();

		// Initialize or update settings repository with defaults
		$settings = $settings ?: SettingsRepository::get_instance( $default_settings );

		// Only set the setup option if it doesn't already exist
		if ( false === get_option( 'nuclear_engagement_setup' ) ) {
			update_option( 'nuclear_engagement_setup', $default_settings );
		}

				// Ensure opt-in table exists on activation
				OptinData::maybe_create_table();

				// Create indexes on wp_postmeta for faster lookups
				self::maybe_create_postmeta_indexes();

				// Generate asset version strings for cache busting
				AssetVersions::update_versions();
	}

		/**
		 * Create custom indexes on the postmeta table if they do not exist.
		 */
	private static function maybe_create_postmeta_indexes(): void {
			global $wpdb;

			$table   = $wpdb->postmeta;
			$indexes = array(
				'nuclen_quiz_data_idx'         => 'nuclen-quiz-data',
				'nuclen_summary_data_idx'      => 'nuclen-summary-data',
				'nuclen_quiz_protected_idx'    => 'nuclen_quiz_protected',
				'nuclen_summary_protected_idx' => 'nuclen_summary_protected',
			);

			foreach ( $indexes as $index => $meta_key ) {
					$exists = $wpdb->get_var(
						$wpdb->prepare(
							"SHOW INDEX FROM {$table} WHERE Key_name = %s",
							$index
						)
					);
				if ( $exists ) {
					continue;
				}

					$sql = "CREATE INDEX {$index} ON {$table} (post_id, meta_key(191))";
					$wpdb->query( $sql );
			}
	}
}
