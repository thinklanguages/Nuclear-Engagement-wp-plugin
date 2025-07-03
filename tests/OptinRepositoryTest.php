<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Repositories\OptinRepository;

class OptinRepositoryTest extends TestCase {
	private OptinRepository $repository;
	private $mockWpdb;
	
	protected function setUp(): void {
		parent::setUp();
		
		// Create mock wpdb
		$this->mockWpdb = $this->createMock(stdClass::class);
		$this->mockWpdb->prefix = 'wp_';
		$this->mockWpdb->method('get_charset_collate')->willReturn('');
		
		// Create repository with reflection to inject mock
		$this->repository = new OptinRepository();
		$reflection = new ReflectionClass($this->repository);
		$property = $reflection->getProperty('wpdb');
		$property->setAccessible(true);
		$property->setValue($this->repository, $this->mockWpdb);
	}
	
	public function test_get_optin_table_name() {
		$this->assertEquals('wp_nuclen_optins', $this->repository->get_optin_table_name());
	}
	
	public function test_optin_table_exists_returns_true_when_cached() {
		// Use reflection to set cache
		$reflection = new ReflectionClass(OptinRepository::class);
		$property = $reflection->getProperty('table_exists_cache');
		$property->setAccessible(true);
		$property->setValue(null, true);
		
		// Should not call database
		$this->mockWpdb->expects($this->never())->method('get_var');
		
		$this->assertTrue($this->repository->optin_table_exists());
		
		// Reset cache
		$property->setValue(null, null);
	}
	
	public function test_insert_optin_validates_required_fields() {
		// Test missing email
		$result = $this->repository->insert_optin(['post_id' => 1]);
		$this->assertFalse($result);
		
		// Test missing post_id
		$result = $this->repository->insert_optin(['email' => 'test@example.com']);
		$this->assertFalse($result);
	}
	
	public function test_insert_optin_checks_for_duplicates() {
		// Mock optin_exists to return true
		$repository = $this->getMockBuilder(OptinRepository::class)
			->onlyMethods(['optin_exists', 'insert'])
			->getMock();
		
		$repository->expects($this->once())
			->method('optin_exists')
			->with('test@example.com', 123)
			->willReturn(true);
		
		$repository->expects($this->never())
			->method('insert');
		
		$result = $repository->insert_optin([
			'email' => 'test@example.com',
			'post_id' => 123
		]);
		
		$this->assertFalse($result);
	}
	
	public function test_insert_optin_sanitizes_data() {
		// Mock methods
		$repository = $this->getMockBuilder(OptinRepository::class)
			->onlyMethods(['optin_exists', 'insert'])
			->getMock();
		
		$repository->expects($this->once())
			->method('optin_exists')
			->willReturn(false);
		
		$expectedData = [
			'email' => 'test@example.com',
			'post_id' => 123,
			'post_title' => 'Test Title',
			'quiz_score' => '8/10',
			'user_agent' => 'Mozilla/5.0',
			'ip_address' => '192.168.1.1'
		];
		
		$repository->expects($this->once())
			->method('insert')
			->with($this->anything(), $expectedData)
			->willReturn(1);
		
		$result = $repository->insert_optin([
			'email' => '  TEST@EXAMPLE.COM  ',
			'post_id' => '123',
			'post_title' => '<script>Test Title</script>',
			'quiz_score' => '8/10',
			'user_agent' => 'Mozilla/5.0',
			'ip_address' => '192.168.1.1'
		]);
		
		$this->assertEquals(1, $result);
	}
	
	public function test_get_optins_validates_order_direction() {
		$this->mockWpdb->expects($this->once())
			->method('prepare')
			->with(
				$this->stringContains('ORDER BY created_at DESC'),
				[50, 0]
			)
			->willReturn('');
		
		$this->mockWpdb->expects($this->once())
			->method('get_results')
			->willReturn([]);
		
		$this->repository->get_optins(50, 0, 'created_at', 'invalid');
	}
	
	public function test_get_optins_validates_order_by_column() {
		$this->mockWpdb->expects($this->once())
			->method('prepare')
			->with(
				$this->stringContains('ORDER BY created_at DESC'),
				[50, 0]
			)
			->willReturn('');
		
		$this->mockWpdb->expects($this->once())
			->method('get_results')
			->willReturn([]);
		
		$this->repository->get_optins(50, 0, 'invalid_column', 'DESC');
	}
	
