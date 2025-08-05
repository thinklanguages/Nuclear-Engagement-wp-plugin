<?php
/**
 * ServiceRegistry.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service Registry for Dependency Injection
 *
 * Provides a centralized registry for services to improve dependency injection
 * and reduce tight coupling between classes.
 */
class ServiceRegistry {

	private static ?self $instance = null;
	private array $services        = array();
	private array $singletons      = array();

	/**
	 * Get singleton instance.
	 */
	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a service factory.
	 *
	 * @param string   $id Service identifier.
	 * @param callable $factory Service factory function.
	 * @param bool     $singleton Whether service should be singleton.
	 */
	public function register( string $id, callable $factory, bool $singleton = true ): void {
		$this->services[ $id ] = array(
			'factory'   => $factory,
			'singleton' => $singleton,
		);
	}

	/**
	 * Get service instance.
	 *
	 * @param string $id Service identifier.
	 * @return mixed Service instance.
	 * @throws \RuntimeException If service not found.
	 */
	public function get( string $id ) {
		if ( ! isset( $this->services[ $id ] ) ) {
			throw new \RuntimeException( "Service '{$id}' not found in registry" );
		}

		$service_config = $this->services[ $id ];

		// Return singleton instance if already created.
		if ( $service_config['singleton'] && isset( $this->singletons[ $id ] ) ) {
			return $this->singletons[ $id ];
		}

		// Create new instance.
		$instance = call_user_func( $service_config['factory'] );

		// Store singleton instance.
		if ( $service_config['singleton'] ) {
			$this->singletons[ $id ] = $instance;
		}

		return $instance;
	}

	/**
	 * Check if service is registered.
	 *
	 * @param string $id Service identifier.
	 * @return bool Whether service is registered.
	 */
	public function has( string $id ): bool {
		return isset( $this->services[ $id ] );
	}

	/**
	 * Initialize core services.
	 */
	public static function initCoreServices(): void {
		$registry = self::getInstance();

		// Settings Repository.
		$registry->register(
			'settings',
			function () {
				return new \NuclearEngagement\Core\SettingsRepository();
			}
		);

		// Cache Manager.
		$registry->register(
			'cache',
			function () {
				return CacheManager::class; // Static class.
			}
		);

		// Post Repository.
		$registry->register(
			'post_repository',
			function () use ( $registry ) {
				return new \NuclearEngagement\Repositories\PostRepository();
			}
		);

		// Content Storage Service.
		$registry->register(
			'content_storage',
			function () use ( $registry ) {
				return new \NuclearEngagement\Services\ContentStorageService(
					$registry->get( 'settings' )
				);
			}
		);
	}

	/**
	 * Get service with fallback to manual instantiation.
	 *
	 * Provides backward compatibility while encouraging DI usage.
	 *
	 * @param string        $id Service identifier.
	 * @param callable|null $fallback Fallback factory function.
	 * @return mixed Service instance.
	 */
	public function getWithFallback( string $id, ?callable $fallback = null ) {
		if ( $this->has( $id ) ) {
			return $this->get( $id );
		}

		if ( $fallback ) {
			return call_user_func( $fallback );
		}

		throw new \RuntimeException( "Service '{$id}' not found and no fallback provided" );
	}

	/**
	 * Clear all singleton instances (for testing).
	 */
	public function clearSingletons(): void {
		$this->singletons = array();
	}
}
