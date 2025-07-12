<?php
/**
 * ThemeLoaderTest.php - Test suite for the ThemeLoader class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\ThemeLoader;
use NuclearEngagement\Repositories\ThemeRepository;
use NuclearEngagement\Models\Theme;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;

/**
 * Test suite for the ThemeLoader class
 */
class ThemeLoaderTest extends TestCase {

	private $theme_loader;
	private $repository_mock;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		
		// Create mock repository
		$this->repository_mock = $this->createMock(ThemeRepository::class);
		
		// Mock WordPress functions
		Functions\when('add_action')->justReturn(true);
		Functions\when('wp_enqueue_script')->justReturn(true);
		Functions\when('wp_localize_script')->justReturn(true);
		Functions\when('wp_enqueue_style')->justReturn(true);
		Functions\when('admin_url')->justReturn('http://example.com/wp-admin/admin-ajax.php');
		Functions\when('wp_create_nonce')->justReturn('test_nonce');
		Functions\when('apply_filters')->returnArg(2);
		Functions\when('add_filter')->justReturn(true);
		Functions\when('file_exists')->justReturn(true);
		Functions\when('check_ajax_referer')->justReturn(true);
		Functions\when('wp_send_json_success')->justReturn(true);
		
		// Mock constants
		if (!defined('NUCLEN_PLUGIN_URL')) {
			define('NUCLEN_PLUGIN_URL', 'http://example.com/wp-content/plugins/nuclear-engagement/');
		}
		if (!defined('NUCLEN_PLUGIN_VERSION')) {
			define('NUCLEN_PLUGIN_VERSION', '2.0.4');
		}
		if (!defined('NUCLEN_VERSION')) {
			define('NUCLEN_VERSION', '2.0.4');
		}
		
