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
 * Bootstraps the plugin.
 * 
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class Bootloader {
	/**
	 * Initialization state tracking.
	 *
	 * @var array<string, bool>
	 */
	private static array $initialized = [];
	
	/**
	 * Cached service instances.
	 *
	 * @var array<string, object>
	 */
	private static array $instances = [];
	
	/**
	 * Plugin initialization status.
	 *
	 * @var bool
	 */
	private static bool $plugin_initialized = false;
	/**
	 * Initialize plugin loading.
	 */
	public static function init(): void {
		if ( self::$plugin_initialized ) {
			return;
		}
		
		// Initialize core systems first
		ErrorRecovery::init();
		ErrorManager::init();
		SecurityErrorHandler::init();
		UserErrorManager::init();
		PerformanceMonitor::init();
		CacheManager::init();
		QueryOptimizer::init();
		LazyLoader::init();
		BackgroundProcessor::init();
		
		PerformanceMonitor::start( 'bootloader_init' );
		
		try {
			self::define_constants();
			self::register_autoloaders();
			self::setup_container();
			self::load_helpers();
			self::register_hooks();
			self::$plugin_initialized = true;
		} catch ( \Throwable $e ) {
			self::handle_initialization_error( $e );
		} finally {
			PerformanceMonitor::stop( 'bootloader_init' );
		}
	}
	
	/**
	 * Handle initialization errors with comprehensive error management.
	 *
	 * @param \Throwable $e The exception that occurred.
	 */
	private static function handle_initialization_error( \Throwable $e ): void {
		// Use enhanced error management if available
		if ( class_exists( ErrorManager::class ) ) {
			$error_context = ErrorManager::handle_error(
				$e,
				ErrorManager::SEVERITY_CRITICAL,
				ErrorManager::CATEGORY_CONFIGURATION,
				[
					'component' => 'bootloader',
					'initialization_step' => 'core_systems',
					'plugin_version' => NUCLEN_PLUGIN_VERSION ?? 'unknown',
				],
				function() {
					// Attempt minimal recovery
					return self::attempt_minimal_recovery();
				}
			);
			
			// Show user-friendly error
			if ( is_admin() ) {
				$user_response = UserErrorManager::handle_user_error( $error_context );
				add_action( 'admin_notices', function() use ( $user_response ) {
					printf(
						'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
						esc_html__( 'Nuclear Engagement Plugin Error', 'nuclear-engagement' ),
						esc_html( $user_response['message'] )
					);
				} );
			}
		} else {
			// Fallback to basic error handling
			if ( class_exists( LoggingService::class ) ) {
				LoggingService::log( 'Nuclear Engagement: Initialization failed - ' . $e->getMessage() );
			}
			
			error_log( 'Nuclear Engagement: Critical initialization error - ' . $e->getMessage() );
			
			if ( is_admin() ) {
				add_action( 'admin_notices', function() use ( $e ) {
					printf(
						'<div class="error"><p>%s</p></div>',
						esc_html( sprintf(
							__( 'Nuclear Engagement failed to initialize: %s', 'nuclear-engagement' ),
							$e->getMessage()
						) )
					);
				} );
			}
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
			define( 'NUCLEN_ASSET_VERSION', '250628-13' );
		}
	}

	/**
	 * Register Composer and fallback autoloaders.
	 */
	private static function register_autoloaders(): void {
		if ( isset( self::$initialized['autoloaders'] ) ) {
			return;
		}
		
		if ( ! self::load_composer_autoloader() ) {
			self::handle_missing_autoloader();
			return;
		}
		
		try {
			Autoloader::register();
			self::$initialized['autoloaders'] = true;
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Failed to register autoloader: ' . $e->getMessage(), 0, $e );
		}
	}
	
	/**
	 * Attempt to load Composer autoloader.
	 *
	 * @return bool True if autoloader was loaded, false otherwise.
	 */
	private static function load_composer_autoloader(): bool {
		$possible_paths = [
			__DIR__ . '/../../vendor/autoload.php',
			dirname( __DIR__, 2 ) . '/vendor/autoload.php'
		];
		
		foreach ( $possible_paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Handle missing Composer autoloader.
	 */
	private static function handle_missing_autoloader(): void {
		$logging_path = __DIR__ . '/../Services/LoggingService.php';
		
		if ( file_exists( $logging_path ) ) {
			require_once $logging_path;
		}
		
		LoggingService::log( 'Nuclear Engagement: vendor autoload not found.' );
		LoggingService::notify_admin( 'Nuclear Engagement dependencies missing. Please run composer install.' );
	}
	

	/**
	 * Setup dependency injection container.
	 */
	private static function setup_container(): void {
		if ( isset( self::$initialized['container'] ) ) {
			return;
		}
		
		PerformanceMonitor::start( 'setup_container' );
		
		// Initialize service discovery
		ServiceDiscovery::init();
		
		// Auto-discover and register services with lazy loading
		self::setupLazyServices();
		ServiceDiscovery::autoRegister();
		
		// Register core services manually for explicit control
		Container::singleton( LoggingService::class, function() {
			return new LoggingService();
		} );
		
		Container::singleton( InventoryCache::class, function() {
			return Container::resolve( InventoryCache::class );
		} );
		
		Container::singleton( PostsQueryService::class, function() {
			return Container::resolve( PostsQueryService::class );
		} );
		
		Container::singleton( PostDataFetcher::class, function() {
			return Container::resolve( PostDataFetcher::class );
		} );
		
		// Register error recovery fallbacks
		self::registerFallbackServices();
		
		// Load critical service providers immediately, defer others
		self::loadCriticalServices();
		self::deferNonCriticalServices();
		
		self::$initialized['container'] = true;
		PerformanceMonitor::stop( 'setup_container' );
	}
	
	/**
	 * Register fallback services for error recovery.
	 */
	private static function registerFallbackServices(): void {
		// Cache fallback
		ErrorRecovery::registerFallback( 'InventoryCache', function() {
			return new class {
				public function register_hooks() {
					// Minimal cache implementation
				}
				public function get( $key ) {
					return false;
				}
				public function set( $key, $value, $ttl = 0 ) {
					return false;
				}
			};
		} );
		
		// Query service fallback
		ErrorRecovery::registerFallback( 'PostsQueryService', function() {
			return new class {
				public function register_hooks() {
					// Basic query functionality
				}
				public function query( $args ) {
					return new \WP_Query( $args );
				}
			};
		} );
		
		// Data fetcher fallback
		ErrorRecovery::registerFallback( 'PostDataFetcher', function() {
			return new class {
				public function register_hooks() {
					// Basic data fetching
				}
				public function fetch( $post_id ) {
					return get_post( $post_id );
				}
			};
		} );
	}
	
	/**
	 * Load helper files and constants.
	 */
	private static function load_helpers(): void {
		if ( isset( self::$initialized['helpers'] ) ) {
			return;
		}
		
		PerformanceMonitor::start( 'load_helpers' );
		
		try {
			self::load_constants();
			
			if ( class_exists( AssetVersions::class ) ) {
				AssetVersions::init();
			}
			
			self::$initialized['helpers'] = true;
		} finally {
			PerformanceMonitor::stop( 'load_helpers' );
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
			self::$instances['installer'] = Container::bound( Installer::class )
				? Container::resolve( Installer::class )
				: new Installer();
		}
		return self::$instances['installer'];
	}
	
	/**
	 * Setup lazy loading configurations for services.
	 */
	private static function setupLazyServices(): void {
		// Configure lazy loading for non-critical services
		LazyLoader::register(
			'admin_dashboard',
			function() {
				// Load dashboard-specific services
				if ( class_exists( 'NuclearEngagement\Admin\Dashboard' ) ) {
					Container::resolve( 'NuclearEngagement\Admin\Dashboard' )->init();
				}
			},
			'admin_init',
			function() {
				return is_admin() && ( get_current_screen()->id ?? '' ) === 'dashboard';
			}
		);
		
		LazyLoader::register(
			'frontend_assets',
			function() {
				// Load frontend-specific services
				if ( class_exists( 'NuclearEngagement\Frontend\AssetManager' ) ) {
					Container::resolve( 'NuclearEngagement\Frontend\AssetManager' )->enqueue();
				}
			},
			'wp_enqueue_scripts',
			function() {
				return ! is_admin() && self::needsFrontendAssets();
			}
		);
	}
	
	/**
	 * Load critical services immediately.
	 */
	private static function loadCriticalServices(): void {
		$critical_providers = [
			'logging' => function() {
				ServiceDiscovery::loadProviders();
			},
		];
		
		foreach ( $critical_providers as $name => $provider ) {
			try {
				call_user_func( $provider );
			} catch ( \Throwable $e ) {
				ErrorRecovery::addErrorContext(
					"Failed to load critical service provider: {$name}",
					[ 'error' => $e->getMessage() ],
					'error'
				);
			}
		}
	}
	
	/**
	 * Defer non-critical services to background.
	 */
	private static function deferNonCriticalServices(): void {
		// Queue cache warmup as background job
		BackgroundProcessor::queue_job( 'cache_warmup', [], 20, 30 ); // Low priority, 30s delay
		
		// Queue service health checks
		LazyLoader::defer( 'health_checks', function() {
			ServiceDiscovery::run_health_checks();
		}, 30 );
	}
	
	/**
	 * Get services required for current request.
	 *
	 * @return array Required services.
	 */
	private static function getRequiredServices(): array {
		$base_services = [
			'LoggingService' => LoggingService::class,
		];
		
		// Add context-specific services
		if ( is_admin() ) {
			$base_services['InventoryCache'] = InventoryCache::class;
		}
		
		if ( ! is_admin() || defined( 'REST_REQUEST' ) ) {
			$base_services['PostsQueryService'] = PostsQueryService::class;
			$base_services['PostDataFetcher'] = PostDataFetcher::class;
		}
		
		return $base_services;
	}
	
	/**
	 * Check if service should be loaded lazily.
	 *
	 * @param string $service_name Service name.
	 * @return bool Whether to load lazily.
	 */
	private static function shouldLoadLazily( string $service_name ): bool {
		$lazy_services = [ 'InventoryCache', 'PostDataFetcher' ];
		
		// Don't lazy load on REST requests or AJAX
		if ( defined( 'REST_REQUEST' ) || defined( 'DOING_AJAX' ) ) {
			return false;
		}
		
		return in_array( $service_name, $lazy_services, true );
	}
	
	/**
	 * Get load trigger for service.
	 *
	 * @param string $service_name Service name.
	 * @return string Load trigger.
	 */
	private static function getServiceLoadTrigger( string $service_name ): string {
		$triggers = [
			'InventoryCache'   => 'admin_init',
			'PostDataFetcher'  => 'wp',
		];
		
		return $triggers[$service_name] ?? 'init';
	}
	
	/**
	 * Register a single service with error handling.
	 *
	 * @param string $class Service class.
	 * @return bool Success status.
	 */
	private static function registerSingleService( string $class ): bool {
		try {
			$service = Container::resolve( $class );
			if ( method_exists( $service, 'register_hooks' ) ) {
				$service->register_hooks();
			}
			return true;
		} catch ( \Throwable $e ) {
			ErrorRecovery::addErrorContext(
				"Failed to register service: {$class}",
				[ 'error' => $e->getMessage() ],
				'error'
			);
			return false;
		}
	}
	
	/**
	 * Check if frontend assets are needed.
	 *
	 * @return bool Whether frontend assets are needed.
	 */
	private static function needsFrontendAssets(): bool {
		return CacheManager::remember( 
			'needs_frontend_assets_' . get_queried_object_id(),
			function() {
				if ( is_singular() ) {
					$post = get_queried_object();
					if ( $post ) {
						return has_shortcode( $post->post_content, 'nuclen' ) ||
							   strpos( $post->post_content, 'wp:nuclen/' ) !== false ||
							   get_post_meta( $post->ID, 'nuclen-quiz-data', true ) ||
							   get_post_meta( $post->ID, 'nuclen-summary-data', true );
					}
				}
				return false;
			},
			'assets',
			300
		);
	}
	
	/**
	 * Attempt minimal recovery from initialization failure.
	 *
	 * @return bool Whether recovery was successful.
	 */
	private static function attempt_minimal_recovery(): bool {
		try {
			// Load only essential services
			if ( class_exists( LoggingService::class ) ) {
				Container::singleton( LoggingService::class, function() {
					return new LoggingService();
				} );
			}
			
			// Initialize basic text domain
			self::load_textdomain();
			
			return true;
		} catch ( \Throwable $recovery_error ) {
			error_log( 'Nuclear Engagement: Recovery attempt failed - ' . $recovery_error->getMessage() );
			return false;
		}
	}
	
	/**
	 * Attempt service registration recovery.
	 *
	 * @return bool Whether recovery was successful.
	 */
	private static function attempt_service_recovery(): bool {
		try {
			// Clear any problematic service registrations
			Container::flush();
			
			// Re-register only critical services
			Container::singleton( LoggingService::class, function() {
				return new LoggingService();
			} );
			
			return true;
		} catch ( \Throwable $recovery_error ) {
			return false;
		}
	}
	
	/**
	 * Get comprehensive system status for debugging.
	 *
	 * @return array System status information.
	 */
	public static function getSystemStatus(): array {
		$status = [
			'initialized'        => self::$initialized,
			'plugin_initialized' => self::$plugin_initialized,
			'performance'        => PerformanceMonitor::getAllMetrics(),
			'memory'            => PerformanceMonitor::getMemoryUsage(),
			'cache_stats'       => CacheManager::get_statistics(),
			'query_stats'       => QueryOptimizer::get_query_stats(),
			'lazy_loading'      => LazyLoader::get_stats(),
			'background_jobs'   => BackgroundProcessor::get_statistics(),
			'services'          => Container::getServices(),
			'service_health'    => ServiceDiscovery::getHealthStatus(),
			'error_context'     => ErrorRecovery::getErrorContext(),
			'dependency_graph'  => ServiceDiscovery::getDependencyGraph(),
			'missing_deps'      => ServiceDiscovery::validateDependencies(),
		];
		
		// Add enhanced error management status
		if ( class_exists( ErrorManager::class ) ) {
			$status['error_analytics'] = ErrorManager::get_error_analytics();
		}
		
		if ( class_exists( SecurityErrorHandler::class ) ) {
			$status['security_dashboard'] = SecurityErrorHandler::get_security_dashboard();
		}
		
		if ( class_exists( UserErrorManager::class ) ) {
			$status['user_error_trends'] = UserErrorManager::get_error_trends_for_admin();
		}
		
		return $status;
	}
	
	/**
	 * Initialize plugin settings with caching.
	 */
	public static function initialize_settings(): void {
		if ( isset( self::$initialized['settings'] ) ) {
			return;
		}
		
		PerformanceMonitor::start( 'initialize_settings' );
		
		// Use cached settings if available
		$cached_settings = CacheManager::get( 'plugin_settings', 'metadata' );
		if ( $cached_settings !== false ) {
			SettingsRepository::set_cached_instance( $cached_settings );
			self::$initialized['settings'] = true;
			PerformanceMonitor::stop( 'initialize_settings' );
			return;
		}
		
		ErrorRecovery::executeWithRetry(
			function() {
				$defaults = Defaults::nuclen_get_default_settings();
				$settings = SettingsRepository::get_instance( $defaults );
				
				// Cache settings for next request
				CacheManager::set( 'plugin_settings', $settings, 'metadata', 1800 );
				
				self::$initialized['settings'] = true;
			},
			'default',
			[ 'operation' => 'settings_initialization' ]
		);
		
		PerformanceMonitor::stop( 'initialize_settings' );
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
		
		PerformanceMonitor::start( 'run_plugin' );
		
		ErrorRecovery::executeWithRetry(
			function() {
				MetaRegistration::init();
				
				if ( ! isset( self::$instances['plugin'] ) ) {
					self::$instances['plugin'] = Container::bound( Plugin::class ) 
						? Container::resolve( Plugin::class )
						: new Plugin();
				}
				
				self::$instances['plugin']->nuclen_run();
				self::$initialized['plugin'] = true;
			},
			'default',
			[ 'operation' => 'plugin_initialization' ]
		);
		
		PerformanceMonitor::stop( 'run_plugin' );
	}
	
	/**
	 * Register services and bootstrap the plugin.
	 */
	public static function init_plugin(): void {
		PerformanceMonitor::start( 'init_plugin' );
		
		ErrorRecovery::executeWithGracefulDegradation(
			function() {
				self::register_services();
				self::run_plugin();
			},
			function() {
				// Fallback: Load minimal functionality
				self::loadMinimalFunctionality();
			},
			[ 'operation' => 'plugin_initialization' ]
		);
		
		PerformanceMonitor::stop( 'init_plugin' );
	}
	
	/**
	 * Load minimal functionality as fallback.
	 */
	private static function loadMinimalFunctionality(): void {
		// Load only essential features
		if ( ! isset( self::$initialized['minimal_mode'] ) ) {
			ErrorRecovery::addErrorContext(
				'Loading minimal functionality due to initialization failure',
				[ 'mode' => 'fallback' ],
				'warning'
			);
			
			// Load basic textdomain
			self::load_textdomain();
			
			self::$initialized['minimal_mode'] = true;
		}
	}
	
	/**
	 * Register all plugin services with performance optimizations.
	 */
	private static function register_services(): void {
		if ( isset( self::$initialized['services'] ) ) {
			return;
		}
		
		PerformanceMonitor::start( 'register_services' );
		
		// Only register services that are actually needed for this request
		$services = self::getRequiredServices();
		
		foreach ( $services as $name => $class ) {
			// Check if service should be loaded lazily
			if ( self::shouldLoadLazily( $name ) ) {
				LazyLoader::register(
					$name,
					function() use ( $class ) {
						return self::registerSingleService( $class );
					},
					self::getServiceLoadTrigger( $name )
				);
				continue;
			}
			
			// Load critical services immediately
			PerformanceMonitor::start( "register_service_{$name}" );
			
			ErrorRecovery::executeWithCircuitBreaker(
				$name,
				function() use ( $class ) {
					return self::registerSingleService( $class );
				},
				[ 'failure_threshold' => 3, 'timeout' => 300 ]
			);
			
			PerformanceMonitor::stop( "register_service_{$name}" );
		}
		
		self::$initialized['services'] = true;
		PerformanceMonitor::stop( 'register_services' );
	}
	
	/**
	 * Handle errors during service registration with enhanced error management.
	 *
	 * @param \Throwable $e The exception that occurred.
	 */
	private static function handle_service_registration_error( \Throwable $e ): void {
		$error_context = ErrorManager::handle_error(
			$e,
			ErrorManager::SEVERITY_HIGH,
			ErrorManager::CATEGORY_CONFIGURATION,
			[
				'component' => 'service_registration',
				'system_state' => self::$initialized,
				'available_services' => Container::getServices(),
			],
			function() {
				// Attempt service registration recovery
				return self::attempt_service_recovery();
			}
		);
		
		// Show appropriate user message
		if ( is_admin() ) {
			$user_response = UserErrorManager::handle_user_error( $error_context );
			add_action( 'admin_notices', function() use ( $user_response ) {
				$severity_class = $user_response['severity'] === ErrorManager::SEVERITY_CRITICAL ? 'error' : 'warning';
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $severity_class ),
					esc_html( $user_response['message'] )
				);
			} );
		}
	}
}
