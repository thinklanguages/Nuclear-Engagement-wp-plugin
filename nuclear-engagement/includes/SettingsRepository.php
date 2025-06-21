<?php
/**
 * File: includes/SettingsRepository.php
 *
 * Centralized, type-safe settings repository for Nuclear Engagement plugin.
 */

namespace NuclearEngagement;
use NuclearEngagement\SettingsSanitizer;
use NuclearEngagement\SettingsCache;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsRepository
 *
 * A fluent, type-safe settings repository for the Nuclear Engagement plugin.
 */
final class SettingsRepository
{
    /**
     * The option name used to store settings in the database.
     */
    const OPTION = 'nuclear_engagement_settings';

    /**
     * Maximum size (in bytes) for settings to be autoloaded.
     */
    const MAX_AUTOLOAD_SIZE = 512000;


    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Default settings values.
     */
    private $defaults = [];

    /**
     * Pending changes not yet saved.
     */
    private $pending = [];

    /**
     * Cache handler.
     */
    private SettingsCache $cache;


    /**
     * Get the singleton instance.
     */
    public static function get_instance(array $defaults = []): self {
        if (self::$instance === null) {
            self::$instance = new self($defaults);
        }
        return self::$instance;
    }

    /**
     * Private constructor - use get_instance() instead.
     */
    private function __construct(array $defaults = []) {
        // Merge provided defaults with built-in defaults
        $this->defaults = wp_parse_args($defaults, Defaults::nuclen_get_default_settings());
        $this->cache = new SettingsCache();
        $this->cache->register_hooks();
    }


    /* ===================================================================
     * GETTERS
     * =================================================================== */

    /**
     * Get all settings with defaults merged in.
     */
    public function all(): array {
        $cached = $this->cache->get();
        if (null !== $cached) {
            return $cached;
        }

        // Not in cache, fetch from database
        $saved = get_option(self::OPTION, []);
        $settings = wp_parse_args(
            is_array($saved) ? $saved : [],
            $this->defaults
        );

        // Store in cache
        $this->cache->set($settings);

        return $settings;
    }

    /**
     * Get all settings from database (bypasses cache)
     */
    public function get_all(): array {
        return $this->all();
    }

    /**
     * Get a specific setting by key.
     */
    public function get(string $key, $fallback = null) {
        $all = $this->all();
        $value = $all[$key] ?? $fallback;

        // Allow filtering of individual settings
        if (func_num_args() === 1) {
            $value = apply_filters("nuclen_setting_{$key}", $value, $key);
        }

        return $value;
    }

    use SettingsAccessTrait;
    use PendingSettingsTrait;


    /* ===================================================================
     * SAVE/PERSISTENCE
     * =================================================================== */

    /**
     * Save pending settings to database.
     */
    public function save(): bool {
        if (empty($this->pending)) {
            return false;
        }

        $current = $this->all();
        $sanitized = SettingsSanitizer::sanitize_settings($this->pending);
        $merged = wp_parse_args($sanitized, $current);

        // Clear pending settings
        $this->pending = [];

        // Invalidate cache before save
        $this->invalidate_cache();

        // Only update if settings have changed
        if ($merged !== $current) {
            $autoload = $this->should_autoload($merged);
            $result = update_option(self::OPTION, $merged, $autoload ? 'yes' : 'no');

            // Also update legacy option for backward compatibility
            if ($result && false !== get_option('nuclear_engagement_setup')) {
                $legacy_data = [
                    'api_key' => $merged['api_key'] ?? '',
                    'connected' => $merged['connected'] ?? false,
                    'wp_app_pass_created' => $merged['wp_app_pass_created'] ?? false,
                    'wp_app_pass_uuid' => $merged['wp_app_pass_uuid'] ?? '',
                    'plugin_password' => $merged['plugin_password'] ?? ''
                ];
                update_option('nuclear_engagement_setup', $legacy_data);
            }

            return $result;
        }

        return false;
    }


    /* ===================================================================
     * CACHE MANAGEMENT
     * =================================================================== */

    /**
     * Invalidate the settings cache.
     */
    public function invalidate_cache(): void {
        $this->cache->invalidate_cache();
    }

    /**
     * Handle option updates to invalidate cache.
     */
    public function maybe_invalidate_cache($option, $old_value, $value): void {
        $this->cache->maybe_invalidate_cache($option);
    }

    /**
     * Handle option deletion to invalidate cache.
     */
    public function maybe_invalidate_cache_on_delete($option): void {
        $this->cache->maybe_invalidate_cache($option);
    }

    /* ===================================================================
     * HELPERS
     * =================================================================== */

    /**
     * Check if a setting exists.
     */
    public function has(string $key): bool {
        $all = $this->all();
        return array_key_exists($key, $all);
    }


    /**
     * Determine if settings should be autoloaded.
     */
    private function should_autoload(array $settings): bool {
        $size = strlen(serialize($settings));
        return $size <= self::MAX_AUTOLOAD_SIZE;
    }

    /**
     * Get the default values.
     */
    public function get_defaults(): array {
        return $this->defaults;
    }

    /**
     * Clear all cached data (for testing).
     */
    public function clear_cache(): void {
        $this->cache->clear();
    }

    /**
     * Reset singleton instance (for testing).
     */
    public static function _reset_for_tests(): void {
        self::$instance = null;
        wp_cache_flush_group( SettingsCache::CACHE_GROUP );
    }
}
