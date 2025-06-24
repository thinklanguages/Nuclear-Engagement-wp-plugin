<?php
declare(strict_types=1);

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central theme definitions to avoid duplication.
 */
final class ThemeRegistry {
	/** Map of registered theme names to CSS files. */
	private static array $themes = array(
		'bright' => 'nuclen-theme-bright.css',
		'dark'   => 'nuclen-theme-dark.css',
	);

	/**
	 * Register a theme stylesheet.
	 */
	public static function register( string $name, string $stylesheet ): void {
		self::$themes[ $name ] = $stylesheet;
	}

	/**
	 * Retrieve the registered themes.
	 */
	public static function get_themes(): array {
		return self::$themes;
	}

	/**
	 * Get the stylesheet for a theme name.
	 */
	public static function get( string $name ): ?string {
		return self::$themes[ $name ] ?? null;
	}
}
