<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Efficient plugin bootstrap with lazy loading.
 * 
 * @package NuclearEngagement\Core
 */
final class PluginBootstrap {
	private static ?self $instance = null;
	private bool $initialized = false;
	private array $lazy_services = [];
	
	private function __construct() {}

	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		if ($this->initialized) {
			return;
		}

		// Register activation/deactivation hooks
		$this->registerLifecycleHooks();
		
		// Initialize only essential services immediately
		$this->initializeEssentialServices();
		
		// Register lazy loading for heavy services
		$this->registerLazyServices();
		
		// Register WordPress hooks
		$this->registerWordPressHooks();
		
		$this->initialized = true;
	}

	private function registerLifecycleHooks(): void {
		register_activation_hook(NUCLEN_PLUGIN_FILE, [$this, 'onActivation']);
		register_deactivation_hook(NUCLEN_PLUGIN_FILE, [$this, 'onDeactivation']);
	}

	public function onActivation(): void {
		// Only run essential setup on activation
		if ( ! \NuclearEngagement\OptinData::table_exists() ) {
			\NuclearEngagement\OptinData::maybe_create_table();
		}
		
		// Schedule theme migration for next request
		wp_schedule_single_event(time() + 30, 'nuclen_theme_migration');
	}

	public function onDeactivation(): void {
		// Clean up scheduled events
		wp_clear_scheduled_hook('nuclen_theme_migration');
		wp_clear_scheduled_hook('nuclen_cleanup_logs');
	}

	private function initializeEssentialServices(): void {
		// Only initialize what's absolutely necessary
		$defaults = Defaults::nuclen_get_default_settings();
		SettingsRepository::get_instance($defaults);
		
		// Initialize error handling
		if (class_exists('NuclearEngagement\Core\Error\ErrorContext')) {
			// New error system is available
		}
	}

	private function registerLazyServices(): void {
		// Register services to be loaded only when needed
		$this->lazy_services = [
			'admin' => function() {
				return $this->initializeAdminServices();
			},
			'frontend' => function() {
				return $this->initializeFrontendServices();
			},
			'api' => function() {
				return $this->initializeApiServices();
			},
		];
	}

	private function registerWordPressHooks(): void {
		// WARNING: This class has a critical timing issue!
		// Admin services are loaded too late for the 'admin_menu' hook.
		// The 'admin_menu' hook fires before 'init', causing admin menu items to not appear.
		// This is why bootstrap.php uses the old Bootloader system instead.
		// TODO: Fix this by loading admin services earlier or restructuring the initialization.
		
		// Register hooks that determine when to load services
		// Changed from admin_init to init for earlier loading of admin services
		add_action('init', [$this, 'maybeLoadAdminServices'], 1);
		add_action('wp_loaded', [$this, 'maybeLoadFrontendServices']);
		add_action('rest_api_init', [$this, 'maybeLoadApiServices']);
		
		// Initialize modules
		add_action('init', [$this, 'initializeModules'], 5);
		
		// Register theme migration hook
		add_action('nuclen_theme_migration', [$this, 'runThemeMigration']);
		
		// Register cleanup hook
		if (!wp_next_scheduled('nuclen_cleanup_logs')) {
			wp_schedule_event(time(), 'weekly', 'nuclen_cleanup_logs');
		}
		add_action('nuclen_cleanup_logs', [$this, 'cleanupLogs']);
	}

	public function maybeLoadAdminServices(): void {
		if (is_admin() && !$this->isServiceLoaded('admin')) {
			$this->loadService('admin');
		}
	}

	public function maybeLoadFrontendServices(): void {
		if (!is_admin() && !$this->isServiceLoaded('frontend')) {
			$this->loadService('frontend');
		}
	}

	public function maybeLoadApiServices(): void {
		if (!$this->isServiceLoaded('api')) {
			$this->loadService('api');
		}
	}

	public function runThemeMigration(): void {
		if (class_exists('NuclearEngagement\Services\ThemeMigrationService')) {
			$migration_service = new \NuclearEngagement\Services\ThemeMigrationService();
			$migration_service->migrate_legacy_settings();
		}
	}

	public function cleanupLogs(): void {
		// Clean up old log entries
		if (class_exists('NuclearEngagement\Services\LoggingService')) {
			\NuclearEngagement\Services\LoggingService::cleanup_old_logs();
		}
	}

	private function loadService(string $service_name): void {
		if (isset($this->lazy_services[$service_name])) {
			$this->lazy_services[$service_name]();
			$this->lazy_services[$service_name] = true; // Mark as loaded
		}
	}

	private function isServiceLoaded(string $service_name): bool {
		return isset($this->lazy_services[$service_name]) && 
			   $this->lazy_services[$service_name] === true;
	}

	private function initializeAdminServices(): void {
		// Load admin-specific services
		$container = ServiceContainer::getInstance();
		$settings = SettingsRepository::get_instance();
		
		// Initialize admin controllers and services
		if (class_exists('NuclearEngagement\Admin\Admin')) {
			$admin = new \NuclearEngagement\Admin\Admin('nuclear-engagement', '1.0.0', $settings, $container);
			
			// Register admin hooks
			add_action('admin_enqueue_scripts', [$admin, 'wp_enqueue_styles']);
			add_action('admin_enqueue_scripts', [$admin, 'wp_enqueue_scripts']);
			add_action('admin_menu', [$admin, 'nuclen_add_admin_menu']);
		}
	}

	private function initializeFrontendServices(): void {
		// Load frontend-specific services
		$container = ServiceContainer::getInstance();
		$settings = SettingsRepository::get_instance();
		
		if (class_exists('NuclearEngagement\Front\FrontClass')) {
			$frontend = new \NuclearEngagement\Front\FrontClass('nuclear-engagement', '1.0.0', $settings, $container);
			
			// Register frontend hooks
			add_action('wp_enqueue_scripts', [$frontend, 'wp_enqueue_styles']);
			add_action('wp_enqueue_scripts', [$frontend, 'wp_enqueue_scripts']);
			add_action('init', [$frontend, 'nuclen_register_quiz_shortcode']);
			add_action('init', [$frontend, 'nuclen_register_summary_shortcode']);
		}
	}

	private function initializeApiServices(): void {
		// Load API-specific services
		$container = ServiceContainer::getInstance();
		
		// Register REST routes
		if (class_exists('NuclearEngagement\Front\Controller\Rest\ContentController')) {
			register_rest_route(
				'nuclear-engagement/v1',
				'/receive-content',
				[
					'methods' => 'POST',
					'callback' => [$container->get('content_controller'), 'handle'],
					'permission_callback' => [$container->get('content_controller'), 'permissions'],
				]
			);
		}
	}

	/**
	 * Initialize modular system.
	 */
	public function initializeModules(): void {
		// Register modules
		$registry = \NuclearEngagement\Core\Module\ModuleRegistry::getInstance();
		
		// Register TOC module
		if (class_exists('NuclearEngagement\Modules\TOC\TocModule')) {
			$registry->register(new \NuclearEngagement\Modules\TOC\TocModule());
		}
		
		// Register Quiz module (would need to be created)
		// if (class_exists('NuclearEngagement\Modules\Quiz\QuizModule')) {
		//     $registry->register(new \NuclearEngagement\Modules\Quiz\QuizModule());
		// }
		
		// Register Summary module (would need to be created)
		// if (class_exists('NuclearEngagement\Modules\Summary\SummaryModule')) {
		//     $registry->register(new \NuclearEngagement\Modules\Summary\SummaryModule());
		// }
		
		// Initialize all modules
		$registry->initializeAll();
	}
}