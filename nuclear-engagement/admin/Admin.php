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

/**
 * Main Admin Class for Nuclear Engagement Plugin.
 *
 * @package NuclearEngagement\Admin
 */
class Admin {

	use AdminMetaboxes;
	use AdminAjax;
	use AdminMenu;
	use AdminAssets;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Utils instance.
	 *
	 * @var Utils
	 */
	private $utils;

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepository
	 */
	private $settings_repository;

	/**
	 * Service container instance.
	 *
	 * @var ServiceContainer
	 */
	private $container;

	/**
	 * Constructor.
	 *
	 * @param string             $plugin_name         Plugin name.
	 * @param string             $version            Plugin version.
	 * @param SettingsRepository $settings_repository Settings repository instance.
	 * @param ServiceContainer   $container          Service container instance.
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

	/**
	 * Get plugin name.
	 *
	 * @return string
	 */
	public function nuclen_get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public function nuclen_get_version() {
		return $this->version;
	}

	/**
	 * Get utils instance.
	 *
	 * @return Utils
	 */
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

	/**
	 * Handle early redirects for the Tasks page.
	 * Called on admin_init to ensure redirects happen before headers are sent.
	 */
	public function nuclen_handle_tasks_early_redirects() {
		// Only process on admin pages.
		if ( ! is_admin() ) {
			return;
		}

		// Check if we're on the tasks page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed for read-only check
		if ( isset( $_GET['page'] ) && 'nuclear-engagement-tasks' === $_GET['page'] ) {
			$settings_repo = $this->nuclen_get_settings_repository();
			$tasks         = new \NuclearEngagement\Admin\Tasks( $settings_repo, $this->container );
			$tasks->handle_early_redirects();
		}
	}
}
