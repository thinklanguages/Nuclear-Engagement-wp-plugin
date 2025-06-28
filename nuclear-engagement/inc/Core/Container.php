<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dependency Injection Container.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class Container {
	/**
	 * Service bindings.
	 *
	 * @var array<string, array{factory: callable, singleton: bool, instance?: object}>
	 */
	private static array $bindings = [];

	/**
	 * Service instances (singletons).
	 *
	 * @var array<string, object>
	 */
	private static array $instances = [];

	/**
	 * Interface to implementation mappings.
	 *
	 * @var array<string, string>
	 */
	private static array $interfaces = [];

	/**
	 * Service aliases.
	 *
	 * @var array<string, string>
	 */
	private static array $aliases = [];

	/**
	 * Circular dependency tracking.
	 *
	 * @var array<string, bool>
	 */
	private static array $resolving = [];

	/**
	 * Bind a service to the container.
	 *
	 * @param string   $abstract  Service identifier.
	 * @param callable $factory   Factory function to create the service.
	 * @param bool     $singleton Whether to treat as singleton.
	 */
	public static function bind( string $abstract, callable $factory, bool $singleton = false ): void {
		self::$bindings[$abstract] = [
			'factory'   => $factory,
			'singleton' => $singleton,
		];
	}

	/**
	 * Bind a singleton service.
	 *
	 * @param string   $abstract Service identifier.
	 * @param callable $factory  Factory function.
	 */
	public static function singleton( string $abstract, callable $factory ): void {
		self::bind( $abstract, $factory, true );
	}

	/**
	 * Bind an interface to an implementation.
	 *
	 * @param string $interface      Interface name.
	 * @param string $implementation Implementation class name.
	 */
	public static function interface( string $interface, string $implementation ): void {
		self::$interfaces[$interface] = $implementation;
	}

	/**
	 * Create an alias for a service.
	 *
	 * @param string $alias    Alias name.
	 * @param string $abstract Original service identifier.
	 */
	public static function alias( string $alias, string $abstract ): void {
		self::$aliases[$alias] = $abstract;
	}

	/**
	 * Resolve a service from the container.
	 *
	 * @param string $abstract Service identifier.
	 * @return object
	 * @throws \RuntimeException If service cannot be resolved.
	 */
	public static function resolve( string $abstract ): object {
		$abstract = self::resolveAlias( $abstract );

		// Check for circular dependencies
		if ( isset( self::$resolving[$abstract] ) ) {
			throw new \RuntimeException( "Circular dependency detected for service: {$abstract}" );
		}

		// Return existing singleton instance
		if ( isset( self::$instances[$abstract] ) ) {
			return self::$instances[$abstract];
		}

		// Check interface binding
		if ( isset( self::$interfaces[$abstract] ) ) {
			$abstract = self::$interfaces[$abstract];
		}

		self::$resolving[$abstract] = true;

		try {
			$instance = self::createInstance( $abstract );

			// Store singleton instance
			if ( isset( self::$bindings[$abstract] ) && self::$bindings[$abstract]['singleton'] ) {
				self::$instances[$abstract] = $instance;
			}

			return $instance;
		} finally {
			unset( self::$resolving[$abstract] );
		}
	}

	/**
	 * Create an instance of the service.
	 *
	 * @param string $abstract Service identifier.
	 * @return object
	 * @throws \RuntimeException If service cannot be created.
	 */
	private static function createInstance( string $abstract ): object {
		// Use bound factory if available
		if ( isset( self::$bindings[$abstract] ) ) {
			return call_user_func( self::$bindings[$abstract]['factory'] );
		}

		// Try to autowire the class
		if ( class_exists( $abstract ) ) {
			return self::autowire( $abstract );
		}

		throw new \RuntimeException( "Service not found: {$abstract}" );
	}

	/**
	 * Autowire a class by resolving its dependencies.
	 *
	 * @param string $class Class name.
	 * @return object
	 * @throws \RuntimeException If class cannot be autowired.
	 */
	private static function autowire( string $class ): object {
		try {
			$reflection = new \ReflectionClass( $class );

			if ( ! $reflection->isInstantiable() ) {
				throw new \RuntimeException( "Class {$class} is not instantiable" );
			}

			$constructor = $reflection->getConstructor();

			if ( ! $constructor ) {
				return new $class();
			}

			$dependencies = [];
			foreach ( $constructor->getParameters() as $parameter ) {
				$type = $parameter->getType();

				if ( ! $type instanceof \ReflectionNamedType ) {
					if ( $parameter->isDefaultValueAvailable() ) {
						$dependencies[] = $parameter->getDefaultValue();
						continue;
					}
					throw new \RuntimeException( "Cannot resolve parameter {$parameter->getName()} for {$class}" );
				}

				$typeName = $type->getName();

				// Skip scalar types
				if ( $type->isBuiltin() ) {
					if ( $parameter->isDefaultValueAvailable() ) {
						$dependencies[] = $parameter->getDefaultValue();
						continue;
					}
					throw new \RuntimeException( "Cannot resolve scalar parameter {$parameter->getName()} for {$class}" );
				}

				$dependencies[] = self::resolve( $typeName );
			}

			return $reflection->newInstanceArgs( $dependencies );
		} catch ( \ReflectionException $e ) {
			throw new \RuntimeException( "Failed to autowire {$class}: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Resolve alias to actual service identifier.
	 *
	 * @param string $abstract Service identifier or alias.
	 * @return string
	 */
	private static function resolveAlias( string $abstract ): string {
		return self::$aliases[$abstract] ?? $abstract;
	}

	/**
	 * Check if a service is bound.
	 *
	 * @param string $abstract Service identifier.
	 * @return bool
	 */
	public static function bound( string $abstract ): bool {
		$abstract = self::resolveAlias( $abstract );
		return isset( self::$bindings[$abstract] ) || isset( self::$instances[$abstract] ) || class_exists( $abstract );
	}

	/**
	 * Forget a service binding.
	 *
	 * @param string $abstract Service identifier.
	 */
	public static function forget( string $abstract ): void {
		$abstract = self::resolveAlias( $abstract );
		unset( self::$bindings[$abstract], self::$instances[$abstract] );
	}

	/**
	 * Clear all bindings and instances.
	 */
	public static function flush(): void {
		self::$bindings = [];
		self::$instances = [];
		self::$interfaces = [];
		self::$aliases = [];
		self::$resolving = [];
	}

	/**
	 * Get all registered services.
	 *
	 * @return array{bindings: array, instances: array, interfaces: array}
	 */
	public static function getServices(): array {
		return [
			'bindings'   => array_keys( self::$bindings ),
			'instances'  => array_keys( self::$instances ),
			'interfaces' => self::$interfaces,
		];
	}
}