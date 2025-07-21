<?php
/**
 * ModuleRegistry.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core_Module
 */

declare(strict_types=1);

namespace NuclearEngagement\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry for managing plugin modules.
 *
 * @package NuclearEngagement\Core\Module
 */
final class ModuleRegistry {
	private static ?self $instance     = null;
	private array $modules             = array();
	private array $initialized_modules = array();

	private function __construct() {}

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a module.
	 */
	public function register( ModuleInterface $module ): void {
		$name = $module->getName();

		if ( isset( $this->modules[ $name ] ) ) {
			throw new \RuntimeException( "Module {$name} is already registered" );
		}

		$this->modules[ $name ] = $module;
	}

	/**
	 * Initialize all registered modules.
	 */
	public function initializeAll(): void {
		// Sort modules by dependencies.
		$sorted_modules = $this->topologicalSort();

		foreach ( $sorted_modules as $module ) {
			$this->initializeModule( $module );
		}
	}

	/**
	 * Initialize a specific module.
	 */
	public function initializeModule( ModuleInterface $module ): void {
		$name = $module->getName();

		if ( in_array( $name, $this->initialized_modules, true ) ) {
			return; // Already initialized.
		}

		// Initialize dependencies first.
		foreach ( $module->getDependencies() as $dependency ) {
			if ( isset( $this->modules[ $dependency ] ) ) {
				$this->initializeModule( $this->modules[ $dependency ] );
			}
		}

		try {
			$module->init();
			$this->initialized_modules[] = $name;
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[Nuclear Engagement] Failed to initialize module {$name}: " . $e->getMessage() );
		}
	}

	/**
	 * Get a registered module.
	 */
	public function getModule( string $name ): ?ModuleInterface {
		return $this->modules[ $name ] ?? null;
	}

	/**
	 * Get all registered modules.
	 *
	 * @return ModuleInterface[]
	 */
	public function getAllModules(): array {
		return $this->modules;
	}

	/**
	 * Check if a module is registered.
	 */
	public function hasModule( string $name ): bool {
		return isset( $this->modules[ $name ] );
	}

	/**
	 * Check if a module is initialized.
	 */
	public function isInitialized( string $name ): bool {
		return in_array( $name, $this->initialized_modules, true );
	}

	/**
	 * Sort modules by dependencies (topological sort).
	 *
	 * @return ModuleInterface[]
	 */
	private function topologicalSort(): array {
		$sorted   = array();
		$visited  = array();
		$visiting = array();

		foreach ( $this->modules as $module ) {
			if ( ! isset( $visited[ $module->getName() ] ) ) {
				$this->visit( $module, $visited, $visiting, $sorted );
			}
		}

		return array_reverse( $sorted );
	}

	/**
	 * Visit module for topological sort.
	 */
	private function visit(
		ModuleInterface $module,
		array &$visited,
		array &$visiting,
		array &$sorted
	): void {
		$name = $module->getName();

		if ( isset( $visiting[ $name ] ) ) {
			throw new \RuntimeException( "Circular dependency detected for module {$name}" );
		}

		if ( isset( $visited[ $name ] ) ) {
			return;
		}

		$visiting[ $name ] = true;

		foreach ( $module->getDependencies() as $dependency ) {
			if ( isset( $this->modules[ $dependency ] ) ) {
				$this->visit( $this->modules[ $dependency ], $visited, $visiting, $sorted );
			}
		}

		unset( $visiting[ $name ] );
		$visited[ $name ] = true;
		$sorted[]         = $module;
	}
}
