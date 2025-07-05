<?php
/**
 * DatabaseRepositoryTest.php - Test suite for the DatabaseRepository class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Repositories\DatabaseRepository;

/**
 * Test suite for the DatabaseRepository class
 */
class DatabaseRepositoryTest extends TestCase {

	private $repository;
	private $wpdb_mock;

	protected function setUp(): void {
		parent::setUp();
		
		// Create mock wpdb object
		$this->wpdb_mock = $this->createMock('wpdb');
		$this->wpdb_mock->prefix = 'wp_';
		$this->wpdb_mock->insert_id = 123;
		$this->wpdb_mock->last_error = '';
		
		// Create a concrete implementation of the abstract class for testing
		$this->repository = new class($this->wpdb_mock) extends DatabaseRepository {
			private $mock_wpdb;
			
			public function __construct($wpdb_mock) {
				$this->mock_wpdb = $wpdb_mock;
				$this->wpdb = $wpdb_mock;
			}
			
			// Expose protected methods for testing
			public function public_execute_query(string $query, array $params = array()) {
				return $this->execute_query($query, $params);
			}
			
			public function public_get_row(string $query, array $params = array(), string $output = OBJECT) {
				return $this->get_row($query, $params, $output);
			}
			
			public function public_get_results(string $query, array $params = array(), string $output = OBJECT): array {
				return $this->get_results($query, $params, $output);
			}
			
			public function public_get_var(string $query, array $params = array(), int $col_offset = 0, int $row_offset = 0) {
				return $this->get_var($query, $params, $col_offset, $row_offset);
			}
			
			public function public_insert(string $table, array $data, $format = null) {
				return $this->insert($table, $data, $format);
			}
			
			public function public_update(string $table, array $data, array $where, $format = null, $where_format = null) {
				return $this->update($table, $data, $where, $format, $where_format);
			}
			
			public function public_delete(string $table, array $where, $where_format = null) {
				return $this->delete($table, $where, $where_format);
			}
			
			public function public_table_exists(string $table_name): bool {
				return $this->table_exists($table_name);
			}
			
			public function public_get_last_error(): string {
				return $this->get_last_error();
			}
			
			public function public_start_transaction(): void {
				$this->start_transaction();
			}
			
			public function public_commit(): void {
				$this->commit();
			}
			
			public function public_rollback(): void {
				$this->rollback();
			}
			
			public function public_get_table_prefix(): string {
				return $this->get_table_prefix();
			}
			
			public function public_escape(string $data): string {
				return $this->escape($data);
			}
			
			public function public_get_table_name(string $table_name): string {
				return $this->get_table_name($table_name);
			}
		};
	}

	protected function tearDown(): void {
		$this->repository = null;
		$this->wpdb_mock = null;
		parent::tearDown();
	}

	/**
	 * Test constructor initializes wpdb properly
	 */
	public function test_constructor_initializes_wpdb() {
		$this->assertInstanceOf('wpdb', $this->repository->wpdb);
	}

	/**
	 * Test execute_query without parameters
	 */
	public function test_execute_query_without_parameters() {
		$query = 'SELECT * FROM test_table';
		
		$this->wpdb_mock->expects($this->once())
			->method('query')
			->with($query)
			->willReturn(5);
		
		$result = $this->repository->public_execute_query($query);
		$this->assertEquals(5, $result);
	}

	/**
	 * Test execute_query with parameters
	 */
	public function test_execute_query_with_parameters() {
		$query = 'SELECT * FROM test_table WHERE id = %d';
		$params = [123];
		$prepared_query = 'SELECT * FROM test_table WHERE id = 123';
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with($query, ...$params)
			->willReturn($prepared_query);
		
		$this->wpdb_mock->expects($this->once())
			->method('query')
			->with($prepared_query)
			->willReturn(1);
		
		$result = $this->repository->public_execute_query($query, $params);
		$this->assertEquals(1, $result);
	}

	/**
	 * Test get_row without parameters
	 */
	public function test_get_row_without_parameters() {
		$query = 'SELECT * FROM test_table LIMIT 1';
		$expected_row = (object) ['id' => 1, 'name' => 'test'];
		
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->with($query, OBJECT)
			->willReturn($expected_row);
		
		$result = $this->repository->public_get_row($query);
		$this->assertEquals($expected_row, $result);
	}

