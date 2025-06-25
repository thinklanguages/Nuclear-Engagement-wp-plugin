<?php
/**
 * File: includes/Traits/SettingsCacheTrait.php
 *
 * Provides cache management helpers for SettingsRepository.
 *
 * @package NuclearEngagement
 * @subpackage Traits
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement\Traits;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait SettingsCacheTrait {
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
     * @param string $option    The option name being updated.
     * @param mixed  $old_value The old option value.
     * @param mixed  $value     The new option value.
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

    /**
     * Clear all cached data (for testing).
     *
     * @since 1.0.0
     */
    public function clear_cache(): void {
        $this->cache->clear();
    }
}