	public function test_get_optin_count_with_filters() {
		$filters = [
			'post_id' => 123,
			'email' => 'test@example.com',
			'date_from' => '2024-01-01',
			'date_to' => '2024-12-31'
		];
		
		$this->mockWpdb->expects($this->once())
			->method('prepare')
			->with(
				$this->stringContains('WHERE post_id = %d AND email = %s AND created_at >= %s AND created_at <= %s'),
				[123, 'test@example.com', '2024-01-01', '2024-12-31']
			)
			->willReturn('');
		
		$this->mockWpdb->expects($this->once())
			->method('get_var')
			->willReturn('42');
		
		$count = $this->repository->get_optin_count($filters);
		$this->assertEquals(42, $count);
	}
	
	public function test_cleanup_old_optins() {
		$days = 30;
		$expectedDate = date('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
		
		$this->mockWpdb->expects($this->once())
			->method('prepare')
			->with(
				$this->stringContains('DELETE FROM wp_nuclen_optins WHERE created_at < %s'),
				[$this->callback(function($date) use ($expectedDate) {
					// Allow for small time differences in test execution
					$diff = abs(strtotime($date) - strtotime($expectedDate));
					return $diff < 5; // 5 seconds tolerance
				})]
			)
			->willReturn('');
		
		$this->mockWpdb->expects($this->once())
			->method('query')
			->willReturn(10);
		
		$result = $this->repository->cleanup_old_optins($days);
		$this->assertEquals(10, $result);
	}
	
	public function test_get_optin_stats() {
		// Set up expected results
		$this->mockWpdb->expects($this->exactly(3))
			->method('get_var')
			->willReturnOnConsecutiveCalls('1000', '250');
		
		$topPosts = [
			(object)['post_id' => 1, 'post_title' => 'Post 1', 'count' => 50],
			(object)['post_id' => 2, 'post_title' => 'Post 2', 'count' => 30]
		];
		
		$this->mockWpdb->expects($this->once())
			->method('get_results')
			->willReturn($topPosts);
		
		$stats = $this->repository->get_optin_stats(30);
		
		$this->assertEquals(1000, $stats['total_optins']);
		$this->assertEquals(250, $stats['recent_optins']);
		$this->assertEquals($topPosts, $stats['top_posts']);
	}
}

// Mock WordPress functions
if (!function_exists('absint')) {
	function absint($val) {
		return abs(intval($val));
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email($email) {
		return strtolower(trim($email));
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($text) {
		return strip_tags(trim($text));
	}
}

if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

if (!defined('ABSPATH')) {
	define('ABSPATH', '/tmp/');
}

// Mock DatabaseRepository class
namespace NuclearEngagement\Repositories;

class DatabaseRepository {
	protected $wpdb;
	
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}
	
	protected function get_table_name($slug) {
		return $this->wpdb->prefix . $slug;
	}
	
	protected function table_exists($table_name) {
		return $this->wpdb->get_var($this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
	}
	
	protected function insert($table, $data, $format = null) {
		return $this->wpdb->insert($table, $data, $format);
	}
	
	protected function get_var($query, $params = []) {
		if (!empty($params)) {
			$query = $this->wpdb->prepare($query, ...$params);
		}
		return $this->wpdb->get_var($query);
	}
	
	protected function get_row($query, $params = []) {
		if (!empty($params)) {
			$query = $this->wpdb->prepare($query, ...$params);
		}
		return $this->wpdb->get_row($query);
	}
	
	protected function get_results($query, $params = []) {
		if (!empty($params)) {
			$query = $this->wpdb->prepare($query, ...$params);
		}
		return $this->wpdb->get_results($query);
	}
	
	protected function delete($table, $where, $format = null) {
		return $this->wpdb->delete($table, $where, $format);
	}
	
	protected function execute_query($query, $params = []) {
		if (!empty($params)) {
			$query = $this->wpdb->prepare($query, ...$params);
		}
		return $this->wpdb->query($query);
	}
}