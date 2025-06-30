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
use NuclearEngagement\Services\PostDataFetcher;
use NuclearEngagement\Services\LoggingService;

/**
 * Simplified bootstraps the plugin using dependency injection.
 * 
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class Bootloader {
	/**
	 * Service container instance.
	 *
	 * @var ServiceContainer
	 */
	private static ?ServiceContainer $container = null;
	
	/**
	 * Plugin initialization status.
	 *
	 * @var bool
	 */
	private static bool $plugin_initialized = false;
	
	/**
	 * Track initialization status for various components.
	 *
	 * @var array<string, bool>
	 */
	private static array $initialized = [];
	
	/**
	 * Store singleton instances.
	 *
	 * @var array<string, object>
	 */
	private static array $instances = [];
	
	/**
	 * Get the service container
	 *
	 * @return ServiceContainer
	 */
	public static function getContainer(): ServiceContainer {
		if ( self::$container === null ) {
			self::$container = ServiceContainer::getInstance();
			self::$container->registerCoreServices();
		}
		return self::$container;
	}
	
	/**
	 * Initialize plugin loading.
	 */
	public static function init(): void {
		if ( self::$plugin_initialized ) {
			return;
		}
		
		try {
			self::define_constants();
			self::register_autoloaders();
			self::load_helpers();
			self::getContainer()->initializeCoreServices();
			self::register_hooks();
			self::$plugin_initialized = true;
		} catch ( \Throwable $e ) {
			self::handle_initialization_error( $e );
		}
	}
	
	/**
	 * Handle initialization errors.
	 *
	 * @param \Throwable $e The exception that occurred.
	 */
	private static function handle_initialization_error( \Throwable $e ): void {
		// Log the error
		if ( class_exists( LoggingService::class ) ) {
			LoggingService::log( 'Nuclear Engagement: Initialization failed - ' . $e->getMessage() );
		}
		
		
	}

	/**
	 * Define core constants.
	 */
	private static function define_constants(): void {
		if ( ! defined( 'NUCLEN_PLUGIN_FILE' ) ) {
			throw new \RuntimeException( 'NUCLEN_PLUGIN_FILE constant must be defined before initializing Bootloader.' );
		}
		
		if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
			define( 'NUCLEN_PLUGIN_DIR', plugin_dir_path( NUCLEN_PLUGIN_FILE ) );
		}

		if ( ! defined( 'NUCLEN_PLUGIN_URL' ) ) {
			define( 'NUCLEN_PLUGIN_URL', plugins_url( '/', NUCLEN_PLUGIN_FILE ) );
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
			define( 'NUCLEN_PLUGIN_VERSION', $data['Version'] ?? '1.0.0' );
		}

		if ( ! defined( 'NUCLEN_ASSET_VERSION' ) ) {
			define( 'NUCLEN_ASSET_VERSION', NUCLEN_PLUGIN_VERSION );
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
	// Ensure the plugin autoloader is available even without Composer.
	require_once __DIR__ . '/Autoloader.php';
	Autoloader::register();
	
	$logging = __DIR__ . '/../Services/LoggingService.php';
	if ( file_exists( $logging ) ) {
	require_once $logging;
	LoggingService::log( 'Nuclear Engagement: vendor autoload not found.' );
	LoggingService::notify_admin( 'Nuclear Engagement dependencies missing. Please run composer install.' );
	}
	
	return;
	}
	
	// Composer autoloader loaded – now register the plugin autoloader.
	Autoloader::register();
	}
	
	/**
	 * Load helper files and constants.
	 */
	private static function load_helpers(): void {
		if ( isset( self::$initialized['helpers'] ) ) {
			return;
		}
		
		try {
			self::load_constants();
			
			if ( class_exists( AssetVersions::class ) ) {
				AssetVersions::init();
			}
			
			self::$initialized['helpers'] = true;
		} catch ( \Throwable $e ) {
			LoggingService::log( 'Nuclear Engagement: Failed to load helpers - ' . $e->getMessage() );
		}
	}
	
	/**
	 * Load plugin constants file.
	 */
	private static function load_constants(): void {
		$constants_file = NUCLEN_PLUGIN_DIR . 'inc/Core/constants.php';
		
		if ( file_exists( $constants_file ) ) {
			require_once $constants_file;
		} else {
			LoggingService::log( 'Nuclear Engagement: constants.php missing.' );
		}
	}

	/**
	 * Register WordPress hooks.
	 */
	private static function register_hooks(): void {
		self::register_init_hooks();
		self::register_activation_hooks();
		self::register_admin_hooks();
		
		add_action( 'plugins_loaded', array( self::class, 'init_plugin' ) );
	}
	
	/**
	 * Register init action hooks.
	 */
	private static function register_init_hooks(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ) );
		add_action( 'init', array( self::class, 'initialize_settings' ), 20 );
	}
	
	/**
	 * Register activation/deactivation hooks.
	 */
	private static function register_activation_hooks(): void {
		if ( isset( self::$initialized['activation_hooks'] ) ) {
			return;
		}
		
		$installer = self::get_installer();
		\register_activation_hook( NUCLEN_PLUGIN_FILE, array( $installer, 'activate' ) );
		\register_deactivation_hook( NUCLEN_PLUGIN_FILE, array( $installer, 'deactivate' ) );
		
		self::$initialized['activation_hooks'] = true;
	}
	
	/**
	 * Register admin-specific hooks.
	 */
	private static function register_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		
		$installer = self::get_installer();
		add_action( 'admin_init', array( self::class, 'redirect_on_activation' ), 5 );
		add_action( 'admin_init', array( $installer, 'migrate_post_meta' ), 20 );
	}
	
	/**
	 * Get singleton instance of Installer.
	 *
	 * @return Installer
	 */
	private static function get_installer(): Installer {
		if ( ! isset( self::$instances['installer'] ) ) {
			self::$instances['installer'] = new Installer();
		}
		return self::$instances['installer'];
	}
	
	/**
	 * Initialize plugin settings.
	 */
	public static function initialize_settings(): void {
		if ( isset( self::$initialized['settings'] ) ) {
			return;
		}
		
		try {
			$defaults = Defaults::nuclen_get_default_settings();
			$settings = SettingsRepository::get_instance( $defaults );
			self::$initialized['settings'] = true;
		} catch ( \Throwable $e ) {
			LoggingService::log( 'Nuclear Engagement: Settings initialization failed - ' . $e->getMessage() );
		}
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'nuclear-engagement',
			false,
			dirname( plugin_basename( NUCLEN_PLUGIN_FILE ) ) . '/languages/'
		);
	}
	
	/**
	 * Redirect to the setup screen on plugin activation.
	 */
	public static function redirect_on_activation(): void {
		if ( get_transient( 'nuclen_plugin_activation_redirect' ) ) {
			delete_transient( 'nuclen_plugin_activation_redirect' );
			if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=nuclear-engagement-setup' ) );
				exit;
			}
		}
	}
	
	/**
	 * Initialize and execute the core plugin logic.
	 */
	public static function run_plugin(): void {
		if ( isset( self::$initialized['plugin'] ) ) {
			return;
		}
		
		try {
			MetaRegistration::init();
			
			if ( ! isset( self::$instances['plugin'] ) ) {
				self::$instances['plugin'] = new Plugin();
			}
			
			self::$instances['plugin']->nuclen_run();
			self::$initialized['plugin'] = true;
		} catch ( \Throwable $e ) {
			LoggingService::log( 'Nuclear Engagement: Plugin run failed - ' . $e->getMessage() );
		}
	}
	
	/**
	 * Register services and bootstrap the plugin.
	 */
	public static function init_plugin(): void {
		try {
			self::register_services();
			self::run_plugin();
		} catch ( \Throwable $e ) {
			LoggingService::log( 'Nuclear Engagement: Plugin initialization failed - ' . $e->getMessage() );
		}
	}
	
	/**
	 * Register all plugin services.
	 */
	private static function register_services(): void {
		if ( isset( self::$initialized['services'] ) ) {
			return;
		}
		
		try {
			if ( class_exists( InventoryCache::class ) ) {
				InventoryCache::register_hooks();
			}
			if ( class_exists( PostsQueryService::class ) ) {
				PostsQueryService::register_hooks();
			}
			if ( class_exists( PostDataFetcher::class ) ) {
				PostDataFetcher::register_hooks();
			}
			
			self::$initialized['services'] = true;
		} catch ( \Throwable $e ) {
			LoggingService::log( 'Nuclear Engagement: Service registration failed - ' . $e->getMessage() );
			
		}
	}
}