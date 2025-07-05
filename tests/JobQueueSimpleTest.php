<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\JobQueue;

class JobQueueSimpleTest extends TestCase {

	private $mockWpdb;

	protected function setUp(): void {
		// Reset static state
		$reflection = new ReflectionClass(JobQueue::class);
		$property = $reflection->getProperty('job_queue');
		$property->setAccessible(true);
		$property->setValue([]);
		
		// Mock global $wpdb
		$this->mockWpdb = new class {
			public $prefix = 'wp_';
			public $last_error = '';
			public $insert_id = 1;
			public $queries = [];
			public $results = [];
			
			public function prepare($query, ...$args) {
				return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
			}
			
			public function insert($table, $data, $format) {
				$this->queries[] = ['type' => 'insert', 'table' => $table, 'data' => $data];
				if (isset($data['job_id']) && strpos($data['job_id'], 'error') !== false) {
					$this->last_error = 'Insert failed';
					return false;
				}
				return 1;
			}
			
			public function update($table, $data, $where, $data_format, $where_format) {
				$this->queries[] = ['type' => 'update', 'table' => $table, 'data' => $data, 'where' => $where];
				return 1;
			}
			
			public function get_results($query, $output) {
				$this->queries[] = ['type' => 'get_results', 'query' => $query];
				return $this->results['get_results'] ?? [];
			}
			
			public function get_row($query, $output) {
				$this->queries[] = ['type' => 'get_row', 'query' => $query];
				return $this->results['get_row'] ?? [];
			}
			
			public function get_var($query) {
				$this->queries[] = ['type' => 'get_var', 'query' => $query];
				if (strpos($query, 'SHOW TABLES') !== false) {
					return $this->results['table_exists'] ?? null;
				}
				return $this->results['get_var'] ?? null;
			}
			
			public function query($query) {
				$this->queries[] = ['type' => 'query', 'query' => $query];
				return 1;
			}
			
			public function get_charset_collate() {
				return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
			}
		};
		
		global $wpdb;
		$wpdb = $this->mockWpdb;
		
		// Mock WordPress functions
		if (!function_exists('wp_generate_uuid4')) {
			function wp_generate_uuid4() {
				return 'test-uuid-' . uniqid();
			}
		}
		
		if (!function_exists('wp_json_encode')) {
			function wp_json_encode($data, $options = 0, $depth = 512) {
				return json_encode($data, $options, $depth);
			}
		}
		
		// Mock LoggingService if it doesn't exist
		if (!class_exists('NuclearEngagement\Services\LoggingService')) {
			eval('
				namespace NuclearEngagement\Services {
					class LoggingService {
						public static $logs = [];
						public static function log($message) {
							self::$logs[] = $message;
						}
					}
				}
			');
		}
		
		// Mock constants
		if (!defined('DAY_IN_SECONDS')) {
			define('DAY_IN_SECONDS', 86400);
		}
	}

	public function test_queue_job_returns_job_id(): void {
		$job_id = JobQueue::queue_job('test_job', ['data' => 'test']);
		
		$this->assertIsString($job_id);
		$this->assertStringStartsWith('test-uuid-', $job_id);
	}

	public function test_queue_job_stores_in_database(): void {
		JobQueue::queue_job('test_job', ['data' => 'test'], 5, 10);
		
		$insertQuery = null;
		foreach ($this->mockWpdb->queries as $query) {
			if ($query['type'] === 'insert') {
				$insertQuery = $query;
				break;
			}
		}
		
		$this->assertNotNull($insertQuery);
		$this->assertEquals('wp_nuclen_background_jobs', $insertQuery['table']);
		$this->assertEquals('test_job', $insertQuery['data']['type']);
		$this->assertEquals(5, $insertQuery['data']['priority']);
	}

	public function test_queue_job_with_delay(): void {
		$currentTime = time();
		$delay = 60;
		
		JobQueue::queue_job('delayed_job', [], 10, $delay);
		
		$insertQuery = null;
		foreach ($this->mockWpdb->queries as $query) {
			if ($query['type'] === 'insert') {
				$insertQuery = $query;
				break;
			}
		}
		
		$this->assertNotNull($insertQuery);
		$this->assertGreaterThanOrEqual($currentTime + $delay, $insertQuery['data']['scheduled']);
	}

	public function test_cancel_job_updates_database(): void {
		$result = JobQueue::cancel_job('test-job-123');
		
		$updateQuery = null;
		foreach ($this->mockWpdb->queries as $query) {
			if ($query['type'] === 'update') {
				$updateQuery = $query;
				break;
			}
		}
		
		$this->assertNotNull($updateQuery);
		$this->assertEquals('wp_nuclen_background_jobs', $updateQuery['table']);
		$this->assertEquals('cancelled', $updateQuery['data']['status']);
		$this->assertEquals('test-job-123', $updateQuery['where']['job_id']);
		$this->assertTrue($result);
	}

	public function test_cleanup_completed_jobs(): void {
		JobQueue::cleanup_completed_jobs();
		
		$deleteQuery = null;
		foreach ($this->mockWpdb->queries as $query) {
			if ($query['type'] === 'query' && strpos($query['query'], 'DELETE') !== false) {
				$deleteQuery = $query;
				break;
			}
		}
		
		$this->assertNotNull($deleteQuery);
		$this->assertStringContainsString('DELETE FROM wp_nuclen_background_jobs', $deleteQuery['query']);
		$this->assertStringContainsString("status IN ('completed', 'failed', 'cancelled')", $deleteQuery['query']);
		$this->assertStringContainsString('created <', $deleteQuery['query']);
	}

	public function test_job_data_serialization(): void {
		$complexData = [
			'posts' => [1, 2, 3],
			'options' => [
				'format' => 'json',
				'include_meta' => true
			]
		];
		
		JobQueue::queue_job('complex_job', $complexData);
		
		$insertQuery = null;
		foreach ($this->mockWpdb->queries as $query) {
			if ($query['type'] === 'insert') {
				$insertQuery = $query;
				break;
			}
		}
		
		$this->assertNotNull($insertQuery);
		$serializedData = $insertQuery['data']['data'];
		$this->assertIsString($serializedData);
		
		$decodedData = json_decode($serializedData, true);
		$this->assertEquals($complexData, $decodedData);
	}

	public function test_job_priority_and_status_defaults(): void {
		JobQueue::queue_job('default_job');
		
		$insertQuery = null;
		foreach ($this->mockWpdb->queries as $query) {
			if ($query['type'] === 'insert') {
				$insertQuery = $query;
				break;
			}
		}
		
		$this->assertNotNull($insertQuery);
		$this->assertEquals(10, $insertQuery['data']['priority']); // Default priority
		$this->assertEquals('queued', $insertQuery['data']['status']); // Default status
		$this->assertEquals(0, $insertQuery['data']['attempts']); // Default attempts
		$this->assertEquals(0, $insertQuery['data']['progress']); // Default progress
	}

	public function test_memory_queue_storage(): void {
		$job_id = JobQueue::queue_job('memory_test');
		
		// Use reflection to check memory storage
		$reflection = new ReflectionClass(JobQueue::class);
		$property = $reflection->getProperty('job_queue');
		$property->setAccessible(true);
		$queue = $property->getValue();
		
		$this->assertArrayHasKey($job_id, $queue);
		$this->assertEquals('memory_test', $queue[$job_id]['type']);
	}

	public function test_cancel_job_removes_from_memory(): void {
		$job_id = JobQueue::queue_job('cancel_test');
		
		// Verify it's in memory
		$reflection = new ReflectionClass(JobQueue::class);
		$property = $reflection->getProperty('job_queue');
		$property->setAccessible(true);
		$queue = $property->getValue();
		$this->assertArrayHasKey($job_id, $queue);
		
		// Cancel the job
		JobQueue::cancel_job($job_id);
		
		// Verify it's removed from memory
		$queue = $property->getValue();
		$this->assertArrayNotHasKey($job_id, $queue);
	}

	public function test_constants_are_defined(): void {
		$reflection = new ReflectionClass(JobQueue::class);
		
		$this->assertTrue($reflection->hasConstant('MAX_CONCURRENT_JOBS'));
		$this->assertEquals(3, $reflection->getConstant('MAX_CONCURRENT_JOBS'));
	}
}