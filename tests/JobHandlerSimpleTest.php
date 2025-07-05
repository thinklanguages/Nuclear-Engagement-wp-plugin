<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\JobHandler;

class JobHandlerSimpleTest extends TestCase {

	protected function setUp(): void {
		// Reset static state
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$property->setValue([]);
	}

	public function test_register_handler(): void {
		$handler = function($context) {
			return 'test_result';
		};
		
		JobHandler::register_handler('test_job', $handler);
		
		// Use reflection to verify the handler was registered
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$handlers = $property->getValue();
		
		$this->assertArrayHasKey('test_job', $handlers);
		$this->assertSame($handler, $handlers['test_job']);
	}

	public function test_register_multiple_handlers(): void {
		$handler1 = function($context) { return 'result1'; };
		$handler2 = function($context) { return 'result2'; };
		
		JobHandler::register_handler('job_type_1', $handler1);
		JobHandler::register_handler('job_type_2', $handler2);
		
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$handlers = $property->getValue();
		
		$this->assertCount(2, $handlers);
		$this->assertArrayHasKey('job_type_1', $handlers);
		$this->assertArrayHasKey('job_type_2', $handlers);
	}

	public function test_register_default_handlers(): void {
		JobHandler::register_default_handlers();
		
		// Use reflection to verify handlers were registered
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$handlers = $property->getValue();
		
		$this->assertArrayHasKey('api_generation', $handlers);
		$this->assertArrayHasKey('cache_warmup', $handlers);
		$this->assertArrayHasKey('data_export', $handlers);
		
		// Verify they are callable
		$this->assertIsCallable($handlers['api_generation']);
		$this->assertIsCallable($handlers['cache_warmup']);
		$this->assertIsCallable($handlers['data_export']);
	}

	public function test_handler_can_be_replaced(): void {
		$originalHandler = function($context) { return 'original'; };
		$newHandler = function($context) { return 'new'; };
		
		JobHandler::register_handler('replaceable_job', $originalHandler);
		JobHandler::register_handler('replaceable_job', $newHandler);
		
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$handlers = $property->getValue();
		
		$this->assertSame($newHandler, $handlers['replaceable_job']);
		$this->assertNotSame($originalHandler, $handlers['replaceable_job']);
	}

	public function test_constants_are_defined(): void {
		$reflection = new ReflectionClass(JobHandler::class);
		
		$this->assertTrue($reflection->hasConstant('MAX_RETRY_ATTEMPTS'));
		$this->assertTrue($reflection->hasConstant('JOB_TIMEOUT'));
		
		$this->assertEquals(3, $reflection->getConstant('MAX_RETRY_ATTEMPTS'));
		$this->assertEquals(300, $reflection->getConstant('JOB_TIMEOUT'));
	}

	public function test_execute_with_timeout_method_exists(): void {
		$reflection = new ReflectionClass(JobHandler::class);
		$this->assertTrue($reflection->hasMethod('execute_with_timeout'));
		
		$method = $reflection->getMethod('execute_with_timeout');
		$this->assertTrue($method->isStatic());
		$this->assertTrue($method->isPrivate());
	}

	public function test_process_post_generation_method_exists(): void {
		$reflection = new ReflectionClass(JobHandler::class);
		$this->assertTrue($reflection->hasMethod('process_post_generation'));
		
		$method = $reflection->getMethod('process_post_generation');
		$this->assertTrue($method->isStatic());
		$this->assertTrue($method->isPrivate());
	}

	public function test_execute_with_timeout_basic_functionality(): void {
		$reflection = new ReflectionClass(JobHandler::class);
		$method = $reflection->getMethod('execute_with_timeout');
		$method->setAccessible(true);
		
		$testFunction = function($arg) {
			return "result: $arg";
		};
		
		$result = $method->invoke(null, $testFunction, ['test'], 5);
		$this->assertEquals('result: test', $result);
	}

	public function test_handler_registry_is_static(): void {
		$handler1 = function() { return 'handler1'; };
		$handler2 = function() { return 'handler2'; };
		
		JobHandler::register_handler('test1', $handler1);
		
		// Create new instance and register another handler
		JobHandler::register_handler('test2', $handler2);
		
		// Both handlers should be available (static storage)
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$handlers = $property->getValue();
		
		$this->assertCount(2, $handlers);
		$this->assertArrayHasKey('test1', $handlers);
		$this->assertArrayHasKey('test2', $handlers);
	}

	public function test_process_post_generation_does_not_throw(): void {
		$reflection = new ReflectionClass(JobHandler::class);
		$method = $reflection->getMethod('process_post_generation');
		$method->setAccessible(true);
		
		// This method just simulates processing, should not throw
		try {
			$method->invoke(null, 123);
			$this->assertTrue(true);
		} catch (Throwable $e) {
			$this->fail('process_post_generation should not throw: ' . $e->getMessage());
		}
	}
}