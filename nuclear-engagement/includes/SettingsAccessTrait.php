<?php
declare(strict_types=1);
/**
 * File: includes/SettingsAccessTrait.php
 *
 * Provides typed getter and setter helpers for SettingsRepository.
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if (!defined('ABSPATH')) {
    exit;
}

trait SettingsAccessTrait {
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
        return $this->set($key, SettingsSanitizer::sanitize_setting($key, $value));
    }
}

