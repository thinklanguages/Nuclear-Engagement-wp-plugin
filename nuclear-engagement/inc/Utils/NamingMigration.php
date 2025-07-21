<?php
/**
 * NamingMigration.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Utils
 */

declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles migration from Nuclen naming to Nuclear Engagement naming
 */
class NamingMigration {

	/**
	 * Migration mappings for different naming patterns
	 */
	private const OPTION_MIGRATIONS = array(
		'nuclen_settings'                  => 'nuclear_engagement_settings',
		'nuclen_error_tracking'            => 'nuclear_engagement_error_tracking',
		'nuclen_rate_limits'               => 'nuclear_engagement_rate_limits',
		'nuclen_custom_css_version'        => 'nuclear_engagement_custom_css_version',
		'nuclen_theme_migration_completed' => 'nuclear_engagement_theme_migration_completed',
	);

	private const META_KEY_MIGRATIONS = array(
		'nuclen-quiz-data'      => 'nuclear-engagement-quiz-data',
		'nuclen_quiz_protected' => 'nuclear_engagement_quiz_protected',
		'nuclen_toc_disabled'   => 'nuclear_engagement_toc_disabled',
	);

	private const TABLE_MIGRATIONS = array(
		'nuclen_optins'          => 'nuclear_engagement_optins',
		'nuclen_error_log'       => 'nuclear_engagement_error_log',
		'nuclen_security_events' => 'nuclear_engagement_security_events',
	);

	/**
	 * Run all naming migrations
	 */
	public static function migrate_all(): void {
		self::migrate_options();
		self::migrate_meta_keys();
		self::migrate_transients();
		self::update_migration_flag();
	}

	/**
	 * Migrate WordPress options
	 */
	private static function migrate_options(): void {
		foreach ( self::OPTION_MIGRATIONS as $old_key => $new_key ) {
			$value = get_option( $old_key );
			if ( false !== $value ) {
				update_option( $new_key, $value );
				delete_option( $old_key );
			}
		}
	}

	/**
	 * Migrate post meta keys
	 */
	private static function migrate_meta_keys(): void {
		global $wpdb;

		foreach ( self::META_KEY_MIGRATIONS as $old_key => $new_key ) {
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_key' => $new_key ),
				array( 'meta_key' => $old_key ),
				array( '%s' ),
				array( '%s' )
			);
		}
	}

	/**
	 * Migrate transients
	 */
	private static function migrate_transients(): void {
		// Clear old transients with nuclen prefix
		$old_transients = array(
			'nuclen_pq_',
			'nuclen_allowed_post_types',
			'nuclen_toc_cache_',
		);

		foreach ( $old_transients as $prefix ) {
			self::clear_transients_by_prefix( $prefix );
		}
	}

	/**
	 * Clear transients by prefix
	 *
	 * @param string $prefix Transient prefix.
	 */
	private static function clear_transients_by_prefix( string $prefix ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
			)
		);
	}

	/**
	 * Update migration completion flag
	 */
	private static function update_migration_flag(): void {
		update_option( 'nuclear_engagement_naming_migration_completed', true );
	}

	/**
	 * Check if migration has been completed
	 *
	 * @return bool Whether migration is complete.
	 */
	public static function is_migration_completed(): bool {
		return (bool) get_option( 'nuclear_engagement_naming_migration_completed', false );
	}

	/**
	 * Get standardized handle for assets
	 *
	 * @param string $type Asset type (admin, front, etc.).
	 * @return string Standardized handle.
	 */
	public static function get_asset_handle( string $type ): string {
		return 'nuclear-engagement-' . $type;
	}

	/**
	 * Get standardized CSS class prefix
	 *
	 * @return string CSS class prefix.
	 */
	public static function get_css_prefix(): string {
		return 'nuclear-engagement-';
	}

	/**
	 * Get standardized option name
	 *
	 * @param string $suffix Option suffix.
	 * @return string Standardized option name.
	 */
	public static function get_option_name( string $suffix ): string {
		return 'nuclear_engagement_' . $suffix;
	}

	/**
	 * Get standardized meta key
	 *
	 * @param string $suffix Meta key suffix.
	 * @return string Standardized meta key.
	 */
	public static function get_meta_key( string $suffix ): string {
		return 'nuclear-engagement-' . $suffix;
	}

	/**
	 * Get standardized action name
	 *
	 * @param string $action Action name.
	 * @return string Standardized action name.
	 */
	public static function get_action_name( string $action ): string {
		return 'nuclear_engagement_' . $action;
	}

	/**
	 * Get standardized transient key
	 *
	 * @param string $key Transient key.
	 * @return string Standardized transient key.
	 */
	public static function get_transient_key( string $key ): string {
		return 'nuclear_engagement_' . $key;
	}
}
