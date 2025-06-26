<?php
declare(strict_types=1);
/**
 * File: includes/Themes.php
 *
 * Provides a simple registry of available CSS themes.
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central theme definitions to avoid duplication.
 */
final class ThemeRegistry {
        /**
         * Map of registered theme names to CSS files.
         *
         * @var array<string,string>
         */
    private static array $themes = array(
        'bright' => 'nuclen-theme-bright.css',
        'dark'   => 'nuclen-theme-dark.css',
    );

        /**
         * Register a theme stylesheet.
         *
         * @param string $name      Theme slug.
         * @param string $stylesheet Stylesheet filename.
         */
    public static function register( string $name, string $stylesheet ): void {
            self::$themes[ $name ] = $stylesheet;
    }

        /**
         * Retrieve the registered themes.
         *
         * @return array<string,string> Map of theme names to stylesheets.
         */
    public static function get_themes(): array {
            return self::$themes;
    }

        /**
         * Get the stylesheet for a theme name.
         *
         * @param string $name Theme slug.
         * @return string|null Stylesheet filename if registered.
         */
    public static function get( string $name ): ?string {
            return self::$themes[ $name ] ?? null;
    }
}