		// Create theme loader instance
		$this->theme_loader = new ThemeLoader($this->repository_mock);
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		$this->theme_loader = null;
		$this->repository_mock = null;
		parent::tearDown();
	}

	/**
	 * Test constructor with repository
	 */
	public function test_constructor_with_repository() {
		$this->assertInstanceOf(ThemeLoader::class, $this->theme_loader);
	}

	/**
	 * Test constructor without repository creates new one
	 */
	public function test_constructor_without_repository() {
		$theme_loader = new ThemeLoader();
		$this->assertInstanceOf(ThemeLoader::class, $theme_loader);
	}

	/**
	 * Test init method registers hooks
	 */
	public function test_init_registers_hooks() {
		Actions\expectAdded('wp_enqueue_scripts')
			->once()
			->with([$this->theme_loader, 'enqueue_lazy_loader']);
		
		Actions\expectAdded('wp_ajax_nuclen_get_theme_urls')
			->once()
			->with([$this->theme_loader, 'ajax_get_theme_urls']);
		
		Actions\expectAdded('wp_ajax_nopriv_nuclen_get_theme_urls')
			->once()
			->with([$this->theme_loader, 'ajax_get_theme_urls']);
		
		$this->theme_loader->init();
	}

	/**
	 * Test enqueue_lazy_loader with active custom theme
	 */
	public function test_enqueue_lazy_loader_with_active_custom_theme() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = 1;
		$theme_mock->type = Theme::TYPE_CUSTOM;
		
		$this->repository_mock->expects($this->once())
			->method('get_active')
			->willReturn($theme_mock);
		
		// Mock the wp_enqueue_script and wp_localize_script calls
		Functions\expect('wp_enqueue_script')
			->once()
			->with(
				'nuclen-theme-loader',
				NUCLEN_PLUGIN_URL . 'assets/js/theme-loader.js',
				[],
				NUCLEN_PLUGIN_VERSION,
				true
			);
		
		Functions\expect('wp_localize_script')
			->once()
			->with(
				'nuclen-theme-loader',
				'nuclenThemeLoader',
				\Mockery::type('array')
			);
		
		$this->theme_loader->enqueue_lazy_loader();
	}

	/**
	 * Test enqueue_lazy_loader with no active theme
	 */
	public function test_enqueue_lazy_loader_with_no_active_theme() {
		$this->repository_mock->expects($this->once())
			->method('get_active')
			->willReturn(null);
		
		Functions\expect('wp_enqueue_script')->never();
		
		$this->theme_loader->enqueue_lazy_loader();
	}

	/**
	 * Test enqueue_lazy_loader with preset theme
	 */
	public function test_enqueue_lazy_loader_with_preset_theme() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->type = Theme::TYPE_PRESET;
		
		$this->repository_mock->expects($this->once())
			->method('get_active')
			->willReturn($theme_mock);
		
		Functions\expect('wp_enqueue_script')->never();
		
		$this->theme_loader->enqueue_lazy_loader();
	}

	/**
	 * Test enqueue_lazy_loader only runs once
	 */
	public function test_enqueue_lazy_loader_only_runs_once() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = 1;
		$theme_mock->type = Theme::TYPE_CUSTOM;
		
		$this->repository_mock->expects($this->exactly(2))
			->method('get_active')
			->willReturn($theme_mock);
		
		Functions\expect('wp_enqueue_script')->once();
		
		// Call twice, should only enqueue once
		$this->theme_loader->enqueue_lazy_loader();
		$this->theme_loader->enqueue_lazy_loader();
	}

	/**
	 * Test load_theme_css with active theme
	 */
	public function test_load_theme_css_with_active_theme() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = 1;
		$theme_mock->type = Theme::TYPE_PRESET;
		$theme_mock->name = 'default';
		
		$this->repository_mock->expects($this->once())
			->method('get_active')
			->willReturn($theme_mock);
		
		Functions\expect('wp_enqueue_style')
			->once()
			->with(
				'nuclen-theme-default',
				NUCLEN_PLUGIN_URL . 'assets/css/themes/default.css',
				['nuclen-front'],
				NUCLEN_VERSION
			);
		
		$this->theme_loader->load_theme_css();
	}

	/**
	 * Test load_theme_css with specific theme ID
	 */
	public function test_load_theme_css_with_specific_theme_id() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = 2;
		$theme_mock->type = Theme::TYPE_PRESET;
		$theme_mock->name = 'dark';
		
		$this->repository_mock->expects($this->once())
			->method('find')
			->with(2)
			->willReturn($theme_mock);
		
		Functions\expect('wp_enqueue_style')->once();
		Functions\expect('add_filter')->once();
		
		$this->theme_loader->load_theme_css(2);
	}

	/**
	 * Test load_theme_css with nonexistent theme
	 */
	public function test_load_theme_css_with_nonexistent_theme() {
		$this->repository_mock->expects($this->once())
			->method('find')
			->with(999)
			->willReturn(null);
		
		Functions\expect('wp_enqueue_style')->never();
		
		$this->theme_loader->load_theme_css(999);
	}

	/**
	 * Test load_theme_css doesn't load same theme twice
	 */
	public function test_load_theme_css_doesnt_load_same_theme_twice() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = 1;
		$theme_mock->type = Theme::TYPE_PRESET;
		$theme_mock->name = 'default';
		
		$this->repository_mock->expects($this->exactly(2))
			->method('find')
			->with(1)
			->willReturn($theme_mock);
		
		Functions\expect('wp_enqueue_style')->once();
		
		// Load twice, should only enqueue once
		$this->theme_loader->load_theme_css(1);
		$this->theme_loader->load_theme_css(1);
	}

	/**
	 * Test get_component_selectors method via reflection
	 */
	public function test_get_component_selectors() {
		$reflection = new \ReflectionClass($this->theme_loader);
		$method = $reflection->getMethod('get_component_selectors');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->theme_loader);
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('quiz', $result);
		$this->assertArrayHasKey('progress', $result);
		$this->assertArrayHasKey('summary', $result);
		$this->assertArrayHasKey('toc', $result);
		$this->assertArrayHasKey('button', $result);
		
		$this->assertEquals('.nuclen-quiz-container', $result['quiz']);
		$this->assertEquals('.nuclen-progress-bar', $result['progress']);
	}

	/**
	 * Test get_inline_critical_css with active theme
	 */
	public function test_get_inline_critical_css_with_active_theme() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->config = [
			'quiz_container' => [
				'background_color' => '#ffffff',
				'border_color' => '#cccccc'
			],
			'quiz_button' => [
				'background_color' => '#007cba',
				'text_color' => '#ffffff'
			]
		];
		
		$this->repository_mock->expects($this->once())
			->method('get_active')
			->willReturn($theme_mock);
		
		$result = $this->theme_loader->get_inline_critical_css();
		
		$this->assertStringContainsString('<style id=\'nuclen-theme-critical\'>', $result);
		$this->assertStringContainsString(':root {', $result);
		$this->assertStringContainsString('--nuclen-quiz-container-background-color: #ffffff;', $result);
		$this->assertStringContainsString('--nuclen-quiz-button-text-color: #ffffff;', $result);
		$this->assertStringContainsString('</style>', $result);
	}

	/**
	 * Test get_inline_critical_css with no active theme
	 */
	public function test_get_inline_critical_css_with_no_active_theme() {
		$this->repository_mock->expects($this->once())
			->method('get_active')
			->willReturn(null);
		
		$result = $this->theme_loader->get_inline_critical_css();
		
		$this->assertEquals('', $result);
	}

	/**
	 * Test get_inline_critical_css with empty config
	 */
	public function test_get_inline_critical_css_with_empty_config() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->config = [];
		
		$this->repository_mock->expects($this->once())
			->method('get_active')
			->willReturn($theme_mock);
		
		$result = $this->theme_loader->get_inline_critical_css();
		
		$this->assertEquals('', $result);
	}

	/**
	 * Test extract_critical_vars method via reflection
	 */
	public function test_extract_critical_vars() {
		$reflection = new \ReflectionClass($this->theme_loader);
		$method = $reflection->getMethod('extract_critical_vars');
		$method->setAccessible(true);
		
		$config = [
			'quiz_container' => [
				'background_color' => '#ffffff',
				'border_color' => '#cccccc'
			],
			'progress_bar' => [
				'background_color' => '#f0f0f0',
				'fill_color' => '#007cba'
			]
		];
		
		$result = $method->invoke($this->theme_loader, $config);
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('--nuclen-quiz-container-background-color', $result);
		$this->assertArrayHasKey('--nuclen-quiz-container-border-color', $result);
		$this->assertArrayHasKey('--nuclen-progress-bar-background-color', $result);
		$this->assertArrayHasKey('--nuclen-progress-bar-fill-color', $result);
		
		$this->assertEquals('#ffffff', $result['--nuclen-quiz-container-background-color']);
		$this->assertEquals('#007cba', $result['--nuclen-progress-bar-fill-color']);
	}

	/**
	 * Test ajax_get_theme_urls with POST data
	 */
	public function test_ajax_get_theme_urls() {
		// Mock $_POST data
		$_POST['theme_ids'] = ['1', '2'];
		$_POST['nonce'] = 'test_nonce';
		
		$theme1 = $this->createMock(Theme::class);
		$theme1->id = 1;
		$theme1->type = Theme::TYPE_PRESET;
		$theme1->name = 'default';
		
		$theme2 = $this->createMock(Theme::class);
		$theme2->id = 2;
		$theme2->type = Theme::TYPE_CUSTOM;
		$theme2->css_path = '/path/to/custom.css';
		$theme2->expects($this->once())
			->method('get_css_url')
			->willReturn('http://example.com/custom.css');
		
		$this->repository_mock->expects($this->exactly(2))
			->method('find')
			->withConsecutive([1], [2])
			->willReturnOnConsecutiveCalls($theme1, $theme2);
		
		Functions\expect('check_ajax_referer')
			->once()
			->with('nuclen_theme_loader', 'nonce');
		
		Functions\expect('wp_send_json_success')
			->once()
			->with([
				1 => NUCLEN_PLUGIN_URL . 'assets/css/themes/default.css',
				2 => 'http://example.com/custom.css'
			]);
		
		$this->theme_loader->ajax_get_theme_urls();
		
		// Clean up $_POST
		unset($_POST['theme_ids'], $_POST['nonce']);
	}

	/**
	 * Test that private properties are set correctly
	 */
	public function test_private_properties() {
		$reflection = new \ReflectionClass($this->theme_loader);
		
		$repository_property = $reflection->getProperty('repository');
		$repository_property->setAccessible(true);
		$this->assertSame($this->repository_mock, $repository_property->getValue($this->theme_loader));
		
		$loaded_themes_property = $reflection->getProperty('loaded_themes');
		$loaded_themes_property->setAccessible(true);
		$this->assertEquals([], $loaded_themes_property->getValue($this->theme_loader));
		
		$enqueued_scripts_property = $reflection->getProperty('enqueued_scripts');
		$enqueued_scripts_property->setAccessible(true);
		$this->assertFalse($enqueued_scripts_property->getValue($this->theme_loader));
	}
}