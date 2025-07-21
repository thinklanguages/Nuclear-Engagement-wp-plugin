<?php
/**
 * TasksControllerTest.php - Tests for TasksController
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Controller\Ajax\TasksController;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Services\TaskTransientManager;
use NuclearEngagement\Services\CentralizedPollingQueue;
use NuclearEngagement\Services\AdminNoticeService;
use NuclearEngagement\Services\LoggingService;

/**
 * Test TasksController
 */
class TasksControllerTest extends TestCase {
	private TasksController $controller;
	private ServiceContainer $container;
	private $mock_polling_queue;
	private $mock_notice_service;

	public function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions
		\Brain\Monkey\setUp();
		
		// Setup container with mocks
		$this->container = $this->createMock(ServiceContainer::class);
		$this->mock_polling_queue = $this->createMock(CentralizedPollingQueue::class);
		$this->mock_notice_service = $this->createMock(AdminNoticeService::class);
		
		$this->container->method('has')
			->willReturnCallback(function($service) {
				return in_array($service, ['centralized_polling_queue', 'admin_notice_service']);
			});
			
		$this->container->method('get')
			->willReturnCallback(function($service) {
				if ($service === 'centralized_polling_queue') {
					return $this->mock_polling_queue;
				}
				if ($service === 'admin_notice_service') {
					return $this->mock_notice_service;
				}
				return null;
			});
		
		$this->controller = new TasksController($this->container);
		
