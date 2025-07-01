<?php
declare(strict_types=1);
/**
 * File: inc/Core/Container.php
 *
 * Dependency injection container.
 *
 * @package NuclearEngagement\Core
 */

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple dependency injection container.
 */
class Container {
	
	/** @var array Service definitions */
	private array $services = array();
	
	/** @var array Singleton instances */
	private array $instances = array();
	
	/** @var array Service aliases */
	private array $aliases = array();
	
	/** @var Container */
	private static ?Container $instance = null;
	
	/**
	 * Get container instance.
	 */
	public static function get_instance(): Container {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Register a service.
	 *
	 * @param string   $id       Service ID.
	 * @param callable $factory  Service factory.
	 * @param bool     $singleton Whether to treat as singleton.
	 */
	public function register( string $id, callable $factory, bool $singleton = true ): void {
		$this->services[ $id ] = array(
			'factory' => $factory,
			'singleton' => $singleton,
		);
	}
	
	/**
	 * Register a singleton service.
	 *
	 * @param string   $id      Service ID.
	 * @param callable $factory Service factory.
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->register( $id, $factory, true );
	}
	
	/**
	 * Register a service instance.
	 *
	 * @param string $id       Service ID.
	 * @param mixed  $instance Service instance.
	 */
	public function instance( string $id, $instance ): void {
		$this->instances[ $id ] = $instance;
	}
	
	/**
	 * Register an alias.
	 *
	 * @param string $alias Service alias.
	 * @param string $id    Actual service ID.
	 */
	public function alias( string $alias, string $id ): void {
		$this->aliases[ $alias ] = $id;
	}
	
	/**
	 * Get service from container.
	 *
	 * @param string $id Service ID.
	 * @return mixed Service instance.
	 * @throws \InvalidArgumentException If service not found.
	 */
	public function get( string $id ) {
		// Resolve alias
		$id = $this->aliases[ $id ] ?? $id;
		
		// Return existing instance if singleton
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}
		
		// Check if service is registered
		if ( ! isset( $this->services[ $id ] ) ) {
			throw new \InvalidArgumentException( "Service '{$id}' not found in container." );
		}
		
		$service = $this->services[ $id ];
		$instance = $service['factory']( $this );
		
		// Store instance if singleton
		if ( $service['singleton'] ) {
			$this->instances[ $id ] = $instance;
		}
		
		return $instance;
	}
	
	/**
	 * Check if service exists.
	 *
	 * @param string $id Service ID.
	 * @return bool Whether service exists.
	 */
	public function has( string $id ): bool {
		$id = $this->aliases[ $id ] ?? $id;
		return isset( $this->services[ $id ] ) || isset( $this->instances[ $id ] );
	}
	
	/**
	 * Create instance with dependency injection.
	 *
	 * @param string $class_name Class name to instantiate.
	 * @param array  $parameters Additional parameters.
	 * @return mixed Class instance.
	 * @throws \ReflectionException If class reflection fails.
	 */
	public function make( string $class_name, array $parameters = array() ) {
		$reflection = new \ReflectionClass( $class_name );
		
		if ( ! $reflection->isInstantiable() ) {
			throw new \InvalidArgumentException( "Class '{$class_name}' is not instantiable." );
		}
		
		$constructor = $reflection->getConstructor();
		
		if ( $constructor === null ) {
			return new $class_name();
		}
		
		$dependencies = $this->resolve_dependencies( $constructor, $parameters );
		
		return $reflection->newInstanceArgs( $dependencies );
	}
	
	/**
	 * Resolve constructor dependencies.
	 *
	 * @param \ReflectionMethod $constructor Constructor method.
	 * @param array             $parameters  Additional parameters.
	 * @return array Resolved dependencies.
	 * @throws \ReflectionException If dependency resolution fails.
	 */
	private function resolve_dependencies( \ReflectionMethod $constructor, array $parameters = array() ): array {
		$dependencies = array();
		
		foreach ( $constructor->getParameters() as $parameter ) {
			$name = $parameter->getName();
			
			// Use provided parameter if available
			if ( array_key_exists( $name, $parameters ) ) {
				$dependencies[] = $parameters[ $name ];
				continue;
			}
			
			// Try to resolve type-hinted dependency
			$type = $parameter->getType();
			
			if ( $type && ! $type->isBuiltin() && $type instanceof \ReflectionNamedType ) {
				$type_name = $type->getName();
				
				if ( $this->has( $type_name ) ) {
					$dependencies[] = $this->get( $type_name );
					continue;
				}
				
				// Try to auto-wire
				if ( class_exists( $type_name ) || interface_exists( $type_name ) ) {
					$dependencies[] = $this->make( $type_name );
					continue;
				}
			}
			
			// Use default value if available
			if ( $parameter->isDefaultValueAvailable() ) {
				$dependencies[] = $parameter->getDefaultValue();
				continue;
			}
			
			// Check if parameter is nullable
			if ( $parameter->allowsNull() ) {
				$dependencies[] = null;
				continue;
			}
			
			throw new \InvalidArgumentException( "Cannot resolve dependency '{$name}' for class '{$constructor->getDeclaringClass()->getName()}'." );
		}
		
		return $dependencies;
	}
}