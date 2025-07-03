<?php
declare(strict_types=1);

namespace NuclearEngagement\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for plugin modules.
 * 
 * @package NuclearEngagement\Core\Module
 */
abstract class AbstractModule implements ModuleInterface {
	protected string $name;
	protected string $version = '1.0.0';
	protected array $dependencies = [];
	protected array $config = [];
	protected bool $initialized = false;
	
	public function __construct(string $name, array $config = []) {
		$this->name = $name;
		$this->config = $config;
	}
	
	public function getName(): string {
		return $this->name;
	}
	
	public function getVersion(): string {
		return $this->version;
	}
	
	public function getDependencies(): array {
		return $this->dependencies;
	}
	
	public function getConfig(): array {
		return $this->config;
	}
	
	public function isEnabled(): bool {
		// Check if module is enabled in settings
		$enabled_modules = get_option('nuclen_enabled_modules', []);
		return in_array($this->name, $enabled_modules, true);
	}
	
	public function init(): void {
		if ($this->initialized) {
			return;
		}
		
		if (!$this->isEnabled()) {
			return;
		}
		
		$this->validateDependencies();
		$this->registerHooks();
		$this->initialized = true;
	}
	
	/**
	 * Register WordPress hooks for this module.
	 */
	abstract protected function registerHooks(): void;
	
	/**
	 * Validate that all dependencies are available.
	 * 
	 * @throws \RuntimeException If dependencies are not met
	 */
	protected function validateDependencies(): void {
		foreach ($this->dependencies as $dependency) {
			if (!$this->isDependencyAvailable($dependency)) {
				throw new \RuntimeException(
					sprintf('Module %s requires %s but it is not available', $this->name, $dependency)
				);
			}
		}
	}
	
	/**
	 * Check if a dependency is available.
	 */
	protected function isDependencyAvailable(string $dependency): bool {
		// For now, just check if the class exists
		// Could be enhanced to check specific module registry
		return class_exists($dependency) || function_exists($dependency);
	}
	
	/**
	 * Get module configuration value.
	 */
	protected function getConfigValue(string $key, $default = null) {
		return $this->config[$key] ?? $default;
	}
	
	/**
	 * Log module messages.
	 */
	protected function log(string $message, string $level = 'info'): void {
		if (class_exists('NuclearEngagement\Services\LoggingService')) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf('[%s] %s', $this->name, $message),
				$level
			);
		}
	}
}