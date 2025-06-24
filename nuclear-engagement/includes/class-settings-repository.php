<?php
/**
 * File: includes/SettingsRepository.php
 *
 * Centralized, type-safe settings repository for Nuclear Engagement plugin.
 *
 * @package NuclearEngagement
 * @subpackage Core
 * @since     1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement;

use NuclearEngagement\SettingsSanitizer;
use NuclearEngagement\SettingsCache;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A fluent, type-safe settings repository for the Nuclear Engagement plugin.
 *
 * This class provides a centralized way to manage plugin settings with type safety,
 * caching, and automatic sanitization.
 *
 * @since 1.0.0
 */
final class SettingsRepository {

	/**
	 * The option name used to store settings in the database.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION = 'nuclear_engagement_settings';

	/**
	 * Maximum size (in bytes) for settings to be autoloaded.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_AUTOLOAD_SIZE = 512000;


	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var SettingsRepository|null
	 */
	private static $instance = null;

	/**
	 * Default settings values.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $defaults = array();

	/**
	 * Pending changes not yet saved.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $pending = array();

	/**
	 * Cache handler.
	 *
	 * @since 1.0.0
	 * @var SettingsCache
	 */
	private SettingsCache $cache;


	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array $defaults Optional. Default settings to use if not already set.
	 * @return self The singleton instance.
	 */
	public static function get_instance( array $defaults = array() ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $defaults );
		}
		return self::$instance;
	}

	/**
	 * Private constructor - use get_instance() instead.
	 *
	 * @since 1.0.0
	 *
	 * @param array $defaults Optional. Default settings to use.
	 */
	private function __construct( array $defaults = array() ) {
		// Merge provided defaults with built-in defaults.
		$this->defaults = wp_parse_args( $defaults, Defaults::nuclen_get_default_settings() );
		$this->cache    = new SettingsCache();
		$this->cache->register_hooks();
	}


		/*
		===================================================================
		* GETTERS
		* ===================================================================
		*/

use SettingsGettersTrait;
use SettingsPersistenceTrait;
use SettingsCacheTrait;
use SettingsAccessTrait;
use PendingSettingsTrait;

        /**
         * Get the default values.
         *
         * @since 1.0.0
         *
         * @return array The default settings values.
         */
        public function get_defaults(): array {
                return $this->defaults;
        }



	/**
	 * Reset singleton instance (for testing).
	 *
	 * @since 1.0.0
	 */
	public static function reset_for_tests(): void {
			self::$instance = null;
		if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( SettingsCache::CACHE_GROUP );
		} else {
					wp_cache_flush();
		}
	}
}
