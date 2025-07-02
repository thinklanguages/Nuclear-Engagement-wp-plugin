<?php
declare(strict_types=1);

namespace NuclearEngagement\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consolidated settings access trait.
 * 
 * Combines functionality from SettingsAccessTrait, SettingsGettersTrait,
 * SettingsCacheTrait, and SettingsPersistenceTrait.
 *
 * @package NuclearEngagement\Traits
 * @since 1.0.0
 */
trait ConsolidatedSettingsAccessTrait {

	/**
	 * Cache for settings data.
	 *
	 * @var array|null
	 */
	private static ?array $settings_cache = null;

	/**
	 * Get a setting value with type safety.
	 *
	 * @param string $key Setting key.
	 * @param mixed $default Default value.
	 * @return mixed Setting value.
	 */
	public function get( string $key, $default = null ) {
		$all_settings = $this->get_all();
		return $all_settings[$key] ?? $default;
	}

	/**
	 * Get a string setting value.
	 *
	 * @param string $key Setting key.
	 * @param string $default Default value.
	 * @return string Setting value.
	 */
	public function get_string( string $key, string $default = '' ): string {
		$value = $this->get( $key, $default );
		return is_string( $value ) ? $value : $default;
	}

	/**
	 * Get an integer setting value.
	 *
	 * @param string $key Setting key.
	 * @param int $default Default value.
	 * @return int Setting value.
	 */
	public function get_int( string $key, int $default = 0 ): int {
		$value = $this->get( $key, $default );
		return is_numeric( $value ) ? (int) $value : $default;
	}

	/**
	 * Get a boolean setting value.
	 *
	 * @param string $key Setting key.
	 * @param bool $default Default value.
	 * @return bool Setting value.
	 */
	public function get_bool( string $key, bool $default = false ): bool {
		$value = $this->get( $key, $default );
		return (bool) $value;
	}

	/**
	 * Get an array setting value.
	 *
	 * @param string $key Setting key.
	 * @param array $default Default value.
	 * @return array Setting value.
	 */
	public function get_array( string $key, array $default = [] ): array {
		$value = $this->get( $key, $default );
		return is_array( $value ) ? $value : $default;
	}

	/**
	 * Set a setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed $value Setting value.
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$this->invalidate_cache();
		
		// Update the setting using the repository
		if ( isset( $this->settings_repository ) ) {
			$this->settings_repository->set( $key, $value );
		}
		
		return $this;
	}

	/**
	 * Get all settings with caching.
	 *
	 * @param bool $use_cache Whether to use cache.
	 * @return array All settings.
	 */
	public function get_all( bool $use_cache = true ): array {
		if ( $use_cache && self::$settings_cache !== null ) {
			return self::$settings_cache;
		}

		$settings = [];
		
		// Get settings from repository if available
		if ( isset( $this->settings_repository ) ) {
			$settings = $this->settings_repository->get_all();
		} else {
			// Fallback to direct database access
			$settings = $this->get_settings_from_database();
		}

		// Cache the settings
		if ( $use_cache ) {
			self::$settings_cache = $settings;
		}

		return $settings;
	}

	/**
	 * Check if a setting exists.
	 *
	 * @param string $key Setting key.
	 * @return bool Whether setting exists.
	 */
	public function has( string $key ): bool {
		$all_settings = $this->get_all();
		return array_key_exists( $key, $all_settings );
	}

