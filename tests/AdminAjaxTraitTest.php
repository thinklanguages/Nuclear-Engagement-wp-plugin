<?php
/**
 * AdminAjaxTraitTest.php - Test suite for the AdminAjax trait
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Traits\AdminAjax;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Admin\Controller\Ajax\UpdatesController;
use NuclearEngagement\Admin\Controller\Ajax\PostsCountController;
use NuclearEngagement\Admin\Controller\Ajax\GenerateController;

/**
 * Test suite for the AdminAjax trait
 */
class AdminAjaxTraitTest extends TestCase {

	private $mock_class;
	private $container;
	private $updates_controller;
	private $posts_count_controller;
	private $generate_controller;

	protected function setUp(): void {
		parent::setUp();
		
		// Create mock container
		$this->container = $this->createMock(ServiceContainer::class);
		
		// Create mock controllers
		$this->updates_controller = $this->createMock(UpdatesController::class);
		$this->posts_count_controller = $this->createMock(PostsCountController::class);
		$this->generate_controller = $this->createMock(GenerateController::class);
		
		// Create anonymous class that uses the trait
		$this->mock_class = new class($this->container) {
			use AdminAjax;
			
			private $container;
			
			public function __construct($container) {
				$this->container = $container;
			}
			
			protected function get_container() {
				return $this->container;
			}
		};
	}

	protected function tearDown(): void {
		$this->mock_class = null;
		$this->container = null;
		$this->updates_controller = null;
		$this->posts_count_controller = null;
		$this->generate_controller = null;
		parent::tearDown();
	}

	/**
	 * Test nuclen_fetch_app_updates delegates to updates controller
	 */
	public function test_nuclen_fetch_app_updates_delegates_to_controller() {
		// Set up container to return the updates controller
		$this->container->expects($this->once())
			->method('get')
			->with('updates_controller')
			->willReturn($this->updates_controller);
		
		// Expect the controller's handle method to be called
		$this->updates_controller->expects($this->once())
			->method('handle');
		
		// Call the method
		$this->mock_class->nuclen_fetch_app_updates();
	}

	/**
	 * Test nuclen_get_posts_count delegates to posts count controller
	 */
	public function test_nuclen_get_posts_count_delegates_to_controller() {
		// Set up container to return the posts count controller
		$this->container->expects($this->once())
			->method('get')
			->with('posts_count_controller')
			->willReturn($this->posts_count_controller);
		
		// Expect the controller's handle method to be called
		$this->posts_count_controller->expects($this->once())
			->method('handle');
		
		// Call the method
		$this->mock_class->nuclen_get_posts_count();
	}

	/**
	 * Test nuclen_handle_trigger_generation delegates to generate controller
	 */
	public function test_nuclen_handle_trigger_generation_delegates_to_controller() {
		// Set up container to return the generate controller
		$this->container->expects($this->once())
			->method('get')
			->with('generate_controller')
			->willReturn($this->generate_controller);
		
		// Expect the controller's handle method to be called
		$this->generate_controller->expects($this->once())
			->method('handle');
		
		// Call the method
		$this->mock_class->nuclen_handle_trigger_generation();
	}

	/**
	 * Test trait requires get_container method
	 */
	public function test_trait_requires_get_container_method() {
		$this->assertTrue(method_exists($this->mock_class, 'get_container'));
	}

	/**
	 * Test all public methods are available
	 */
	public function test_all_public_methods_available() {
		$methods = get_class_methods($this->mock_class);
		
		$this->assertContains('nuclen_fetch_app_updates', $methods);
		$this->assertContains('nuclen_get_posts_count', $methods);
		$this->assertContains('nuclen_handle_trigger_generation', $methods);
	}

	/**
	 * Test exception handling when container fails
	 */
	public function test_exception_handling_when_container_fails() {
		// Mock container to throw exception
		$this->container->expects($this->once())
			->method('get')
			->with('updates_controller')
			->willThrowException(new \Exception('Container error'));
		
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Container error');
		
		$this->mock_class->nuclen_fetch_app_updates();
	}

	/**
	 * Test exception handling when controller handle fails
	 */
	public function test_exception_handling_when_controller_handle_fails() {
		// Set up container to return controller
		$this->container->expects($this->once())
			->method('get')
			->with('generate_controller')
			->willReturn($this->generate_controller);
		
		// Mock controller to throw exception
		$this->generate_controller->expects($this->once())
			->method('handle')
			->willThrowException(new \Exception('Controller error'));
		
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Controller error');
		
		$this->mock_class->nuclen_handle_trigger_generation();
	}
}