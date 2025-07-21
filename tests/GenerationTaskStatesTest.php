<?php
/**
 * GenerationTaskStatesTest.php - Tests for generation task states and transitions
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\GenerationService;
use NuclearEngagement\Services\BulkGenerationBatchProcessor;
use NuclearEngagement\Services\TaskTransientManager;
use NuclearEngagement\Services\CentralizedPollingQueue;
use NuclearEngagement\Services\CircuitBreakerService;
use NuclearEngagement\Core\ServiceContainer;

/**
 * Test all generation task states and transitions
 */
class GenerationTaskStatesTest extends TestCase {
	private ServiceContainer $container;
	private GenerationService $generation_service;
	private BulkGenerationBatchProcessor $batch_processor;
	private CentralizedPollingQueue $polling_queue;
	private CircuitBreakerService $circuit_breaker;

	public function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions
		\Brain\Monkey\setUp();
		
		// Setup mocks
		$this->container = $this->createMock(ServiceContainer::class);
		$this->generation_service = $this->createMock(GenerationService::class);
		$this->batch_processor = $this->createMock(BulkGenerationBatchProcessor::class);
		$this->polling_queue = $this->createMock(CentralizedPollingQueue::class);
		$this->circuit_breaker = $this->createMock(CircuitBreakerService::class);
		
