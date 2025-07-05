<?php
/**
 * PostRepositoryTest.php - Test suite for the PostRepository class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Repositories\PostRepository;
use NuclearEngagement\Entities\Post;
use NuclearEngagement\Contracts\CacheInterface;
use Brain\Monkey\Functions;

/**
 * Test suite for the PostRepository class
 */
class PostRepositoryTest extends TestCase {

	private $repository;
	private $cache_mock;
	private $wpdb_mock;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		
		// Create mock cache
		$this->cache_mock = $this->createMock(CacheInterface::class);
		
		// Create mock wpdb
		$this->wpdb_mock = $this->createMock('wpdb');
		$this->wpdb_mock->posts = 'wp_posts';
		$this->wpdb_mock->prefix = 'wp_';
		
		// Mock WordPress functions
		Functions\when('maybe_serialize')->returnArg();
		Functions\when('get_posts')->justReturn([]);
		
		// Create repository instance
		$this->repository = new PostRepository();
		
		// Inject dependencies via reflection
		$reflection = new \ReflectionClass($this->repository);
		
		$cache_property = $reflection->getProperty('cache');
		$cache_property->setAccessible(true);
		$cache_property->setValue($this->repository, $this->cache_mock);
		
		$wpdb_property = $reflection->getProperty('wpdb');
		$wpdb_property->setAccessible(true);
		$wpdb_property->setValue($this->repository, $this->wpdb_mock);
		
