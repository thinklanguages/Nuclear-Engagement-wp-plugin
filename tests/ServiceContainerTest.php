<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\ServiceContainer;

class ServiceContainerTest extends TestCase {

	private ServiceContainer $container;

	protected function setUp(): void {
		$this->container = ServiceContainer::getInstance();
		$this->container->reset();
	}

	protected function tearDown(): void {
		$this->container->reset();
	}

	public function test_get_instance_returns_singleton(): void {
		$instance1 = ServiceContainer::getInstance();
		$instance2 = ServiceContainer::getInstance();
		
		$this->assertSame($instance1, $instance2);
	}

	public function test_register_and_get_service(): void {
		$this->container->register('test_service', function() {
			return 'test_value';
		});
		
		$this->assertTrue($this->container->has('test_service'));
		$this->assertEquals('test_value', $this->container->get('test_service'));
	}

	public function test_register_singleton_service(): void {
		$counter = 0;
		$this->container->register('counter_service', function() use (&$counter) {
			$counter++;
			return $counter;
		}, true);
		
		$first = $this->container->get('counter_service');
		$second = $this->container->get('counter_service');
		
		$this->assertEquals(1, $first);
		$this->assertEquals(1, $second); // Should be cached
	}

	public function test_register_non_singleton_service(): void {
		$counter = 0;
		$this->container->register('counter_service', function() use (&$counter) {
			$counter++;
			return $counter;
		}, false);
		
		$first = $this->container->get('counter_service');
		$second = $this->container->get('counter_service');
		
		$this->assertEquals(1, $first);
		$this->assertEquals(2, $second); // Should create new instance
	}

	public function test_set_and_get_service_instance(): void {
		$instance = new stdClass();
		$instance->property = 'test_value';
		
		$this->container->set('test_instance', $instance);
		
		$this->assertTrue($this->container->has('test_instance'));
		$this->assertSame($instance, $this->container->get('test_instance'));
	}

	public function test_factory_receives_container_instance(): void {
		$this->container->register('test_service', function($container) {
			$this->assertInstanceOf(ServiceContainer::class, $container);
			return 'success';
		});
		
		$result = $this->container->get('test_service');
		$this->assertEquals('success', $result);
	}

	public function test_service_dependency_injection(): void {
		$this->container->register('dependency', function() {
			return 'dependency_value';
		});
		
		$this->container->register('service_with_dependency', function($container) {
			$dependency = $container->get('dependency');
			return "service_with_{$dependency}";
		});
		
		$result = $this->container->get('service_with_dependency');
		$this->assertEquals('service_with_dependency_value', $result);
	}

	public function test_get_nonexistent_service_throws_exception(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Service 'nonexistent_service' not found in container.");
		
		$this->container->get('nonexistent_service');
	}

	public function test_circular_dependency_detection(): void {
		$this->container->register('service_a', function($container) {
			return $container->get('service_b');
		});
		
		$this->container->register('service_b', function($container) {
			return $container->get('service_a');
		});
		
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Circular dependency detected for service: service_a");
		
		$this->container->get('service_a');
	}

	public function test_alias_functionality(): void {
		$this->container->register('original_service', function() {
			return 'original_value';
		});
		
		$this->container->alias('aliased_service', 'original_service');
		
		// The has() method doesn't check aliases, but get() should work with aliases
		$this->assertEquals('original_value', $this->container->get('aliased_service'));
	}

	public function test_has_returns_false_for_nonexistent_service(): void {
		$this->assertFalse($this->container->has('nonexistent_service'));
	}

	public function test_clear_cache_removes_cached_instances(): void {
		$counter = 0;
		$this->container->register('counter_service', function() use (&$counter) {
			$counter++;
			return $counter;
		}, true);
		
		$first = $this->container->get('counter_service');
		$this->container->clearCache();
		$second = $this->container->get('counter_service');
		
		$this->assertEquals(1, $first);
		$this->assertEquals(2, $second); // Should create new instance after cache clear
	}

	public function test_reset_clears_all_container_state(): void {
		$this->container->register('test_service', function() {
			return 'test_value';
		});
		
		$this->container->set('test_instance', 'instance_value');
		$this->container->alias('test_alias', 'test_service');
		
		$this->assertTrue($this->container->has('test_service'));
		$this->assertTrue($this->container->has('test_instance'));
		// Note: has() doesn't check aliases in the current implementation
		
		$this->container->reset();
		
		$this->assertFalse($this->container->has('test_service'));
		$this->assertFalse($this->container->has('test_instance'));
		
		// Test that alias is cleared by trying to get it (should throw exception)
		$this->expectException(RuntimeException::class);
		$this->container->get('test_alias');
	}

	public function test_get_service_names_returns_all_registered_services(): void {
		$this->container->register('factory_service', function() {
			return 'factory_value';
		});
		
		$this->container->set('instance_service', 'instance_value');
		
		$service_names = $this->container->getServiceNames();
		
		$this->assertContains('factory_service', $service_names);
		$this->assertContains('instance_service', $service_names);
		$this->assertCount(2, $service_names);
	}

	public function test_exception_handling_in_factory(): void {
		$this->container->register('failing_service', function() {
			throw new Exception('Factory failed');
		});
		
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Factory failed');
		
		$this->container->get('failing_service');
	}

	public function test_resolving_state_cleared_after_exception(): void {
		$this->container->register('failing_service', function() {
			throw new Exception('Factory failed');
		});
		
		try {
			$this->container->get('failing_service');
		} catch (Exception $e) {
			// Expected
		}
		
		// The service should be able to be resolved again (resolving state cleared)
		$this->container->register('failing_service', function() {
			return 'success';
		});
		
		$this->assertEquals('success', $this->container->get('failing_service'));
	}
}