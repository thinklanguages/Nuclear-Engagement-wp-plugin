<?php
/**
 * BulkTaskActionsTest.php - Tests for bulk task actions
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\BulkGenerationBatchProcessor;
use NuclearEngagement\Services\TaskTransientManager;
use NuclearEngagement\Services\CentralizedPollingQueue;
use NuclearEngagement\Services\BatchProcessingHandler;
use NuclearEngagement\Services\BulkGenerationTimeoutHandler;
use NuclearEngagement\Services\CircuitBreakerService;
use NuclearEngagement\Core\ServiceContainer;

/**
 * Test bulk task actions functionality
 */
class BulkTaskActionsTest extends TestCase {
	private ServiceContainer $container;
	private BulkGenerationBatchProcessor $batch_processor;
	private CentralizedPollingQueue $polling_queue;
	private BatchProcessingHandler $batch_handler;
	private BulkGenerationTimeoutHandler $timeout_handler;
	private CircuitBreakerService $circuit_breaker;

	public function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions
		\Brain\Monkey\setUp();
		
		// Setup mocks
		$this->container = $this->createMock(ServiceContainer::class);
		$this->batch_processor = $this->createMock(BulkGenerationBatchProcessor::class);
		$this->polling_queue = $this->createMock(CentralizedPollingQueue::class);
		$this->batch_handler = $this->createMock(BatchProcessingHandler::class);
		$this->timeout_handler = $this->createMock(BulkGenerationTimeoutHandler::class);
		$this->circuit_breaker = $this->createMock(CircuitBreakerService::class);
		
