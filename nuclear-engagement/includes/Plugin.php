<?php
/**
 * File: includes/Plugin.php
 *
 * Plugin Class for Nuclear Engagement
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use NuclearEngagement\Admin\Admin;
use NuclearEngagement\Front\FrontClass;
// Import the Onboarding class
use NuclearEngagement\Admin\Onboarding;

class Plugin {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if ( defined( 'NUCLEN_PLUGIN_VERSION' ) ) {
			$this->version = NUCLEN_PLUGIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'nuclear-engagement';

		$this->nuclen_load_dependencies();
		$this->nuclen_define_admin_hooks();
		$this->nuclen_define_public_hooks();
	}

	private function nuclen_load_dependencies() {
		$this->loader = new Loader();
	}

	private function nuclen_define_admin_hooks() {

		$plugin_admin = new Admin( $this->nuclen_get_plugin_name(), $this->nuclen_get_version() );

		// Enqueue
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_styles' );
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_scripts' );
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_dashboard_styles' );
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_generate_page_scripts' );

		// Admin Menu
		$this->loader->nuclen_add_action( 'admin_menu', $plugin_admin, 'nuclen_add_admin_menu' );

		// AJAX
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_trigger_generation', $plugin_admin, 'nuclen_handle_trigger_generation' );
		$this->loader->nuclen_add_action( 'wp_ajax_nuclen_get_posts_count', $plugin_admin, 'nuclen_get_posts_count' );

		// Setup actions
		$setup = new \NuclearEngagement\Admin\Setup();
		$this->loader->nuclen_add_action( 'admin_menu', $setup, 'nuclen_add_setup_page' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_connect_app', $setup, 'nuclen_handle_connect_app' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_generate_app_password', $setup, 'nuclen_handle_generate_app_password' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_reset_api_key', $setup, 'nuclen_handle_reset_api_key' );
		$this->loader->nuclen_add_action( 'admin_post_nuclen_reset_wp_app_connection', $setup, 'nuclen_handle_reset_wp_app_connection' );

		// Instantiate Onboarding and register pointer hooks
		$onboarding = new Onboarding();
		// Option 1: let Onboarding handle its own add_action calls
		$onboarding->nuclen_register_hooks();

		// Option 2: or integrate with your loader:
		// $this->loader->nuclen_add_action('admin_enqueue_scripts', $onboarding, 'enqueue_nuclen_onboarding_pointers');
	}

	private function nuclen_define_public_hooks() {
		$plugin_public = new FrontClass( $this->nuclen_get_plugin_name(), $this->nuclen_get_version() );

		$this->loader->nuclen_add_action( 'wp_enqueue_scripts', $plugin_public, 'wp_enqueue_styles' );
		$this->loader->nuclen_add_action( 'wp_enqueue_scripts', $plugin_public, 'wp_enqueue_scripts' );
		$this->loader->nuclen_add_action( 'rest_api_init', $plugin_public, 'nuclen_register_content_endpoint' );
		$this->loader->nuclen_add_action( 'init', $plugin_public, 'nuclen_register_quiz_shortcodeodes' );
		$this->loader->nuclen_add_action( 'init', $plugin_public, 'nuclen_register_summary_shortcode' );
		$this->loader->nuclen_add_filter( 'the_content', $plugin_public, 'nuclen_auto_insert_shortcodes' );
	}

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

	function load_nuclear_engagement_admin_display() {
		include_once plugin_dir_path( __FILE__ ) . 'admin/partials/nuclen-admin-display.php';
	}
}
