<?php
/**
 * Admin.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin
 */

declare(strict_types=1);
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

use NuclearEngagement\Utils\Utils;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Admin\Traits\AdminMetaboxes;
use NuclearEngagement\Admin\Traits\AdminAjax;
use NuclearEngagement\Admin\Traits\AdminMenu;
use NuclearEngagement\Admin\Traits\AdminAssets;

class Admin {

	use AdminMetaboxes;
	use AdminAjax;
	use AdminMenu;
	use AdminAssets;

	private $plugin_name;
	private $version;
	private $utils;
	private $settings_repository;
	/** @var ServiceContainer */
	private $container;

	/**
	 * Constructor
	 *
	 * @param string             $plugin_name
	 * @param string             $version
	 * @param SettingsRepository $settings_repository
	 */
	public function __construct( $plugin_name, $version, SettingsRepository $settings_repository, ServiceContainer $container ) {
		$this->plugin_name         = $plugin_name;
		$this->version             = $version;
		$this->utils               = new Utils();
		$this->settings_repository = $settings_repository;
				$this->container   = $container;

		// Meta-boxes handled by module loader.

		// Note: Hooks are registered via the loader system in Plugin.php
		// This avoids duplicate registration and ensures proper timing.

		// Auto-generation on publish is now handled by AutoGenerationService.
		// The service is registered in the Plugin class and handles its own hooks.
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
	public function nuclen_get_settings_repository() {
		return $this->settings_repository;
	}

	/**
	 * Get the container instance.
	 *
	 * @return \NuclearEngagement\Core\ServiceContainer
	 */
	protected function get_container() {
		return $this->container;
	}
}
