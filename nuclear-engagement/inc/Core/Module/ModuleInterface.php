<?php
/**
 * ModuleInterface.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core_Module
 */

declare(strict_types=1);

namespace NuclearEngagement\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for plugin modules.
 *
 * @package NuclearEngagement\Core\Module
 */
interface ModuleInterface {
	/**
	 * Get module name.
	 */
	public function getName(): string;

	/**
	 * Get module version.
	 */
	public function getVersion(): string;

	/**
	 * Get module dependencies.
	 *
	 * @return string[] Array of required module names
	 */
	public function getDependencies(): array;

	/**
	 * Initialize the module.
	 */
	public function init(): void;

	/**
	 * Check if module is enabled.
	 */
	public function isEnabled(): bool;

	/**
	 * Get module configuration.
	 */
	public function getConfig(): array;
}
