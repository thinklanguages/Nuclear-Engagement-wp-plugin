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
use NuclearEngagement\Core\Plugin;
use NuclearEngagement\Services\LoggingService;

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
	private BootstrapConstants $constants;
	private AutoloaderManager $autoloader;
	private LifecycleManager $lifecycle;
	private ServiceManager $services;
	private HookManager $hooks;
	private ModuleManager $modules;
	private AdminLoader $admin_loader;

	private function __construct() {
		$this->constants    = new BootstrapConstants();
		$this->autoloader   = new AutoloaderManager();
		$this->lifecycle    = new LifecycleManager();
		$this->services     = new ServiceManager();
		$this->hooks        = new HookManager();
		$this->modules      = new ModuleManager();
		$this->admin_loader = new AdminLoader();
	}

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

		try {
			// CRITICAL: DO NOT CHANGE THE ORDER OF THESE CALLS!
			// The initialization order is extremely important for the plugin to work correctly.

			// 1. Define essential constants first
			$this->constants->defineEssentialConstants();

			// 2. Register autoloader
			$this->autoloader->registerAutoloader();

			// 3. Register activation/deactivation hooks
			$this->lifecycle->registerLifecycleHooks();

			// 4. Initialize only essential services immediately
			$this->services->initializeEssentialServices();

			// 5. Register lazy loading for heavy services
			$this->services->registerLazyServices();

			// 6. Register WordPress hooks
			$this->hooks->registerWordPressHooks();

			// 7. CRITICAL: Load admin services selectively in admin context
			if ( is_admin() ) {
				$this->admin_loader->loadMinimalAdminServices();
			}

			$this->initialized = true;

			// Clear any previous bootstrap errors
			delete_option( 'nuclen_bootstrap_error' );

		} catch ( \Throwable $e ) {
			// Log the error but allow partial initialization
			LoggingService::log(
				sprintf(
					'Partial initialization error: %s in %s:%d',
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				)
			);

			// Try to continue with minimal functionality
			$this->loadMinimalFunctionality();
			$this->initialized = true;

			// Re-throw if in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				throw $e;
			}
		}
	}

	/**
	 * Load minimal functionality when full initialization fails
	 */
	private function loadMinimalFunctionality(): void {
		try {
			// Load core settings if possible
			if ( class_exists( 'NuclearEngagement\Core\SettingsRepository' ) ) {
				$settings = SettingsRepository::get_instance();
			}

			// Register minimal admin notice functionality
			if ( is_admin() ) {
				add_action( 'admin_notices', array( $this, 'showLimitedFunctionalityNotice' ) );
			}

			// Register deactivation cleanup
			register_deactivation_hook( NUCLEN_PLUGIN_FILE, array( $this->lifecycle, 'onDeactivation' ) );

		} catch ( \Throwable $e ) {
			// Even minimal functionality failed - just log it
			LoggingService::log( 'Failed to load minimal functionality: ' . $e->getMessage() );
		}
	}

	/**
	 * Show limited functionality notice in admin
	 */
	public function showLimitedFunctionalityNotice(): void {
		$error = get_option( 'nuclen_bootstrap_error' );
		if ( $error && current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>' . esc_html__( 'Nuclear Engagement is running with limited functionality', 'nuclear-engagement' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Some features may not be available. Please check the error logs for details.', 'nuclear-engagement' ) . '</p>';
			echo '</div>';
		}
	}

	// Delegate methods to appropriate managers
	public function onActivation(): void {
		$this->lifecycle->onActivation();
	}

	public function onDeactivation(): void {
		$this->lifecycle->onDeactivation();
	}

	public function registerAutoGenerationHooks(): void {
		$this->services->registerAutoGenerationHooks();
	}

	public function initializeBatchProcessing(): void {
		$this->services->initializeBatchProcessing();
	}

	public function initializeTaskTimeoutHandler(): void {
		$this->services->initializeTaskTimeoutHandler();
	}

	public function initializeCircuitBreaker(): void {
		$this->services->initializeCircuitBreaker();
	}

	public function initializeModules(): void {
		$this->modules->initializeModules();
	}

	public function maybeLoadFrontendServices(): void {
		$this->services->maybeLoadFrontendServices();
	}

	public function maybeLoadApiServices(): void {
		$this->services->maybeLoadApiServices();
	}

	public function runThemeMigration(): void {
		$this->services->runThemeMigration();
	}

	public function cleanupLogs(): void {
		$this->services->cleanupLogs();
	}

	public function conditionallyLoadAdminServices(): void {
		$this->admin_loader->conditionallyLoadAdminServices();
	}

	public function handleDismissPointer(): void {
		$this->admin_loader->handleDismissPointer();
	}

	public function handleLoadEditorAssets(): void {
		$this->admin_loader->handleLoadEditorAssets();
	}
}

