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
use NuclearEngagement\Services\{GenerationService, RemoteApiService, ContentStorageService, PointerService, PostsQueryService, AutoGenerationService, ThemeMigrationService, ThemeLoader};
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

		// Register hooks for auto-generation after admin/public hooks are defined.
		$auto_generation_service = $this->container->get( 'auto_generation_service' );
		$auto_generation_service->register_hooks();
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
			$plugin_admin = new Admin( $this->nuclen_get_plugin_name(), $this->nuclen_get_version(), $this->settings_repository, $this->container );

			// Scripts registration.
			$this->loader->nuclen_add_action( 'init', $plugin_admin, 'nuclen_register_admin_scripts', 9 );

			// Enqueue.
			$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_styles' );
			$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_scripts' );
			$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_dashboard_styles' );
			$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_generate_page_scripts' );

			// Admin Menu - Register immediately to ensure it's not missed.
			// IMPORTANT: Using add_action directly instead of the loader to ensure.
			// the menu hook is registered before WordPress fires the 'admin_menu' action.
			add_action( 'admin_menu', array( $plugin_admin, 'nuclen_add_admin_menu' ) );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Nuclear Engagement: Failed to initialize admin hooks - ' . $e->getMessage() );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Nuclear Engagement: Stack trace - ' . $e->getTraceAsString() );
		}

		// AJAX - now using controllers.
		$generate_controller    = $this->container->get( 'generate_controller' );
		$updates_controller     = $this->container->get( 'updates_controller' );
		$posts_count_controller = $this->container->get( 'posts_count_controller' );

		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_trigger_generation', $generate_controller, 'handle' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_fetch_app_updates', $updates_controller, 'handle' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_get_posts_count', $posts_count_controller, 'handle' );

		// Setup actions (menu registration is now handled in AdminMenu trait).
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
	 * @return Container
	 */
	public function get_container(): Container {
		return $this->container;
	}
}
