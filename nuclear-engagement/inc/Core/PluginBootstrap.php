<?php
/**
 * PluginBootstrap.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Core\ContainerRegistrar;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\Defaults;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Efficient plugin bootstrap with lazy loading.
 *
 * CRITICAL REQUIREMENTS FOR THIS CLASS TO WORK:
 *
 * 1. NUCLEN_PLUGIN_FILE constant MUST be defined before instantiation
 * 2. Autoloader.php MUST be in the same directory as this file (inc/Core/)
 * 3. The init() method MUST follow the exact initialization order:
 *    a) Define constants (defineEssentialConstants)
 *    b) Register autoloader (registerAutoloader)
 *    c) Load admin services immediately if is_admin()
 * 4. Admin services MUST be loaded BEFORE WordPress 'admin_menu' hook fires
 *
 * DO NOT MODIFY THIS CLASS WITHOUT UNDERSTANDING THE ABOVE REQUIREMENTS!
 * Changing the initialization order or paths will break the admin menu.
 *
 * @package NuclearEngagement\Core
 */
final class PluginBootstrap {
	private static ?self $instance = null;
	private bool $initialized      = false;
	private array $lazy_services   = array();

	private function __construct() {}

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		// CRITICAL: DO NOT CHANGE THE ORDER OF THESE CALLS!
		// The initialization order is extremely important for the plugin to work correctly.

		// 1. Define essential constants first - MUST be before any class loading
		// This defines NUCLEN_PLUGIN_DIR, NUCLEN_PLUGIN_URL, NUCLEN_PLUGIN_VERSION.
		$this->defineEssentialConstants();

		// 2. Register autoloader - MUST be after constants are defined
		// The autoloader uses NUCLEN_PLUGIN_DIR constant.
		$this->registerAutoloader();

		// 3. Register activation/deactivation hooks
		$this->registerLifecycleHooks();

		// 4. Initialize only essential services immediately
		$this->initializeEssentialServices();

		// 5. Register lazy loading for heavy services
		$this->registerLazyServices();

		// 6. Register WordPress hooks
		$this->registerWordPressHooks();

		// 7. CRITICAL: Load admin services immediately if we're in admin context
		// This MUST happen before WordPress fires the 'admin_menu' hook.
		// Otherwise, admin menu items will not appear!
		if ( is_admin() ) {
			$this->loadService( 'admin' );
		}