/**
 * Manages bootstrap constants
 */
class BootstrapConstants {
	/**
	 * Define essential constants needed by the plugin.
	 *
	 * CRITICAL: This MUST be called FIRST before any class loading!
	 * Many classes depend on these constants, especially NUCLEN_PLUGIN_DIR.
	 *
	 * @throws \RuntimeException if NUCLEN_PLUGIN_FILE is not defined
	 */
	public function defineEssentialConstants(): void {
		// CRITICAL: NUCLEN_PLUGIN_FILE must be defined in nuclear-engagement.php
		if ( ! defined( 'NUCLEN_PLUGIN_FILE' ) ) {
			throw new \RuntimeException( 'NUCLEN_PLUGIN_FILE must be defined before bootstrapping' );
		}

		// Define NUCLEN_PLUGIN_DIR if not already defined.
		if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
			define( 'NUCLEN_PLUGIN_DIR', plugin_dir_path( NUCLEN_PLUGIN_FILE ) );
		}

		// Define NUCLEN_PLUGIN_URL if not already defined.
		if ( ! defined( 'NUCLEN_PLUGIN_URL' ) ) {
			define( 'NUCLEN_PLUGIN_URL', plugin_dir_url( NUCLEN_PLUGIN_FILE ) );
		}

		// Define NUCLEN_PLUGIN_VERSION if not already defined.
		if ( ! defined( 'NUCLEN_PLUGIN_VERSION' ) ) {
			$data = get_file_data(
				NUCLEN_PLUGIN_FILE,
				array( 'Version' => 'Version' ),
				'plugin'
			);
			define( 'NUCLEN_PLUGIN_VERSION', $data['Version'] ?? '1.0.0' );
		}
	}

	/**
	 * Load additional constants from file
	 */
	public function loadConstants(): void {
		$constants_file = dirname( __DIR__, 2 ) . '/inc/Core/constants.php';
		if ( file_exists( $constants_file ) ) {
			require_once $constants_file;
		}
	}
}

/**
 * Manages autoloader registration
 */
class AutoloaderManager {
	/**
	 * Register the plugin autoloader.
	 *
	 * CRITICAL: DO NOT CHANGE THE PATH!
	 * The Autoloader.php file is in the same directory as PluginBootstrap (inc/Core/)
	 */
	public function registerAutoloader(): void {
		$autoloader_path = __DIR__ . '/Autoloader.php';

		if ( ! file_exists( $autoloader_path ) ) {
			throw new \RuntimeException(
				sprintf( 'Autoloader not found at expected path: %s', $autoloader_path )
			);
		}

		require_once $autoloader_path;

		if ( ! class_exists( 'NuclearEngagement\Core\Autoloader' ) ) {
			throw new \RuntimeException( 'Autoloader class not found after including file' );
		}

		Autoloader::register();
	}
}

/**
 * Manages plugin lifecycle hooks
 */
class LifecycleManager {
	public function registerLifecycleHooks(): void {
		register_activation_hook( NUCLEN_PLUGIN_FILE, array( $this, 'onActivation' ) );
		register_deactivation_hook( NUCLEN_PLUGIN_FILE, array( $this, 'onDeactivation' ) );
	}

	public function onActivation(): void {
		try {
			// Only run essential setup on activation.
			if ( class_exists( '\NuclearEngagement\OptinData' ) &&
				! \NuclearEngagement\OptinData::table_exists() ) {
				\NuclearEngagement\OptinData::maybe_create_table();
			}

			// Schedule theme migration for next request.
			wp_schedule_single_event( time() + 30, 'nuclen_theme_migration' );

			// Clear any bootstrap errors from previous activation attempts
			delete_option( 'nuclen_bootstrap_error' );

		} catch ( \Throwable $e ) {
			// Log but don't block activation
			LoggingService::log( 'Activation error: ' . $e->getMessage() );
		}
	}