	/**
	 * Save settings to database.
	 *
	 * @param array|null $settings Settings to save (null for current settings).
	 * @return bool Whether save was successful.
	 */
	public function save( array $settings = null ): bool {
		if ( $settings === null ) {
			$settings = $this->get_all( false ); // Don't use cache
		}

		try {
			$this->save_settings_to_database( $settings );
			$this->invalidate_cache();
			return true;
		} catch ( \Exception $e ) {
			error_log( 'Nuclear Engagement: Settings save failed - ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Save multiple settings at once.
	 *
	 * @param array $settings Key-value pairs of settings.
	 * @return bool Whether save was successful.
	 */
	public function save_multiple( array $settings ): bool {
		$current_settings = $this->get_all( false );
		$merged_settings = array_merge( $current_settings, $settings );
		
		return $this->save( $merged_settings );
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key Setting key.
	 * @return bool Whether deletion was successful.
	 */
	public function delete( string $key ): bool {
		$all_settings = $this->get_all( false );
		
		if ( ! array_key_exists( $key, $all_settings ) ) {
			return false;
		}
		
		unset( $all_settings[$key] );
		return $this->save( $all_settings );
	}

	/**
	 * Clear all settings.
	 *
	 * @return bool Whether clearing was successful.
	 */
	public function clear_all(): bool {
		try {
			delete_option( 'nuclen_settings' );
			$this->invalidate_cache();
			return true;
		} catch ( \Exception $e ) {
			error_log( 'Nuclear Engagement: Settings clear failed - ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Invalidate the settings cache.
	 */
	public function invalidate_cache(): void {
		self::$settings_cache = null;
		
		// Clear related caches
		wp_cache_delete( 'nuclen_settings', 'nuclear_engagement' );
		wp_cache_delete( 'nuclen_all_settings', 'nuclear_engagement' );
		
		// Trigger cache invalidation action
		do_action( 'nuclen_settings_cache_invalidated' );
	}

	/**
	 * Warm up the settings cache.
	 *
	 * @return array Cached settings.
	 */
	public function warm_cache(): array {
		$this->invalidate_cache();
		return $this->get_all( true );
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	public function get_cache_stats(): array {
		return [
			'is_cached' => self::$settings_cache !== null,
			'cache_size' => self::$settings_cache ? count( self::$settings_cache ) : 0,
			'memory_usage' => self::$settings_cache ? strlen( serialize( self::$settings_cache ) ) : 0,
		];
	}

	/**
	 * Get settings directly from database.
	 *
	 * @return array Settings from database.
	 */
	private function get_settings_from_database(): array {
		// Try object cache first
		$cached = wp_cache_get( 'nuclen_all_settings', 'nuclear_engagement' );
		if ( $cached !== false ) {
			return $cached;
		}

		// Get from WordPress options
		$settings = get_option( 'nuclen_settings', [] );
		
		// Ensure we have an array
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		// Cache the result
		wp_cache_set( 'nuclen_all_settings', $settings, 'nuclear_engagement', HOUR_IN_SECONDS );

		return $settings;
	}

	/**
	 * Save settings to database.
	 *
	 * @param array $settings Settings to save.
	 * @throws \Exception If save fails.
	 */
	private function save_settings_to_database( array $settings ): void {
		// Sanitize settings before saving
		$sanitized_settings = $this->sanitize_settings_for_storage( $settings );
		
		// Determine if option should be autoloaded
		$autoload = $this->should_autoload_settings( $sanitized_settings );
		
		// Save to WordPress options
		$result = update_option( 'nuclen_settings', $sanitized_settings, $autoload );
		
		if ( ! $result ) {
			throw new \Exception( 'Failed to update settings option' );
		}

		// Update object cache
		wp_cache_set( 'nuclen_all_settings', $sanitized_settings, 'nuclear_engagement', HOUR_IN_SECONDS );
		
		// Trigger settings saved action
		do_action( 'nuclen_settings_saved', $sanitized_settings );
	}

	/**
	 * Sanitize settings for database storage.
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	private function sanitize_settings_for_storage( array $settings ): array {
		$sanitized = [];
		
		foreach ( $settings as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			
			if ( is_array( $value ) ) {
				$sanitized[$sanitized_key] = $this->sanitize_settings_for_storage( $value );
			} elseif ( is_string( $value ) ) {
				$sanitized[$sanitized_key] = sanitize_text_field( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[$sanitized_key] = (bool) $value;
			} elseif ( is_numeric( $value ) ) {
				$sanitized[$sanitized_key] = is_float( $value ) ? (float) $value : (int) $value;
			} else {
				// For other types, store as serialized string
				$sanitized[$sanitized_key] = maybe_serialize( $value );
			}
		}
		
		return $sanitized;
	}

	/**
	 * Determine if settings should be autoloaded.
	 *
	 * @param array $settings Settings to check.
	 * @return bool Whether to autoload.
	 */
	private function should_autoload_settings( array $settings ): bool {
		// Don't autoload if settings are too large (> 100KB)
		$serialized_size = strlen( serialize( $settings ) );
		if ( $serialized_size > 100 * 1024 ) {
			return false;
		}
		
		// Don't autoload if there are too many settings
		if ( count( $settings, COUNT_RECURSIVE ) > 1000 ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	public function get_defaults(): array {
		if ( isset( $this->settings_repository ) ) {
			return $this->settings_repository->get_defaults();
		}
		
		// Fallback defaults
		return [
			'theme' => 'default',
			'count_summary' => 5,
			'count_toc' => 10,
			'count_quiz' => 5,
			'placement_summary' => 'after',
			'placement_toc' => 'after',
			'placement_quiz' => 'after',
			'allow_html_summary' => false,
			'allow_html_toc' => false,
			'allow_html_quiz' => false,
			'auto_generate_summary' => true,
			'auto_generate_toc' => true,
			'auto_generate_quiz' => true,
		];
	}

	/**
	 * Reset settings to defaults.
	 *
	 * @return bool Whether reset was successful.
	 */
	public function reset_to_defaults(): bool {
		$defaults = $this->get_defaults();
		return $this->save( $defaults );
	}

	/**
	 * Export settings for backup/migration.
	 *
	 * @return array Exportable settings data.
	 */
	public function export_settings(): array {
		$settings = $this->get_all( false );
		
		return [
			'version' => NUCLEN_PLUGIN_VERSION,
			'exported_at' => time(),
			'settings' => $settings,
			'checksum' => md5( serialize( $settings ) ),
		];
	}

	/**
	 * Import settings from backup/migration.
	 *
	 * @param array $import_data Import data.
	 * @return bool Whether import was successful.
	 */
	public function import_settings( array $import_data ): bool {
		// Validate import data
		if ( ! isset( $import_data['settings'] ) || ! is_array( $import_data['settings'] ) ) {
			return false;
		}
		
		// Verify checksum if present
		if ( isset( $import_data['checksum'] ) ) {
			$calculated_checksum = md5( serialize( $import_data['settings'] ) );
			if ( $calculated_checksum !== $import_data['checksum'] ) {
				return false;
			}
		}
		
		// Backup current settings
		$backup = $this->export_settings();
		update_option( 'nuclen_settings_backup', $backup );
		
		// Import new settings
		try {
			return $this->save( $import_data['settings'] );
		} catch ( \Exception $e ) {
			// Restore backup on failure
			if ( isset( $backup['settings'] ) ) {
				$this->save( $backup['settings'] );
			}
			return false;
		}
	}

	/**
	 * Get usage statistics for settings.
	 *
	 * @return array Usage statistics.
	 */
	public function get_usage_stats(): array {
		$all_settings = $this->get_all( false );
		$defaults = $this->get_defaults();
		
		$changed_count = 0;
		$total_count = count( $defaults );
		
		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $all_settings[$key] ) && $all_settings[$key] !== $default_value ) {
				$changed_count++;
			}
		}
		
		return [
			'total_settings' => $total_count,
			'changed_settings' => $changed_count,
			'default_settings' => $total_count - $changed_count,
			'customization_percentage' => $total_count > 0 ? round( ( $changed_count / $total_count ) * 100, 1 ) : 0,
			'cache_status' => $this->get_cache_stats(),
		];
	}
}