<?php
/**
 * File: admin/Admin.php
 *
 * Main Admin Class for Nuclear Engagement Plugin
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Utils;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Container;

require_once __DIR__ . '/trait-admin-metaboxes.php';
require_once __DIR__ . '/trait-admin-ajax.php';
require_once __DIR__ . '/trait-admin-menu.php';
require_once __DIR__ . '/trait-admin-assets.php';

class Admin {

	use Admin_Metaboxes;
	use Admin_Ajax;
	use Admin_Menu;
	use Admin_Assets;

	private $plugin_name;
	private $version;
	private $utils;
	private $settings_repository;

	/**
	 * Constructor
	 *
	 * @param string $plugin_name
	 * @param string $version
	 * @param SettingsRepository $settings_repository
	 */
	public function __construct( $plugin_name, $version, SettingsRepository $settings_repository ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->utils       = new Utils();
		$this->settings_repository = $settings_repository;

		// Meta‑boxes
		add_action( 'add_meta_boxes', array( $this, 'nuclen_add_quiz_data_meta_box' ) );
		add_action( 'save_post',      array( $this, 'nuclen_save_quiz_data_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'nuclen_add_summary_data_meta_box' ) );
		add_action( 'save_post',      array( $this, 'nuclen_save_summary_data_meta' ) );

		// AJAX & assets
		add_action( 'wp_ajax_nuclen_fetch_app_updates', array( $this, 'nuclen_fetch_app_updates' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wp_enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'nuclen_enqueue_dashboard_styles' ) );

		// Admin menu
		add_action( 'admin_menu', array( $this, 'nuclen_add_admin_menu' ) );

		// Auto‑generation on publish is now handled by AutoGenerationService
		// The service is registered in the Plugin class and handles its own hooks
	}

	/* --------------------------------‑ getters ---------------------------- */

	public function nuclen_get_plugin_name() {
		return $this->plugin_name;
	}
	public function nuclen_get_version() {
		return $this->version;
	}
	public function nuclen_get_utils() {
		return $this->utils;
	}

	/**
	 * Get the settings repository instance.
	 *
	 * @return SettingsRepository
	 */
		/**
	 * Get the settings repository instance.
	 *
	 * @return SettingsRepository
	 */
	public function get_settings_repository() {
		return $this->settings_repository;
	}

	/**
	 * Get the container instance.
	 *
	 * @return \NuclearEngagement\Container
	 */
	protected function get_container() {
		return Container::getInstance();
	}
}