	public function onDeactivation(): void {
		// Clean up scheduled events.
		wp_clear_scheduled_hook( 'nuclen_theme_migration' );
		wp_clear_scheduled_hook( 'nuclen_cleanup_logs' );
		delete_option( 'nuclen_bootstrap_error' );
	}
}

/**
 * Manages service initialization
 */
class ServiceManager {
	private array $lazy_services               = array();
	private static array $initialized_services = array();

	public function initializeEssentialServices(): void {
		// Load additional constants.
		$constants = new BootstrapConstants();
		$constants->loadConstants();

		// Defer OptinData initialization to reduce overhead
		if ( ! $this->isMinimalLoadContext() ) {
			// Initialize OptinData hooks.
			if ( class_exists( 'NuclearEngagement\OptinData' ) ) {
				\NuclearEngagement\OptinData::init();
			}
		}

		// Use lazy module loading for better performance
		if ( class_exists( 'NuclearEngagement\Core\LazyModuleLoader' ) ) {
			\NuclearEngagement\Core\LazyModuleLoader::init();
		} elseif ( ! $this->isPostNewPage() && class_exists( 'NuclearEngagement\Core\ModuleLoader' ) ) {
			// Fallback to old module loader if lazy loader not available
			( new \NuclearEngagement\Core\ModuleLoader() )->load_all();
		}

		// Only initialize what's absolutely necessary.
		$defaults = Defaults::nuclen_get_default_settings();
		SettingsRepository::get_instance( $defaults );

		// CRITICAL: DO NOT REMOVE THIS! The Plugin class MUST be instantiated!
		if ( class_exists( 'NuclearEngagement\Core\Plugin' ) ) {
			new \NuclearEngagement\Core\Plugin();
			// Mark that full plugin is loaded
			$this->lazy_services['plugin_loaded'] = true;
		}
	}

	public function registerLazyServices(): void {
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
		if ( class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
			\NuclearEngagement\Services\LoggingService::cleanup_old_logs();
		}
	}

	public function registerAutoGenerationHooks(): void {
		// Check if already initialized
		if ( isset( self::$initialized_services['auto_generation'] ) ) {
			return;
		}

		$container = ServiceContainer::getInstance();
		$settings  = SettingsRepository::get_instance();

		// Ensure core services and container are registered
		if ( ! $container->has( 'settings' ) ) {
			$container->registerCoreServices();
			ContainerRegistrar::register( $container, $settings );
		}

		if ( $container->has( 'auto_generation_service' ) ) {
			// Mark as initialized BEFORE calling register_hooks
			self::$initialized_services['auto_generation'] = true;

			$auto_generation_service = $container->get( 'auto_generation_service' );
			$auto_generation_service->register_hooks();
		}
	}

	public function initializeBatchProcessing(): void {
		// Check if already initialized
		if ( isset( self::$initialized_services['batch_processing'] ) ) {
			return;
		}

		// Ensure BatchProcessingHandler is initialized
		if ( class_exists( '\NuclearEngagement\Services\BatchProcessingHandler' ) ) {
			// Mark as initialized BEFORE calling init
			self::$initialized_services['batch_processing'] = true;

			// Always initialize to ensure hooks are registered
			\NuclearEngagement\Services\BatchProcessingHandler::init();
		} else {
			\NuclearEngagement\Services\LoggingService::log(
				'ERROR: BatchProcessingHandler class not found during initialization',
				'error'
			);
		}
	}

	public function initializeTaskTimeoutHandler(): void {
		// Check if already initialized
		if ( isset( self::$initialized_services['task_timeout'] ) ) {
			return;
		}

		$container = ServiceContainer::getInstance();
		if ( $container->has( 'task_timeout_handler' ) ) {
			$timeout_handler = $container->get( 'task_timeout_handler' );
			$timeout_handler->register_hooks();
			self::$initialized_services['task_timeout'] = true;
		}
	}

