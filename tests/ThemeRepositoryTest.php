<?php
/**
 * ThemeRepositoryTest.php - Test suite for the ThemeRepository class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Repositories\ThemeRepository;
use NuclearEngagement\Models\Theme;
use NuclearEngagement\Database\Schema\ThemeSchema;
use Brain\Monkey\Functions;

/**
 * Test suite for the ThemeRepository class
 */
class ThemeRepositoryTest extends TestCase {

	private $repository;
	private $wpdb_mock;
	private $table_name = 'wp_nuclen_themes';

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		
		// Create mock wpdb
		$this->wpdb_mock = $this->createMock('wpdb');
		$this->wpdb_mock->insert_id = 123;
		
		// Mock WordPress functions
		Functions\when('wp_json_encode')->returnArg();
		
		// Mock the ThemeSchema static method
		Functions\when('NuclearEngagement\Database\Schema\ThemeSchema::get_table_name')
			->justReturn($this->table_name);
		
		// Set global $wpdb
		global $wpdb;
		$wpdb = $this->wpdb_mock;
		
		// Create repository instance
		$this->repository = new ThemeRepository();
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		$this->repository = null;
		$this->wpdb_mock = null;
		parent::tearDown();
	}

	/**
	 * Test constructor initializes table name
	 */
	public function test_constructor_initializes_table_name() {
		$this->assertInstanceOf(ThemeRepository::class, $this->repository);
	}

	/**
	 * Test find method returns Theme when found
	 */
	public function test_find_returns_theme_when_found() {
		$theme_data = [
			'id' => 1,
			'name' => 'Test Theme',
			'type' => 'quiz',
			'config_json' => '{"color": "blue"}',
			'css_hash' => 'abc123',
			'css_path' => '/path/to/css',
			'is_active' => 1
		];
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with("SELECT * FROM {$this->table_name} WHERE id = %d", 1)
			->willReturn("SELECT * FROM {$this->table_name} WHERE id = 1");
		
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->with("SELECT * FROM {$this->table_name} WHERE id = 1", ARRAY_A)
			->willReturn($theme_data);
		
		$result = $this->repository->find(1);
		
		$this->assertInstanceOf(Theme::class, $result);
	}

	/**
	 * Test find method returns null when not found
	 */
	public function test_find_returns_null_when_not_found() {
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with("SELECT * FROM {$this->table_name} WHERE id = %d", 999)
			->willReturn("SELECT * FROM {$this->table_name} WHERE id = 999");
		
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->with("SELECT * FROM {$this->table_name} WHERE id = 999", ARRAY_A)
			->willReturn(null);
		
		$result = $this->repository->find(999);
		
		$this->assertNull($result);
	}

	/**
	 * Test find_by_name method returns Theme when found
	 */
	public function test_find_by_name_returns_theme_when_found() {
		$theme_data = [
			'id' => 1,
			'name' => 'Test Theme',
			'type' => 'quiz',
			'config_json' => '{"color": "blue"}',
			'css_hash' => 'abc123',
			'css_path' => '/path/to/css',
			'is_active' => 1
		];
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with("SELECT * FROM {$this->table_name} WHERE name = %s", 'Test Theme')
			->willReturn("SELECT * FROM {$this->table_name} WHERE name = 'Test Theme'");
		
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->with("SELECT * FROM {$this->table_name} WHERE name = 'Test Theme'", ARRAY_A)
			->willReturn($theme_data);
		
		$result = $this->repository->find_by_name('Test Theme');
		
		$this->assertInstanceOf(Theme::class, $result);
	}

	/**
	 * Test find_by_name method returns null when not found
	 */
	public function test_find_by_name_returns_null_when_not_found() {
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with("SELECT * FROM {$this->table_name} WHERE name = %s", 'Nonexistent Theme')
			->willReturn("SELECT * FROM {$this->table_name} WHERE name = 'Nonexistent Theme'");
		
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->with("SELECT * FROM {$this->table_name} WHERE name = 'Nonexistent Theme'", ARRAY_A)
			->willReturn(null);
		
		$result = $this->repository->find_by_name('Nonexistent Theme');
		
		$this->assertNull($result);
	}

	/**
	 * Test get_active method returns active theme
	 */
	public function test_get_active_returns_active_theme() {
		$theme_data = [
			'id' => 1,
			'name' => 'Active Theme',
			'type' => 'quiz',
			'config_json' => '{"color": "blue"}',
			'css_hash' => 'abc123',
			'css_path' => '/path/to/css',
			'is_active' => 1
		];
		
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->with("SELECT * FROM {$this->table_name} WHERE is_active = 1 LIMIT 1", ARRAY_A)
			->willReturn($theme_data);
		
		$result = $this->repository->get_active();
		
		$this->assertInstanceOf(Theme::class, $result);
	}

	/**
	 * Test get_active method returns null when no active theme
	 */
	public function test_get_active_returns_null_when_no_active_theme() {
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->with("SELECT * FROM {$this->table_name} WHERE is_active = 1 LIMIT 1", ARRAY_A)
			->willReturn(null);
		
		$result = $this->repository->get_active();
		
		$this->assertNull($result);
	}

	/**
	 * Test get_all method returns all themes
	 */
	public function test_get_all_returns_all_themes() {
		$themes_data = [
			[
				'id' => 1,
				'name' => 'Theme 1',
				'type' => 'quiz',
				'config_json' => '{"color": "blue"}',
				'css_hash' => 'abc123',
				'css_path' => '/path/to/css1',
				'is_active' => 1
			],
			[
				'id' => 2,
				'name' => 'Theme 2',
				'type' => 'summary',
				'config_json' => '{"color": "red"}',
				'css_hash' => 'def456',
				'css_path' => '/path/to/css2',
				'is_active' => 0
			]
		];
		
		$expected_query = "SELECT * FROM {$this->table_name} ORDER BY name ASC";
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with($expected_query, ARRAY_A)
			->willReturn($themes_data);
		
		$result = $this->repository->get_all();
		
		$this->assertCount(2, $result);
		$this->assertInstanceOf(Theme::class, $result[0]);
		$this->assertInstanceOf(Theme::class, $result[1]);
	}

	/**
	 * Test get_all method with type filter
	 */
	public function test_get_all_with_type_filter() {
		$themes_data = [
			[
				'id' => 1,
				'name' => 'Quiz Theme',
				'type' => 'quiz',
				'config_json' => '{"color": "blue"}',
				'css_hash' => 'abc123',
				'css_path' => '/path/to/css',
				'is_active' => 1
			]
		];
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with("SELECT * FROM {$this->table_name} WHERE type = %s", 'quiz')
			->willReturn("SELECT * FROM {$this->table_name} WHERE type = 'quiz'");
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with("SELECT * FROM {$this->table_name} WHERE type = 'quiz' ORDER BY name ASC", ARRAY_A)
			->willReturn($themes_data);
		
		$result = $this->repository->get_all('quiz');
		
		$this->assertCount(1, $result);
		$this->assertInstanceOf(Theme::class, $result[0]);
	}

	/**
	 * Test save method for new theme (insert)
	 */
	public function test_save_inserts_new_theme() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = null;
		$theme_mock->name = 'New Theme';
		$theme_mock->type = 'quiz';
		$theme_mock->config = ['color' => 'blue'];
		$theme_mock->css_hash = null;
		$theme_mock->css_path = '/path/to/css';
		$theme_mock->is_active = false;
		
		$theme_mock->expects($this->once())
			->method('generate_hash')
			->willReturn('generated_hash');
		
		$expected_data = [
			'name' => 'New Theme',
			'type' => 'quiz',
			'config_json' => ['color' => 'blue'],
			'css_hash' => 'generated_hash',
			'css_path' => '/path/to/css',
			'is_active' => 0
		];
		
		$this->wpdb_mock->expects($this->once())
			->method('insert')
			->with(
				$this->table_name,
				$expected_data,
				['%s', '%s', '%s', '%s', '%s', '%d']
			)
			->willReturn(1);
		
		$result = $this->repository->save($theme_mock);
		
		$this->assertEquals($theme_mock, $result);
		$this->assertEquals(123, $theme_mock->id);
	}

	/**
	 * Test save method for existing theme (update)
	 */
	public function test_save_updates_existing_theme() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = 1;
		$theme_mock->name = 'Updated Theme';
		$theme_mock->type = 'quiz';
		$theme_mock->config = ['color' => 'green'];
		$theme_mock->css_hash = 'existing_hash';
		$theme_mock->css_path = '/path/to/updated_css';
		$theme_mock->is_active = true;
		
		$expected_data = [
			'name' => 'Updated Theme',
			'type' => 'quiz',
			'config_json' => ['color' => 'green'],
			'css_hash' => 'existing_hash',
			'css_path' => '/path/to/updated_css',
			'is_active' => 1
		];
		
		$this->wpdb_mock->expects($this->once())
			->method('update')
			->with(
				$this->table_name,
				$expected_data,
				['id' => 1],
				['%s', '%s', '%s', '%s', '%s', '%d'],
				['%d']
			)
			->willReturn(1);
		
		$result = $this->repository->save($theme_mock);
		
		$this->assertEquals($theme_mock, $result);
	}

	/**
	 * Test save method returns false on insert failure
	 */
	public function test_save_returns_false_on_insert_failure() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = null;
		$theme_mock->name = 'Failed Theme';
		$theme_mock->type = 'quiz';
		$theme_mock->config = ['color' => 'blue'];
		$theme_mock->css_hash = 'hash';
		$theme_mock->css_path = '/path';
		$theme_mock->is_active = false;
		
		$this->wpdb_mock->expects($this->once())
			->method('insert')
			->willReturn(false);
		
		$result = $this->repository->save($theme_mock);
		
		$this->assertFalse($result);
	}

	/**
	 * Test save method returns false on update failure
	 */
	public function test_save_returns_false_on_update_failure() {
		$theme_mock = $this->createMock(Theme::class);
		$theme_mock->id = 1;
		$theme_mock->name = 'Failed Theme';
		$theme_mock->type = 'quiz';
		$theme_mock->config = ['color' => 'blue'];
		$theme_mock->css_hash = 'hash';
		$theme_mock->css_path = '/path';
		$theme_mock->is_active = false;
		
		$this->wpdb_mock->expects($this->once())
			->method('update')
			->willReturn(false);
		
		$result = $this->repository->save($theme_mock);
		
		$this->assertFalse($result);
	}

	/**
	 * Test delete method
	 */
	public function test_delete() {
		$this->wpdb_mock->expects($this->once())
			->method('delete')
			->with(
				$this->table_name,
				['id' => 1],
				['%d']
			)
			->willReturn(1);
		
		$result = $this->repository->delete(1);
		
		$this->assertEquals(1, $result);
	}

	/**
	 * Test set_active method
	 */
	public function test_set_active() {
		// First call to deactivate all themes
		$this->wpdb_mock->expects($this->at(0))
			->method('prepare')
			->with("UPDATE {$this->table_name} SET is_active = 0")
			->willReturn("UPDATE {$this->table_name} SET is_active = 0");
		
		$this->wpdb_mock->expects($this->at(1))
			->method('query')
			->with("UPDATE {$this->table_name} SET is_active = 0");
		
		// Second call to activate specific theme
		$this->wpdb_mock->expects($this->at(2))
			->method('update')
			->with(
				$this->table_name,
				['is_active' => 1],
				['id' => 1],
				['%d'],
				['%d']
			)
			->willReturn(1);
		
		$result = $this->repository->set_active(1);
		
		$this->assertEquals(1, $result);
	}

	/**
	 * Test deactivate_all method
	 */
	public function test_deactivate_all() {
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with("UPDATE {$this->table_name} SET is_active = 0")
			->willReturn("UPDATE {$this->table_name} SET is_active = 0");
		
		$this->wpdb_mock->expects($this->once())
			->method('query')
			->with("UPDATE {$this->table_name} SET is_active = 0")
			->willReturn(1);
		
		$result = $this->repository->deactivate_all();
		
		$this->assertEquals(1, $result);
	}

	/**
	 * Test that ThemeRepository uses global wpdb
	 */
	public function test_uses_global_wpdb() {
		// This test verifies that the repository properly uses the global $wpdb
		// The setup already mocks the global $wpdb, so any method call should work
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->willReturn(null);
		
		$this->repository->get_active();
	}

	/**
	 * Test constructor with ThemeSchema dependency
	 */
	public function test_constructor_uses_theme_schema() {
		// Verify that constructor calls ThemeSchema::get_table_name()
		// This is implicitly tested by the setUp method, but we can verify
		// the table name is set correctly by examining private property
		$reflection = new \ReflectionClass($this->repository);
		$property = $reflection->getProperty('table_name');
		$property->setAccessible(true);
		
		$this->assertEquals($this->table_name, $property->getValue($this->repository));
	}
}