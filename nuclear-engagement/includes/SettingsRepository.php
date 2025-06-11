<?php
/**
 * File: includes/SettingsRepository.php
 *
 * Centralized, type-safe settings repository for Nuclear Engagement plugin.
 */

namespace NuclearEngagement;

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
     * Cache group for object caching
     */
    const CACHE_GROUP = 'nuclen_settings';
    
    /**
     * Cache expiration time
     */
    const CACHE_EXPIRATION = 3600; // 1 hour

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
     * Sanitization rules for settings.
     */
    private const SANITIZATION_RULES = [
        'api_key' => 'sanitize_text_field',
        'theme' => 'sanitize_text_field',
        'font_size' => 'absint',
        'font_color' => 'sanitize_hex_color',
        'bg_color' => 'sanitize_hex_color',
        'border_color' => 'sanitize_hex_color',
        'border_style' => 'sanitize_text_field',
        'border_width' => 'absint',
        'quiz_title' => 'sanitize_text_field',
        'summary_title' => 'sanitize_text_field',
        'toc_title' => 'sanitize_text_field',
        'show_attribution' => 'rest_sanitize_boolean',
        'display_summary' => 'sanitize_text_field',
        'display_quiz' => 'sanitize_text_field',
        'display_toc' => 'sanitize_text_field',
        'connected' => 'rest_sanitize_boolean',
        'wp_app_pass_created' => 'rest_sanitize_boolean',
        'wp_app_pass_uuid' => 'sanitize_text_field',
        'plugin_password' => 'sanitize_text_field',
        'delete_settings_on_uninstall' => 'rest_sanitize_boolean',
        'delete_generated_content_on_uninstall' => 'rest_sanitize_boolean',
        'toc_heading_levels' => [self::class, 'sanitize_heading_levels'],
        'generation_post_types' => [self::class, 'sanitize_post_types'],
    ];

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
        $this->setup_hooks();
    }

    /**
     * Set up WordPress hooks.
     */
    private function setup_hooks(): void {
        add_action('updated_option', [$this, 'maybe_invalidate_cache'], 10, 3);
        add_action('deleted_option', [$this, 'maybe_invalidate_cache_on_delete'], 10, 1);
        add_action('switch_blog', [$this, 'invalidate_cache']);
    }

    /**
     * Get cache key for current site
     *
     * @return string
     */
    private function get_cache_key(): string {
        return 'settings_' . get_current_blog_id();
    }

    /* ===================================================================
     * GETTERS
     * =================================================================== */

    /**
     * Get all settings with defaults merged in.
     */
    public function all(): array {
        $cache_key = $this->get_cache_key();
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }
        
        // Not in cache, fetch from database
        $saved = get_option(self::OPTION, []);
        $settings = wp_parse_args(
            is_array($saved) ? $saved : [],
            $this->defaults
        );
        
        // Store in cache
        wp_cache_set($cache_key, $settings, self::CACHE_GROUP, self::CACHE_EXPIRATION);
        
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

    /**
     * Get a string setting.
     */
    public function get_string(string $key, string $default = ''): string {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : (string) $value;
    }

    /**
     * Get an integer setting.
     */
    public function get_int(string $key, int $default = 0): int {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get a boolean setting.
     */
    public function get_bool(string $key, bool $default = false): bool {
        return (bool) $this->get($key, $default);
    }

    /**
     * Get an array setting.
     */
    public function get_array(string $key, array $default = []): array {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /* ===================================================================
     * SETTERS
     * =================================================================== */

    /**
     * Set a setting value to be saved later.
     */
    public function set(string $key, $value): self {
        $this->pending[$key] = $value;
        return $this;
    }

    /**
     * Set a string setting.
     */
    public function set_string(string $key, string $value): self {
        return $this->set($key, sanitize_text_field($value));
    }

    /**
     * Set an integer setting.
     */
    public function set_int(string $key, int $value): self {
        return $this->set($key, (int) $value);
    }

    /**
     * Set a boolean setting.
     */
    public function set_bool(string $key, bool $value): self {
        return $this->set($key, (bool) $value);
    }

    /**
     * Set an array setting.
     */
    public function set_array(string $key, array $value): self {
        return $this->set($key, $this->sanitize_array($value));
    }

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
        $sanitized = $this->sanitize_settings($this->pending);
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
     * SANITIZATION
     * =================================================================== */

    /**
     * Sanitize settings before saving.
     */
    private function sanitize_settings(array $settings): array {
        $sanitized = [];
        
        foreach ($settings as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            
            // Apply field-specific sanitization if defined
            if (isset(self::SANITIZATION_RULES[$key])) {
                $rule = self::SANITIZATION_RULES[$key];
                
                if (is_callable($rule)) {
                    $sanitized[$key] = call_user_func($rule, $value);
                } else {
                    $sanitized[$key] = $value;
                }
            } else {
                // Default sanitization based on type
                if (is_array($value)) {
                    $sanitized[$key] = $this->sanitize_array($value);
                } elseif (is_bool($value)) {
                    $sanitized[$key] = (bool) $value;
                } elseif (is_numeric($value)) {
                    $sanitized[$key] = is_float($value) ? (float) $value : (int) $value;
                } else {
                    $sanitized[$key] = sanitize_text_field((string) $value);
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize an array of values.
     */
    private function sanitize_array(array $values): array {
        return array_map(function ($value) {
            if (is_array($value)) {
                return $this->sanitize_array($value);
            }
            return is_string($value) ? sanitize_text_field($value) : $value;
        }, $values);
    }

    /**
     * Sanitize heading levels array.
     */
    private static function sanitize_heading_levels($value): array {
        if (!is_array($value)) {
            return [2, 3, 4, 5, 6];
        }
        
        return array_values(array_filter(
            array_map('absint', $value),
            function($level) {
                return $level >= 1 && $level <= 6;
            }
        ));
    }

    /**
     * Sanitize post types array.
     */
    private static function sanitize_post_types($value): array {
        if (!is_array($value)) {
            return ['post'];
        }
        
        return array_values(array_filter(
            array_map('sanitize_key', $value),
            'post_type_exists'
        ));
    }

    /* ===================================================================
     * CACHE MANAGEMENT
     * =================================================================== */

    /**
     * Invalidate the settings cache.
     */
    public function invalidate_cache(): void {
        $cache_key = $this->get_cache_key();
        wp_cache_delete($cache_key, self::CACHE_GROUP);
        
        // Also clear any global cache keys if using external object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }

    /**
     * Handle option updates to invalidate cache.
     */
    public function maybe_invalidate_cache($option, $old_value, $value): void {
        if ($option === self::OPTION) {
            $this->invalidate_cache();
        }
    }

    /**
     * Handle option deletion to invalidate cache.
     */
    public function maybe_invalidate_cache_on_delete($option): void {
        if ($option === self::OPTION) {
            $this->invalidate_cache();
        }
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
     * Remove a setting.
     */
    public function remove(string $key): self {
        if (isset($this->pending[$key])) {
            unset($this->pending[$key]);
        }
        
        // Set to null to indicate removal
        $this->pending[$key] = null;
        return $this;
    }

    /**
     * Check if there are pending changes.
     */
    public function has_pending(): bool {
        return !empty($this->pending);
    }

    /**
     * Get all pending changes.
     */
    public function get_pending(): array {
        return $this->pending;
    }

    /**
     * Clear pending changes without saving.
     */
    public function clear_pending(): self {
        $this->pending = [];
        return $this;
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
        $this->invalidate_cache();
    }

    /**
     * Reset singleton instance (for testing).
     */
    public static function _reset_for_tests(): void {
        self::$instance = null;
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }
}