	/**
	 * Test get_row with parameters
	 */
	public function test_get_row_with_parameters() {
		$query = 'SELECT * FROM test_table WHERE id = %d';
		$params = [123];
		$prepared_query = 'SELECT * FROM test_table WHERE id = 123';
		$expected_row = (object) ['id' => 123, 'name' => 'test'];
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with($query, ...$params)
			->willReturn($prepared_query);
		
		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->with($prepared_query, OBJECT)
			->willReturn($expected_row);
		
		$result = $this->repository->public_get_row($query, $params);
		$this->assertEquals($expected_row, $result);
	}

	/**
	 * Test get_results without parameters
	 */
	public function test_get_results_without_parameters() {
		$query = 'SELECT * FROM test_table';
		$expected_results = [
			(object) ['id' => 1, 'name' => 'test1'],
			(object) ['id' => 2, 'name' => 'test2']
		];
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with($query, OBJECT)
			->willReturn($expected_results);
		
		$result = $this->repository->public_get_results($query);
		$this->assertEquals($expected_results, $result);
	}

	/**
	 * Test get_results with parameters
	 */
	public function test_get_results_with_parameters() {
		$query = 'SELECT * FROM test_table WHERE status = %s';
		$params = ['active'];
		$prepared_query = 'SELECT * FROM test_table WHERE status = \'active\'';
		$expected_results = [(object) ['id' => 1, 'name' => 'test1']];
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with($query, ...$params)
			->willReturn($prepared_query);
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with($prepared_query, OBJECT)
			->willReturn($expected_results);
		
		$result = $this->repository->public_get_results($query, $params);
		$this->assertEquals($expected_results, $result);
	}

	/**
	 * Test get_results returns empty array when null
	 */
	public function test_get_results_returns_empty_array_when_null() {
		$query = 'SELECT * FROM test_table';
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with($query, OBJECT)
			->willReturn(null);
		
		$result = $this->repository->public_get_results($query);
		$this->assertEquals([], $result);
	}

	/**
	 * Test get_var without parameters
	 */
	public function test_get_var_without_parameters() {
		$query = 'SELECT COUNT(*) FROM test_table';
		$expected_value = 5;
		
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->with($query, 0, 0)
			->willReturn($expected_value);
		
		$result = $this->repository->public_get_var($query);
		$this->assertEquals($expected_value, $result);
	}

	/**
	 * Test get_var with parameters
	 */
	public function test_get_var_with_parameters() {
		$query = 'SELECT COUNT(*) FROM test_table WHERE status = %s';
		$params = ['active'];
		$prepared_query = 'SELECT COUNT(*) FROM test_table WHERE status = \'active\'';
		$expected_value = 3;
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with($query, ...$params)
			->willReturn($prepared_query);
		
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->with($prepared_query, 0, 0)
			->willReturn($expected_value);
		
		$result = $this->repository->public_get_var($query, $params);
		$this->assertEquals($expected_value, $result);
	}

	/**
	 * Test insert method
	 */
	public function test_insert() {
		$table = 'test_table';
		$data = ['name' => 'test', 'status' => 'active'];
		$format = ['%s', '%s'];
		
		$this->wpdb_mock->expects($this->once())
			->method('insert')
			->with($table, $data, $format)
			->willReturn(1);
		
		$result = $this->repository->public_insert($table, $data, $format);
		$this->assertEquals(123, $result); // insert_id
	}

	/**
	 * Test insert method returns false on error
	 */
	public function test_insert_returns_false_on_error() {
		$table = 'test_table';
		$data = ['name' => 'test'];
		
		$this->wpdb_mock->expects($this->once())
			->method('insert')
			->with($table, $data, null)
			->willReturn(false);
		
		$result = $this->repository->public_insert($table, $data);
		$this->assertFalse($result);
	}

	/**
	 * Test update method
	 */
	public function test_update() {
		$table = 'test_table';
		$data = ['name' => 'updated'];
		$where = ['id' => 1];
		$format = ['%s'];
		$where_format = ['%d'];
		
		$this->wpdb_mock->expects($this->once())
			->method('update')
			->with($table, $data, $where, $format, $where_format)
			->willReturn(1);
		
		$result = $this->repository->public_update($table, $data, $where, $format, $where_format);
		$this->assertEquals(1, $result);
	}

