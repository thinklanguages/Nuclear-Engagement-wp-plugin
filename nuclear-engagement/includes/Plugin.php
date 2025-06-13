<?php
/**
 * File: includes/Plugin.php
 *
 * Main Plugin Class for Nuclear Engagement
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Admin\Admin;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Admin\Onboarding;
use NuclearEngagement\Defaults;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Container;
use NuclearEngagement\OptinData;
use NuclearEngagement\Services\{GenerationService, RemoteApiService, ContentStorageService, PointerService, PostsQueryService, AutoGenerationService};
use NuclearEngagement\Admin\Controller\Ajax\{GenerateController, UpdatesController, PointerController, PostsCountController};
use NuclearEngagement\Front\Controller\Rest\ContentController;

class Plugin {

	protected $loader;
	protected $plugin_name;
	protected $version;
	protected $settings_repository;
	
	/**
	 * @var Container
	 */
        private Container $container;

        public function __construct() {
                $this->version     = defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0';
                $this->plugin_name = 'nuclear-engagement';
		
		// Initialize settings repository with defaults
		$defaults = Defaults::nuclen_get_default_settings();
		$this->settings_repository = SettingsRepository::get_instance( $defaults );

               /* ───── Ensure DB table exists on activation ───── */
               register_activation_hook(
                       dirname( dirname( __FILE__ ) ) . '/nuclear-engagement.php',
                       function() {
                               if ( ! \NuclearEngagement\OptinData::table_exists() ) {
                                       \NuclearEngagement\OptinData::maybe_create_table();
                               }
                       }
               );

                $this->nuclen_load_dependencies();
                $this->initializeContainer();
                // Register hooks for auto-generation on every request
                $autoGen = $this->container->get('auto_generation_service');
                $autoGen->register_hooks();
                $this->nuclen_define_admin_hooks();
                $this->nuclen_define_public_hooks();
        }

	/* ─────────────────────────────────────────────
	   Dependencies & Loader
	──────────────────────────────────────────── */
        private function nuclen_load_dependencies() {

                /* ► Ensure OptinData hooks are registered */
                OptinData::init();

                // TOC module
                require_once plugin_dir_path( __FILE__ ) . '../modules/toc/loader.php';

                $this->loader = new Loader();
        }
	
	/**
	 * Initialize the dependency injection container
	 */
	private function initializeContainer(): void {
		$this->container = Container::getInstance();
		
		// Register services
		$this->container->register('settings', function() {
			return $this->settings_repository;
		});
		
		$this->container->register('remote_api', function($c) {
			return new RemoteApiService($c->get('settings'));
		});
		
                $this->container->register('content_storage', function($c) {
                        return new ContentStorageService($c->get('settings'));
                });

                $this->container->register('auto_generation_service', function($c) {
                        return new AutoGenerationService(
                                $c->get('settings'),
                                $c->get('remote_api'),
                                $c->get('content_storage')
                        );
                });
		
		$this->container->register('generation_service', function($c) {
			return new GenerationService(
				$c->get('settings'),
				$c->get('remote_api'),
				$c->get('content_storage')
			);
		});
		
		$this->container->register('pointer_service', function() {
			return new PointerService();
		});
		
		$this->container->register('posts_query_service', function() {
			return new PostsQueryService();
		});
		
		// Register controllers
		$this->container->register('generate_controller', function($c) {
			return new GenerateController($c->get('generation_service'));
		});
		
		$this->container->register('updates_controller', function($c) {
			return new UpdatesController(
				$c->get('remote_api'),
				$c->get('content_storage')
			);
		});
		
		$this->container->register('pointer_controller', function($c) {
			return new PointerController($c->get('pointer_service'));
		});
		
		$this->container->register('posts_count_controller', function($c) {
			return new PostsCountController($c->get('posts_query_service'));
		});
		
		$this->container->register('content_controller', function($c) {
			return new ContentController($c->get('content_storage'));
		});
	}

	/* ─────────────────────────────────────────────
	   Admin-side hooks
	──────────────────────────────────────────── */
	private function nuclen_define_admin_hooks() {

		$plugin_admin = new Admin( $this->nuclen_get_plugin_name(), $this->nuclen_get_version(), $this->settings_repository );

		// Enqueue
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_styles' );
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_scripts' );
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_dashboard_styles' );
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_generate_page_scripts' );

		// Admin Menu
		$this->loader->nuclen_add_action( 'admin_menu', $plugin_admin, 'nuclen_add_admin_menu' );

		// AJAX - now using controllers
		$generateController = $this->container->get('generate_controller');
		$updatesController = $this->container->get('updates_controller');
		$postsCountController = $this->container->get('posts_count_controller');
		
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_trigger_generation', $generateController, 'handle' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_fetch_app_updates', $updatesController, 'handle' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_get_posts_count', $postsCountController, 'handle' );

		// Setup actions
		$setup = new \NuclearEngagement\Admin\Setup();
		$this->loader->nuclen_add_action( 'admin_menu',                      $setup, 'nuclen_add_setup_page' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_connect_app',   $setup, 'nuclen_handle_connect_app' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_generate_app_password', $setup, 'nuclen_handle_generate_app_password' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_reset_api_key',          $setup, 'nuclen_handle_reset_api_key' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_reset_wp_app_connection', $setup, 'nuclen_handle_reset_wp_app_connection' );

		// Onboarding pointers - use controller
		$onboarding = new Onboarding();
		$onboarding->nuclen_register_hooks();
		
		// Replace pointer dismiss with controller
		$pointerController = $this->container->get('pointer_controller');
		remove_action('wp_ajax_nuclen_dismiss_pointer', [$onboarding, 'nuclen_ajax_dismiss_pointer']);
		$this->loader->nuclen_add_action('wp_ajax_nuclen_dismiss_pointer', $pointerController, 'dismiss');

		/* Opt-in CSV export (proxy ensures class is loaded) */
		$this->loader->nuclen_add_action( 'admin_post_nuclen_export_optin', $this, 'nuclen_export_optin_proxy' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_export_optin',    $this, 'nuclen_export_optin_proxy' );
	}

	/**
	 * Proxy: make sure OptinData is available, then stream CSV (exits).
	 */
        public function nuclen_export_optin_proxy(): void {
                OptinData::handle_export();
        }

	/* ─────────────────────────────────────────────
	   Front-end hooks
	──────────────────────────────────────────── */
	private function nuclen_define_public_hooks() {
		$plugin_public = new FrontClass( $this->nuclen_get_plugin_name(), $this->nuclen_get_version(), $this->settings_repository );

		$this->loader->nuclen_add_action( 'wp_enqueue_scripts', $plugin_public, 'wp_enqueue_styles' );
		$this->loader->nuclen_add_action( 'wp_enqueue_scripts', $plugin_public, 'wp_enqueue_scripts' );
		
		// REST API - use controller
		add_action('rest_api_init', function() {
			$contentController = $this->container->get('content_controller');
			register_rest_route('nuclear-engagement/v1', '/receive-content', [
				'methods' => 'POST',
				'callback' => [$contentController, 'handle'],
				'permission_callback' => [$contentController, 'permissions'],
			]);
		});
		
		$this->loader->nuclen_add_action( 'init',               $plugin_public, 'nuclen_register_quiz_shortcode' );
		$this->loader->nuclen_add_action( 'init',               $plugin_public, 'nuclen_register_summary_shortcode' );
		$this->loader->nuclen_add_filter( 'the_content',        $plugin_public, 'nuclen_auto_insert_shortcodes', 50 );
	}

	/* ─────────────────────────────────────────────
	   Boilerplate
	──────────────────────────────────────────── */
	public function nuclen_run() {
		$this->loader->nuclen_run();
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

	function load_nuclear_engagement_admin_display() {
		include_once plugin_dir_path( __FILE__ ) . 'admin/partials/nuclen-admin-display.php';
	}
}