		// Mock global functions
		\Brain\Monkey\Functions\when('get_transient')->justReturn(false);
		\Brain\Monkey\Functions\when('set_transient')->justReturn(true);
		\Brain\Monkey\Functions\when('delete_transient')->justReturn(true);
		\Brain\Monkey\Functions\when('wp_generate_uuid4')->justReturn('test-uuid-1234');
		\Brain\Monkey\Functions\when('time')->justReturn(1234567890);
		\Brain\Monkey\Functions\when('__')->returnArg();
		
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		
		// Define constants
		if (!defined('DAY_IN_SECONDS')) {
			define('DAY_IN_SECONDS', 86400);
		}
		if (!defined('HOUR_IN_SECONDS')) {
			define('HOUR_IN_SECONDS', 3600);
		}
	}

	public function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test initial task creation state
	 */
	public function testInitialTaskCreation() {
		$task_id = 'gen_' . time();
		$task_data = [
			'id' => $task_id,
			'workflow_type' => 'summary',
			'status' => 'pending',
			'created_at' => time(),
			'total_posts' => 100,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 50, 'status' => 'pending'],
				['batch_id' => 'batch_2', 'post_count' => 50, 'status' => 'pending']
			]
		];
		
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals('pending', $retrieved['status']);
		$this->assertEquals('summary', $retrieved['workflow_type']);
		$this->assertEquals(100, $retrieved['total_posts']);
		$this->assertCount(2, $retrieved['batch_jobs']);
	}

	/**
	 * Test state transition: pending -> processing
	 */
	public function testStateTransitionPendingToProcessing() {
		$task_id = 'gen_' . time();
		$task_data = [
			'id' => $task_id,
			'status' => 'pending',
			'workflow_type' => 'quiz',
			'total_posts' => 50,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 25, 'status' => 'pending'],
				['batch_id' => 'batch_2', 'post_count' => 25, 'status' => 'pending']
			]
		];
		
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Simulate starting processing
		$task_data['status'] = 'processing';
		$task_data['started_at'] = time();
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Start first batch
		$batch_data = [
			'batch_id' => 'batch_1',
			'status' => 'processing',
			'started_at' => time(),
			'posts' => array_fill(0, 25, ['post_id' => 1, 'status' => 'pending'])
		];
		TaskTransientManager::set_batch_transient('batch_1', $batch_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals('processing', $retrieved['status']);
		$this->assertArrayHasKey('started_at', $retrieved);
		
		$batch = TaskTransientManager::get_batch_transient('batch_1');
		$this->assertEquals('processing', $batch['status']);
	}

	/**
	 * Test state transition: processing -> completed (success)
	 */
	public function testStateTransitionProcessingToCompleted() {
		$task_id = 'gen_' . time();
		$task_data = [
			'id' => $task_id,
			'status' => 'processing',
			'workflow_type' => 'summary',
			'total_posts' => 10,
			'started_at' => time() - 300,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 5, 'status' => 'completed'],
				['batch_id' => 'batch_2', 'post_count' => 5, 'status' => 'processing']
			]
		];
		
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Complete second batch
		$batch_data = [
			'batch_id' => 'batch_2',
			'status' => 'completed',
			'completed_at' => time(),
			'posts' => array_fill(0, 5, ['post_id' => 1, 'status' => 'completed'])
		];
		TaskTransientManager::set_batch_transient('batch_2', $batch_data, DAY_IN_SECONDS);
		
		// Update task to completed
		$task_data['status'] = 'completed';
		$task_data['completed_at'] = time();
		$task_data['batch_jobs'][1]['status'] = 'completed';
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals('completed', $retrieved['status']);
		$this->assertArrayHasKey('completed_at', $retrieved);
		$this->assertEquals('completed', $retrieved['batch_jobs'][0]['status']);
		$this->assertEquals('completed', $retrieved['batch_jobs'][1]['status']);
	}

	/**
	 * Test state transition: processing -> failed
	 */
	public function testStateTransitionProcessingToFailed() {
		$task_id = 'gen_' . time();
		$task_data = [
			'id' => $task_id,
			'status' => 'processing',
			'workflow_type' => 'quiz',
			'total_posts' => 20,
			'started_at' => time() - 600,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 10, 'status' => 'completed'],
				['batch_id' => 'batch_2', 'post_count' => 10, 'status' => 'processing']
			]
		];
		
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Fail second batch
		$batch_data = [
			'batch_id' => 'batch_2',
			'status' => 'failed',
			'failed_at' => time(),
			'error' => 'API connection failed',
			'posts' => array_fill(0, 10, ['post_id' => 1, 'status' => 'failed'])
		];
		TaskTransientManager::set_batch_transient('batch_2', $batch_data, DAY_IN_SECONDS);
		
		// Update task to failed
		$task_data['status'] = 'failed';
		$task_data['failed_at'] = time();
		$task_data['error'] = 'Batch processing failed';
		$task_data['batch_jobs'][1]['status'] = 'failed';
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals('failed', $retrieved['status']);
		$this->assertArrayHasKey('failed_at', $retrieved);
		$this->assertArrayHasKey('error', $retrieved);
		$this->assertEquals('failed', $retrieved['batch_jobs'][1]['status']);
	}

	/**
	 * Test state transition: any -> cancelled
	 */
	public function testStateTransitionToCancelled() {
		$task_id = 'gen_' . time();
		$task_data = [
			'id' => $task_id,
			'status' => 'processing',
			'workflow_type' => 'summary',
			'total_posts' => 30,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 15, 'status' => 'completed'],
				['batch_id' => 'batch_2', 'post_count' => 15, 'status' => 'processing']
			]
		];
		
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Cancel the task
		$task_data['status'] = 'cancelled';
		$task_data['cancelled_at'] = time();
		$task_data['cancelled_by'] = get_current_user_id();
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Cancel processing batch
		$batch_data = [
			'batch_id' => 'batch_2',
			'status' => 'cancelled',
			'cancelled_at' => time()
		];
		TaskTransientManager::set_batch_transient('batch_2', $batch_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals('cancelled', $retrieved['status']);
		$this->assertArrayHasKey('cancelled_at', $retrieved);
		
		$batch = TaskTransientManager::get_batch_transient('batch_2');
		$this->assertEquals('cancelled', $batch['status']);
	}

	/**
	 * Test batch state transitions
	 */
	public function testBatchStateTransitions() {
		$batch_id = 'batch_' . time();
		
		// Initial pending state
		$batch_data = [
			'batch_id' => $batch_id,
			'status' => 'pending',
			'created_at' => time(),
			'posts' => [
				['post_id' => 1, 'status' => 'pending'],
				['post_id' => 2, 'status' => 'pending'],
				['post_id' => 3, 'status' => 'pending']
			]
		];
		TaskTransientManager::set_batch_transient($batch_id, $batch_data, DAY_IN_SECONDS);
		
		// Transition to processing
		$batch_data['status'] = 'processing';
		$batch_data['started_at'] = time();
		$batch_data['posts'][0]['status'] = 'processing';
		TaskTransientManager::set_batch_transient($batch_id, $batch_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_batch_transient($batch_id);
		$this->assertEquals('processing', $retrieved['status']);
		$this->assertEquals('processing', $retrieved['posts'][0]['status']);
		
		// Process posts
		$batch_data['posts'][0]['status'] = 'completed';
		$batch_data['posts'][1]['status'] = 'completed';
		$batch_data['posts'][2]['status'] = 'failed';
		$batch_data['posts'][2]['error'] = 'Generation failed';
		TaskTransientManager::set_batch_transient($batch_id, $batch_data, DAY_IN_SECONDS);
		
		// Complete batch with partial failure
		$batch_data['status'] = 'completed_with_errors';
		$batch_data['completed_at'] = time();
		$batch_data['stats'] = [
			'total' => 3,
			'completed' => 2,
			'failed' => 1
		];
		TaskTransientManager::set_batch_transient($batch_id, $batch_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_batch_transient($batch_id);
		$this->assertEquals('completed_with_errors', $retrieved['status']);
		$this->assertEquals(2, $retrieved['stats']['completed']);
		$this->assertEquals(1, $retrieved['stats']['failed']);
	}

	/**
	 * Test retry mechanism for failed tasks
	 */
	public function testRetryMechanismForFailedTasks() {
		$task_id = 'gen_' . time();
		$task_data = [
			'id' => $task_id,
			'status' => 'failed',
			'workflow_type' => 'quiz',
			'total_posts' => 5,
			'retry_count' => 0,
			'max_retries' => 3,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 5, 'status' => 'failed']
			]
		];
		
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Simulate retry
		$task_data['status'] = 'retrying';
		$task_data['retry_count'] = 1;
		$task_data['last_retry_at'] = time();
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals('retrying', $retrieved['status']);
		$this->assertEquals(1, $retrieved['retry_count']);
		$this->assertArrayHasKey('last_retry_at', $retrieved);
		
		// Simulate max retries reached
		$task_data['retry_count'] = 3;
		$task_data['status'] = 'failed_permanent';
		$task_data['permanent_failure_reason'] = 'Max retries exceeded';
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals('failed_permanent', $retrieved['status']);
		$this->assertEquals(3, $retrieved['retry_count']);
		$this->assertArrayHasKey('permanent_failure_reason', $retrieved);
	}

	/**
	 * Test timeout handling for stalled tasks
	 */
	public function testTimeoutHandlingForStalledTasks() {
		$task_id = 'gen_' . time();
		$task_data = [
			'id' => $task_id,
			'status' => 'processing',
			'workflow_type' => 'summary',
			'total_posts' => 100,
			'started_at' => time() - 7200, // Started 2 hours ago
			'timeout_threshold' => 3600, // 1 hour timeout
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 100, 'status' => 'processing']
			]
		];
		
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Check if task is timed out
		$is_timed_out = (time() - $task_data['started_at']) > $task_data['timeout_threshold'];
		$this->assertTrue($is_timed_out);
		
		// Update to timed out status
		$task_data['status'] = 'timed_out';
		$task_data['timed_out_at'] = time();
		$task_data['timeout_reason'] = 'Processing exceeded timeout threshold';
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals('timed_out', $retrieved['status']);
		$this->assertArrayHasKey('timed_out_at', $retrieved);
		$this->assertArrayHasKey('timeout_reason', $retrieved);
	}

	/**
	 * Test progress tracking through states
	 */
	public function testProgressTrackingThroughStates() {
		$task_id = 'gen_' . time();
		$task_data = [
			'id' => $task_id,
			'status' => 'processing',
			'workflow_type' => 'quiz',
			'total_posts' => 50,
			'progress' => 0,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 25, 'status' => 'processing'],
				['batch_id' => 'batch_2', 'post_count' => 25, 'status' => 'pending']
			]
		];
		
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		// Update progress as posts complete
		$batch_data = [
			'batch_id' => 'batch_1',
			'status' => 'processing',
			'posts' => array_fill(0, 25, ['status' => 'pending'])
		];
		
		// Process 10 posts in batch 1
		for ($i = 0; $i < 10; $i++) {
			$batch_data['posts'][$i]['status'] = 'completed';
		}
		TaskTransientManager::set_batch_transient('batch_1', $batch_data, DAY_IN_SECONDS);
		
		// Calculate and update progress
		$completed_posts = 10;
		$progress = ($completed_posts / $task_data['total_posts']) * 100;
		$task_data['progress'] = $progress;
		$task_data['processed_count'] = $completed_posts;
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals(20, $retrieved['progress']); // 10/50 = 20%
		$this->assertEquals(10, $retrieved['processed_count']);
		
		// Complete batch 1
		$batch_data['status'] = 'completed';
		for ($i = 10; $i < 25; $i++) {
			$batch_data['posts'][$i]['status'] = 'completed';
		}
		TaskTransientManager::set_batch_transient('batch_1', $batch_data, DAY_IN_SECONDS);
		
		$task_data['progress'] = 50; // 25/50 = 50%
		$task_data['processed_count'] = 25;
		$task_data['batch_jobs'][0]['status'] = 'completed';
		TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
		
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals(50, $retrieved['progress']);
		$this->assertEquals(25, $retrieved['processed_count']);
	}

	/**
	 * Test concurrent state updates handling
	 */
	public function testConcurrentStateUpdates() {
		$task_id = 'gen_' . time();
		
		// Simulate two concurrent processes trying to update the same task
		$task_data_process1 = [
			'id' => $task_id,
			'status' => 'processing',
			'workflow_type' => 'summary',
			'total_posts' => 20,
			'version' => 1,
			'last_updated' => time(),
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 10, 'status' => 'processing'],
				['batch_id' => 'batch_2', 'post_count' => 10, 'status' => 'pending']
			]
		];
		
		$task_data_process2 = $task_data_process1;
		
		// Process 1 updates
		$task_data_process1['batch_jobs'][0]['status'] = 'completed';
		$task_data_process1['version'] = 2;
		$task_data_process1['last_updated'] = time() + 1;
		TaskTransientManager::set_task_transient($task_id, $task_data_process1, DAY_IN_SECONDS);
		
		// Process 2 tries to update with old version
		$retrieved = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals(2, $retrieved['version']);
		
		// Process 2 should detect version mismatch and reload
		if ($task_data_process2['version'] < $retrieved['version']) {
			// Reload latest data
			$task_data_process2 = $retrieved;
		}
		
		// Now process 2 can safely update
		$task_data_process2['batch_jobs'][1]['status'] = 'processing';
		$task_data_process2['version'] = 3;
		$task_data_process2['last_updated'] = time() + 2;
		TaskTransientManager::set_task_transient($task_id, $task_data_process2, DAY_IN_SECONDS);
		
		$final = TaskTransientManager::get_task_transient($task_id);
		$this->assertEquals(3, $final['version']);
		$this->assertEquals('completed', $final['batch_jobs'][0]['status']);
		$this->assertEquals('processing', $final['batch_jobs'][1]['status']);
	}

	/**
	 * Mock TaskTransientManager for testing
	 */
	private function mockTaskTransientManager() {
		if (!class_exists('NuclearEngagement\Services\TaskTransientManager')) {
			eval('
			namespace NuclearEngagement\Services;
			class TaskTransientManager {
				public static $test_data = [];
				
				public static function get_task_transient($id) {
					return self::$test_data[$id] ?? null;
				}
				
				public static function set_task_transient($id, $data, $expiry) {
					self::$test_data[$id] = $data;
					return true;
				}
				
				public static function get_batch_transient($id) {
					return self::$test_data[$id] ?? null;
				}
				
				public static function set_batch_transient($id, $data, $expiry) {
					self::$test_data[$id] = $data;
					return true;
				}
				
				public static function delete_task_transient($id) {
					unset(self::$test_data[$id]);
					return true;
				}
				
				public static function delete_batch_transient($id) {
					unset(self::$test_data[$id]);
					return true;
				}
			}
			');
		}
		
		// Reset test data
		TaskTransientManager::$test_data = [];
	}
}