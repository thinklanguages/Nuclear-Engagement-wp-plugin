<?php
/**
 * LazyModuleLoader.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy loading for modules to improve performance.
 * Modules are only loaded when their functionality is actually needed.
 *
 * @package NuclearEngagement\Core
 */
class LazyModuleLoader {
	/**
	 * Loaded modules cache.
	 *
	 * @var array
	 */
	private static array $loaded_modules = array();

	/**
	 * Module definitions.
	 *
	 * @var array
	 */
	private static array $module_definitions = array(
		'toc'     => array(
			'class'        => 'NuclearEngagement\Modules\TOC\TocModule',
			'hooks'        => array( 'the_content' ),
			'admin_pages'  => array( 'post.php', 'post-new.php' ),
			'settings_key' => 'toc_enabled',
		),
		'quiz'    => array(
			'class'        => 'NuclearEngagement\Modules\Quiz\QuizModule',
			'hooks'        => array( 'init' ),
			'admin_pages'  => array( 'post.php', 'post-new.php' ),
			'shortcodes'   => array( 'nuclear-quiz' ),
			'settings_key' => 'quiz_enabled',
		),
		'summary' => array(
			'class'        => 'NuclearEngagement\Modules\Summary\SummaryModule',
			'hooks'        => array( 'init' ),
			'admin_pages'  => array( 'post.php', 'post-new.php' ),
			'shortcodes'   => array( 'nuclear-summary' ),
			'settings_key' => 'summary_enabled',
		),
	);

	/**
	 * Initialize lazy loading.
	 */
	public static function init(): void {
		// Register hook listeners for lazy loading
		add_action( 'init', array( __CLASS__, 'register_lazy_hooks' ), 5 );

		// Load modules on demand for admin pages
		add_action( 'current_screen', array( __CLASS__, 'maybe_load_admin_modules' ) );

		// Load modules when shortcodes are detected
		add_filter( 'the_content', array( __CLASS__, 'detect_and_load_shortcode_modules' ), 1 );
	}

	/**
	 * Register hooks that trigger module loading.
	 */
	public static function register_lazy_hooks(): void {
		foreach ( self::$module_definitions as $module_id => $definition ) {
			if ( ! isset( $definition['hooks'] ) ) {
						continue;
			}

			foreach ( $definition['hooks'] as $hook ) {
				if ( 'the_content' === $hook ) {
						add_filter(
							$hook,
							function ( $content ) use ( $module_id ) {
									self::load_module( $module_id );
									return $content;
							},
							1,
							1
						);
				} else {
						add_action(
							$hook,
							function () use ( $module_id ) {
									self::load_module( $module_id );
							},
							1,
							0
						);
				}
			}
		}
	}

	/**
	 * Load modules needed for admin pages.
	 */
	public static function maybe_load_admin_modules(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		global $pagenow;

		// Don't skip loading on post-new.php - we need metaboxes there
		// if ( 'post-new.php' === $pagenow ) {
		// 	return;
		// }

		foreach ( self::$module_definitions as $module_id => $definition ) {
			if ( isset( $definition['admin_pages'] ) &&
				in_array( $pagenow, $definition['admin_pages'], true ) ) {
				self::load_module( $module_id );
			}
		}
	}

	/**
	 * Detect shortcodes in content and load required modules.
	 *
	 * @param string $content The post content.
	 * @return string The unmodified content.
	 */
	public static function detect_and_load_shortcode_modules( string $content ): string {
		// Quick check to avoid processing if no shortcodes
		if ( false === strpos( $content, '[' ) ) {
			return $content;
		}

		foreach ( self::$module_definitions as $module_id => $definition ) {
			if ( isset( $definition['shortcodes'] ) ) {
				foreach ( $definition['shortcodes'] as $shortcode ) {
					if ( has_shortcode( $content, $shortcode ) ) {
						self::load_module( $module_id );
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Load a specific module.
	 *
	 * @param string $module_id The module identifier.
	 */
	public static function load_module( string $module_id ): void {
		// Check if already loaded
		if ( isset( self::$loaded_modules[ $module_id ] ) ) {
			return;
		}

		// Check if module is enabled in settings
		if ( ! self::is_module_enabled( $module_id ) ) {
			return;
		}

		$definition = self::$module_definitions[ $module_id ] ?? null;
		if ( ! $definition || ! isset( $definition['class'] ) ) {
			return;
		}

		// Load the module
		if ( class_exists( $definition['class'] ) ) {
			$registry = \NuclearEngagement\Core\Module\ModuleRegistry::getInstance();

			// Check if module is already registered
			$module_name = strtolower( $module_id );
			if ( $registry->hasModule( $module_name ) ) {
				// Module already registered, just mark as loaded
				self::$loaded_modules[ $module_id ] = true;
				return;
			}

			$module = new $definition['class']();
			$registry->register( $module );

			// Check if module is already initialized
			if ( ! $registry->isInitialized( $module_name ) ) {
				$registry->initializeModule( $module );
			}

			self::$loaded_modules[ $module_id ] = true;
		}
	}

	/**
	 * Check if a module is enabled in settings.
	 *
	 * @param string $module_id The module identifier.
	 * @return bool
	 */
	private static function is_module_enabled( string $module_id ): bool {
		$definition = self::$module_definitions[ $module_id ] ?? null;
		if ( ! $definition || ! isset( $definition['settings_key'] ) ) {
			return true; // Default to enabled if no setting
		}

		// Use cached settings check
		static $settings = null;
		if ( null === $settings ) {
			$settings = get_option( 'nuclear_engagement_settings', array() );
		}

		return ! isset( $settings[ $definition['settings_key'] ] ) ||
				! empty( $settings[ $definition['settings_key'] ] );
	}

	/**
	 * Force load all modules (for backwards compatibility).
	 */
	public static function load_all(): void {
		foreach ( array_keys( self::$module_definitions ) as $module_id ) {
			self::load_module( $module_id );
		}
	}
}
