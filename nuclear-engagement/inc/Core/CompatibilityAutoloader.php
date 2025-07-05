<?php
/**
 * CompatibilityAutoloader.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles compatibility between src/ and nuclear-engagement/inc/ structures.
 *
 * @package NuclearEngagement\Core
 */
final class CompatibilityAutoloader {
	private static bool $registered = false;

	/**
	 * Register the compatibility autoloader.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		spl_autoload_register( array( self::class, 'autoload' ), true, true );
		self::$registered = true;
	}

	/**
	 * Autoload classes from canonical location.
	 */
	public static function autoload( string $class ): void {
		// Only handle our namespace.
		if ( ! str_starts_with( $class, 'NuclearEngagement\\' ) ) {
			return;
		}

		// Map deprecated src/ paths to canonical nuclear-engagement/inc/ paths.
		$class_map = self::getClassMap();

		if ( isset( $class_map[ $class ] ) ) {
			$canonical_file = $class_map[ $class ];
			if ( file_exists( $canonical_file ) ) {
				require_once $canonical_file;
				return;
			}
		}

		// Try to map by path structure.
		$relative_path = str_replace( 'NuclearEngagement\\', '', $class );
		$relative_path = str_replace( '\\', '/', $relative_path );

		// Try nuclear-engagement/inc/ first (canonical).
		$canonical_file = __DIR__ . '/../' . $relative_path . '.php';
		if ( file_exists( $canonical_file ) ) {
			require_once $canonical_file;
			return;
		}

		// Try src/ as fallback for new files.
		$src_file = dirname( __DIR__, 3 ) . '/src/' . $relative_path . '.php';
		if ( file_exists( $src_file ) ) {
			require_once $src_file;
		}
	}

	/**
	 * Get mapping of classes to their canonical locations.
	 */
	private static function getClassMap(): array {
		$base_path = __DIR__ . '/../';

		return array(
			// Services.
			'NuclearEngagement\\Services\\ThemeSettingsService' => $base_path . 'Services/ThemeSettingsService.php',
			'NuclearEngagement\\Services\\ThemeCssGenerator' => $base_path . 'Services/ThemeCssGenerator.php',
			'NuclearEngagement\\Services\\ThemeEventManager' => $base_path . 'Services/ThemeEventManager.php',
			'NuclearEngagement\\Services\\ThemeLoader'    => $base_path . 'Services/ThemeLoader.php',
			'NuclearEngagement\\Services\\ThemeMigrationService' => $base_path . 'Services/ThemeMigrationService.php',
			'NuclearEngagement\\Services\\ThemeConfigConverter' => $base_path . 'Services/ThemeConfigConverter.php',
			'NuclearEngagement\\Services\\ThemeValidator' => $base_path . 'Services/ThemeValidator.php',

			// Repositories.
			'NuclearEngagement\\Repositories\\ThemeRepository' => $base_path . 'Repositories/ThemeRepository.php',

			// Models.
			'NuclearEngagement\\Models\\Theme'            => $base_path . 'Models/Theme.php',

			// Database Schema.
			'NuclearEngagement\\Database\\Schema\\ThemeSchema' => $base_path . 'Database/Schema/ThemeSchema.php',

			// Style Generators.
			'NuclearEngagement\\Services\\Styles\\ProgressBarStyleGenerator' => $base_path . 'Services/Styles/ProgressBarStyleGenerator.php',
			'NuclearEngagement\\Services\\Styles\\QuizButtonStyleGenerator' => $base_path . 'Services/Styles/QuizButtonStyleGenerator.php',
			'NuclearEngagement\\Services\\Styles\\QuizContainerStyleGenerator' => $base_path . 'Services/Styles/QuizContainerStyleGenerator.php',
			'NuclearEngagement\\Services\\Styles\\StyleGeneratorFactory' => $base_path . 'Services/Styles/StyleGeneratorFactory.php',
			'NuclearEngagement\\Services\\Styles\\StyleGeneratorInterface' => $base_path . 'Services/Styles/StyleGeneratorInterface.php',
			'NuclearEngagement\\Services\\Styles\\SummaryContainerStyleGenerator' => $base_path . 'Services/Styles/SummaryContainerStyleGenerator.php',
			'NuclearEngagement\\Services\\Styles\\TocStyleGenerator' => $base_path . 'Services/Styles/TocStyleGenerator.php',
		);
	}
}
