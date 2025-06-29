<?php
/**
 * Tests for ServiceContainer class
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use NuclearEngagement\Core\ServiceContainer;

class ServiceContainerTest extends TestCase {

    private $container;

    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
        
        // Reset singleton instance for testing
        $reflection = new \ReflectionClass(ServiceContainer::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        $this->container = ServiceContainer::getInstance();
    }

    protected function tearDown(): void {
        \WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern_returns_same_instance() {
        // Act
        $instance1 = ServiceContainer::getInstance();
        $instance2 = ServiceContainer::getInstance();

        // Assert
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ServiceContainer::class, $instance1);
    }

    /**
     * Test service registration with factory
     */
    public function test_register_service_with_factory() {
        // Arrange
        $serviceName = 'test_service';
        $factory = function() {
            return new \stdClass();
        };

        // Act
        $this->container->register($serviceName, $factory);

        // Assert
        $this->assertTrue($this->container->has($serviceName));
    }

    /**
     * Test service registration as singleton by default
     */
    public function test_register_service_as_singleton_by_default() {
        // Arrange
        $serviceName = 'singleton_service';
        $factory = function() {
            return new \stdClass();
        };

        // Act
        $this->container->register($serviceName, $factory);
        $instance1 = $this->container->get($serviceName);
        $instance2 = $this->container->get($serviceName);

        // Assert
        $this->assertSame($instance1, $instance2, 'Should return same instance for singleton services');
    }

    /**
     * Test service registration as non-singleton
     */
    public function test_register_service_as_non_singleton() {
        // Arrange
        $serviceName = 'non_singleton_service';
        $factory = function() {
            return new \stdClass();
        };

        // Act
        $this->container->register($serviceName, $factory, false);
        $instance1 = $this->container->get($serviceName);
        $instance2 = $this->container->get($serviceName);

        // Assert
        $this->assertNotSame($instance1, $instance2, 'Should return different instances for non-singleton services');
    }

    /**
     * Test direct service instance registration
     */
    public function test_set_service_instance_directly() {
        // Arrange
        $serviceName = 'direct_service';
        $instance = new \stdClass();
        $instance->value = 'test_value';

        // Act
        $this->container->set($serviceName, $instance);
        $retrieved = $this->container->get($serviceName);

        // Assert
        $this->assertSame($instance, $retrieved);
        $this->assertEquals('test_value', $retrieved->value);
    }

    /**
     * Test getting non-existent service throws exception
     */
    public function test_get_non_existent_service_throws_exception() {
        // Arrange
        $serviceName = 'non_existent_service';

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Service 'non_existent_service' not found in container.");

        // Act
        $this->container->get($serviceName);
    }

    /**
     * Test service factory receives container as parameter
     */
    public function test_service_factory_receives_container() {
        // Arrange
        $serviceName = 'container_aware_service';
        $receivedContainer = null;
        
        $factory = function($container) use (&$receivedContainer) {
            $receivedContainer = $container;
            return new \stdClass();
        };

        // Act
        $this->container->register($serviceName, $factory);
        $this->container->get($serviceName);

        // Assert
        $this->assertSame($this->container, $receivedContainer);
    }

    /**
     * Test dependency injection between services
     */
    public function test_dependency_injection_between_services() {
        // Arrange
        $dependencyService = 'dependency';
        $mainService = 'main_service';

        // Register dependency
        $this->container->register($dependencyService, function() {
            $obj = new \stdClass();
            $obj->name = 'dependency_instance';
            return $obj;
        });

        // Register main service that depends on dependency
        $this->container->register($mainService, function($container) use ($dependencyService) {
            $obj = new \stdClass();
            $obj->dependency = $container->get($dependencyService);
            return $obj;
        });

        // Act
        $instance = $this->container->get($mainService);

        // Assert
        $this->assertInstanceOf(\stdClass::class, $instance);
        $this->assertInstanceOf(\stdClass::class, $instance->dependency);
        $this->assertEquals('dependency_instance', $instance->dependency->name);
    }

    /**
     * Test has() method returns correct boolean values
     */
    public function test_has_method_returns_correct_values() {
        // Arrange
        $existingService = 'existing_service';
        $nonExistingService = 'non_existing_service';

        $this->container->register($existingService, function() {
            return new \stdClass();
        });

        // Act & Assert
        $this->assertTrue($this->container->has($existingService));
        $this->assertFalse($this->container->has($nonExistingService));
    }

    /**
     * Test has() method works with directly set services
     */
    public function test_has_method_works_with_direct_services() {
        // Arrange
        $serviceName = 'direct_service';
        $instance = new \stdClass();

        $this->container->set($serviceName, $instance);

        // Act & Assert
        $this->assertTrue($this->container->has($serviceName));
    }

    /**
     * Test clear cache functionality
     */
    public function test_clear_cache_removes_cached_instances() {
        // Arrange
        $serviceName = 'cacheable_service';
        $this->container->register($serviceName, function() {
            return new \stdClass();
        });

        // Get instance to cache it
        $instance1 = $this->container->get($serviceName);

        // Act
        $this->container->clearCache();
        $instance2 = $this->container->get($serviceName);

        // Assert
        $this->assertNotSame($instance1, $instance2, 'Should create new instance after cache clear');
    }

    /**
     * Test getServiceNames returns all registered services
     */
    public function test_get_service_names_returns_all_services() {
        // Arrange
        $this->container->register('service1', function() { return new \stdClass(); });
        $this->container->register('service2', function() { return new \stdClass(); });
        $this->container->set('service3', new \stdClass());

        // Act
        $serviceNames = $this->container->getServiceNames();

        // Assert
        $this->assertIsArray($serviceNames);
        $this->assertContains('service1', $serviceNames);
        $this->assertContains('service2', $serviceNames);
        $this->assertContains('service3', $serviceNames);
        $this->assertCount(3, $serviceNames);
    }

    /**
     * Test getServiceNames removes duplicates
     */
    public function test_get_service_names_removes_duplicates() {
        // Arrange
        $serviceName = 'duplicate_service';
        
        // Register factory first
        $this->container->register($serviceName, function() { return new \stdClass(); });
        
        // Then set instance directly (overwrites)
        $this->container->set($serviceName, new \stdClass());

        // Act
        $serviceNames = $this->container->getServiceNames();

        // Assert
        $this->assertCount(1, array_filter($serviceNames, function($name) use ($serviceName) {
            return $name === $serviceName;
        }), 'Should not contain duplicate service names');
    }

    /**
     * Test registering core services doesn't cause errors
     */
    public function test_register_core_services_completes_successfully() {
        // Mock WordPress functions and classes that core services depend on
        \WP_Mock::userFunction('wp_generate_password')
            ->andReturn('random_password');

        // Act
        $this->container->registerCoreServices();

        // Assert
        $this->assertTrue($this->container->has('settings_repository'));
        $this->assertTrue($this->container->has('token_manager'));
        $this->assertTrue($this->container->has('error_handler'));
        $this->assertTrue($this->container->has('cache_manager'));
    }

    /**
     * Test initialize core services doesn't cause errors
     */
    public function test_initialize_core_services_completes_successfully() {
        // Mock WordPress functions
        \WP_Mock::userFunction('wp_generate_password')
            ->andReturn('random_password');

        // Register core services first
        $this->container->registerCoreServices();

        // Act
        $this->container->initializeCoreServices();

        // Assert - Should complete without errors
        $this->assertTrue(true, 'Core services initialization should complete without errors');
    }

    /**
     * Test circular dependency detection/handling
     */
    public function test_circular_dependency_handling() {
        // Arrange
        $service1 = 'circular_service1';
        $service2 = 'circular_service2';

        $this->container->register($service1, function($container) use ($service2) {
            $obj = new \stdClass();
            $obj->dependency = $container->get($service2);
            return $obj;
        });

        $this->container->register($service2, function($container) use ($service1) {
            $obj = new \stdClass();
            $obj->dependency = $container->get($service1);
            return $obj;
        });

        // Act & Assert - This should detect circular dependency
        $this->expectException(\RuntimeException::class);
        $this->container->get($service1);
    }

    /**
     * Test factory function that returns null
     */
    public function test_factory_returning_null() {
        // Arrange
        $serviceName = 'null_service';
        $this->container->register($serviceName, function() {
            return null;
        });

        // Act
        $result = $this->container->get($serviceName);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test factory function that throws exception
     */
    public function test_factory_throwing_exception() {
        // Arrange
        $serviceName = 'throwing_service';
        $this->container->register($serviceName, function() {
            throw new \Exception('Factory error');
        });

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Factory error');
        $this->container->get($serviceName);
    }

    /**
     * Test overriding existing service registration
     */
    public function test_overriding_existing_service() {
        // Arrange
        $serviceName = 'override_service';
        
        $this->container->register($serviceName, function() {
            $obj = new \stdClass();
            $obj->version = 'v1';
            return $obj;
        });

        // Act - Override with new factory
        $this->container->register($serviceName, function() {
            $obj = new \stdClass();
            $obj->version = 'v2';
            return $obj;
        });

        $instance = $this->container->get($serviceName);

        // Assert
        $this->assertEquals('v2', $instance->version, 'Should use the new factory');
    }

    /**
     * Test overriding factory with direct instance
     */
    public function test_overriding_factory_with_direct_instance() {
        // Arrange
        $serviceName = 'override_direct_service';
        
        $this->container->register($serviceName, function() {
            $obj = new \stdClass();
            $obj->source = 'factory';
            return $obj;
        });

        $directInstance = new \stdClass();
        $directInstance->source = 'direct';

        // Act
        $this->container->set($serviceName, $directInstance);
        $instance = $this->container->get($serviceName);

        // Assert
        $this->assertEquals('direct', $instance->source, 'Should use direct instance instead of factory');
        $this->assertSame($directInstance, $instance);
    }

    /**
     * Test empty service name handling
     */
    public function test_empty_service_name_handling() {
        // Arrange
        $emptyName = '';

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->container->get($emptyName);
    }

    /**
     * Test service registration with complex factory
     */
    public function test_complex_service_factory() {
        // Arrange
        $serviceName = 'complex_service';
        
        $this->container->register($serviceName, function($container) {
            // Simulate complex service creation
            $service = new \stdClass();
            $service->initialized = true;
            $service->config = [
                'option1' => 'value1',
                'option2' => 'value2'
            ];
            $service->timestamp = time();
            
            return $service;
        });

        // Act
        $instance = $this->container->get($serviceName);

        // Assert
        $this->assertTrue($instance->initialized);
        $this->assertIsArray($instance->config);
        $this->assertEquals('value1', $instance->config['option1']);
        $this->assertIsInt($instance->timestamp);
    }
}