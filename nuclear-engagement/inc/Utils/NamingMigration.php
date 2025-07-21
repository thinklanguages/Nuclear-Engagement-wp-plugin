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
		global $wpdb;
		
		// Fetch all old options in a single query
		$old_keys = array_keys( self::OPTION_MIGRATIONS );
		$placeholders = implode( ',', array_fill( 0, count( $old_keys ), '%s' ) );
		
		$old_options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
				...$old_keys
			),
			ARRAY_A
		);
		
		if ( empty( $old_options ) ) {
			return;
		}
		
		// Prepare bulk insert values
		$values_to_insert = array();
		$keys_to_delete = array();
		
		foreach ( $old_options as $option ) {
			$old_key = $option['option_name'];
			$new_key = self::OPTION_MIGRATIONS[ $old_key ];
			$values_to_insert[] = $wpdb->prepare( '(%s, %s, %s)', $new_key, $option['option_value'], 'yes' );
			$keys_to_delete[] = $old_key;
		}
		
		// Bulk insert new options
		if ( ! empty( $values_to_insert ) ) {
			$wpdb->query(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES " . 
				implode( ',', $values_to_insert ) . 
				" ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)"
			);
			
			// Bulk delete old options
			$delete_placeholders = implode( ',', array_fill( 0, count( $keys_to_delete ), '%s' ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name IN ($delete_placeholders)",
					...$keys_to_delete
				)
			);
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
