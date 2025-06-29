<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ServiceContainer {
	
	private static ?ServiceContainer $instance = null;
	private array $services = [];
	private array $singletons = [];
	private array $factories = [];
	private array $aliases = [];
	private array $resolving = [];
	
	private function __construct() {}
	
	public static function getInstance(): ServiceContainer {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Register a service factory
	 *
	 * @param string   $service_name The service identifier.
	 * @param callable $factory      Factory function to create the service.
	 * @param bool     $singleton    Whether to cache the instance.
	 * @return void
	 */
	public function register( string $service_name, callable $factory, bool $singleton = true ): void {
		$this->factories[ $service_name ] = $factory;
		if ( $singleton ) {
			$this->singletons[ $service_name ] = true;
		}
	}
	
	/**
	 * Register a service instance directly
	 *
	 * @param string $service_name The service identifier.
	 * @param mixed  $instance     The service instance.
	 * @return void
	 */
	public function set( string $service_name, $instance ): void {
		$this->services[ $service_name ] = $instance;
	}
	
	/**
	 * Get a service instance
	 *
	 * @param string $service_name The service identifier.
	 * @return mixed The service instance.
	 * @throws \RuntimeException If service not found.
	 */
	public function get( string $service_name ) {
		$service_name = $this->resolveAlias( $service_name );
		
		// Check for circular dependencies
		if ( isset( $this->resolving[ $service_name ] ) ) {
			throw new \RuntimeException( "Circular dependency detected for service: {$service_name}" );
		}
		
		// Return cached instance if available
		if ( isset( $this->services[ $service_name ] ) ) {
			return $this->services[ $service_name ];
		}
		
		// Create new instance using factory
		if ( isset( $this->factories[ $service_name ] ) ) {
			$this->resolving[ $service_name ] = true;
			
			try {
				$instance = $this->factories[ $service_name ]( $this );
				
				// Cache if singleton
				if ( isset( $this->singletons[ $service_name ] ) ) {
					$this->services[ $service_name ] = $instance;
				}
				
				return $instance;
			} finally {
				unset( $this->resolving[ $service_name ] );
			}
		}
		
		throw new \RuntimeException( "Service '{$service_name}' not found in container." );
	}
	
	/**
	 * Check if a service is registered
	 *
	 * @param string $service_name The service identifier.
	 * @return bool True if service exists.
	 */
	public function has( string $service_name ): bool {
		return isset( $this->services[ $service_name ] ) || isset( $this->factories[ $service_name ] );
	}
	
	/**
	 * Register core plugin services
	 *
	 * @return void
	 */
	public function registerCoreServices(): void {
		// Settings Repository
		$this->register( 'settings_repository', function() {
			return SettingsRepository::get_instance();
		} );
		
		// Token Manager
		$this->register( 'token_manager', function( $container ) {
			return new \NuclearEngagement\Security\TokenManager(
				$container->get( 'settings_repository' )
			);
		} );
		
		// Remote Request Service
		$this->register( 'remote_request', function( $container ) {
			return new \NuclearEngagement\Services\Remote\RemoteRequest(
				$container->get( 'settings_repository' )
			);
		} );
		
		// Setup Service
		$this->register( 'setup_service', function( $container ) {
			return new \NuclearEngagement\Services\SetupService(
				$container->get( 'remote_request' )
			);
		} );
		
		// App Password Handler
		$this->register( 'app_password_handler', function( $container ) {
			return new \NuclearEngagement\Admin\Setup\AppPasswordHandler(
				$container->get( 'setup_service' ),
				$container->get( 'settings_repository' ),
				$container->get( 'token_manager' )
			);
		} );
		
		// API Configuration Page
		$this->register( 'api_configuration_page', function( $container ) {
			return new \NuclearEngagement\Admin\ApiConfigurationPage(
				$container->get( 'settings_repository' )
			);
		} );
		
		// Error Handler
		$this->register( 'error_handler', function() {
			return new ErrorHandler();
		} );
		
		// Cache Manager
		$this->register( 'cache_manager', function() {
			return new CacheManager();
		} );
	}
	
	/**
	 * Initialize all singleton services that need early initialization
	 *
	 * @return void
	 */
	public function initializeCoreServices(): void {
		// Initialize error handler early
		$this->get( 'error_handler' );
		
		// Initialize settings repository
		$this->get( 'settings_repository' );
		
		// Initialize cache manager
		$this->get( 'cache_manager' );
	}
	
	/**
	 * Clear all cached services (useful for testing)
	 *
	 * @return void
	 */
	public function clearCache(): void {
		$this->services = [];
	}
	
	/**
	 * Create an alias for a service
	 *
	 * @param string $alias    Alias name.
	 * @param string $service_name Original service identifier.
	 * @return void
	 */
	public function alias( string $alias, string $service_name ): void {
		$this->aliases[ $alias ] = $service_name;
	}
	
	/**
	 * Resolve alias to actual service identifier
	 *
	 * @param string $service_name Service identifier or alias.
	 * @return string
	 */
	private function resolveAlias( string $service_name ): string {
		return $this->aliases[ $service_name ] ?? $service_name;
	}
	
	/**
	 * Get all registered service names
	 *
	 * @return array List of service names.
	 */
	public function getServiceNames(): array {
		return array_unique( array_merge(
			array_keys( $this->services ),
			array_keys( $this->factories )
		) );
	}
}