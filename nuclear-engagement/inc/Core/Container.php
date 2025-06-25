<?php
declare(strict_types=1);
/**
 * File: includes/Container.php

 * Dependency Injection Container
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple service container for dependency injection
 */
class Container {
	/**
	 * @var self|null Singleton instance
	 */
	private static ?self $instance = null;

	/**
	 * @var array Stored service instances
	 */
	private array $services = array();

	/**
	 * @var array Service factory callbacks
	 */
	private array $factories = array();

	/**
	 * Get the singleton instance
	 *
	 * @return self
	 */
	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a service factory
	 *
	 * @param string   $id Service identifier
	 * @param callable $factory Factory callback
	 */
	public function register( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Get a service instance
	 *
	 * @param string $id Service identifier
	 * @return mixed Service instance
	 * @throws \RuntimeException If service not registered
	 */
	public function get( string $id ) {
		if ( ! isset( $this->services[ $id ] ) ) {
			if ( ! isset( $this->factories[ $id ] ) ) {
				throw new \RuntimeException( "Service {$id} not registered" );
			}
			$this->services[ $id ] = $this->factories[ $id ]( $this );
		}
		return $this->services[ $id ];
	}

	/**
	 * Check if a service is registered
	 *
	 * @param string $id Service identifier
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Reset the container (mainly for testing)
	 */
	public function reset(): void {
		$this->services  = array();
		$this->factories = array();
	}
}
