<?php
/**
 * File: includes/SettingsCache.php
 *
 * Handles settings caching logic separately from SettingsRepository.
 *
 * @package NuclearEngagement
 * @subpackage Cache
 * @since     1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement\Core;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles caching for plugin settings.
 *
 * This class provides an object-oriented interface for managing the caching
 * of plugin settings to improve performance.
 *
 * @since 1.0.0
 */
final class SettingsCache {

	/**
	 * Cache group used for settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const CACHE_GROUP = 'nuclen_settings';

	/**
	 * Default cache lifetime in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const CACHE_EXPIRATION = HOUR_IN_SECONDS; // 1 hour.


	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		add_action( 'updated_option', array( $this, 'maybe_invalidate_cache' ), 10, 3 );
		add_action( 'deleted_option', array( $this, 'maybe_invalidate_cache' ), 10, 1 );
		add_action( 'switch_blog', array( $this, 'invalidate_cache' ) );
	}

	/**
	 * Generate a cache key for the current site.
	 *
	 * @since 1.0.0
	 *
	 * @return string The cache key.
	 */
	public function get_cache_key(): string {
		return 'settings_' . get_current_blog_id();
	}

	/**
	 * Get cached settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null Cached settings array or null if not found.
	 */
	public function get(): ?array {
		$cached = wp_cache_get( $this->get_cache_key(), self::CACHE_GROUP );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Cache settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Settings array to cache.
	 */
	public function set( array $settings ): void {
		wp_cache_set( $this->get_cache_key(), $settings, self::CACHE_GROUP, self::CACHE_EXPIRATION );
	}

	/**
	 * Invalidate the settings cache.
	 *
	 * @since 1.0.0
	 */
	public function invalidate_cache(): void {
		$key = $this->get_cache_key();
		wp_cache_delete( $key, self::CACHE_GROUP );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_flush();
		}
	}

	/**
	 * Invalidate cache when relevant options are updated.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option Name of the option being updated.
	 */
	public function maybe_invalidate_cache( $option ): void {
		if ( SettingsRepository::OPTION === $option ) {
			$this->invalidate_cache();
		}
	}

	/**
	 * Clear all cached settings.
	 *
	 * @since 1.0.0
	 */
	public function clear(): void {
		$this->invalidate_cache();
	}
}
