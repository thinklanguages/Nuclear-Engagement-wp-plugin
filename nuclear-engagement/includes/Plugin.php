<?php
namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Admin\Admin;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Admin\Onboarding;

class Plugin {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->version     = defined( 'NUCLEN_PLUGIN_VERSION' ) ? NUCLEN_PLUGIN_VERSION : '1.0.0';
		$this->plugin_name = 'nuclear-engagement';

		$this->nuclen_load_dependencies();
		$this->nuclen_define_admin_hooks();
		$this->nuclen_define_public_hooks();
	}

	private function nuclen_load_dependencies() {
		$this->loader = new Loader();
	}

	/**
	 * Defines admin-side hooks for the FREE plugin.
	 * (AI-generation hooks were removed; they’ll live in the Pro plugin.)
	 */
	private function nuclen_define_admin_hooks() {
		$plugin_admin = new Admin( $this->nuclen_get_plugin_name(), $this->nuclen_get_version() );

		// Enqueue admin CSS/JS (no AI references).
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_styles' );
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'wp_enqueue_scripts' );
		$this->loader->nuclen_add_action( 'admin_enqueue_scripts', $plugin_admin, 'nuclen_enqueue_dashboard_styles' );

		// Admin menu (just Dashboard and Settings for free)
		$this->loader->nuclen_add_action( 'admin_menu', $plugin_admin, 'nuclen_add_admin_menu' );

		// Onboarding pointers
		$onboarding = new Onboarding();
		$onboarding->nuclen_register_hooks();
	}

	/**
	 * Defines front-end hooks for the FREE plugin.
	 */
	private function nuclen_define_public_hooks() {
		$plugin_public = new FrontClass( $this->nuclen_get_plugin_name(), $this->nuclen_get_version() );

		// CSS/JS for quizzes & summaries
		$this->loader->nuclen_add_action( 'wp_enqueue_scripts', $plugin_public, 'wp_enqueue_styles' );
		$this->loader->nuclen_add_action( 'wp_enqueue_scripts', $plugin_public, 'wp_enqueue_scripts' );

		// Shortcodes
		$this->loader->nuclen_add_action( 'init', $plugin_public, 'nuclen_register_quiz_shortcode' );
		$this->loader->nuclen_add_action( 'init', $plugin_public, 'nuclen_register_summary_shortcode' );

		// Automatic insertion of shortcodes if set in the settings
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
}
