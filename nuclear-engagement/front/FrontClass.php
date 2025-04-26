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

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->utils       = new Utils();
	}

	/** Allow traits to read internal utils object */
	public function nuclen_get_utils() : Utils {
		return $this->utils;
	}
}
