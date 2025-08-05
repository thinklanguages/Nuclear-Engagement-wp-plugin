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
	private static ?self $instance             = null;
	private bool $initialized                  = false;
	private array $lazy_services               = array();
	private static array $initialized_services = array();
	private static int $init_count             = 0;

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

		// Track initialization attempts
		++self::$init_count;

		try {
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

			// 7. CRITICAL: Load admin services selectively in admin context
			// Only load essential services immediately, defer heavy services
			if ( is_admin() ) {
				$this->loadMinimalAdminServices();
			}

			$this->initialized = true;

			// Clear any previous bootstrap errors if we got this far
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
	 * Check if we're in a minimal load context (like post-new.php).
	 *
	 * @return bool
	 */
	private function isMinimalLoadContext(): bool {
		global $pagenow;
		return 'post-new.php' === $pagenow;
	}

	/**
	 * Check if we're on the post-new.php page.
	 *
	 * @return bool
	 */
	private function isPostNewPage(): bool {
		global $pagenow;
		return 'post-new.php' === $pagenow;
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
		$autoloader_path = __DIR__ . '/Autoloader.php';

		if ( ! file_exists( $autoloader_path ) ) {
			throw new \RuntimeException(
				sprintf(
					'Autoloader not found at expected path: %s',
					$autoloader_path
				)
			);
		}

		// CRITICAL: This MUST be __DIR__ . '/Autoloader.php'
		// Do NOT use dirname(__DIR__) or any other path!
		require_once $autoloader_path;

		if ( ! class_exists( 'NuclearEngagement\Core\Autoloader' ) ) {
			throw new \RuntimeException( 'Autoloader class not found after including file' );
		}

		Autoloader::register();
	}

	private function registerLifecycleHooks(): void {
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

			// Schedule more frequent timeout checks (every 5 minutes instead of hourly)
			// This helps catch stuck tasks more quickly
			if ( ! wp_next_scheduled( 'nuclen_frequent_timeout_check' ) ) {
				wp_schedule_event( time() + 60, 'nuclen_five_minutes', 'nuclen_frequent_timeout_check' );
			}
		} catch ( \Throwable $e ) {
			// Log but don't block activation
			LoggingService::log( 'Activation error: ' . $e->getMessage() );
		}
	}

	public function onDeactivation(): void {
		// Clean up scheduled events.
		wp_clear_scheduled_hook( 'nuclen_theme_migration' );
		wp_clear_scheduled_hook( 'nuclen_cleanup_logs' );
		wp_clear_scheduled_hook( 'nuclen_frequent_timeout_check' );
		wp_clear_scheduled_hook( 'nuclen_check_task_timeouts' );
		wp_clear_scheduled_hook( 'nuclear_engagement_daily_generation' );
		wp_clear_scheduled_hook( 'nuclen_check_generation_status' );
		wp_clear_scheduled_hook( 'nuclen_cleanup_batch_transients' );
	}

	private function initializeEssentialServices(): void {
		// Load additional constants.
		$this->loadConstants();

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
		// The Plugin class is responsible for:
		// - Registering shortcodes via FrontClass (quiz, summary)
		// - Registering blocks via Blocks::register()
		// - Setting up all WordPress hooks
		// Without this, NO shortcodes or blocks will work!
		if ( class_exists( 'NuclearEngagement\Core\Plugin' ) ) {
			new \NuclearEngagement\Core\Plugin();
			// Mark that full plugin is loaded so we don't duplicate menu registration
			$this->lazy_services['plugin_loaded'] = true;
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
		add_action( 'wp_loaded', array( $this, 'maybeLoadFrontendServices' ) );
		add_action( 'rest_api_init', array( $this, 'maybeLoadApiServices' ) );

		// Initialize modules.
		add_action( 'init', array( $this, 'initializeModules' ), 5 );

		// Register admin page detection for conditional loading
		add_action( 'current_screen', array( $this, 'conditionallyLoadAdminServices' ) );

		// Register auto-generation hooks after admin services are loaded.
		add_action( 'init', array( $this, 'registerAutoGenerationHooks' ), 10 );

		// Initialize batch processing handler immediately during bootstrap
		$this->initializeBatchProcessing();

		// Initialize circuit breaker service
		add_action( 'init', array( $this, 'initializeCircuitBreaker' ), 5 );

		// Initialize task timeout handler
		add_action( 'init', array( $this, 'initializeTaskTimeoutHandler' ), 5 );

		// Register theme migration hook.
		add_action( 'nuclen_theme_migration', array( $this, 'runThemeMigration' ) );

		// Register cleanup hook.
		if ( ! wp_next_scheduled( 'nuclen_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'weekly', 'nuclen_cleanup_logs' );
		}

		// Register custom cron schedules
		add_filter( 'cron_schedules', array( $this, 'registerCronSchedules' ) );

		// Register frequent timeout check hook
		add_action( 'nuclen_frequent_timeout_check', array( $this, 'runFrequentTimeoutCheck' ) );
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
			// Don't register admin menu here - Plugin class already handles it

			// REMOVED: Block registration is now handled by Plugin class
			// to avoid duplicate registration. DO NOT add block registration here!

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

	public function registerAutoGenerationHooks(): void {
		// Check if already initialized
		if ( isset( self::$initialized_services['auto_generation'] ) ) {
			return;
		}

		// Auto-generation hooks need to work on all requests
		// including frontend, REST API, and AJAX requests

		$container = ServiceContainer::getInstance();
		$settings  = SettingsRepository::get_instance();

		// Ensure core services and container are registered
		if ( ! $container->has( 'settings' ) ) {
			$container->registerCoreServices();
			ContainerRegistrar::register( $container, $settings );
		}

		if ( $container->has( 'auto_generation_service' ) ) {
			// Mark as initialized BEFORE calling register_hooks to prevent any recursive calls
			self::$initialized_services['auto_generation'] = true;

			$auto_generation_service = $container->get( 'auto_generation_service' );
			$auto_generation_service->register_hooks();
		}
	}

	/**
	 * Initialize batch processing handler.
	 */
	public function initializeBatchProcessing(): void {
		// Initialize BatchProcessingHandler early to ensure hooks are registered
		// before any batch scheduling occurs. This prevents warning messages
		// about missing handlers.
		if ( class_exists( '\NuclearEngagement\Services\BatchProcessingHandler' ) ) {
			\NuclearEngagement\Services\BatchProcessingHandler::init();
		}
	}

	/**
	 * Initialize task timeout handler.
	 */
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

			// Run an immediate timeout check on initialization, but only once per request
			// and not during AJAX or cron requests to avoid excessive checks
			static $initial_check_done = false;
			if ( ! $initial_check_done && ! wp_doing_ajax() && ! wp_doing_cron() ) {
				$initial_check_done = true;
				try {
					LoggingService::log( '[PluginBootstrap] Running immediate timeout check on initialization' );
					$timeout_handler->check_timeouts();
				} catch ( \Throwable $e ) {
					LoggingService::log(
						sprintf( '[PluginBootstrap] Error running initial timeout check: %s', $e->getMessage() ),
						'error'
					);
				}
			}
		}
	}


	/**
	 * Initialize circuit breaker service.
	 */
	public function initializeCircuitBreaker(): void {
		// Initialize static circuit breaker service
		if ( class_exists( '\NuclearEngagement\Services\CircuitBreakerService' ) ) {
			\NuclearEngagement\Services\CircuitBreakerService::init();
		}
	}

	/**
	 * Initialize modular system.
	 */
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
		// if (class_exists('NuclearEngagement\Modules\Quiz\QuizModule') && !$registry->hasModule('quiz')) {.
		// $registry->register(new \NuclearEngagement\Modules\Quiz\QuizModule());.
		// }.

		// Register Summary module (would need to be created).
		// if (class_exists('NuclearEngagement\Modules\Summary\SummaryModule') && !$registry->hasModule('summary')) {.
		// $registry->register(new \NuclearEngagement\Modules\Summary\SummaryModule());.
		// }.

		// Initialize all modules.
		$registry->initializeAll();
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
				add_action(
					'admin_notices',
					function () {
						$error = get_option( 'nuclen_bootstrap_error' );
						if ( $error && current_user_can( 'manage_options' ) ) {
							echo '<div class="notice notice-warning is-dismissible">';
							echo '<p><strong>' . esc_html__( 'Nuclear Engagement is running with limited functionality', 'nuclear-engagement' ) . '</strong></p>';
							echo '<p>' . esc_html__( 'Some features may not be available. Please check the error logs for details.', 'nuclear-engagement' ) . '</p>';
							echo '</div>';
						}
					}
				);
			}

			// Register deactivation cleanup
			register_deactivation_hook(
				NUCLEN_PLUGIN_FILE,
				function () {
					delete_option( 'nuclen_bootstrap_error' );
					wp_clear_scheduled_hook( 'nuclen_theme_migration' );
					wp_clear_scheduled_hook( 'nuclen_cleanup_logs' );
				}
			);

		} catch ( \Throwable $e ) {
			// Even minimal functionality failed - just log it
			LoggingService::log( 'Failed to load minimal functionality: ' . $e->getMessage() );
		}
	}

	/**
	 * Load only minimal admin services on all admin pages.
	 * This prevents loading heavy services on irrelevant pages.
	 */
	private function loadMinimalAdminServices(): void {
		// Skip heavy initialization on post-new.php unless needed
		global $pagenow;
		if ( 'post-new.php' === $pagenow ) {
			// Don't register menu here - Plugin class handles it
			return;
		}

		// Don't register menu here - Plugin class handles it via AdminMenu trait

		// Load AJAX handlers (lightweight)
		$this->registerCriticalAjaxHooks();
	}

	/**
	 * Register admin menu without loading full admin services.
	 */
	public function registerAdminMenu(): void {
		// Don't register menu if Plugin class is already loaded
		if ( isset( $this->lazy_services['plugin_loaded'] ) && $this->lazy_services['plugin_loaded'] === true ) {
			return;
		}

		// Check if menu is already registered by Plugin class
		global $menu;
		$menu_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && $item[2] === 'nuclear-engagement' ) {
					$menu_exists = true;
					break;
				}
			}
		}

		// If menu already exists, don't register again
		if ( $menu_exists ) {
			return;
		}

		// Simple menu registration without heavy dependencies
		add_menu_page(
			__( 'Nuclear Engagement', 'nuclear-engagement' ),
			__( 'Nuclear Engagement', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement',
			array( $this, 'renderMinimalAdminPage' ),
			'dashicons-airplane',
			30
		);

		// Add submenus with callbacks
		add_submenu_page(
			'nuclear-engagement',
			__( 'Dashboard', 'nuclear-engagement' ),
			__( 'Dashboard', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement',
			array( $this, 'renderMinimalAdminPage' )
		);

		add_submenu_page(
			'nuclear-engagement',
			__( 'Generate', 'nuclear-engagement' ),
			__( 'Generate', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-generate',
			array( $this, 'renderMinimalGeneratePage' )
		);

		add_submenu_page(
			'nuclear-engagement',
			__( 'Settings', 'nuclear-engagement' ),
			__( 'Settings', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-settings',
			array( $this, 'renderMinimalSettingsPage' )
		);

		add_submenu_page(
			'nuclear-engagement',
			__( 'Setup', 'nuclear-engagement' ),
			__( 'Setup', 'nuclear-engagement' ),
			'manage_options',
			'nuclear-engagement-setup',
			array( $this, 'renderMinimalSetupPage' )
		);
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
			$this->loadService( 'admin' );
		}
	}

	/**
	 * Register only critical AJAX hooks.
	 */
	private function registerCriticalAjaxHooks(): void {
		// Only register absolutely essential AJAX hooks
		// Most AJAX hooks can be loaded when full admin services load
		add_action( 'wp_ajax_nuclen_dismiss_pointer', array( $this, 'handleDismissPointer' ) );
		add_action( 'wp_ajax_nuclen_load_editor_assets', array( $this, 'handleLoadEditorAssets' ) );
	}

	/**
	 * Minimal admin page callback.
	 */
	public function renderMinimalAdminPage(): void {
		// This will trigger full admin loading when the page is accessed
		if ( ! $this->isServiceLoaded( 'admin' ) ) {
			$this->loadService( 'admin' );
		}

		// Defer to actual admin class
		$container = ServiceContainer::getInstance();
		if ( $container->has( 'admin' ) ) {
			$admin = $container->get( 'admin' );
			if ( method_exists( $admin, 'nuclen_display_dashboard' ) ) {
				$admin->nuclen_display_dashboard();
				return;
			}
		}

		// Only show loading if we couldn't render the dashboard
		echo '<div class="wrap"><h1>' . esc_html__( 'Nuclear Engagement', 'nuclear-engagement' ) . '</h1><p>' . esc_html__( 'Loading...', 'nuclear-engagement' ) . '</p></div>';
	}

	/**
	 * Minimal generate page callback.
	 */
	public function renderMinimalGeneratePage(): void {
		// This will trigger full admin loading when the page is accessed
		if ( ! $this->isServiceLoaded( 'admin' ) ) {
			$this->loadService( 'admin' );
		}

		// Defer to actual admin class
		$container = ServiceContainer::getInstance();
		if ( $container->has( 'admin' ) ) {
			$admin = $container->get( 'admin' );
			if ( method_exists( $admin, 'nuclen_display_generate_page' ) ) {
				$admin->nuclen_display_generate_page();
				return;
			}
		}

		// Only show loading if we couldn't render the page
		echo '<div class="wrap"><h1>' . esc_html__( 'Generate', 'nuclear-engagement' ) . '</h1><p>' . esc_html__( 'Loading...', 'nuclear-engagement' ) . '</p></div>';
	}

	/**
	 * Minimal settings page callback.
	 */
	public function renderMinimalSettingsPage(): void {
		// This will trigger full admin loading when the page is accessed
		if ( ! $this->isServiceLoaded( 'admin' ) ) {
			$this->loadService( 'admin' );
		}

		// Defer to actual admin class
		$container     = ServiceContainer::getInstance();
		$settings_repo = SettingsRepository::get_instance( Defaults::nuclen_get_default_settings() );
		$settings      = new \NuclearEngagement\Admin\Settings( $settings_repo );
		$settings->nuclen_display_settings_page();
		return;
	}

	/**
	 * Minimal setup page callback.
	 */
	public function renderMinimalSetupPage(): void {
		// This will trigger full admin loading when the page is accessed
		if ( ! $this->isServiceLoaded( 'admin' ) ) {
			$this->loadService( 'admin' );
		}

		// Defer to actual admin class
		$container = ServiceContainer::getInstance();
		if ( $container->has( 'admin' ) ) {
			$admin = $container->get( 'admin' );
			if ( method_exists( $admin, 'nuclen_display_setup_page' ) ) {
				$admin->nuclen_display_setup_page();
				return;
			}
		}

		// Only show loading if we couldn't render the page
		echo '<div class="wrap"><h1>' . esc_html__( 'Setup', 'nuclear-engagement' ) . '</h1><p>' . esc_html__( 'Loading...', 'nuclear-engagement' ) . '</p></div>';
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
			$this->loadService( 'admin' );
		}

		wp_send_json_success();
	}

	/**
	 * Register custom cron schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function registerCronSchedules( array $schedules ): array {
		// Add 5 minute schedule for more frequent timeout checks
		$schedules['nuclen_five_minutes'] = array(
			'interval' => 300, // 5 minutes in seconds
			'display'  => __( 'Every Five Minutes', 'nuclear-engagement' ),
		);

		return $schedules;
	}

	/**
	 * Run frequent timeout check.
	 */
	public function runFrequentTimeoutCheck(): void {
		try {
			$container = ServiceContainer::getInstance();
			if ( $container->has( 'task_timeout_handler' ) ) {
				$timeout_handler = $container->get( 'task_timeout_handler' );
				$timeout_handler->check_timeouts();
			}
		} catch ( \Throwable $e ) {
			LoggingService::log(
				sprintf( '[PluginBootstrap] Error running frequent timeout check: %s', $e->getMessage() ),
				'error'
			);
		}
	}
}