		$cache_ttl_property = $reflection->getProperty('cache_ttl');
		$cache_ttl_property->setAccessible(true);
		$cache_ttl_property->setValue($this->repository, 3600);
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		$this->repository = null;
		$this->cache_mock = null;
		$this->wpdb_mock = null;
		parent::tearDown();
	}

	/**
	 * Test get_cache_group returns correct value
	 */
	public function test_get_cache_group() {
		$reflection = new \ReflectionClass($this->repository);
		$method = $reflection->getMethod('get_cache_group');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->repository);
		$this->assertEquals('nuclen_posts', $result);
	}

	/**
	 * Test get_table_name returns correct value
	 */
	public function test_get_table_name() {
		$reflection = new \ReflectionClass($this->repository);
		$method = $reflection->getMethod('get_table_name');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->repository);
		$this->assertEquals('wp_posts', $result);
	}

	/**
	 * Test get_primary_key returns correct value
	 */
	public function test_get_primary_key() {
		$reflection = new \ReflectionClass($this->repository);
		$method = $reflection->getMethod('get_primary_key');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->repository);
		$this->assertEquals('ID', $result);
	}

	/**
	 * Test find_with_meta returns cached results
	 */
	public function test_find_with_meta_returns_cached_results() {
		$cached_posts = [
			new Post(1, 'Test Post', 'Content', 'Excerpt', 'publish', 'post', 1, '2023-01-01', '2023-01-01')
		];
		
		$this->cache_mock->expects($this->once())
			->method('get')
			->willReturn($cached_posts);
		
		$result = $this->repository->find_with_meta(['post_type' => 'post']);
		$this->assertEquals($cached_posts, $result);
	}

	/**
	 * Test find_with_meta executes query when no cache
	 */
	public function test_find_with_meta_executes_query_when_no_cache() {
		// Mock cache miss
		$this->cache_mock->expects($this->at(0))
			->method('get')
			->willReturn(null);
		
		// Mock WP_Query
		$query_mock = $this->createMock('WP_Query');
		$query_mock->posts = [1, 2];
		
		// Mock database results
		$db_rows = [
			(object) [
				'ID' => 1,
				'post_title' => 'Test Post 1',
				'post_content' => 'Content 1',
				'post_excerpt' => 'Excerpt 1',
				'post_status' => 'publish',
				'post_type' => 'post',
				'post_author' => 1,
				'post_date' => '2023-01-01',
				'post_modified' => '2023-01-01'
			],
			(object) [
				'ID' => 2,
				'post_title' => 'Test Post 2',
				'post_content' => 'Content 2',
				'post_excerpt' => 'Excerpt 2',
				'post_status' => 'publish',
				'post_type' => 'post',
				'post_author' => 1,
				'post_date' => '2023-01-01',
				'post_modified' => '2023-01-01'
			]
		];
		
		$this->wpdb_mock->expects($this->once())
			->method('prepare')
			->willReturn('SELECT * FROM wp_posts WHERE ID IN (1,2)');
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->willReturn($db_rows);
		
		// Mock cache set operations
		$this->cache_mock->expects($this->at(1))
			->method('set')
			->with($this->anything(), $this->isInstanceOf(Post::class), 3600);
		
		$this->cache_mock->expects($this->at(2))
			->method('set')
			->with($this->anything(), $this->isInstanceOf(Post::class), 3600);
		
		$this->cache_mock->expects($this->at(3))
			->method('set')
			->with($this->anything(), $this->isType('array'), 3600);
		
		// Mock WP_Query constructor
		Functions\when('new')->alias(function($class, $args) use ($query_mock) {
			if ($class === 'WP_Query') {
				return $query_mock;
			}
			return new $class($args);
		});
		
		$result = $this->repository->find_with_meta(['post_type' => 'post']);
		
		$this->assertCount(2, $result);
		$this->assertInstanceOf(Post::class, $result[0]);
		$this->assertEquals('Test Post 1', $result[0]->get_title());
	}

	/**
	 * Test find_with_meta returns empty array when no posts found
	 */
	public function test_find_with_meta_returns_empty_array_when_no_posts() {
		// Mock cache miss
		$this->cache_mock->expects($this->once())
			->method('get')
			->willReturn(null);
		
		// Mock WP_Query with no results
		$query_mock = $this->createMock('WP_Query');
		$query_mock->posts = [];
		
		Functions\when('new')->alias(function($class, $args) use ($query_mock) {
			if ($class === 'WP_Query') {
				return $query_mock;
			}
			return new $class($args);
		});
		
		$result = $this->repository->find_with_meta(['post_type' => 'post']);
		$this->assertEquals([], $result);
	}

	/**
	 * Test count_with_meta returns cached count
	 */
	public function test_count_with_meta_returns_cached_count() {
		$cached_count = 5;
		
		$this->cache_mock->expects($this->once())
			->method('get')
			->willReturn($cached_count);
		
		$result = $this->repository->count_with_meta(['post_type' => 'post']);
		$this->assertEquals(5, $result);
	}

	/**
	 * Test count_with_meta executes query when no cache
	 */
	public function test_count_with_meta_executes_query_when_no_cache() {
		// Mock cache miss
		$this->cache_mock->expects($this->once())
			->method('get')
			->willReturn(null);
		
		// Mock WP_Query
		$query_mock = $this->createMock('WP_Query');
		$query_mock->found_posts = 10;
		
		// Mock cache set
		$this->cache_mock->expects($this->once())
			->method('set')
			->with($this->anything(), 10, 3600);
		
		Functions\when('new')->alias(function($class, $args) use ($query_mock) {
			if ($class === 'WP_Query') {
				return $query_mock;
			}
			return new $class($args);
		});
		
		$result = $this->repository->count_with_meta(['post_type' => 'post']);
		$this->assertEquals(10, $result);
	}

	/**
	 * Test find_without_meta calls find_with_meta with correct meta criteria
	 */
	public function test_find_without_meta() {
		// Mock cache miss for find_with_meta
		$this->cache_mock->expects($this->once())
			->method('get')
			->willReturn(null);
		
		// Mock WP_Query
		$query_mock = $this->createMock('WP_Query');
		$query_mock->posts = [];
		
		Functions\when('new')->alias(function($class, $args) use ($query_mock) {
			if ($class === 'WP_Query') {
				return $query_mock;
			}
			return new $class($args);
		});
		
		$result = $this->repository->find_without_meta('test_meta_key');
		$this->assertEquals([], $result);
	}

	/**
	 * Test find_by_workflow for quiz type
	 */
	public function test_find_by_workflow_quiz() {
		// Mock cache miss
		$this->cache_mock->expects($this->once())
			->method('get')
			->willReturn(null);
		
		// Mock WP_Query
		$query_mock = $this->createMock('WP_Query');
		$query_mock->posts = [];
		
		Functions\when('new')->alias(function($class, $args) use ($query_mock) {
			if ($class === 'WP_Query') {
				return $query_mock;
			}
			return new $class($args);
		});
		
		$result = $this->repository->find_by_workflow('quiz');
		$this->assertEquals([], $result);
	}

	/**
	 * Test find_by_workflow for quiz type with protected only
	 */
	public function test_find_by_workflow_quiz_protected_only() {
		// Mock cache miss
		$this->cache_mock->expects($this->once())
			->method('get')
			->willReturn(null);
		
		// Mock WP_Query
		$query_mock = $this->createMock('WP_Query');
		$query_mock->posts = [];
		
		Functions\when('new')->alias(function($class, $args) use ($query_mock) {
			if ($class === 'WP_Query') {
				return $query_mock;
			}
			return new $class($args);
		});
		
		$result = $this->repository->find_by_workflow('quiz', true);
		$this->assertEquals([], $result);
	}

	/**
	 * Test find_by_workflow for summary type
	 */
	public function test_find_by_workflow_summary() {
		// Mock cache miss
		$this->cache_mock->expects($this->once())
			->method('get')
			->willReturn(null);
		
		// Mock WP_Query
		$query_mock = $this->createMock('WP_Query');
		$query_mock->posts = [];
		
		Functions\when('new')->alias(function($class, $args) use ($query_mock) {
			if ($class === 'WP_Query') {
				return $query_mock;
			}
			return new $class($args);
		});
		
		$result = $this->repository->find_by_workflow('summary');
		$this->assertEquals([], $result);
	}

	/**
	 * Test build_meta_query with empty criteria
	 */
	public function test_build_meta_query_empty_criteria() {
		$reflection = new \ReflectionClass($this->repository);
		$method = $reflection->getMethod('build_meta_query');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->repository, []);
		$this->assertEquals([], $result);
	}

	/**
	 * Test build_meta_query with criteria
	 */
	public function test_build_meta_query_with_criteria() {
		$reflection = new \ReflectionClass($this->repository);
		$method = $reflection->getMethod('build_meta_query');
		$method->setAccessible(true);
		
		$criteria = [
			['key' => 'test_key', 'value' => 'test_value', 'compare' => '='],
			['key' => 'another_key', 'compare' => 'EXISTS']
		];
		
		$result = $method->invoke($this->repository, $criteria);
		
		$this->assertArrayHasKey('relation', $result);
		$this->assertEquals('AND', $result['relation']);
		$this->assertCount(3, $result); // relation + 2 criteria
	}

	/**
	 * Test hydrate method
	 */
	public function test_hydrate() {
		$reflection = new \ReflectionClass($this->repository);
		$method = $reflection->getMethod('hydrate');
		$method->setAccessible(true);
		
		$row = (object) [
			'ID' => 1,
			'post_title' => 'Test Post',
			'post_content' => 'Test Content',
			'post_excerpt' => 'Test Excerpt',
			'post_status' => 'publish',
			'post_type' => 'post',
			'post_author' => 1,
			'post_date' => '2023-01-01',
			'post_modified' => '2023-01-01'
		];
		
		$result = $method->invoke($this->repository, $row);
		
		$this->assertInstanceOf(Post::class, $result);
		$this->assertEquals(1, $result->get_id());
		$this->assertEquals('Test Post', $result->get_title());
		$this->assertEquals('Test Content', $result->get_content());
	}

	/**
	 * Test extract method
	 */
	public function test_extract() {
		$reflection = new \ReflectionClass($this->repository);
		$method = $reflection->getMethod('extract');
		$method->setAccessible(true);
		
		$post = new Post(1, 'Test Post', 'Test Content', 'Test Excerpt', 'publish', 'post', 1, '2023-01-01', '2023-01-01');
		
		$result = $method->invoke($this->repository, $post);
		
		$this->assertIsArray($result);
		$this->assertEquals(1, $result['ID']);
		$this->assertEquals('Test Post', $result['post_title']);
		$this->assertEquals('Test Content', $result['post_content']);
		$this->assertEquals('Test Excerpt', $result['post_excerpt']);
		$this->assertEquals('publish', $result['post_status']);
		$this->assertEquals('post', $result['post_type']);
		$this->assertEquals(1, $result['post_author']);
		$this->assertEquals('2023-01-01', $result['post_date']);
		$this->assertEquals('2023-01-01', $result['post_modified']);
	}

	/**
	 * Test extract method throws exception for invalid entity
	 */
	public function test_extract_throws_exception_for_invalid_entity() {
		$reflection = new \ReflectionClass($this->repository);
		$method = $reflection->getMethod('extract');
		$method->setAccessible(true);
		
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Entity must be instance of Post');
		
		$method->invoke($this->repository, new \stdClass());
	}

	/**
	 * Test PostRepository extends AbstractRepository
	 */
	public function test_post_repository_extends_abstract_repository() {
		$reflection = new \ReflectionClass($this->repository);
		$parent = $reflection->getParentClass();
		$this->assertEquals('NuclearEngagement\Repositories\AbstractRepository', $parent->getName());
	}

	/**
	 * Test PostRepository implements required abstract methods
	 */
	public function test_post_repository_implements_required_methods() {
		$required_methods = ['get_cache_group', 'get_table_name', 'get_primary_key', 'hydrate', 'extract'];
		
		foreach ($required_methods as $method) {
			$this->assertTrue(method_exists($this->repository, $method));
		}
	}
}