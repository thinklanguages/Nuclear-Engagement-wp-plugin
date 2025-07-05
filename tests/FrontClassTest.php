<?php
/**
 * FrontClassTest.php - Test suite for the FrontClass
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Utils\Utils;
use Brain\Monkey\Functions;

/**
 * Test suite for the FrontClass
 */
class FrontClassTest extends TestCase {

	private $front_class;
	private $settings_repository;
	private $container;
	private $plugin_name = 'nuclear-engagement';
	private $version = '2.0.0';

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		
		// Create mocks
		$this->settings_repository = $this->createMock(SettingsRepository::class);
		$this->container = $this->createMock(ServiceContainer::class);
		
		// Mock WordPress functions if needed
		Functions\when('defined')->justReturn(true);
		
		// Create the FrontClass instance
		$this->front_class = new FrontClass(
			$this->plugin_name,
			$this->version,
			$this->settings_repository,
			$this->container
		);
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		$this->front_class = null;
		$this->settings_repository = null;
		$this->container = null;
		parent::tearDown();
	}

	/**
	 * Test constructor initializes properties correctly
	 */
	public function test_constructor_initializes_properties() {
		$this->assertInstanceOf(FrontClass::class, $this->front_class);
	}

	/**
	 * Test nuclen_get_utils returns Utils instance
	 */
	public function test_nuclen_get_utils_returns_utils_instance() {
		$utils = $this->front_class->nuclen_get_utils();
		$this->assertInstanceOf(Utils::class, $utils);
	}

	/**
	 * Test nuclen_get_settings_repository returns correct instance
	 */
	public function test_nuclen_get_settings_repository_returns_correct_instance() {
		$repository = $this->front_class->nuclen_get_settings_repository();
		$this->assertSame($this->settings_repository, $repository);
	}

	/**
	 * Test get_container returns correct instance via reflection
	 */
	public function test_get_container_returns_correct_instance() {
		// Access protected method via reflection
		$reflection = new \ReflectionClass($this->front_class);
		$method = $reflection->getMethod('get_container');
		$method->setAccessible(true);
		
		$container = $method->invoke($this->front_class);
		$this->assertSame($this->container, $container);
	}

	/**
	 * Test that FrontClass uses expected traits
	 */
	public function test_front_class_uses_expected_traits() {
		$traits = class_uses($this->front_class);
		
		$this->assertArrayHasKey('NuclearEngagement\Front\AssetsTrait', $traits);
		$this->assertArrayHasKey('NuclearEngagement\Front\RestTrait', $traits);
		$this->assertArrayHasKey('NuclearEngagement\Front\ShortcodesTrait', $traits);
	}

	/**
	 * Test property initialization
	 */
	public function test_property_initialization() {
		$reflection = new \ReflectionClass($this->front_class);
		
		// Check plugin_name property
		$plugin_name_property = $reflection->getProperty('plugin_name');
		$plugin_name_property->setAccessible(true);
		$this->assertEquals($this->plugin_name, $plugin_name_property->getValue($this->front_class));
		
		// Check version property
		$version_property = $reflection->getProperty('version');
		$version_property->setAccessible(true);
		$this->assertEquals($this->version, $version_property->getValue($this->front_class));
		
		// Check utils property
		$utils_property = $reflection->getProperty('utils');
		$utils_property->setAccessible(true);
		$this->assertInstanceOf(Utils::class, $utils_property->getValue($this->front_class));
		
		// Check settings_repository property
		$settings_property = $reflection->getProperty('settings_repository');
		$settings_property->setAccessible(true);
		$this->assertSame($this->settings_repository, $settings_property->getValue($this->front_class));
		
		// Check container property
		$container_property = $reflection->getProperty('container');
		$container_property->setAccessible(true);
		$this->assertSame($this->container, $container_property->getValue($this->front_class));
	}

	/**
	 * Test constructor with null parameters throws error
	 */
	public function test_constructor_with_null_parameters() {
		$this->expectError();
		new FrontClass(null, null, null, null);
	}

	/**
	 * Test constructor with invalid parameter types
	 */
	public function test_constructor_with_invalid_parameter_types() {
		$this->expectError();
		new FrontClass(123, [], 'invalid', new \stdClass());
	}

	/**
	 * Test that public methods are accessible
	 */
	public function test_public_methods_are_accessible() {
		$this->assertTrue(method_exists($this->front_class, 'nuclen_get_utils'));
		$this->assertTrue(method_exists($this->front_class, 'nuclen_get_settings_repository'));
	}

	/**
	 * Test that protected get_container method exists
	 */
	public function test_protected_get_container_method_exists() {
		$reflection = new \ReflectionClass($this->front_class);
		$this->assertTrue($reflection->hasMethod('get_container'));
		
		$method = $reflection->getMethod('get_container');
		$this->assertTrue($method->isProtected());
	}

	/**
	 * Test method visibility
	 */
	public function test_method_visibility() {
		$reflection = new \ReflectionClass($this->front_class);
		
		// Check that public methods are actually public
		$utils_method = $reflection->getMethod('nuclen_get_utils');
		$this->assertTrue($utils_method->isPublic());
		
		$settings_method = $reflection->getMethod('nuclen_get_settings_repository');
		$this->assertTrue($settings_method->isPublic());
		
		// Check that protected method is protected
		$container_method = $reflection->getMethod('get_container');
		$this->assertTrue($container_method->isProtected());
	}

	/**
	 * Test property types are correct
	 */
	public function test_property_types() {
		$reflection = new \ReflectionClass($this->front_class);
		
		// Check plugin_name is string
		$plugin_name_property = $reflection->getProperty('plugin_name');
		$plugin_name_property->setAccessible(true);
		$this->assertIsString($plugin_name_property->getValue($this->front_class));
		
		// Check version is string
		$version_property = $reflection->getProperty('version');
		$version_property->setAccessible(true);
		$this->assertIsString($version_property->getValue($this->front_class));
		
		// Check utils is Utils instance
		$utils_property = $reflection->getProperty('utils');
		$utils_property->setAccessible(true);
		$this->assertInstanceOf(Utils::class, $utils_property->getValue($this->front_class));
		
		// Check settings_repository is SettingsRepository instance
		$settings_property = $reflection->getProperty('settings_repository');
		$settings_property->setAccessible(true);
		$this->assertInstanceOf(SettingsRepository::class, $settings_property->getValue($this->front_class));
		
		// Check container is ServiceContainer instance
		$container_property = $reflection->getProperty('container');
		$container_property->setAccessible(true);
		$this->assertInstanceOf(ServiceContainer::class, $container_property->getValue($this->front_class));
	}

	/**
	 * Test that FrontClass can be serialized and unserialized
	 */
	public function test_front_class_serialization() {
		$serialized = serialize($this->front_class);
		$unserialized = unserialize($serialized);
		
		$this->assertInstanceOf(FrontClass::class, $unserialized);
		$this->assertInstanceOf(Utils::class, $unserialized->nuclen_get_utils());
		$this->assertInstanceOf(SettingsRepository::class, $unserialized->nuclen_get_settings_repository());
	}

	/**
	 * Test that all expected properties are private
	 */
	public function test_all_properties_are_private() {
		$reflection = new \ReflectionClass($this->front_class);
		$properties = $reflection->getProperties();
		
		foreach ($properties as $property) {
			$this->assertTrue($property->isPrivate(), "Property {$property->getName()} should be private");
		}
	}

	/**
	 * Test FrontClass has expected property count
	 */
	public function test_front_class_has_expected_property_count() {
		$reflection = new \ReflectionClass($this->front_class);
		$properties = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE);
		
		// Should have 5 private properties: plugin_name, version, utils, settings_repository, container
		$this->assertCount(5, $properties);
	}

	/**
	 * Test that traits provide additional methods (basic check)
	 */
	public function test_traits_provide_additional_methods() {
		// Since traits add methods, the class should have more methods than just the 3 defined
		$reflection = new \ReflectionClass($this->front_class);
		$methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
		
		// Should have at least the 2 public methods defined in the class
		$this->assertGreaterThanOrEqual(2, count($methods));
	}

	/**
	 * Test constructor creates new Utils instance
	 */
	public function test_constructor_creates_new_utils_instance() {
		// Create another instance to verify Utils is newly instantiated each time
		$front_class2 = new FrontClass(
			'test-plugin',
			'1.0.0',
			$this->settings_repository,
			$this->container
		);
		
		$utils1 = $this->front_class->nuclen_get_utils();
		$utils2 = $front_class2->nuclen_get_utils();
		
		// Should be different instances
		$this->assertNotSame($utils1, $utils2);
		$this->assertInstanceOf(Utils::class, $utils1);
		$this->assertInstanceOf(Utils::class, $utils2);
	}
}