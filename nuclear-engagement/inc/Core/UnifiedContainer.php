<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UnifiedContainer {
	private static ?UnifiedContainer $instance = null;
	private array $services = [];
	private array $singletons = [];
	private array $factories = [];
	private array $aliases = [];
	private array $interfaces = [];
	private array $bindings = [];
	private array $resolving = [];
	
	private function __construct() {}
	
	public static function getInstance(): UnifiedContainer {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	public function register( string $service_name, callable $factory, bool $singleton = true ): void {
		$this->factories[ $service_name ] = $factory;
		if ( $singleton ) {
			$this->singletons[ $service_name ] = true;
		}
	}

	public function bind( string $abstract, callable $factory = null, bool $singleton = false ): void {
		if ( $factory === null ) {
			$factory = $this->getDefaultFactory( $abstract );
		}
		
		$this->bindings[ $abstract ] = [
			'factory' => $factory,
			'singleton' => $singleton
		];
	}

	public function singleton( string $abstract, callable $factory = null ): void {
		$this->bind( $abstract, $factory, true );
	}

	public function interface( string $interface, string $implementation ): void {
		$this->interfaces[ $interface ] = $implementation;
	}

	public function alias( string $alias, string $service_name ): void {
		$this->aliases[ $alias ] = $service_name;
	}
	
	public function set( string $service_name, $instance ): void {
		$this->services[ $service_name ] = $instance;
	}
	
	public function get( string $service_name ) {
		$service_name = $this->resolveAlias( $service_name );
		$service_name = $this->resolveInterface( $service_name );
		
		if ( isset( $this->resolving[ $service_name ] ) ) {
			throw new \RuntimeException( "Circular dependency detected for service: {$service_name}" );
		}
		
		if ( isset( $this->services[ $service_name ] ) ) {
			return $this->services[ $service_name ];
		}
		
		$factory = $this->getFactory( $service_name );
		if ( $factory === null ) {
			throw new \RuntimeException( "Service '{$service_name}' not found in container." );
		}
		
		$this->resolving[ $service_name ] = true;
		
		try {
			$instance = $factory( $this );
			
			if ( $this->isSingleton( $service_name ) ) {
				$this->services[ $service_name ] = $instance;
			}
			
			return $instance;
		} finally {
			unset( $this->resolving[ $service_name ] );
		}
	}
	
	public function has( string $service_name ): bool {
		$service_name = $this->resolveAlias( $service_name );
		$service_name = $this->resolveInterface( $service_name );
		
		return isset( $this->services[ $service_name ] ) || 
			   isset( $this->factories[ $service_name ] ) || 
			   isset( $this->bindings[ $service_name ] ) ||
			   class_exists( $service_name );
	}

	public function make( string $class_name, array $parameters = [] ) {
		if ( ! class_exists( $class_name ) ) {
			throw new \RuntimeException( "Class '{$class_name}' does not exist." );
		}

		$reflection = new \ReflectionClass( $class_name );
		$constructor = $reflection->getConstructor();

		if ( $constructor === null ) {
			return new $class_name();
		}

		$dependencies = [];
		foreach ( $constructor->getParameters() as $param ) {
			$type = $param->getType();
			
			if ( $type && ! $type->isBuiltin() ) {
				$typeName = $type->getName();
				if ( isset( $parameters[ $param->getName() ] ) ) {
					$dependencies[] = $parameters[ $param->getName() ];
				} else {
					$dependencies[] = $this->get( $typeName );
				}
			} elseif ( isset( $parameters[ $param->getName() ] ) ) {
				$dependencies[] = $parameters[ $param->getName() ];
			} elseif ( $param->isDefaultValueAvailable() ) {
				$dependencies[] = $param->getDefaultValue();
			} else {
				throw new \RuntimeException( "Cannot resolve parameter '{$param->getName()}' for class '{$class_name}'." );
			}
		}

		return $reflection->newInstanceArgs( $dependencies );
	}
	
	private function resolveAlias( string $service_name ): string {
		return $this->aliases[ $service_name ] ?? $service_name;
	}
	
	private function resolveInterface( string $service_name ): string {
		return $this->interfaces[ $service_name ] ?? $service_name;
	}
	
	private function getFactory( string $service_name ): ?callable {
		if ( isset( $this->factories[ $service_name ] ) ) {
			return $this->factories[ $service_name ];
		}
		
		if ( isset( $this->bindings[ $service_name ] ) ) {
			return $this->bindings[ $service_name ]['factory'];
		}
		
		if ( class_exists( $service_name ) ) {
			return function( $container ) use ( $service_name ) {
				return $container->make( $service_name );
			};
		}
		
		return null;
	}
	
	private function isSingleton( string $service_name ): bool {
		if ( isset( $this->singletons[ $service_name ] ) ) {
			return true;
		}
		
		if ( isset( $this->bindings[ $service_name ] ) ) {
			return $this->bindings[ $service_name ]['singleton'];
		}
		
		return true; // Default to singleton for auto-resolved classes
	}
	
	private function getDefaultFactory( string $class_name ): callable {
		return function( $container ) use ( $class_name ) {
			return $container->make( $class_name );
		};
	}
	
	public function registerCoreServices(): void {
		$this->singleton( 'settings_repository', function() {
			return SettingsRepository::get_instance();
		} );
		
		$this->singleton( \NuclearEngagement\Security\TokenManager::class );
		$this->singleton( \NuclearEngagement\Services\Remote\RemoteRequest::class );
		$this->singleton( \NuclearEngagement\Services\SetupService::class );
		$this->singleton( \NuclearEngagement\Admin\Setup\AppPasswordHandler::class );
		$this->singleton( \NuclearEngagement\Admin\Setup\ConnectHandler::class );
		
		$this->alias( 'token_manager', \NuclearEngagement\Security\TokenManager::class );
		$this->alias( 'remote_request', \NuclearEngagement\Services\Remote\RemoteRequest::class );
		$this->alias( 'setup_service', \NuclearEngagement\Services\SetupService::class );
		$this->alias( 'app_password_handler', \NuclearEngagement\Admin\Setup\AppPasswordHandler::class );
		$this->alias( 'connect_handler', \NuclearEngagement\Admin\Setup\ConnectHandler::class );
	}
	
	public function clearCache(): void {
		$this->services = [];
	}
	
	public function getServiceNames(): array {
		return array_unique( array_merge(
			array_keys( $this->services ),
			array_keys( $this->factories ),
			array_keys( $this->bindings )
		) );
	}
}