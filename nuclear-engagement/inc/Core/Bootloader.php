<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\Defaults;
use NuclearEngagement\Core\Installer;
use NuclearEngagement\Core\MetaRegistration;
use NuclearEngagement\Core\AssetVersions;
use NuclearEngagement\Core\Plugin;
use NuclearEngagement\Core\InventoryCache;
use NuclearEngagement\Core\Autoloader;
use NuclearEngagement\Services\PostsQueryService;

/**
 * Bootstraps the plugin.
 */
final class Bootloader {
	/**
	 * Initialize plugin loading.
	 */
	public static function init(): void {
		self::define_constants();
		self::register_autoloaders();
		self::load_helpers();
		self::register_hooks();
	}

	/**
	 * Define core constants.
	 */
	private static function define_constants(): void {
		if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
			define( 'NUCLEN_PLUGIN_DIR', plugin_dir_path( NUCLEN_PLUGIN_FILE ) );
		}

		if ( ! defined( 'NUCLEN_PLUGIN_VERSION' ) ) {
			if ( ! function_exists( 'get_file_data' ) ) {
				require_once ABSPATH . 'wp-includes/functions.php';
			}
			$data = get_file_data(
				NUCLEN_PLUGIN_FILE,
				array( 'Version' => 'Version' ),
				'plugin'
			);
			define( 'NUCLEN_PLUGIN_VERSION', $data['Version'] );
		}

		if ( ! defined( 'NUCLEN_ASSET_VERSION' ) ) {
			define( 'NUCLEN_ASSET_VERSION', '250627-1' );
		}
	}

	/**
	 * Register Composer and fallback autoloaders.
	 */
	private static function register_autoloaders(): void {
		$autoload = __DIR__ . '/../../vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
		}
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		} else {
			$logging = __DIR__ . '/../Services/LoggingService.php';
			if ( file_exists( $logging ) ) {
				require_once $logging;
			}
			\NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: vendor autoload not found.' );
			\NuclearEngagement\Services\LoggingService::notify_admin( 'Nuclear Engagement dependencies missing. Please run composer install.' );
			return;
		}

	Autoloader::register();

		if ( ! class_exists( AssetVersions::class ) ) {
			$asset_versions_path = NUCLEN_PLUGIN_DIR . 'inc/Core/AssetVersions.php';
			if ( file_exists( $asset_versions_path ) ) {
				require_once $asset_versions_path;
			}
		}
	}

	/**
	 * Load helper files and constants.
	 */
	private static function load_helpers(): void {
	// SettingsFunctions is autoloaded; legacy wrappers removed.

		if ( file_exists( NUCLEN_PLUGIN_DIR . 'inc/Core/constants.php' ) ) {
			require_once NUCLEN_PLUGIN_DIR . 'inc/Core/constants.php';
		} else {
			\NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: constants.php missing.' );
		}

		AssetVersions::init();
	}

	/**
	 * Register WordPress hooks.
	 */
	private static function register_hooks(): void {
		add_action( 'init', 'nuclear_engagement_load_textdomain' );

		add_action(
			'init',
			static function () {
				$defaults = Defaults::nuclen_get_default_settings();
				SettingsRepository::get_instance( $defaults );
			},
			20
		);

		$installer = new Installer();
		\register_activation_hook( NUCLEN_PLUGIN_FILE, array( $installer, 'activate' ) );
		\register_deactivation_hook( NUCLEN_PLUGIN_FILE, array( $installer, 'deactivate' ) );

		add_action( 'admin_init', 'nuclear_engagement_redirect_on_activation' );
		add_action( 'admin_init', array( $installer, 'migrate_post_meta' ), 20 );

		add_action( 'plugins_loaded', 'nuclear_engagement_init' );
	}
}
