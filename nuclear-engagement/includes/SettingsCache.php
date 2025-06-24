<?php
declare(strict_types=1);
/**
 * File: includes/SettingsCache.php
 *
 * Handles settings caching logic separately from SettingsRepository.
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsCache {
	/** Cache group used for settings. */
	public const CACHE_GROUP = 'nuclen_settings';

	/** Default cache lifetime in seconds. */
	public const CACHE_EXPIRATION = HOUR_IN_SECONDS; // 1 hour

	public function register_hooks(): void {
		add_action( 'updated_option', array( $this, 'maybe_invalidate_cache' ), 10, 3 );
		add_action( 'deleted_option', array( $this, 'maybe_invalidate_cache' ), 10, 1 );
		add_action( 'switch_blog', array( $this, 'invalidate_cache' ) );
	}

	public function get_cache_key(): string {
		return 'settings_' . get_current_blog_id();
	}

	public function get(): ?array {
		$cached = wp_cache_get( $this->get_cache_key(), self::CACHE_GROUP );
		return is_array( $cached ) ? $cached : null;
	}

	public function set( array $settings ): void {
		wp_cache_set( $this->get_cache_key(), $settings, self::CACHE_GROUP, self::CACHE_EXPIRATION );
	}

	public function invalidate_cache(): void {
		$key = $this->get_cache_key();
		wp_cache_delete( $key, self::CACHE_GROUP );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_flush();
		}
	}

	public function maybe_invalidate_cache( $option ): void {
		if ( $option === SettingsRepository::OPTION ) {
			$this->invalidate_cache();
		}
	}

	public function clear(): void {
		$this->invalidate_cache();
	}
}
