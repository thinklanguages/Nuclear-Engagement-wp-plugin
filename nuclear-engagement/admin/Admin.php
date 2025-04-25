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

require_once __DIR__ . '/trait-admin-metaboxes.php';
require_once __DIR__ . '/trait-admin-ajax.php';
require_once __DIR__ . '/trait-admin-menu.php';
require_once __DIR__ . '/trait-admin-assets.php';
require_once __DIR__ . '/trait-admin-autogenerate.php';

class Admin {

	use Admin_Metaboxes;
	use Admin_Ajax;
	use Admin_Menu;
	use Admin_Assets;
	use Admin_AutoGenerate;

	private $plugin_name;
	private $version;
	private $utils;

	/**
	 * Constructor
	 *
	 * @param string $plugin_name
	 * @param string $version
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->utils       = new Utils();

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

		// Auto‑generation on publish
		add_action( 'transition_post_status', array( $this, 'nuclen_auto_generate_on_publish' ), 10, 3 );

		// Register the WP‑Cron polling hook
		$this->nuclen_register_autogen_cron_hook();
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
}