	public function initializeCircuitBreaker(): void {
		// Initialize static circuit breaker service
		if ( class_exists( '\NuclearEngagement\Services\CircuitBreakerService' ) ) {
			\NuclearEngagement\Services\CircuitBreakerService::init();
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
		// Delegate to AdminLoader
		$admin_loader = new AdminLoader();
		$admin_loader->initializeAdminServices();
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

	private function isMinimalLoadContext(): bool {
		global $pagenow;
		return 'post-new.php' === $pagenow;
	}

	private function isPostNewPage(): bool {
		global $pagenow;
		return 'post-new.php' === $pagenow;
	}
}

/**
 * Manages WordPress hook registration
 */
class HookManager {
	public function registerWordPressHooks(): void {
		$bootstrap = PluginBootstrap::getInstance();

		// Register hooks for lazy loading services.
		add_action( 'wp_loaded', array( $bootstrap, 'maybeLoadFrontendServices' ) );
		add_action( 'rest_api_init', array( $bootstrap, 'maybeLoadApiServices' ) );

		// Initialize modules.
		add_action( 'init', array( $bootstrap, 'initializeModules' ), 5 );

		// Register admin page detection for conditional loading
		add_action( 'current_screen', array( $bootstrap, 'conditionallyLoadAdminServices' ) );

		// Register auto-generation hooks after admin services are loaded.
		add_action( 'init', array( $bootstrap, 'registerAutoGenerationHooks' ), 10 );

		// Initialize batch processing handler after plugins are loaded
		add_action( 'init', array( $bootstrap, 'initializeBatchProcessing' ), 5 );

		// Initialize circuit breaker service
		add_action( 'init', array( $bootstrap, 'initializeCircuitBreaker' ), 5 );

		// Initialize task timeout handler
		add_action( 'init', array( $bootstrap, 'initializeTaskTimeoutHandler' ), 5 );

		// Register theme migration hook.
		add_action( 'nuclen_theme_migration', array( $bootstrap, 'runThemeMigration' ) );

		// Register cleanup hook.
		if ( ! wp_next_scheduled( 'nuclen_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'weekly', 'nuclen_cleanup_logs' );
		}
		add_action( 'nuclen_cleanup_logs', array( $bootstrap, 'cleanupLogs' ) );
	}
}

/**
 * Manages module initialization
 */
class ModuleManager {
	public function initializeModules(): void {
		// Skip if lazy loading is enabled
		if ( class_exists( 'NuclearEngagement\Core\LazyModuleLoader' ) ) {
			// Lazy loader will handle module registration
			return;
		}

		// Register modules.
		$registry = \NuclearEngagement\Core\Module\ModuleRegistry::getInstance();

		// Register TOC module if not already registered.
		if ( class_exists( 'NuclearEngagement\Modules\TOC\TocModule' ) && ! $registry->hasModule( 'toc' ) ) {
			$registry->register( new \NuclearEngagement\Modules\TOC\TocModule() );
		}

		// Register Quiz module (would need to be created).
		// if (class_exists('NuclearEngagement\Modules\Quiz\QuizModule') && !$registry->hasModule('quiz')) {
		// $registry->register(new \NuclearEngagement\Modules\Quiz\QuizModule());
		// }

		// Register Summary module (would need to be created).
		// if (class_exists('NuclearEngagement\Modules\Summary\SummaryModule') && !$registry->hasModule('summary')) {
		// $registry->register(new \NuclearEngagement\Modules\Summary\SummaryModule());
		// }

		// Initialize all modules.
		$registry->initializeAll();
	}
}

/**
 * Manages admin loading
 */
class AdminLoader {
	private array $lazy_services = array();

	/**
	 * Load only minimal admin services on all admin pages.
	 */
	public function loadMinimalAdminServices(): void {
		// Skip heavy initialization on post-new.php unless needed
		global $pagenow;
		if ( 'post-new.php' === $pagenow ) {
			// Don't register menu here - Plugin class handles it
			return;
		}

		// Load AJAX handlers (lightweight)
		$this->registerCriticalAjaxHooks();
	}

	/**
	 * Register only critical AJAX hooks.
	 */
	private function registerCriticalAjaxHooks(): void {
		add_action( 'wp_ajax_nuclen_dismiss_pointer', array( $this, 'handleDismissPointer' ) );
		add_action( 'wp_ajax_nuclen_load_editor_assets', array( $this, 'handleLoadEditorAssets' ) );
	}

	/**
	 * Load full admin services only when on plugin pages.
	 */
	public function conditionallyLoadAdminServices(): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Check if we're on a plugin-related page
		$plugin_pages = array(
			'toplevel_page_nuclear-engagement',
			'nuclear-engagement_page_nuclear-engagement-generate',
			'nuclear-engagement_page_nuclear-engagement-settings',
			'nuclear-engagement_page_nuclear-engagement-setup',
			'post',
			'page',
		);

		$load_full_services = false;

		// Check if on plugin admin pages
		if ( in_array( $screen->id, $plugin_pages, true ) ) {
			$load_full_services = true;
		}

		// Check if on post editor for supported post types
		if ( in_array( $screen->base, array( 'post', 'edit' ), true ) ) {
			$allowed_post_types = $this->getAllowedPostTypes();

			if ( in_array( $screen->post_type, $allowed_post_types, true ) ) {
				$load_full_services = true;
			}
		}

		// Only load full services if needed
		if ( $load_full_services && ! $this->isServiceLoaded( 'admin' ) ) {
			$this->initializeAdminServices();
		}
	}

	public function initializeAdminServices(): void {
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

			// Register AJAX hooks for controllers.
			$this->registerAdminAjaxHooks( $container );

			// Register Setup hooks.
			$this->registerSetupHooks( $settings );

			// Register Onboarding.
			$this->registerOnboardingHooks();

			// Register Export hooks.
			$this->registerExportHooks( $container );
		}

		$this->lazy_services['admin'] = true;
	}

	private function registerAdminAjaxHooks( ServiceContainer $container ): void {
		$generate_controller    = $container->get( 'generate_controller' );
		$updates_controller     = $container->get( 'updates_controller' );
		$posts_count_controller = $container->get( 'posts_count_controller' );
		$pointer_controller     = $container->get( 'pointer_controller' );
		$stream_controller      = $container->get( 'stream_controller' );

		add_action( 'wp_ajax_nuclen_trigger_generation', array( $generate_controller, 'handle' ) );
		add_action( 'wp_ajax_nuclen_fetch_app_updates', array( $updates_controller, 'handle' ) );
		add_action( 'wp_ajax_nuclen_get_posts_count', array( $posts_count_controller, 'handle' ) );
		add_action( 'wp_ajax_nuclen_dismiss_pointer', array( $pointer_controller, 'dismiss' ) );
		add_action( 'wp_ajax_nuclen_stream_progress', array( $stream_controller, 'stream_progress' ) );
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

	/**
	 * Get allowed post types with caching.
	 * Centralized to avoid duplicate transient calls.
	 *
	 * @return array
	 */
	private function getAllowedPostTypes(): array {
		static $cached_types = null;

		if ( null !== $cached_types ) {
			return $cached_types;
		}

		$cached_types = get_transient( 'nuclear_engagement_allowed_post_types' );

		if ( false === $cached_types ) {
			$settings     = get_option( 'nuclear_engagement_settings', array() );
			$cached_types = isset( $settings['generation_post_types'] ) ?
				$settings['generation_post_types'] : array( 'post' );
			set_transient( 'nuclear_engagement_allowed_post_types', $cached_types, HOUR_IN_SECONDS );
		}

		return $cached_types;
	}

	public function handleDismissPointer(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'nuclen_admin_ajax_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$pointer_id = sanitize_text_field( $_POST['pointer_id'] ?? '' );
		if ( $pointer_id ) {
			$dismissed_pointers = get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true );
			$dismissed_pointers = $dismissed_pointers ? explode( ',', $dismissed_pointers ) : array();

			if ( ! in_array( $pointer_id, $dismissed_pointers, true ) ) {
				$dismissed_pointers[] = $pointer_id;
				update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', implode( ',', $dismissed_pointers ) );
			}
		}

		wp_send_json_success();
	}

	/**
	 * Handle deferred asset loading request.
	 */
	public function handleLoadEditorAssets(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'nuclen_load_assets' ) ) {
			wp_die( 'Security check failed' );
		}

		// Load admin services if not already loaded
		if ( ! $this->isServiceLoaded( 'admin' ) ) {
			$this->initializeAdminServices();
		}

		wp_send_json_success();
	}

	private function isServiceLoaded( string $service_name ): bool {
		return isset( $this->lazy_services[ $service_name ] ) &&
				$this->lazy_services[ $service_name ] === true;
	}
}
