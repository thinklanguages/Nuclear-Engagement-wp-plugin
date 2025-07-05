<?php
/**
 * SettingsTest.php - Test suite for the Settings class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Settings;
use NuclearEngagement\Core\SettingsRepository;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;

/**
 * Test suite for the Settings class
 */
class SettingsTest extends TestCase {

	private $settings;
	private $settings_repository;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		
		// Create mock settings repository
		$this->settings_repository = $this->createMock(SettingsRepository::class);
		
		// Mock WordPress functions
		Functions\when('add_action')->justReturn(true);
		
		// Create the Settings instance
		$this->settings = new Settings($this->settings_repository);
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		$this->settings = null;
		$this->settings_repository = null;
		parent::tearDown();
	}

	/**
	 * Test constructor initializes properties correctly
	 */
	public function test_constructor_initializes_properties() {
		$this->assertInstanceOf(Settings::class, $this->settings);
	}

	/**
	 * Test constructor registers admin_enqueue_scripts action
	 */
	public function test_constructor_registers_admin_enqueue_scripts_action() {
		Actions\expectAdded('admin_enqueue_scripts')
			->once()
			->with([$this->settings, 'nuclen_enqueue_color_picker']);
		
		// Create a new instance to trigger the action registration
		new Settings($this->settings_repository);
	}

	/**
	 * Test nuclen_get_settings_repository returns correct instance
	 */
	public function test_nuclen_get_settings_repository_returns_correct_instance() {
		$repository = $this->settings->nuclen_get_settings_repository();
		$this->assertSame($this->settings_repository, $repository);
	}

	/**
	 * Test nuclen_enqueue_color_picker is callable
	 */
	public function test_nuclen_enqueue_color_picker_is_callable() {
		$this->assertTrue(is_callable([$this->settings, 'nuclen_enqueue_color_picker']));
	}

	/**
	 * Test nuclen_enqueue_color_picker executes without error
	 */
	public function test_nuclen_enqueue_color_picker_executes_without_error() {
		// This should not throw any exception since it's a no-op
		$this->settings->nuclen_enqueue_color_picker('settings_page_nuclear-engagement');
		$this->addToAssertionCount(1);
	}

	/**
	 * Test that Settings uses expected traits
	 */
	public function test_settings_uses_expected_traits() {
		$traits = class_uses($this->settings);
		
		$this->assertArrayHasKey('NuclearEngagement\Admin\SettingsColorPickerTrait', $traits);
		$this->assertArrayHasKey('NuclearEngagement\Admin\SettingsSanitizeTrait', $traits);
		$this->assertArrayHasKey('NuclearEngagement\Admin\SettingsPageTrait', $traits);
	}

	/**
	 * Test settings repository property initialization
	 */
	public function test_settings_repository_property_initialization() {
		$reflection = new \ReflectionClass($this->settings);
		$property = $reflection->getProperty('settings_repository');
		$property->setAccessible(true);
		
		$this->assertSame($this->settings_repository, $property->getValue($this->settings));
	}

	/**
	 * Test constructor with null settings repository
	 */
	public function test_constructor_with_null_settings_repository() {
		$this->expectError();
		new Settings(null);
	}

	/**
	 * Test constructor with invalid settings repository type
	 */
	public function test_constructor_with_invalid_settings_repository_type() {
		$this->expectError();
		new Settings(new \stdClass());
	}

	/**
	 * Test that nuclen_enqueue_color_picker accepts hook_suffix parameter
	 */
	public function test_nuclen_enqueue_color_picker_accepts_hook_suffix() {
		$hook_suffixes = [
			'settings_page_nuclear-engagement',
			'toplevel_page_nuclear-engagement',
			'admin_page_test',
			''
		];
		
		foreach ($hook_suffixes as $hook_suffix) {
			$this->settings->nuclen_enqueue_color_picker($hook_suffix);
		}
		
		$this->addToAssertionCount(count($hook_suffixes));
	}

	/**
	 * Test that Settings class is final in implementation
	 */
	public function test_settings_class_is_not_abstract() {
		$reflection = new \ReflectionClass($this->settings);
		$this->assertFalse($reflection->isAbstract());
	}

	/**
	 * Test that Settings has public methods from traits
	 */
	public function test_settings_has_public_methods_from_traits() {
		$this->assertTrue(method_exists($this->settings, 'nuclen_enqueue_color_picker'));
		$this->assertTrue(method_exists($this->settings, 'nuclen_get_settings_repository'));
	}

	/**
	 * Test method visibility
	 */
	public function test_method_visibility() {
		$reflection = new \ReflectionClass($this->settings);
		
		// Check that public methods are actually public
		$public_method = $reflection->getMethod('nuclen_get_settings_repository');
		$this->assertTrue($public_method->isPublic());
		
		$color_picker_method = $reflection->getMethod('nuclen_enqueue_color_picker');
		$this->assertTrue($color_picker_method->isPublic());
	}

	/**
	 * Test that Settings instance can be serialized and unserialized
	 */
	public function test_settings_serialization() {
		$serialized = serialize($this->settings);
		$unserialized = unserialize($serialized);
		
		$this->assertInstanceOf(Settings::class, $unserialized);
		$this->assertInstanceOf(SettingsRepository::class, $unserialized->nuclen_get_settings_repository());
	}
}