		// Mock global functions
		\Brain\Monkey\Functions\when('current_user_can')
			->justReturn(true);
		\Brain\Monkey\Functions\when('wp_verify_nonce')
			->justReturn(true);
		\Brain\Monkey\Functions\when('get_current_user_id')
			->justReturn(1);
		\Brain\Monkey\Functions\when('get_transient')
			->justReturn(false);
		\Brain\Monkey\Functions\when('set_transient')
			->justReturn(true);
		\Brain\Monkey\Functions\when('delete_transient')
			->justReturn(true);
		\Brain\Monkey\Functions\when('sanitize_text_field')
			->returnArg();
		\Brain\Monkey\Functions\when('wp_send_json_success')
			->alias(function($data) {
				echo json_encode(['success' => true, 'data' => $data]);
				exit;
			});
		\Brain\Monkey\Functions\when('wp_send_json_error')
			->alias(function($data, $code = null) {
				echo json_encode(['success' => false, 'data' => $data]);
				exit;
			});
		\Brain\Monkey\Functions\when('__')
			->returnArg();
		\Brain\Monkey\Functions\when('wp_clear_scheduled_hook')
			->justReturn(null);
		\Brain\Monkey\Functions\when('do_action')
			->justReturn(null);
	}

	public function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test run_task with invalid permissions
	 */
	public function testRunTaskWithoutPermissions() {
		\Brain\Monkey\Functions\when('current_user_can')
			->justReturn(false);
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'test_task';
		
		$this->expectOutputString(json_encode([
			'success' => false,
			'data' => 'Insufficient permissions'
		]));
		
		try {
			$this->controller->run_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test run_task with invalid nonce
	 */
	public function testRunTaskWithInvalidNonce() {
		\Brain\Monkey\Functions\when('wp_verify_nonce')
			->justReturn(false);
		
		$_POST['nonce'] = 'invalid_nonce';
		$_POST['task_id'] = 'test_task';
		
		$this->expectOutputString(json_encode([
			'success' => false,
			'data' => 'Security check failed'
		]));
		
		try {
			$this->controller->run_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test run_task with empty task ID
	 */
	public function testRunTaskWithEmptyTaskId() {
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = '';
		
		$this->expectOutputString(json_encode([
			'success' => false,
			'data' => 'Invalid task ID'
		]));
		
		try {
			$this->controller->run_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test run_task with rate limiting
	 */
	public function testRunTaskRateLimiting() {
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_task_action_1')
			->justReturn(true);
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'test_task';
		
		$this->expectOutputString(json_encode([
			'success' => false,
			'data' => 'Please wait a few seconds before performing another action.'
		]));
		
		try {
			$this->controller->run_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test run_task with batch task that's already processing
	 */
	public function testRunBatchTaskAlreadyProcessing() {
		\Brain\Monkey\Functions\when('get_transient')
			->justReturn(false);
			
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		TaskTransientManager::$test_data['nuclen_batch_123'] = [
			'status' => 'processing'
		];
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'nuclen_batch_123';
		
		$this->expectOutputString(json_encode([
			'success' => false,
			'data' => 'This batch is already processing. Please wait for it to complete.'
		]));
		
		try {
			$this->controller->run_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test successful batch task run
	 */
	public function testRunBatchTaskSuccess() {
		\Brain\Monkey\Functions\when('get_transient')
			->justReturn(false);
		
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		TaskTransientManager::$test_data['nuclen_batch_123'] = [
			'status' => 'pending'
		];
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'nuclen_batch_123';
		
		\Brain\Monkey\Actions\expectDone('nuclen_process_batch')
			->once()
			->with('nuclen_batch_123');
		
		$this->expectOutputString(json_encode([
			'success' => true,
			'data' => [
				'message' => 'Batch nuclen_batch_123 has been triggered for immediate processing.'
			]
		]));
		
		try {
			$this->controller->run_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test run generation task that's already processing
	 */
	public function testRunGenerationTaskAlreadyProcessing() {
		\Brain\Monkey\Functions\when('get_transient')
			->justReturn(false);
		
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		TaskTransientManager::$test_data['test_gen_123'] = [
			'workflow_type' => 'summary',
			'status' => 'processing',
			'batch_jobs' => []
		];
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'test_gen_123';
		
		$this->expectOutputString(json_encode([
			'success' => false,
			'data' => 'Failed to retrieve task data: This task is already processing. Please wait for it to complete or cancel it first.'
		]));
		
		try {
			$this->controller->run_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test successful generation task run
	 */
	public function testRunGenerationTaskSuccess() {
		\Brain\Monkey\Functions\when('get_transient')
			->justReturn(false);
		
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		TaskTransientManager::$test_data['test_gen_123'] = [
			'workflow_type' => 'summary',
			'status' => 'pending',
			'batch_jobs' => [
				[
					'batch_id' => 'batch_1',
					'status' => 'pending'
				],
				[
					'batch_id' => 'batch_2',
					'status' => 'pending'
				]
			]
		];
		
		TaskTransientManager::$test_data['batch_1'] = [
			'posts' => [
				['post_id' => 1],
				['post_id' => 2]
			]
		];
		
		TaskTransientManager::$test_data['batch_2'] = [
			'posts' => [
				['id' => 3],
				['id' => 4]
			]
		];
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'test_gen_123';
		
		// Expect polling queue to be called
		$this->mock_polling_queue->expects($this->once())
			->method('add_to_queue')
			->with('test_gen_123', 'summary', [1, 2, 3, 4], 1);
		
		// Expect batch processing actions
		\Brain\Monkey\Actions\expectDone('nuclen_process_batch')
			->times(2)
			->withArgs(function($batch_id) {
				return in_array($batch_id, ['batch_1', 'batch_2']);
			});
		
		$this->expectOutputString(json_encode([
			'success' => true,
			'data' => [
				'message' => 'Generation test_gen_123 has been queued for immediate processing.'
			]
		]));
		
		try {
			$this->controller->run_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test cancel task without permissions
	 */
	public function testCancelTaskWithoutPermissions() {
		\Brain\Monkey\Functions\when('current_user_can')
			->justReturn(false);
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'test_task';
		
		$this->expectOutputString(json_encode([
			'success' => false,
			'data' => 'Insufficient permissions'
		]));
		
		try {
			$this->controller->cancel_task();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test cancel batch task
	 */
	public function testCancelBatchTask() {
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		TaskTransientManager::$test_data['nuclen_batch_123'] = [
			'status' => 'processing'
		];
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'nuclen_batch_123';
		
		\Brain\Monkey\Functions\expectOnce('wp_clear_scheduled_hook')
			->with('nuclen_process_batch', ['nuclen_batch_123']);
		
		$this->expectOutputString(json_encode([
			'success' => true,
			'data' => [
				'message' => 'Task nuclen_batch_123 has been cancelled.'
			]
		]));
		
		try {
			$this->controller->cancel_task();
		} catch (\Exception $e) {
			// Expected exit
		}
		
		// Verify status was updated
		$this->assertEquals('cancelled', TaskTransientManager::$test_data['nuclen_batch_123']['status']);
	}

	/**
	 * Test cancel generation task
	 */
	public function testCancelGenerationTask() {
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		TaskTransientManager::$test_data['test_gen_123'] = [
			'status' => 'processing',
			'batch_jobs' => [
				['batch_id' => 'batch_1'],
				['batch_id' => 'batch_2']
			]
		];
		
		TaskTransientManager::$test_data['batch_1'] = ['status' => 'processing'];
		TaskTransientManager::$test_data['batch_2'] = ['status' => 'pending'];
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'test_gen_123';
		
		// Expect polling queue to be called
		$this->mock_polling_queue->expects($this->once())
			->method('mark_generation_complete')
			->with('test_gen_123');
		
		// Expect scheduled hooks to be cleared for batches
		\Brain\Monkey\Functions\expect('wp_clear_scheduled_hook')
			->times(2);
		
		$this->expectOutputString(json_encode([
			'success' => true,
			'data' => [
				'message' => 'Task test_gen_123 has been cancelled.'
			]
		]));
		
		try {
			$this->controller->cancel_task();
		} catch (\Exception $e) {
			// Expected exit
		}
		
		// Verify statuses were updated
		$this->assertEquals('cancelled', TaskTransientManager::$test_data['test_gen_123']['status']);
		$this->assertEquals('cancelled', TaskTransientManager::$test_data['batch_1']['status']);
		$this->assertEquals('cancelled', TaskTransientManager::$test_data['batch_2']['status']);
	}

	/**
	 * Test get task status for batch
	 */
	public function testGetTaskStatusBatch() {
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		TaskTransientManager::$test_data['nuclen_batch_123'] = [
			'status' => 'processing'
		];
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'nuclen_batch_123';
		
		$this->expectOutputString(json_encode([
			'success' => true,
			'data' => [
				'status' => 'processing',
				'type' => 'batch'
			]
		]));
		
		try {
			$this->controller->get_task_status();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test get task status for generation with progress
	 */
	public function testGetTaskStatusGenerationWithProgress() {
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		TaskTransientManager::$test_data['test_gen_123'] = [
			'status' => 'processing',
			'total_posts' => 10,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 5],
				['batch_id' => 'batch_2', 'post_count' => 5]
			]
		];
		
		TaskTransientManager::$test_data['batch_1'] = ['status' => 'completed'];
		TaskTransientManager::$test_data['batch_2'] = ['status' => 'failed'];
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'test_gen_123';
		
		$this->expectOutputString(json_encode([
			'success' => true,
			'data' => [
				'status' => 'processing',
				'type' => 'generation',
				'progress' => 50,
				'processed' => 5,
				'failed' => 5,
				'total' => 10
			]
		]));
		
		try {
			$this->controller->get_task_status();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test get task status for non-existent task
	 */
	public function testGetTaskStatusNotFound() {
		// Mock TaskTransientManager
		$this->mockTaskTransientManager();
		
		$_POST['nonce'] = 'test_nonce';
		$_POST['task_id'] = 'non_existent';
		
		$this->expectOutputString(json_encode([
			'success' => false,
			'data' => 'Task not found'
		]));
		
		try {
			$this->controller->get_task_status();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test get recent completions
	 */
	public function testGetRecentCompletions() {
		$recent_data = [
			['task_id' => 'task_1', 'completed_at' => time()],
			['task_id' => 'task_2', 'completed_at' => time() - 60]
		];
		
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_recent_completions')
			->justReturn($recent_data);
		
		\Brain\Monkey\Functions\expectOnce('delete_transient')
			->with('nuclen_recent_completions');
		
		$_POST['nonce'] = 'test_nonce';
		
		$this->expectOutputString(json_encode([
			'success' => true,
			'data' => $recent_data
		]));
		
		try {
			$this->controller->get_recent_completions();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Test get recent completions when empty
	 */
	public function testGetRecentCompletionsEmpty() {
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_recent_completions')
			->justReturn(false);
		
		$_POST['nonce'] = 'test_nonce';
		
		$this->expectOutputString(json_encode([
			'success' => true,
			'data' => []
		]));
		
		try {
			$this->controller->get_recent_completions();
		} catch (\Exception $e) {
			// Expected exit
		}
	}

	/**
	 * Mock TaskTransientManager for testing
	 */
	private function mockTaskTransientManager() {
		// Create a test-friendly version of TaskTransientManager
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
			}
			');
		}
		
		// Reset test data
		TaskTransientManager::$test_data = [];
	}
}