	/**
	 * Test delete method
	 */
	public function test_delete() {
		$table = 'test_table';
		$where = ['id' => 1];
		$where_format = ['%d'];
		
		$this->wpdb_mock->expects($this->once())
			->method('delete')
			->with($table, $where, $where_format)
			->willReturn(1);
		
		$result = $this->repository->public_delete($table, $where, $where_format);
		$this->assertEquals(1, $result);
	}

	/**
	 * Test table_exists method
	 */
	public function test_table_exists() {
		$table_name = 'test_table';
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with('SHOW TABLES LIKE %s', $table_name)
			->willReturn('SHOW TABLES LIKE \'test_table\'');
		
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->with('SHOW TABLES LIKE \'test_table\'', 0, 0)
			->willReturn($table_name);
		
		$result = $this->repository->public_table_exists($table_name);
		$this->assertTrue($result);
	}

	/**
	 * Test table_exists returns false when table doesn't exist
	 */
	public function test_table_exists_returns_false_when_not_exists() {
		$table_name = 'nonexistent_table';
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->with('SHOW TABLES LIKE %s', $table_name)
			->willReturn('SHOW TABLES LIKE \'nonexistent_table\'');
		
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->with('SHOW TABLES LIKE \'nonexistent_table\'', 0, 0)
			->willReturn(null);
		
		$result = $this->repository->public_table_exists($table_name);
		$this->assertFalse($result);
	}

	/**
	 * Test get_last_error method
	 */
	public function test_get_last_error() {
		$this->wpdb_mock->last_error = 'Database error message';
		
		$result = $this->repository->public_get_last_error();
		$this->assertEquals('Database error message', $result);
	}

	/**
	 * Test start_transaction method
	 */
	public function test_start_transaction() {
		$this->wpdb_mock->expects($this->once())
			->method('query')
			->with('START TRANSACTION');
		
		$this->repository->public_start_transaction();
	}

	/**
	 * Test commit method
	 */
	public function test_commit() {
		$this->wpdb_mock->expects($this->once())
			->method('query')
			->with('COMMIT');
		
		$this->repository->public_commit();
	}

	/**
	 * Test rollback method
	 */
	public function test_rollback() {
		$this->wpdb_mock->expects($this->once())
			->method('query')
			->with('ROLLBACK');
		
		$this->repository->public_rollback();
	}

	/**
	 * Test get_table_prefix method
	 */
	public function test_get_table_prefix() {
		$result = $this->repository->public_get_table_prefix();
		$this->assertEquals('wp_', $result);
	}

	/**
	 * Test escape method
	 */
	public function test_escape() {
		$data = "test'data";
		$escaped_data = "test\'data";
		
		$this->wpdb_mock->expects($this->once())
			->method('_escape')
			->with($data)
			->willReturn($escaped_data);
		
		$result = $this->repository->public_escape($data);
		$this->assertEquals($escaped_data, $result);
	}

	/**
	 * Test get_table_name method
	 */
	public function test_get_table_name() {
		$table_name = 'test_table';
		$expected = 'wp_test_table';
		
		$result = $this->repository->public_get_table_name($table_name);
		$this->assertEquals($expected, $result);
	}

	/**
	 * Test that DatabaseRepository is abstract
	 */
	public function test_database_repository_is_abstract() {
		$reflection = new \ReflectionClass(DatabaseRepository::class);
		$this->assertTrue($reflection->isAbstract());
	}

	/**
	 * Test wpdb property is protected
	 */
	public function test_wpdb_property_is_protected() {
		$reflection = new \ReflectionClass(DatabaseRepository::class);
		$property = $reflection->getProperty('wpdb');
		$this->assertTrue($property->isProtected());
	}

	/**
	 * Test all methods are protected except constructor
	 */
	public function test_methods_are_protected() {
		$reflection = new \ReflectionClass(DatabaseRepository::class);
		$methods = $reflection->getMethods(\ReflectionMethod::IS_PROTECTED);
		
		$protected_method_names = array_map(function($method) {
			return $method->getName();
		}, $methods);
		
		$expected_protected_methods = [
			'execute_query',
			'get_row',
			'get_results',
			'get_var',
			'insert',
			'update',
			'delete',
			'table_exists',
			'get_last_error',
			'start_transaction',
			'commit',
			'rollback',
			'get_table_prefix',
			'escape',
			'get_table_name'
		];
		
		foreach ($expected_protected_methods as $method_name) {
			$this->assertContains($method_name, $protected_method_names);
		}
	}
}