		$this->initialized = true;
	}

	/**
	 * Register the plugin autoloader.
	 *
	 * CRITICAL: DO NOT CHANGE THE PATH!
	 * The Autoloader.php file is in the same directory as this file (inc/Core/)
	 * Using __DIR__ ensures we get the correct absolute path regardless of where
	 * the plugin is installed.
	 */
	private function registerAutoloader(): void {
		// CRITICAL: This MUST be __DIR__ . '/Autoloader.php'
		// Do NOT use dirname(__DIR__) or any other path!
		require_once __DIR__ . '/Autoloader.php';
		Autoloader::register();
	}

	private function registerLifecycleHooks(): void {
		register_activation_hook( NUCLEN_PLUGIN_FILE, array( $this, 'onActivation' ) );
		register_deactivation_hook( NUCLEN_PLUGIN_FILE, array( $this, 'onDeactivation' ) );
	}

	public function onActivation(): void {
		// Only run essential setup on activation.
		if ( ! \NuclearEngagement\OptinData::table_exists() ) {
			\NuclearEngagement\OptinData::maybe_create_table();
		}

		// Schedule theme migration for next request.
		wp_schedule_single_event( time() + 30, 'nuclen_theme_migration' );
	}

	public function onDeactivation(): void {
		// Clean up scheduled events.
		wp_clear_scheduled_hook( 'nuclen_theme_migration' );
		wp_clear_scheduled_hook( 'nuclen_cleanup_logs' );
	}

	private function initializeEssentialServices(): void {
		// Load additional constants.
		$this->loadConstants();

		// Initialize OptinData hooks.
		if ( class_exists( 'NuclearEngagement\OptinData' ) ) {
			\NuclearEngagement\OptinData::init();
		}

		// Load all modules.
		if ( class_exists( 'NuclearEngagement\Core\ModuleLoader' ) ) {
			( new \NuclearEngagement\Core\ModuleLoader() )->load_all();
		}

		// Only initialize what's absolutely necessary.
		$defaults = Defaults::nuclen_get_default_settings();
		SettingsRepository::get_instance( $defaults );

		// Initialize error handling.
		if ( class_exists( 'NuclearEngagement\Core\Error\ErrorContext' ) ) {
			// New error system is available.
		}
	}

	/**
	 * Define essential constants needed by the plugin.
	 *
	 * CRITICAL: This MUST be called FIRST before any class loading!
	 * Many classes depend on these constants, especially NUCLEN_PLUGIN_DIR.
	 *
	 * @throws \RuntimeException if NUCLEN_PLUGIN_FILE is not defined
	 */
	private function defineEssentialConstants(): void {
		// CRITICAL: NUCLEN_PLUGIN_FILE must be defined in nuclear-engagement.php
		// This is the absolute path to the main plugin file.
		if ( ! defined( 'NUCLEN_PLUGIN_FILE' ) ) {
			throw new \RuntimeException( 'NUCLEN_PLUGIN_FILE must be defined before bootstrapping' );
		}

		// Define NUCLEN_PLUGIN_DIR if not already defined.
		// This is used by the Autoloader and many other classes.
		if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
			define( 'NUCLEN_PLUGIN_DIR', plugin_dir_path( NUCLEN_PLUGIN_FILE ) );
		}

		// Define NUCLEN_PLUGIN_URL if not already defined.
		// This is used for enqueuing assets.
		if ( ! defined( 'NUCLEN_PLUGIN_URL' ) ) {
			define( 'NUCLEN_PLUGIN_URL', plugin_dir_url( NUCLEN_PLUGIN_FILE ) );
		}

		// Define NUCLEN_PLUGIN_VERSION if not already defined.
		// This is used for cache busting and version checks.
		if ( ! defined( 'NUCLEN_PLUGIN_VERSION' ) ) {
			$data = get_file_data(
				NUCLEN_PLUGIN_FILE,
				array( 'Version' => 'Version' ),
				'plugin'
			);
			define( 'NUCLEN_PLUGIN_VERSION', $data['Version'] ?? '1.0.0' );
		}
	}

	private function loadConstants(): void {
		$constants_file = dirname( __DIR__, 2 ) . '/inc/Core/constants.php';
		if ( file_exists( $constants_file ) ) {
			require_once $constants_file;
		}
	}

	private function registerLazyServices(): void {
		// Register services to be loaded only when needed.
		$this->lazy_services = array(
			'admin'    => function () {
				return $this->initializeAdminServices();
			},
			'frontend' => function () {
				return $this->initializeFrontendServices();
			},
			'api'      => function () {
				return $this->initializeApiServices();
			},
		);
	}

	private function registerWordPressHooks(): void {
		// Register hooks for lazy loading services.
		// Note: Admin services are loaded immediately in init() if is_admin() is true.
		// This ensures admin menu hooks are registered before WordPress fires them.
		add_action( 'wp_loaded', array( $this, 'maybeLoadFrontendServices' ) );
		add_action( 'rest_api_init', array( $this, 'maybeLoadApiServices' ) );

		// Initialize modules.
		add_action( 'init', array( $this, 'initializeModules' ), 5 );

		// Register auto-generation hooks after admin services are loaded.
		add_action( 'init', array( $this, 'registerAutoGenerationHooks' ), 10 );

		// Register theme migration hook.
		add_action( 'nuclen_theme_migration', array( $this, 'runThemeMigration' ) );

		// Register cleanup hook.
		if ( ! wp_next_scheduled( 'nuclen_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'weekly', 'nuclen_cleanup_logs' );
		}
		add_action( 'nuclen_cleanup_logs', array( $this, 'cleanupLogs' ) );
	}


	public function maybeLoadFrontendServices(): void {
		if ( ! is_admin() && ! $this->isServiceLoaded( 'frontend' ) ) {
			$this->loadService( 'frontend' );
		}
	}

	public function maybeLoadApiServices(): void {
		if ( ! $this->isServiceLoaded( 'api' ) ) {
			$this->loadService( 'api' );
		}
	}

	public function runThemeMigration(): void {
		if ( class_exists( 'NuclearEngagement\Services\ThemeMigrationService' ) ) {
			$migration_service = new \NuclearEngagement\Services\ThemeMigrationService();
			$migration_service->migrate_legacy_settings();
		}
	}

	public function cleanupLogs(): void {
		// Clean up old log entries.
		if ( class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
			\NuclearEngagement\Services\LoggingService::cleanup_old_logs();
		}
	}

	private function loadService( string $service_name ): void {
		if ( isset( $this->lazy_services[ $service_name ] ) ) {
			$this->lazy_services[ $service_name ]();
			$this->lazy_services[ $service_name ] = true; // Mark as loaded.
		}
	}

	private function isServiceLoaded( string $service_name ): bool {
		return isset( $this->lazy_services[ $service_name ] ) &&
				$this->lazy_services[ $service_name ] === true;
	}

	private function initializeAdminServices(): void {
		// Load admin-specific services.
		$container = ServiceContainer::getInstance();
		$settings  = SettingsRepository::get_instance();

		// Register core services and containers first.
		$container->registerCoreServices();
		ContainerRegistrar::register( $container, $settings );

		// Initialize admin controllers and services.
		if ( class_exists( 'NuclearEngagement\Admin\Admin' ) ) {
			$plugin_version = defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0';
			$admin          = new \NuclearEngagement\Admin\Admin( 'nuclear-engagement', $plugin_version, $settings, $container );

			// Register admin hooks.
			add_action( 'init', array( $admin, 'nuclen_register_admin_scripts' ), 9 );
			add_action( 'admin_enqueue_scripts', array( $admin, 'wp_enqueue_styles' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'wp_enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'nuclen_enqueue_dashboard_styles' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'nuclen_enqueue_generate_page_scripts' ) );
			add_action( 'admin_menu', array( $admin, 'nuclen_add_admin_menu' ) );

			// Register AJAX hooks for controllers.
			$this->registerAdminAjaxHooks( $container );

			// Register Setup hooks.
			$this->registerSetupHooks( $settings );

			// Register Onboarding.
			$this->registerOnboardingHooks();

			// Register Export hooks.
			$this->registerExportHooks( $container );
		}
	}

	private function registerAdminAjaxHooks( ServiceContainer $container ): void {
		$generate_controller    = $container->get( 'generate_controller' );
		$updates_controller     = $container->get( 'updates_controller' );
		$posts_count_controller = $container->get( 'posts_count_controller' );
		$pointer_controller     = $container->get( 'pointer_controller' );

		add_action( 'wp_ajax_nuclen_trigger_generation', array( $generate_controller, 'handle' ) );
		add_action( 'wp_ajax_nuclen_fetch_app_updates', array( $updates_controller, 'handle' ) );
		add_action( 'wp_ajax_nuclen_get_posts_count', array( $posts_count_controller, 'handle' ) );
		add_action( 'wp_ajax_nuclen_dismiss_pointer', array( $pointer_controller, 'dismiss' ) );
	}

	private function registerSetupHooks( SettingsRepository $settings ): void {
		$setup = new \NuclearEngagement\Admin\Setup( $settings );
		add_action( 'admin_post_nuclen_connect_app', array( $setup, 'nuclen_handle_connect_app' ) );
		add_action( 'admin_post_nuclen_generate_app_password', array( $setup, 'nuclen_handle_generate_app_password' ) );
		add_action( 'admin_post_nuclen_reset_api_key', array( $setup, 'nuclen_handle_reset_api_key' ) );
		add_action( 'admin_post_nuclen_reset_wp_app_connection', array( $setup, 'nuclen_handle_reset_wp_app_connection' ) );
	}

	private function registerOnboardingHooks(): void {
		$onboarding = new \NuclearEngagement\Admin\Onboarding();
		$onboarding->nuclen_register_hooks();
	}

	private function registerExportHooks( ServiceContainer $container ): void {
		$optin_export_controller = $container->get( 'optin_export_controller' );
		add_action( 'admin_post_nuclen_export_optin', array( $optin_export_controller, 'handle' ) );
		add_action( 'wp_ajax_nuclen_export_optin', array( $optin_export_controller, 'handle' ) );
	}

	private function initializeFrontendServices(): void {
		// Load frontend-specific services.
		$container = ServiceContainer::getInstance();
		$settings  = SettingsRepository::get_instance();

		// Ensure container is properly initialized.
		if ( ! $container->has( 'settings' ) ) {
			$container->registerCoreServices();
			ContainerRegistrar::register( $container, $settings );
		}

		if ( class_exists( 'NuclearEngagement\Front\FrontClass' ) ) {
			$plugin_version = defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0';
			$frontend       = new \NuclearEngagement\Front\FrontClass( 'nuclear-engagement', $plugin_version, $settings, $container );

			// Register frontend hooks.
			add_action( 'wp_enqueue_scripts', array( $frontend, 'wp_enqueue_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $frontend, 'wp_enqueue_scripts' ) );
			add_action( 'wp_head', array( $frontend, 'wp_head_custom_theme_vars' ), 99 );
			add_action( 'init', array( $frontend, 'nuclen_register_quiz_shortcode' ) );
			add_action( 'init', array( $frontend, 'nuclen_register_summary_shortcode' ) );
			add_filter( 'the_content', array( $frontend, 'nuclen_auto_insert_shortcodes' ), 50 );
			add_action( 'init', '\\NuclearEngagement\\Core\\Blocks::register' );
		}
	}

	private function initializeApiServices(): void {
		// Load API-specific services.
		$container = ServiceContainer::getInstance();
		$settings  = SettingsRepository::get_instance();

		// Ensure container is properly initialized.
		if ( ! $container->has( 'settings' ) ) {
			$container->registerCoreServices();
			ContainerRegistrar::register( $container, $settings );
		}

		// Register REST routes.
		if ( class_exists( 'NuclearEngagement\Front\Controller\Rest\ContentController' ) ) {
			$content_controller = $container->get( 'content_controller' );
			register_rest_route(
				'nuclear-engagement/v1',
				'/receive-content',
				array(
					'methods'             => 'POST',
					'callback'            => array( $content_controller, 'handle' ),
					'permission_callback' => array( $content_controller, 'permissions' ),
				)
			);
		}
	}

	public function registerAutoGenerationHooks(): void {
		if ( is_admin() ) {
			$container = ServiceContainer::getInstance();
			if ( $container->has( 'auto_generation_service' ) ) {
				$auto_generation_service = $container->get( 'auto_generation_service' );
				$auto_generation_service->register_hooks();
			}
		}
	}

	/**
	 * Initialize modular system.
	 */
	public function initializeModules(): void {
		// Register modules.
		$registry = \NuclearEngagement\Core\Module\ModuleRegistry::getInstance();

		// Register TOC module.
		if ( class_exists( 'NuclearEngagement\Modules\TOC\TocModule' ) ) {
			$registry->register( new \NuclearEngagement\Modules\TOC\TocModule() );
		}

		// Register Quiz module (would need to be created).
		// if (class_exists('NuclearEngagement\Modules\Quiz\QuizModule')) {.
		// $registry->register(new \NuclearEngagement\Modules\Quiz\QuizModule());.
		// }.

		// Register Summary module (would need to be created).
		// if (class_exists('NuclearEngagement\Modules\Summary\SummaryModule')) {.
		// $registry->register(new \NuclearEngagement\Modules\Summary\SummaryModule());.
		// }.

		// Initialize all modules.
		$registry->initializeAll();
	}
}
