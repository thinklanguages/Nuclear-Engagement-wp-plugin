<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles autoloading of plugin classes.
 */
final class Autoloader {
	/**
	 * Register the autoload callback.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Autoload callback for plugin classes.
	 */
	private static function autoload( string $class ): void {
		$prefix = 'NuclearEngagement\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );

		$paths   = array();
		$paths[] = NUCLEN_PLUGIN_DIR . $relative . '.php';

		$segments = explode( '/', $relative );
		if ( in_array( $segments[0], array( 'Admin', 'Front' ), true ) ) {
			$segments[0] = strtolower( $segments[0] );
			$paths[]     = NUCLEN_PLUGIN_DIR . implode( '/', $segments ) . '.php';

			if ( isset( $segments[1] ) ) {
				// Try both uppercase and lowercase for traits directory
				$paths[] = NUCLEN_PLUGIN_DIR . $segments[0] . '/Traits/' . $segments[1] . '.php';
				$paths[] = NUCLEN_PLUGIN_DIR . $segments[0] . '/traits/' . $segments[1] . '.php';
			}
		}

		$paths[] = NUCLEN_PLUGIN_DIR . 'inc/' . $relative . '.php';
		$paths[] = NUCLEN_PLUGIN_DIR . 'inc/Core/' . $relative . '.php';
		$paths[] = NUCLEN_PLUGIN_DIR . 'inc/Helpers/' . $relative . '.php';
		$paths[] = NUCLEN_PLUGIN_DIR . 'inc/Repositories/' . $relative . '.php';

		foreach ( $paths as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
}
