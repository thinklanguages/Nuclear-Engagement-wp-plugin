<?php
/**
 * AdminTest.php - Test suite for the Admin class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Admin;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Utils\Utils;

/**
 * Test suite for the Admin class
 */
class AdminTest extends TestCase {

	private $admin;
	private $settings_repository;
	private $container;
	private $plugin_name = 'nuclear-engagement';
	private $version = '2.0.4';

	protected function setUp(): void {
		parent::setUp();
		
		// Reset settings repository for testing
		SettingsRepository::reset_for_tests();
		
		// Use actual SettingsRepository instance since it's final
		$this->settings_repository = SettingsRepository::get_instance();
		
		// Create mock for ServiceContainer
		$this->container = $this->createMock(ServiceContainer::class);
		
		// Create the Admin instance
		$this->admin = new Admin(
			$this->plugin_name,
			$this->version,
			$this->settings_repository,
			$this->container
		);
	}

	protected function tearDown(): void {
		$this->admin = null;
		$this->settings_repository = null;
		$this->container = null;
		parent::tearDown();
	}

	/**
	 * Test constructor initialization
	 */
	public function test_constructor_initializes_properties() {
		$this->assertInstanceOf(Admin::class, $this->admin);
	}

	/**
	 * Test nuclen_get_plugin_name returns correct value
	 */
	public function test_nuclen_get_plugin_name_returns_correct_value() {
		$this->assertEquals($this->plugin_name, $this->admin->nuclen_get_plugin_name());
	}

	/**
	 * Test nuclen_get_version returns correct value
	 */
	public function test_nuclen_get_version_returns_correct_value() {
		$this->assertEquals($this->version, $this->admin->nuclen_get_version());
	}

	/**
	 * Test nuclen_get_utils returns Utils instance
	 */
	public function test_nuclen_get_utils_returns_utils_instance() {
		$utils = $this->admin->nuclen_get_utils();
		$this->assertInstanceOf(Utils::class, $utils);
	}

	/**
	 * Test nuclen_get_settings_repository returns correct instance
	 */
	public function test_nuclen_get_settings_repository_returns_correct_instance() {
		$repository = $this->admin->nuclen_get_settings_repository();
		$this->assertSame($this->settings_repository, $repository);
	}

	/**
	 * Test get_container returns correct instance via reflection
	 */
	public function test_get_container_returns_correct_instance() {
		// Access protected method via reflection
		$reflection = new \ReflectionClass($this->admin);
		$method = $reflection->getMethod('get_container');
		$method->setAccessible(true);
		
		$container = $method->invoke($this->admin);
		$this->assertSame($this->container, $container);
	}

	/**
	 * Test that admin uses expected traits
	 */
	public function test_admin_uses_expected_traits() {
		$traits = class_uses($this->admin);
		
		$this->assertArrayHasKey('NuclearEngagement\Admin\Traits\AdminMetaboxes', $traits);
		$this->assertArrayHasKey('NuclearEngagement\Admin\Traits\AdminAjax', $traits);
		$this->assertArrayHasKey('NuclearEngagement\Admin\Traits\AdminMenu', $traits);
		$this->assertArrayHasKey('NuclearEngagement\Admin\Traits\AdminAssets', $traits);
	}

	/**
	 * Test admin property initialization
	 */
	public function test_admin_properties_are_initialized() {
		$reflection = new \ReflectionClass($this->admin);
		
		// Check plugin_name property
		$plugin_name_property = $reflection->getProperty('plugin_name');
		$plugin_name_property->setAccessible(true);
		$this->assertEquals($this->plugin_name, $plugin_name_property->getValue($this->admin));
		
		// Check version property
		$version_property = $reflection->getProperty('version');
		$version_property->setAccessible(true);
		$this->assertEquals($this->version, $version_property->getValue($this->admin));
		
		// Check utils property
		$utils_property = $reflection->getProperty('utils');
		$utils_property->setAccessible(true);
		$this->assertInstanceOf(Utils::class, $utils_property->getValue($this->admin));
		
		// Check settings_repository property
		$settings_property = $reflection->getProperty('settings_repository');
		$settings_property->setAccessible(true);
		$this->assertSame($this->settings_repository, $settings_property->getValue($this->admin));
		
		// Check container property
		$container_property = $reflection->getProperty('container');
		$container_property->setAccessible(true);
		$this->assertSame($this->container, $container_property->getValue($this->admin));
	}

	/**
	 * Test constructor with null parameters
	 */
	public function test_constructor_throws_error_with_null_parameters() {
		$this->expectError();
		new Admin(null, null, null, null);
	}

	/**
	 * Test constructor with invalid parameter types
	 */
	public function test_constructor_with_invalid_parameter_types() {
		$this->expectError();
		new Admin(123, [], 'invalid', new \stdClass());
	}
}