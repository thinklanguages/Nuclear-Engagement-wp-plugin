<?php
/**
 * File: front/FrontClass.php
 *
 * Split into three traits for clarity:
 *   – AssetsTrait   → enqueue styles / scripts
 *   – RestTrait     → REST-API receive / send helpers
 *   – ShortcodesTrait → quiz & summary shortcodes + auto-insertion
 *
 * No logic removed or renamed; every public method that existed before is still
 * present (some live inside traits).  Drop the trait files into
 * `front/traits/` and replace this wrapper class.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Utils;
use NuclearEngagement\SettingsRepository;

/* ───── Load traits ───── */
require_once __DIR__ . '/traits/AssetsTrait.php';
require_once __DIR__ . '/traits/RestTrait.php';
require_once __DIR__ . '/traits/ShortcodesTrait.php';

class FrontClass {

	use AssetsTrait;
	use RestTrait;
	use ShortcodesTrait;

	/** @var string */
	private $plugin_name;
	/** @var string */
	private $version;
	/** @var Utils */
	private $utils;
	/** @var SettingsRepository */
	private $settings_repository;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name The plugin name.
	 * @param string $version The plugin version.
	 * @param SettingsRepository $settings_repository The settings repository.
	 */
	public function __construct( $plugin_name, $version, SettingsRepository $settings_repository ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->utils = new Utils();
		$this->settings_repository = $settings_repository;
	}

	/** Allow traits to read internal utils object */
	/**
	 * Get the Utils instance.
	 *
	 * @return Utils
	 */
	public function nuclen_get_utils() : Utils {
		return $this->utils;
	}

	/**
	 * Get the SettingsRepository instance.
	 *
	 * @return SettingsRepository
	 */
	public function get_settings_repository() {
		return $this->settings_repository;
	}
}
