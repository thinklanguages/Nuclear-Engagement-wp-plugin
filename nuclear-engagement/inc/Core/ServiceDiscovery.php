<?php
/**
 * ServiceDiscovery.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service discovery and registration system.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ServiceDiscovery {
	/**
	 * Discovered services cache.
	 *
	 * @var array<string, array{class: string, interfaces: array, dependencies: array, metadata: array}>
	 */
	private static array $discovered_services = array();

	/**
	 * Service providers.
	 *
	 * @var array<string, callable>
	 */
	private static array $service_providers = array();

	/**
	 * Service health status.
	 *
	 * @var array<string, array{status: string, last_check: int, message: string}>
	 */
	private static array $health_status = array();

	/**
	 * Auto-discovery enabled.
	 *
	 * @var bool
	 */
	private static bool $auto_discovery_enabled = true;

	/**
	 * Initialize service discovery.
	 */
	public static function init(): void {
		if ( ! self::$auto_discovery_enabled ) {
			return;
		}

		// Schedule health checks.
		if ( ! wp_next_scheduled( 'nuclen_service_health_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'nuclen_service_health_check' );
		}

		add_action( 'nuclen_service_health_check', array( self::class, 'run_health_checks' ) );
	}

	/**
	 * Discover services in specified directories.
	 *
	 * @param array<string> $directories Directories to scan.
	 * @return array<string, array> Discovered services.
	 */
	public static function discoverServices( array $directories = array() ): array {
		if ( empty( $directories ) ) {
			$directories = array(
				NUCLEN_PLUGIN_DIR . 'inc/Services/',
				NUCLEN_PLUGIN_DIR . 'inc/Core/',
			);
		}

		$cache_key = 'nuclen_discovered_services_' . hash( 'xxh3', implode( '|', $directories ) );
		$cached    = wp_cache_get( $cache_key, 'nuclen_services' );

		if ( $cached !== false ) {
			return $cached;
		}

		$services = array();

		foreach ( $directories as $directory ) {
			if ( ! is_dir( $directory ) ) {
				continue;
			}

			$services = array_merge( $services, self::scanDirectory( $directory ) );
		}

		wp_cache_set( $cache_key, $services, 'nuclen_services', HOUR_IN_SECONDS );
		self::$discovered_services = $services;

		return $services;
	}

	/**
	 * Register a service provider.
	 *
	 * @param string   $name     Provider name.
	 * @param callable $provider Provider function.
	 */
	public static function registerProvider( string $name, callable $provider ): void {
		self::$service_providers[ $name ] = $provider;
	}

	/**
	 * Load all service providers.
	 */
	public static function loadProviders(): void {
		foreach ( self::$service_providers as $name => $provider ) {
			try {
				PerformanceMonitor::start( "provider_{$name}" );
				call_user_func( $provider );
				PerformanceMonitor::stop( "provider_{$name}" );
			} catch ( \Throwable $e ) {
				LoggingService::log_exception( $e );
				LoggingService::log(
					"Failed to load service provider: {$name}"
				);
			}
		}
	}

	/**
	 * Auto-register discovered services.
	 *
	 * @param array<string> $directories Directories to scan.
	 */
	public static function autoRegister( array $directories = array() ): void {
		$services = self::discoverServices( $directories );

		foreach ( $services as $class => $metadata ) {
			self::registerDiscoveredService( $class, $metadata );
		}
	}

	/**
	 * Check service health.
	 *
	 * @param string $service_id Service identifier.
	 * @return array{status: string, message: string, timestamp: int}
	 */
	public static function checkServiceHealth( string $service_id ): array {
		$health = array(
			'status'    => 'unknown',
			'message'   => 'Service not found',
			'timestamp' => time(),
		);

		try {
			if ( ServiceContainer::bound( $service_id ) ) {
				$service = ServiceContainer::resolve( $service_id );

				// Check if service has a health check method.
				if ( method_exists( $service, 'healthCheck' ) ) {
					$result            = $service->healthCheck();
					$health['status']  = $result['status'] ?? 'healthy';
					$health['message'] = $result['message'] ?? 'Service is operational';
				} else {
					$health['status']  = 'healthy';
					$health['message'] = 'Service is available';
				}
			}
		} catch ( \Throwable $e ) {
			$health['status']  = 'unhealthy';
			$health['message'] = $e->getMessage();
		}

		self::$health_status[ $service_id ] = array(
			'status'     => $health['status'],
			'last_check' => $health['timestamp'],
			'message'    => $health['message'],
		);

		return $health;
	}

	/**
	 * Get health status for all services.
	 *
	 * @return array<string, array{status: string, last_check: int, message: string}>
	 */
	public static function getHealthStatus(): array {
		return self::$health_status;
	}

	/**
	 * Run health checks for all registered services.
	 */
	public static function run_health_checks(): void {
		$services = ServiceContainer::getServices();

		foreach ( $services['bindings'] as $service_id ) {
			self::checkServiceHealth( $service_id );
		}

		foreach ( $services['instances'] as $service_id ) {
			self::checkServiceHealth( $service_id );
		}
	}

	/**
	 * Get service dependency graph.
	 *
	 * @return array<string, array<string>>
	 */
	public static function getDependencyGraph(): array {
		$graph = array();

		foreach ( self::$discovered_services as $class => $metadata ) {
			$graph[ $class ] = $metadata['dependencies'] ?? array();
		}

		return $graph;
	}

	/**
	 * Validate service dependencies.
	 *
	 * @return array<string, array<string>> Missing dependencies by service.
	 */
	public static function validateDependencies(): array {
		$missing = array();
		$graph   = self::getDependencyGraph();

		foreach ( $graph as $service => $dependencies ) {
			foreach ( $dependencies as $dependency ) {
				if ( ! ServiceContainer::bound( $dependency ) && ! class_exists( $dependency ) ) {
					$missing[ $service ][] = $dependency;
				}
			}
		}

		return $missing;
	}

	/**
	 * Enable or disable auto-discovery.
	 *
	 * @param bool $enabled Whether to enable auto-discovery.
	 */
	public static function setAutoDiscovery( bool $enabled ): void {
		self::$auto_discovery_enabled = $enabled;
	}

	/**
	 * Clear discovery cache.
	 */
	public static function clearCache(): void {
		wp_cache_flush_group( 'nuclen_services' );
		self::$discovered_services = array();
	}

	/**
	 * Scan directory for service classes.
	 *
	 * @param string $directory Directory to scan.
	 * @return array<string, array> Discovered services.
	 */
	private static function scanDirectory( string $directory ): array {
		$services = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->getExtension() !== 'php' ) {
				continue;
			}

			$class_info = self::analyzeFile( $file->getPathname() );
			if ( $class_info ) {
				$services[ $class_info['class'] ] = $class_info;
			}
		}

		return $services;
	}

	/**
	 * Analyze PHP file for service information.
	 *
	 * @param string $file_path Path to PHP file.
	 * @return array|null Service information or null if not a service.
	 */
	private static function analyzeFile( string $file_path ): ?array {
		$content = file_get_contents( $file_path );
		if ( ! $content ) {
			return null;
		}

		// Extract namespace.
		if ( ! preg_match( '/namespace\s+([^;]+);/', $content, $namespace_match ) ) {
			return null;
		}

		// Extract class name.
		if ( ! preg_match( '/class\s+(\w+)/', $content, $class_match ) ) {
			return null;
		}

		$namespace  = trim( $namespace_match[1] );
		$class_name = $class_match[1];
		$full_class = $namespace . '\\' . $class_name;

		// Skip if class doesn't exist.
		if ( ! class_exists( $full_class ) ) {
			return null;
		}

		try {
			$reflection = new \ReflectionClass( $full_class );

			// Skip abstract classes and interfaces.
			if ( $reflection->isAbstract() || $reflection->isInterface() ) {
				return null;
			}

			$interfaces   = array_keys( $reflection->getInterfaces() );
			$dependencies = self::extractDependencies( $reflection );
			$metadata     = self::extractMetadata( $reflection, $content );

			return array(
				'class'        => $full_class,
				'interfaces'   => $interfaces,
				'dependencies' => $dependencies,
				'metadata'     => $metadata,
			);
		} catch ( \ReflectionException $e ) {
			return null;
		}
	}

	/**
	 * Extract class dependencies from constructor.
	 *
	 * @param \ReflectionClass $reflection Class reflection.
	 * @return array<string> Dependencies.
	 */
	private static function extractDependencies( \ReflectionClass $reflection ): array {
		$dependencies = array();
		$constructor  = $reflection->getConstructor();

		if ( ! $constructor ) {
			return $dependencies;
		}

		foreach ( $constructor->getParameters() as $parameter ) {
			$type = $parameter->getType();

			if ( $type instanceof \ReflectionNamedType && ! $type->isBuiltin() ) {
				$dependencies[] = $type->getName();
			}
		}

		return $dependencies;
	}

	/**
	 * Extract metadata from class docblock and annotations.
	 *
	 * @param \ReflectionClass $reflection Class reflection.
	 * @param string           $content    File content.
	 * @return array Metadata.
	 */
	private static function extractMetadata( \ReflectionClass $reflection, string $content ): array {
		$metadata = array(
			'singleton'   => false,
			'lazy'        => false,
			'priority'    => 10,
			'tags'        => array(),
			'description' => '',
		);

		$docComment = $reflection->getDocComment();
		if ( $docComment ) {
			// Extract description.
			if ( preg_match( '/\*\s*(.+?)(?:\s*\*\s*@|\s*\*\/)/s', $docComment, $desc_match ) ) {
				$metadata['description'] = trim( $desc_match[1] );
			}

			// Check for service annotations.
			if ( strpos( $docComment, '@singleton' ) !== false ) {
				$metadata['singleton'] = true;
			}

			if ( strpos( $docComment, '@lazy' ) !== false ) {
				$metadata['lazy'] = true;
			}

			// Extract priority.
			if ( preg_match( '/@priority\s+(\d+)/', $docComment, $priority_match ) ) {
				$metadata['priority'] = (int) $priority_match[1];
			}

			// Extract tags.
			if ( preg_match_all( '/@tag\s+(\w+)/', $docComment, $tag_matches ) ) {
				$metadata['tags'] = $tag_matches[1];
			}
		}

		// Check for service registration method.
		if ( $reflection->hasMethod( 'register_hooks' ) ) {
			$metadata['tags'][] = 'hook_provider';
		}

		return $metadata;
	}

	/**
	 * Register a discovered service with the container.
	 *
	 * @param string $class    Service class name.
	 * @param array  $metadata Service metadata.
	 */
	private static function registerDiscoveredService( string $class, array $metadata ): void {
		$factory = function () use ( $class ) {
			return ServiceContainer::resolve( $class );
		};

		if ( $metadata['metadata']['singleton'] ?? false ) {
			ServiceContainer::singleton( $class, $factory );
		} else {
			ServiceContainer::bind( $class, $factory );
		}

		// Register interfaces.
		foreach ( $metadata['interfaces'] as $interface ) {
			ServiceContainer::interface( $interface, $class );
		}
	}
}