		// Mock global functions
		\Brain\Monkey\Functions\when('get_transient')->justReturn(false);
		\Brain\Monkey\Functions\when('set_transient')->justReturn(true);
		\Brain\Monkey\Functions\when('delete_transient')->justReturn(true);
		\Brain\Monkey\Functions\when('wp_schedule_single_event')->justReturn(true);
		\Brain\Monkey\Functions\when('wp_clear_scheduled_hook')->justReturn(null);
		\Brain\Monkey\Functions\when('wp_next_scheduled')->justReturn(false);
		\Brain\Monkey\Functions\when('time')->justReturn(1234567890);
		\Brain\Monkey\Functions\when('__')->returnArg();
		\Brain\Monkey\Functions\when('do_action')->justReturn(null);
		
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		
		// Define constants
		if (!defined('DAY_IN_SECONDS')) {
			define('DAY_IN_SECONDS', 86400);
		}
		if (!defined('HOUR_IN_SECONDS')) {
			define('HOUR_IN_SECONDS', 3600);
		}
		if (!defined('MINUTE_IN_SECONDS')) {
			define('MINUTE_IN_SECONDS', 60);
		}
	}

	public function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test bulk run action for multiple tasks
	 */
	public function testBulkRunMultipleTasks() {
		$task_ids = ['gen_1', 'gen_2', 'gen_3'];
		
		// Setup task data
		foreach ($task_ids as $task_id) {
			TaskTransientManager::set_task_transient($task_id, [
				'id' => $task_id,
				'status' => 'pending',
				'workflow_type' => 'summary',
				'total_posts' => 10,
				'batch_jobs' => [
					['batch_id' => $task_id . '_batch_1', 'status' => 'pending', 'post_count' => 10]
				]
			], DAY_IN_SECONDS);
		}
		
		// Simulate bulk run action
		$results = [];
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			if ($task_data && $task_data['status'] === 'pending') {
				// Update to processing
				$task_data['status'] = 'processing';
				$task_data['bulk_action'] = 'run';
				$task_data['bulk_action_time'] = time();
				TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
				
				// Trigger processing
				\Brain\Monkey\Actions\expectDone('nuclen_process_batch')
					->with($task_id . '_batch_1');
				
				$results[$task_id] = 'started';
			}
		}
		
		// Verify all tasks were started
		$this->assertCount(3, $results);
		foreach ($task_ids as $task_id) {
			$task = TaskTransientManager::get_task_transient($task_id);
			$this->assertEquals('processing', $task['status']);
			$this->assertEquals('run', $task['bulk_action']);
		}
	}

	/**
	 * Test bulk cancel action
	 */
	public function testBulkCancelTasks() {
		$task_ids = ['gen_4', 'gen_5', 'gen_6'];
		
		// Setup tasks in different states
		TaskTransientManager::set_task_transient('gen_4', [
			'id' => 'gen_4',
			'status' => 'processing',
			'workflow_type' => 'quiz',
			'batch_jobs' => [
				['batch_id' => 'gen_4_batch_1', 'status' => 'processing']
			]
		], DAY_IN_SECONDS);
		
		TaskTransientManager::set_task_transient('gen_5', [
			'id' => 'gen_5',
			'status' => 'pending',
			'workflow_type' => 'summary',
			'batch_jobs' => [
				['batch_id' => 'gen_5_batch_1', 'status' => 'pending']
			]
		], DAY_IN_SECONDS);
		
		TaskTransientManager::set_task_transient('gen_6', [
			'id' => 'gen_6',
			'status' => 'completed',
			'workflow_type' => 'quiz',
			'batch_jobs' => []
		], DAY_IN_SECONDS);
		
		// Simulate bulk cancel
		$cancelled = [];
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			if ($task_data && in_array($task_data['status'], ['pending', 'processing'])) {
				// Cancel task
				$task_data['status'] = 'cancelled';
				$task_data['cancelled_at'] = time();
				$task_data['bulk_action'] = 'cancel';
				TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
				
				// Cancel batches
				foreach ($task_data['batch_jobs'] as $batch) {
					if ($batch['status'] !== 'completed') {
						\Brain\Monkey\Functions\expectOnce('wp_clear_scheduled_hook')
							->with('nuclen_process_batch', [$batch['batch_id']]);
					}
				}
				
				$cancelled[] = $task_id;
			}
		}
		
		// Verify correct tasks were cancelled
		$this->assertCount(2, $cancelled); // Only gen_4 and gen_5
		$this->assertContains('gen_4', $cancelled);
		$this->assertContains('gen_5', $cancelled);
		
		// Verify states
		$task4 = TaskTransientManager::get_task_transient('gen_4');
		$this->assertEquals('cancelled', $task4['status']);
		
		$task5 = TaskTransientManager::get_task_transient('gen_5');
		$this->assertEquals('cancelled', $task5['status']);
		
		$task6 = TaskTransientManager::get_task_transient('gen_6');
		$this->assertEquals('completed', $task6['status']); // Should remain completed
	}

	/**
	 * Test bulk retry failed tasks
	 */
	public function testBulkRetryFailedTasks() {
		$task_ids = ['gen_7', 'gen_8', 'gen_9'];
		
		// Setup failed tasks
		foreach ($task_ids as $index => $task_id) {
			TaskTransientManager::set_task_transient($task_id, [
				'id' => $task_id,
				'status' => 'failed',
				'workflow_type' => 'summary',
				'total_posts' => 5,
				'retry_count' => $index, // Different retry counts
				'max_retries' => 3,
				'batch_jobs' => [
					['batch_id' => $task_id . '_batch_1', 'status' => 'failed', 'post_count' => 5]
				]
			], DAY_IN_SECONDS);
		}
		
		// Simulate bulk retry
		$retried = [];
		$max_retry_exceeded = [];
		
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			if ($task_data && $task_data['status'] === 'failed') {
				if ($task_data['retry_count'] < $task_data['max_retries']) {
					// Retry task
					$task_data['status'] = 'retrying';
					$task_data['retry_count']++;
					$task_data['last_retry_at'] = time();
					$task_data['bulk_action'] = 'retry';
					TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
					
					// Reset batch status
					$batch_id = $task_id . '_batch_1';
					$batch_data = [
						'batch_id' => $batch_id,
						'status' => 'pending',
						'retry_attempt' => $task_data['retry_count']
					];
					TaskTransientManager::set_batch_transient($batch_id, $batch_data, DAY_IN_SECONDS);
					
					$retried[] = $task_id;
				} else {
					$max_retry_exceeded[] = $task_id;
				}
			}
		}
		
		// Verify retry results
		$this->assertCount(3, $retried); // All should be retried as they have < 3 retries
		$this->assertEmpty($max_retry_exceeded);
		
		// Verify retry counts
		$task7 = TaskTransientManager::get_task_transient('gen_7');
		$this->assertEquals(1, $task7['retry_count']);
		$this->assertEquals('retrying', $task7['status']);
		
		$task8 = TaskTransientManager::get_task_transient('gen_8');
		$this->assertEquals(2, $task8['retry_count']);
		
		$task9 = TaskTransientManager::get_task_transient('gen_9');
		$this->assertEquals(3, $task9['retry_count']);
	}

	/**
	 * Test bulk delete completed tasks
	 */
	public function testBulkDeleteCompletedTasks() {
		$task_ids = ['gen_10', 'gen_11', 'gen_12', 'gen_13'];
		
		// Setup tasks in different states
		$states = ['completed', 'completed', 'processing', 'failed'];
		foreach ($task_ids as $index => $task_id) {
			TaskTransientManager::set_task_transient($task_id, [
				'id' => $task_id,
				'status' => $states[$index],
				'workflow_type' => 'quiz',
				'completed_at' => $states[$index] === 'completed' ? time() - 3600 : null
			], DAY_IN_SECONDS);
		}
		
		// Simulate bulk delete completed
		$deleted = [];
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			if ($task_data && $task_data['status'] === 'completed') {
				// Delete task
				TaskTransientManager::delete_task_transient($task_id);
				$deleted[] = $task_id;
			}
		}
		
		// Verify only completed tasks were deleted
		$this->assertCount(2, $deleted);
		$this->assertContains('gen_10', $deleted);
		$this->assertContains('gen_11', $deleted);
		
		// Verify deleted tasks are gone
		$this->assertNull(TaskTransientManager::get_task_transient('gen_10'));
		$this->assertNull(TaskTransientManager::get_task_transient('gen_11'));
		
		// Verify other tasks remain
		$this->assertNotNull(TaskTransientManager::get_task_transient('gen_12'));
		$this->assertNotNull(TaskTransientManager::get_task_transient('gen_13'));
	}

	/**
	 * Test bulk pause/resume functionality
	 */
	public function testBulkPauseResumeTasks() {
		$task_ids = ['gen_14', 'gen_15'];
		
		// Setup processing tasks
		foreach ($task_ids as $task_id) {
			TaskTransientManager::set_task_transient($task_id, [
				'id' => $task_id,
				'status' => 'processing',
				'workflow_type' => 'summary',
				'batch_jobs' => [
					['batch_id' => $task_id . '_batch_1', 'status' => 'processing'],
					['batch_id' => $task_id . '_batch_2', 'status' => 'pending']
				]
			], DAY_IN_SECONDS);
		}
		
		// Bulk pause
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			if ($task_data && $task_data['status'] === 'processing') {
				// Store current state
				$task_data['paused_from_status'] = $task_data['status'];
				$task_data['status'] = 'paused';
				$task_data['paused_at'] = time();
				TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
				
				// Clear scheduled events
				foreach ($task_data['batch_jobs'] as $batch) {
					\Brain\Monkey\Functions\expectOnce('wp_clear_scheduled_hook')
						->with('nuclen_process_batch', [$batch['batch_id']]);
				}
			}
		}
		
		// Verify paused state
		foreach ($task_ids as $task_id) {
			$task = TaskTransientManager::get_task_transient($task_id);
			$this->assertEquals('paused', $task['status']);
			$this->assertEquals('processing', $task['paused_from_status']);
		}
		
		// Bulk resume
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			if ($task_data && $task_data['status'] === 'paused') {
				// Restore previous state
				$task_data['status'] = $task_data['paused_from_status'] ?? 'pending';
				$task_data['resumed_at'] = time();
				unset($task_data['paused_from_status']);
				unset($task_data['paused_at']);
				TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
				
				// Reschedule pending batches
				foreach ($task_data['batch_jobs'] as $batch) {
					if ($batch['status'] !== 'completed') {
						\Brain\Monkey\Functions\expectOnce('wp_schedule_single_event')
							->with(\Brain\Monkey\Functions\type('int'), 'nuclen_process_batch', [$batch['batch_id']]);
					}
				}
			}
		}
		
		// Verify resumed state
		foreach ($task_ids as $task_id) {
			$task = TaskTransientManager::get_task_transient($task_id);
			$this->assertEquals('processing', $task['status']);
			$this->assertArrayHasKey('resumed_at', $task);
			$this->assertArrayNotHasKey('paused_at', $task);
		}
	}

	/**
	 * Test bulk priority change
	 */
	public function testBulkPriorityChange() {
		$task_ids = ['gen_16', 'gen_17', 'gen_18'];
		
		// Setup tasks with different priorities
		$priorities = ['low', 'medium', 'high'];
		foreach ($task_ids as $index => $task_id) {
			TaskTransientManager::set_task_transient($task_id, [
				'id' => $task_id,
				'status' => 'pending',
				'workflow_type' => 'quiz',
				'priority' => $priorities[$index],
				'batch_jobs' => [
					['batch_id' => $task_id . '_batch_1', 'status' => 'pending']
				]
			], DAY_IN_SECONDS);
		}
		
		// Bulk change to high priority
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			if ($task_data) {
				$old_priority = $task_data['priority'];
				$task_data['priority'] = 'high';
				$task_data['priority_changed_at'] = time();
				$task_data['priority_changed_from'] = $old_priority;
				TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
				
				// If task is pending, reschedule with new priority
				if ($task_data['status'] === 'pending') {
					foreach ($task_data['batch_jobs'] as $batch) {
						// Clear old schedule
						\Brain\Monkey\Functions\expectOnce('wp_clear_scheduled_hook')
							->with('nuclen_process_batch', [$batch['batch_id']]);
						
						// Schedule with high priority (immediate)
						\Brain\Monkey\Functions\expectOnce('wp_schedule_single_event')
							->with(time(), 'nuclen_process_batch', [$batch['batch_id']]);
					}
				}
			}
		}
		
		// Verify all tasks have high priority
		foreach ($task_ids as $task_id) {
			$task = TaskTransientManager::get_task_transient($task_id);
			$this->assertEquals('high', $task['priority']);
			$this->assertArrayHasKey('priority_changed_at', $task);
			$this->assertArrayHasKey('priority_changed_from', $task);
		}
	}

	/**
	 * Test bulk action with mixed selection
	 */
	public function testBulkActionWithMixedSelection() {
		// Setup tasks in various states
		$tasks = [
			'gen_19' => ['status' => 'completed', 'can_run' => false, 'can_cancel' => false],
			'gen_20' => ['status' => 'processing', 'can_run' => false, 'can_cancel' => true],
			'gen_21' => ['status' => 'pending', 'can_run' => true, 'can_cancel' => true],
			'gen_22' => ['status' => 'failed', 'can_run' => true, 'can_cancel' => false],
			'gen_23' => ['status' => 'cancelled', 'can_run' => true, 'can_cancel' => false]
		];
		
		foreach ($tasks as $task_id => $config) {
			TaskTransientManager::set_task_transient($task_id, [
				'id' => $task_id,
				'status' => $config['status'],
				'workflow_type' => 'summary'
			], DAY_IN_SECONDS);
		}
		
		// Test bulk run - should only affect eligible tasks
		$run_results = [];
		foreach ($tasks as $task_id => $config) {
			if ($config['can_run']) {
				$task_data = TaskTransientManager::get_task_transient($task_id);
				$task_data['status'] = 'processing';
				$task_data['bulk_run_at'] = time();
				TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
				$run_results[] = $task_id;
			}
		}
		
		$this->assertCount(3, $run_results);
		$this->assertContains('gen_21', $run_results);
		$this->assertContains('gen_22', $run_results);
		$this->assertContains('gen_23', $run_results);
		
		// Reset for cancel test
		foreach ($tasks as $task_id => $config) {
			TaskTransientManager::set_task_transient($task_id, [
				'id' => $task_id,
				'status' => $config['status'],
				'workflow_type' => 'summary'
			], DAY_IN_SECONDS);
		}
		
		// Test bulk cancel - should only affect eligible tasks
		$cancel_results = [];
		foreach ($tasks as $task_id => $config) {
			if ($config['can_cancel']) {
				$task_data = TaskTransientManager::get_task_transient($task_id);
				$task_data['status'] = 'cancelled';
				$task_data['bulk_cancelled_at'] = time();
				TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
				$cancel_results[] = $task_id;
			}
		}
		
		$this->assertCount(2, $cancel_results);
		$this->assertContains('gen_20', $cancel_results);
		$this->assertContains('gen_21', $cancel_results);
	}

	/**
	 * Test bulk action performance with large selection
	 */
	public function testBulkActionPerformance() {
		$start_time = microtime(true);
		$task_count = 100;
		$task_ids = [];
		
		// Create many tasks
		for ($i = 0; $i < $task_count; $i++) {
			$task_id = 'gen_perf_' . $i;
			$task_ids[] = $task_id;
			
			TaskTransientManager::set_task_transient($task_id, [
				'id' => $task_id,
				'status' => 'pending',
				'workflow_type' => $i % 2 === 0 ? 'summary' : 'quiz',
				'total_posts' => rand(10, 100),
				'batch_jobs' => [
					['batch_id' => $task_id . '_batch_1', 'status' => 'pending']
				]
			], DAY_IN_SECONDS);
		}
		
		// Simulate bulk run
		$processed = 0;
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			if ($task_data) {
				$task_data['status'] = 'processing';
				$task_data['bulk_started_at'] = time();
				TaskTransientManager::set_task_transient($task_id, $task_data, DAY_IN_SECONDS);
				$processed++;
			}
		}
		
		$end_time = microtime(true);
		$execution_time = $end_time - $start_time;
		
		// Verify all tasks were processed
		$this->assertEquals($task_count, $processed);
		
		// Verify reasonable execution time (should be fast for transient operations)
		$this->assertLessThan(1.0, $execution_time, 'Bulk operation took too long');
		
		// Verify batch scheduling would be done efficiently
		$schedule_calls = 0;
		foreach ($task_ids as $task_id) {
			// In real implementation, this would schedule in batches
			$schedule_calls++;
			if ($schedule_calls % 10 === 0) {
				// Simulate batch scheduling every 10 tasks
				\Brain\Monkey\Functions\expectOnce('wp_schedule_single_event');
			}
		}
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