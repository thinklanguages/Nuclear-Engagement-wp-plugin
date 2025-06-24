<?php
/**
 * File: includes/SettingsRepository.php
 *
 * Centralized, type-safe settings repository for Nuclear Engagement plugin.
 *
 * @package	NuclearEngagement
 * @subpackage Core
 * @since	  1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement;

use NuclearEngagement\SettingsSanitizer;
use NuclearEngagement\SettingsCache;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A fluent, type-safe settings repository for the Nuclear Engagement plugin.
 *
 * This class provides a centralized way to manage plugin settings with type safety,
 * caching, and automatic sanitization.
 *
 * @since 1.0.0
 */
final class SettingsRepository {

	/**
	 * The option name used to store settings in the database.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION = 'nuclear_engagement_settings';

	/**
	 * Maximum size (in bytes) for settings to be autoloaded.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_AUTOLOAD_SIZE = 512000;


	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var SettingsRepository|null
	 */
	private static $instance = null;

	/**
	 * Default settings values.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $defaults = array();

	/**
	 * Pending changes not yet saved.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $pending = array();

	/**
	 * Cache handler.
	 *
	 * @since 1.0.0
	 * @var SettingsCache
	 */
	private SettingsCache $cache;


	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array $defaults Optional. Default settings to use if not already set.
	 * @return self The singleton instance.
	 */
	public static function get_instance( array $defaults = array() ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $defaults );
		}
		return self::$instance;
	}

	/**
	 * Private constructor - use get_instance() instead.
	 *
	 * @since 1.0.0
	 *
	 * @param array $defaults Optional. Default settings to use.
	 */
	private function __construct( array $defaults = array() ) {
		// Merge provided defaults with built-in defaults.
		$this->defaults = wp_parse_args( $defaults, Defaults::nuclen_get_default_settings() );
		$this->cache	= new SettingsCache();
		$this->cache->register_hooks();
	}


	   /*
	   ===================================================================
		* GETTERS
		* ===================================================================
		*/

	/**
	 * Get all settings with defaults merged in.
	 *
	 * @since 1.0.0
	 *
	 * @return array The complete settings array with defaults merged in.
	 */
	public function all(): array {
		$cached = $this->cache->get();
		if ( null !== $cached ) {
			return $cached;
		}

		// Not in cache, fetch from database.
		$saved	= get_option( self::OPTION, array() );
		$settings = wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			$this->defaults
		);

		// Store in cache.
		$this->cache->set( $settings );

		return $settings;
	}

	/**
	 * Get all settings from database (bypasses cache).
	 *
	 * @since 1.0.0
	 *
	 * @return array The complete settings array.
	 */
	public function get_all(): array {
		return $this->all();
	}

	/**
	 * Get a specific setting by key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key	 The setting key to retrieve.
	 * @param mixed  $fallback Optional. Fallback value if the setting doesn't exist.
	 * @return mixed The setting value, or fallback if not found.
	 */
	public function get( string $key, $fallback = null ) {
		$all   = $this->all();
		$value = $all[ $key ] ?? $fallback;

		// Allow filtering of individual settings.
		if ( 1 === func_num_args() ) {
			$value = apply_filters( "nuclen_setting_{$key}", $value, $key );
		}

		return $value;
	}

	use SettingsAccessTrait;
	use PendingSettingsTrait;


	   /*
	   ===================================================================
		* SAVE/PERSISTENCE
		* ===================================================================
		*/

	/**
	 * Save pending settings to database.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if settings were saved, false otherwise.
	 */
	public function save(): bool {
		if ( empty( $this->pending ) ) {
			return false;
		}

		$current   = $this->all();
		$sanitized = SettingsSanitizer::sanitize_settings( $this->pending );
		$merged	= wp_parse_args( $sanitized, $current );

		// Clear pending settings.
		$this->pending = array();

		// Invalidate cache before save.
		$this->invalidate_cache();

		// Only update if settings have changed.
		if ( $merged !== $current ) {
			$autoload = $this->should_autoload( $merged );
			$result   = update_option( self::OPTION, $merged, $autoload ? 'yes' : 'no' );

			// Also update legacy option for backward compatibility.
			if ( $result && false !== get_option( 'nuclear_engagement_setup' ) ) {
				$legacy_data = array(
					'api_key'			 => $merged['api_key'] ?? '',
					'connected'		   => $merged['connected'] ?? false,
					'wp_app_pass_created' => $merged['wp_app_pass_created'] ?? false,
					'wp_app_pass_uuid'	=> $merged['wp_app_pass_uuid'] ?? '',
					'plugin_password'	 => $merged['plugin_password'] ?? '',
				);
				update_option( 'nuclear_engagement_setup', $legacy_data );
			}

			return $result;
		}

		return false;
	}


	   /*
	   ===================================================================
		* CACHE MANAGEMENT
		* ===================================================================
		*/

	/**
	 * Invalidate the settings cache.
	 *
	 * @since 1.0.0
	 */
	public function invalidate_cache(): void {
		$this->cache->invalidate_cache();
	}

	/**
	 * Handle option updates to invalidate cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option	The option name being updated.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value	 The new option value.
	 */
	   public function maybe_invalidate_cache( $option, $old_value, $value ): void {
			   unset( $old_value, $value );
			   $this->cache->maybe_invalidate_cache( $option );
	   }

	/**
	 * Handle option deletion to invalidate cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option The option name being deleted.
	 */
	public function maybe_invalidate_cache_on_delete( $option ): void {
		$this->cache->maybe_invalidate_cache( $option );
	}

	   /*
	   ===================================================================
		* HELPERS
		* ===================================================================
		*/

	/**
	 * Check if a setting exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The setting key to check.
	 * @return bool True if the setting exists, false otherwise.
	 */
	public function has( string $key ): bool {
		$all = $this->all();
		return array_key_exists( $key, $all );
	}


	/**
	 * Determine if settings should be autoloaded.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The settings array to check.
	 * @return bool True if settings should be autoloaded, false otherwise.
	 */
	   private function should_autoload( array $settings ): bool {
			   $size = strlen( wp_json_encode( $settings ) );
			   return $size <= self::MAX_AUTOLOAD_SIZE;
	   }

	/**
	 * Get the default values.
	 *
	 * @since 1.0.0
	 *
	 * @return array The default settings values.
	 */
	public function get_defaults(): array {
		return $this->defaults;
	}

	/**
	 * Clear all cached data (for testing).
	 *
	 * @since 1.0.0
	 */
	public function clear_cache(): void {
		$this->cache->clear();
	}

	/**
	 * Reset singleton instance (for testing).
	 *
	 * @since 1.0.0
	 */
	   public static function reset_for_tests(): void {
			   self::$instance = null;
			   if ( function_exists( 'wp_cache_flush_group' ) ) {
					   wp_cache_flush_group( SettingsCache::CACHE_GROUP );
			   } else {
					   wp_cache_flush();
			   }
	   }
}
