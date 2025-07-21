<?php
/**
 * Plugin.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);
/**
 * File: includes/Plugin.php
 *
 * Main Plugin Class for Nuclear Engagement
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Admin\Admin;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Admin\Onboarding;
use NuclearEngagement\Core\Defaults;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\OptinData;
use NuclearEngagement\Services\{GenerationService, RemoteApiService, ContentStorageService, PointerService, PostsQueryService, AutoGenerationService, ThemeMigrationService, ThemeLoader, LoggingService};
use NuclearEngagement\Admin\Controller\Ajax\{GenerateController, UpdatesController, PointerController, PostsCountController};
use NuclearEngagement\Front\Controller\Rest\ContentController;
use NuclearEngagement\Core\ContainerRegistrar;

class Plugin {

		protected $loader;
	protected string $plugin_name;
	protected string $version;
	protected SettingsRepository $settings_repository;

	/**
	 *
	 * @var ServiceContainer
	 */
	private ServiceContainer $container;

	public function __construct() {
		$this->version     = defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0';
		$this->plugin_name = 'nuclear-engagement';

		// Initialize settings repository with defaults.
		$defaults                  = Defaults::nuclen_get_default_settings();
		$this->settings_repository = SettingsRepository::get_instance( $defaults );

		/* ───── Ensure DB table exists on activation ───── */
		register_activation_hook(
			NUCLEN_PLUGIN_FILE,
			function () {
				if ( ! \NuclearEngagement\OptinData::table_exists() ) {
					$created = \NuclearEngagement\OptinData::maybe_create_table();
					if ( ! $created ) {
						\NuclearEngagement\Services\LoggingService::notify_admin(
							'Nuclear Engagement could not create required database tables.'
						);
					}
				}

				// Run theme migration.
				$migration_service = new ThemeMigrationService();
				$migration_service->migrate_legacy_settings();

				// Schedule batch cleanup cron
				if ( ! wp_next_scheduled( 'nuclen_cleanup_old_batches' ) ) {
					wp_schedule_event( time(), 'hourly', 'nuclen_cleanup_old_batches' );
				}

				// Schedule orphaned batch cleanup
				if ( ! wp_next_scheduled( 'nuclen_cleanup_orphaned_batches' ) ) {
					wp_schedule_event( time(), 'twicedaily', 'nuclen_cleanup_orphaned_batches' );
				}

				// Schedule content lock cleanup
				if ( ! wp_next_scheduled( 'nuclen_cleanup_content_locks' ) ) {
					wp_schedule_event( time(), 'hourly', 'nuclen_cleanup_content_locks' );
				}
			}
		);

		// Register deactivation hook to clean up scheduled events
		register_deactivation_hook(
			NUCLEN_PLUGIN_FILE,
			function () {
				wp_clear_scheduled_hook( 'nuclen_cleanup_old_batches' );
				wp_clear_scheduled_hook( 'nuclen_cleanup_orphaned_batches' );
				wp_clear_scheduled_hook( 'nuclen_cleanup_content_locks' );
			}
		);

		$this->nuclen_load_dependencies();
		$this->container = ServiceContainer::getInstance();
		ContainerRegistrar::register( $this->container, $this->settings_repository );

		// Define hooks immediately, before any service initialization.
		$this->nuclen_define_admin_hooks();
		$this->nuclen_define_public_hooks();

		// Initialize theme system.
		$this->nuclen_init_theme_system();

		// Run the loader immediately to register all hooks.
		$this->loader->nuclen_run();

		// Auto-generation hooks are registered by PluginBootstrap::registerAutoGenerationHooks()
		// Do not register them here to avoid duplication

		// Register circuit breaker health check handlers
		\NuclearEngagement\Services\CircuitBreaker::register_health_check_handlers();

		// Initialize centralized polling queue
		if ( $this->container->has( 'centralized_polling_queue' ) ) {
			$polling_queue = $this->container->get( 'centralized_polling_queue' );
			$polling_queue->register_hooks();
		}
	}

	/*
	─────────────────────────────────────────────
		Dependencies & Loader
	──────────────────────────────────────────── */
	private function nuclen_load_dependencies() {

		/* ► Ensure OptinData hooks are registered */
		OptinData::init();

				( new ModuleLoader() )->load_all();
			$this->loader = new Loader();
	}

	/*
	─────────────────────────────────────────────
		Admin-side hooks
	──────────────────────────────────────────── */
	private function nuclen_define_admin_hooks() {
		try {
			// Initialize admin notice service early to ensure hooks are registered
			$this->container->get( 'admin_notice_service' );

			$plugin_admin = new Admin( $this->nuclen_get_plugin_name(), $this->nuclen_get_version(), $this->settings_repository, $this->container );

			// Scripts registration.
			$this->loader->nuclen_add_action( 'init', $plugin_admin, 'nuclen_register_admin_scripts', 9 );

			// Enqueue.
			$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_styles' );
			$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_scripts' );
			$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_dashboard_styles' );
			$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_generate_page_scripts' );
		} catch ( \Throwable $e ) {
			LoggingService::log( 'Failed to initialize admin hooks - ' . $e->getMessage() );
			LoggingService::log( 'Stack trace - ' . $e->getTraceAsString() );
		}

		// AJAX - now using controllers.
		$generate_controller    = $this->container->get( 'generate_controller' );
		$updates_controller     = $this->container->get( 'updates_controller' );
		$posts_count_controller = $this->container->get( 'posts_count_controller' );
		$tasks_controller       = $this->container->get( 'tasks_controller' );

		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_trigger_generation', $generate_controller, 'handle' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_fetch_app_updates', $updates_controller, 'handle' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_get_posts_count', $posts_count_controller, 'handle' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_run_task', $tasks_controller, 'run_task' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_cancel_task', $tasks_controller, 'cancel_task' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_get_task_status', $tasks_controller, 'get_task_status' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_get_recent_completions', $tasks_controller, 'get_recent_completions' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_refresh_tasks_data', $tasks_controller, 'refresh_tasks_data' );

		// Register admin menu
		$this->loader->nuclen_add_action( 'admin_menu', $plugin_admin, 'nuclen_add_admin_menu' );

		// Register early redirect handler for Tasks page
		$this->loader->nuclen_add_action( 'admin_init', $plugin_admin, 'nuclen_handle_tasks_early_redirects' );

		// Setup actions
		$setup = new \NuclearEngagement\Admin\Setup( $this->settings_repository );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_connect_app', $setup, 'nuclen_handle_connect_app' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_generate_app_password', $setup, 'nuclen_handle_generate_app_password' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_reset_api_key', $setup, 'nuclen_handle_reset_api_key' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_reset_wp_app_connection', $setup, 'nuclen_handle_reset_wp_app_connection' );

		// Onboarding pointers - use controller.
		$onboarding = new Onboarding();
		$onboarding->nuclen_register_hooks();

		// Replace pointer dismiss with controller.
		$pointer_controller = $this->container->get( 'pointer_controller' );
		remove_action( 'wp_ajax_nuclen_dismiss_pointer', array( $onboarding, 'nuclen_ajax_dismiss_pointer' ) );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_dismiss_pointer', $pointer_controller, 'dismiss' );

		/* Opt-in CSV export */
		$optin_export_controller = $this->container->get( 'optin_export_controller' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_export_optin', $optin_export_controller, 'handle' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_export_optin', $optin_export_controller, 'handle' );

		/* Batch cleanup cron jobs */
		$this->loader->nuclen_add_action( 'nuclen_cleanup_old_batches', $this, 'cleanup_old_batches' );
		$this->loader->nuclen_add_action( 'nuclen_cleanup_orphaned_batches', $this, 'cleanup_orphaned_batches' );

		/* Generation recovery */
		$this->loader->nuclen_add_action( 'nuclen_recover_generation', $this, 'recover_generation', 10, 1 );

		/* Content lock cleanup */
		$this->loader->nuclen_add_action( 'nuclen_cleanup_content_locks', $this, 'cleanup_content_locks' );
	}

	/*
	─────────────────────────────────────────────
		Front-end hooks
	──────────────────────────────────────────── */
	private function nuclen_define_public_hooks() {
		$plugin_public = new FrontClass( $this->nuclen_get_plugin_name(), $this->nuclen_get_version(), $this->settings_repository, $this->container );

		$this->loader->nuclen_add_action( 'wp_enqueue_scripts', $plugin_public, 'wp_enqueue_styles' );
		$this->loader->nuclen_add_action( 'wp_enqueue_scripts', $plugin_public, 'wp_enqueue_scripts' );

		// Add custom theme CSS variables to head.
		$this->loader->nuclen_add_action( 'wp_head', $plugin_public, 'wp_head_custom_theme_vars', 99 );

		// REST API - use controller.
		add_action(
			'rest_api_init',
			function () {
				$contentController = $this->container->get( 'content_controller' );
				register_rest_route(
					'nuclear-engagement/v1',
					'/receive-content',
					array(
						'methods'             => 'POST',
						'callback'            => array( $contentController, 'handle' ),
						'permission_callback' => array( $contentController, 'permissions' ),
					)
				);
			}
		);

		$this->loader->nuclen_add_action( 'init', $plugin_public, 'nuclen_register_quiz_shortcode' );
		$this->loader->nuclen_add_action( 'init', $plugin_public, 'nuclen_register_summary_shortcode' );
		$this->loader->nuclen_add_filter( 'the_content', $plugin_public, 'nuclen_auto_insert_shortcodes', 50 );
			$this->loader->nuclen_add_action( 'init', '\\NuclearEngagement\\Core\\Blocks', 'register' );
	}

	/*
	─────────────────────────────────────────────
		Theme System
	──────────────────────────────────────────── */
	private function nuclen_init_theme_system() {
		// Run migration on init for existing installations.
		add_action(
			'init',
			function () {
				$migration_service = new \NuclearEngagement\Services\ThemeMigrationService();
				if ( ! $migration_service->check_migration_status() ) {
					$migration_service->migrate_legacy_settings();
				}
			},
			5
		);
	}

	/*
	─────────────────────────────────────────────
		Boilerplate
	──────────────────────────────────────────── */
	public function nuclen_run() {
		// The loader has already been run in the constructor.
		// This method is kept for compatibility but does nothing.
	}

	public function nuclen_get_plugin_name() {
		return $this->plugin_name;
	}

	public function nuclen_get_loader() {
		return $this->loader;
	}

	public function nuclen_get_version() {
		return $this->version;
	}

	/**
	 * Get the settings repository instance.
	 *
	 * @return SettingsRepository
	 */
	public function nuclen_get_settings_repository() {
		return $this->settings_repository;
	}

	/**
	 * Get the container instance (mainly for testing)
	 *
	 * @return ServiceContainer
	 */
	public function get_container(): ServiceContainer {
		return $this->container;
	}

	/**
	 * Clean up old batch transients
	 */
	public function cleanup_old_batches(): void {
		try {
			$batch_processor = $this->container->get( 'bulk_generation_batch_processor' );
			if ( $batch_processor ) {
				$cleaned = $batch_processor->cleanup_old_batches( 24 ); // Clean batches older than 24 hours
				if ( $cleaned > 0 ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( 'Cleaned up %d old batch transients', $cleaned )
					);
				}
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				'Error during batch cleanup: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Clean up orphaned batch transients
	 */
	public function cleanup_orphaned_batches(): void {
		try {
			$batch_processor = $this->container->get( 'bulk_generation_batch_processor' );
			if ( $batch_processor ) {
				$cleaned = $batch_processor->cleanup_orphaned_batches();
				if ( $cleaned > 0 ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( 'Cleaned up %d orphaned batch transients', $cleaned )
					);
				}
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				'Error during orphaned batch cleanup: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Recover a generation
	 *
	 * @param string $generation_id Generation ID to recover
	 */
	public function recover_generation( string $generation_id ): void {
		try {
			$generation_service = $this->container->get( 'generation_service' );
			if ( $generation_service ) {
				$generation_service->recoverGeneration( $generation_id );
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Error recovering generation %s: %s', $generation_id, $e->getMessage() )
			);
		}
	}

	/**
	 * Clean up expired content locks
	 */
	public function cleanup_content_locks(): void {
		global $wpdb;

		try {
			// Find and remove expired content locks (older than 5 minutes)
			$expired_time = time() - 300;
			$cleaned      = 0;
			$batch_size   = 50; // Process in batches to avoid memory issues

			// Find all content lock options with LIMIT for performance
			$locks = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM $wpdb->options 
					WHERE (option_name LIKE %s OR option_name LIKE %s)
					LIMIT %d",
					'nuclen_content_lock_quiz_%',
					'nuclen_content_lock_summary_%',
					$batch_size
				)
			);

			if ( ! empty( $locks ) ) {
				$to_delete = array();

				foreach ( $locks as $lock ) {
					$value = maybe_unserialize( $lock->option_value );
					if ( is_array( $value ) && isset( $value['time'] ) && $value['time'] < $expired_time ) {
						$to_delete[] = $lock->option_name;
					}
				}

				// Bulk delete for better performance
				if ( ! empty( $to_delete ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%s' ) );
					$cleaned      = $wpdb->query(
						$wpdb->prepare(
							"DELETE FROM $wpdb->options WHERE option_name IN ($placeholders)",
							$to_delete
						)
					);
				}
			}

			if ( $cleaned > 0 ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( 'Cleaned up %d expired content locks', $cleaned )
				);
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				'Error during content lock cleanup: ' . $e->getMessage()
			);
		}
	}
}
