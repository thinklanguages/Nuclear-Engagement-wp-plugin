<?php
/**
 * TaskWorkflowIntegrationTest.php - Integration tests for task workflows
 *
 * @package NuclearEngagement_Tests_Integration
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Controller\Ajax\TasksController;
use NuclearEngagement\Admin\Tasks;
use NuclearEngagement\Services\GenerationService;
use NuclearEngagement\Services\BulkGenerationBatchProcessor;
use NuclearEngagement\Services\CentralizedPollingQueue;
use NuclearEngagement\Services\TaskTransientManager;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Services\AutoGenerationService;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Core\SettingsRepository;

/**
 * Integration tests for complete task workflows
 * 
 * These tests verify the end-to-end flow of task generation,
 * from creation through processing to completion.
 */
class TaskWorkflowIntegrationTest extends TestCase {
	private ServiceContainer $container;
	private GenerationService $generation_service;
	private BulkGenerationBatchProcessor $batch_processor;
	private CentralizedPollingQueue $polling_queue;
	private RemoteApiService $api_service;
	private ContentStorageService $storage_service;
	private AutoGenerationService $auto_gen_service;
	private TasksController $tasks_controller;
	private Tasks $tasks_admin;

	public function setUp(): void {
		parent::setUp();
		
		// This is an integration test, so we set up real components
		// In a real environment, this would run with WordPress loaded
		
		// Mock WordPress functions that we can't avoid
		\Brain\Monkey\setUp();
		$this->setupWordPressMocks();
		
		// Initialize service container
		$this->container = new ServiceContainer();
		
		// Register services
		$this->registerServices();
		
		// Get service instances
		$this->generation_service = $this->container->get('generation_service');
		$this->batch_processor = $this->container->get('bulk_batch_processor');
		$this->polling_queue = $this->container->get('centralized_polling_queue');
		$this->api_service = $this->container->get('remote_api_service');
		$this->storage_service = $this->container->get('content_storage_service');
		$this->auto_gen_service = $this->container->get('auto_generation_service');
		
		// Initialize controllers
		$this->tasks_controller = new TasksController($this->container);
		$this->tasks_admin = new Tasks(
			$this->container->get('settings_repository'),
			$this->container
		);
		
		// Setup test database tables (mocked)
		$this->setupTestDatabase();
	}

	public function tearDown(): void {
		\Brain\Monkey\tearDown();
		$this->cleanupTestData();
		parent::tearDown();
	}

	/**
	 * Test complete workflow: Create -> Process -> Complete
	 */
	public function testCompleteGenerationWorkflow() {
		// Step 1: Create a generation task
		$post_ids = [101, 102, 103, 104, 105];
		$workflow_type = 'summary';
		
		$generation_id = $this->createGenerationTask($post_ids, $workflow_type);
		$this->assertNotEmpty($generation_id);
		
		// Verify task was created
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->assertEquals('pending', $task_data['status']);
		$this->assertEquals($workflow_type, $task_data['workflow_type']);
		$this->assertEquals(count($post_ids), $task_data['total_posts']);
		
		// Step 2: Trigger task processing via controller
		$_POST['nonce'] = wp_create_nonce('nuclen_task_action');
		$_POST['task_id'] = $generation_id;
		
		ob_start();
		$this->tasks_controller->run_task();
		$response = json_decode(ob_get_clean(), true);
		
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('queued for immediate processing', $response['data']['message']);
		
		// Step 3: Process batches
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->assertEquals('processing', $task_data['status']);
		
		foreach ($task_data['batch_jobs'] as $batch_job) {
			$this->processBatch($batch_job['batch_id']);
		}
		
		// Step 4: Verify completion
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->assertEquals('completed', $task_data['status']);
		$this->assertArrayHasKey('completed_at', $task_data);
		
		// Verify all posts were processed
		foreach ($post_ids as $post_id) {
			$this->assertTrue($this->storage_service->has_generated_content($post_id, $workflow_type));
		}
	}

	/**
	 * Test workflow with failures and retries
	 */
	public function testWorkflowWithFailuresAndRetries() {
		// Create task
		$post_ids = [201, 202, 203];
		$generation_id = $this->createGenerationTask($post_ids, 'quiz');
		
		// Simulate API failure for first batch
		$this->api_service->simulateFailure(true);
		
		// Process first batch (should fail)
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$first_batch_id = $task_data['batch_jobs'][0]['batch_id'];
		
		$this->processBatch($first_batch_id);
		
		$batch_data = TaskTransientManager::get_batch_transient($first_batch_id);
		$this->assertEquals('failed', $batch_data['status']);
		
		// Verify task reflects partial failure
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->assertContains($task_data['status'], ['processing', 'failed']);
		
		// Clear API failure and retry
		$this->api_service->simulateFailure(false);
		
		// Retry failed batch
		$_POST['task_id'] = $first_batch_id;
		ob_start();
		$this->tasks_controller->run_task();
		ob_end_clean();
		
		$this->processBatch($first_batch_id);
		
		// Verify batch succeeded on retry
		$batch_data = TaskTransientManager::get_batch_transient($first_batch_id);
		$this->assertEquals('completed', $batch_data['status']);
		
		// Process remaining batches
		foreach ($task_data['batch_jobs'] as $batch_job) {
			if ($batch_job['batch_id'] !== $first_batch_id) {
				$this->processBatch($batch_job['batch_id']);
			}
		}
		
		// Verify final completion
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->assertEquals('completed', $task_data['status']);
	}

	/**
	 * Test cancel workflow mid-processing
	 */
	public function testCancelWorkflowMidProcessing() {
		// Create large task
		$post_ids = range(301, 350); // 50 posts
		$generation_id = $this->createGenerationTask($post_ids, 'summary');
		
		// Start processing
		$_POST['nonce'] = wp_create_nonce('nuclen_task_action');
		$_POST['task_id'] = $generation_id;
		ob_start();
		$this->tasks_controller->run_task();
		ob_end_clean();
		
		// Process first batch only
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->processBatch($task_data['batch_jobs'][0]['batch_id']);
		
		// Cancel task
		$_POST['action'] = 'cancel';
		ob_start();
		$this->tasks_controller->cancel_task();
		$response = json_decode(ob_get_clean(), true);
		
		$this->assertTrue($response['success']);
		
		// Verify cancellation
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->assertEquals('cancelled', $task_data['status']);
		
		// Verify remaining batches are cancelled
		foreach ($task_data['batch_jobs'] as $batch_job) {
			$batch_data = TaskTransientManager::get_batch_transient($batch_job['batch_id']);
			if ($batch_job['batch_id'] !== $task_data['batch_jobs'][0]['batch_id']) {
				$this->assertContains($batch_data['status'], ['cancelled', 'pending']);
			}
		}
		
		// Verify no more processing occurs
		$this->assertFalse($this->polling_queue->has_active_generation($generation_id));
	}

	/**
	 * Test auto-generation workflow
	 */
	public function testAutoGenerationWorkflow() {
		// Enable auto-generation
		$settings = $this->container->get('settings_repository');
		$settings->update([
			'auto_generation_enabled' => true,
			'auto_generation_workflow' => 'quiz',
			'auto_generation_interval' => 'daily'
		]);
		
		// Create test posts
		$post_ids = $this->createTestPosts(5);
		
		// Trigger auto-generation
		$generation_id = $this->auto_gen_service->schedule_auto_generation($post_ids);
		$this->assertNotEmpty($generation_id);
		
		// Verify scheduled task
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->assertEquals('scheduled', $task_data['status']);
		$this->assertEquals('quiz', $task_data['workflow_type']);
		$this->assertTrue($task_data['is_auto_generation']);
		
		// Simulate cron trigger
		do_action('nuclen_auto_generation_cron', $generation_id);
		
		// Process all batches
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		foreach ($task_data['batch_jobs'] as $batch_job) {
			$this->processBatch($batch_job['batch_id']);
		}
		
		// Verify completion
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$this->assertEquals('completed', $task_data['status']);
		
		// Verify next auto-generation is scheduled
		$next_scheduled = $this->auto_gen_service->get_next_scheduled_time();
		$this->assertGreaterThan(time(), $next_scheduled);
	}

	/**
	 * Test bulk operations workflow
	 */
	public function testBulkOperationsWorkflow() {
		// Create multiple tasks
		$task_ids = [];
		for ($i = 0; $i < 5; $i++) {
			$post_ids = range($i * 10 + 1, $i * 10 + 10);
			$task_ids[] = $this->createGenerationTask($post_ids, $i % 2 === 0 ? 'summary' : 'quiz');
		}
		
		// Test bulk run
		$this->performBulkAction($task_ids, 'run');
		
		// Verify all tasks are processing
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			$this->assertEquals('processing', $task_data['status']);
		}
		
		// Process some tasks
		for ($i = 0; $i < 3; $i++) {
			$this->processAllBatchesForTask($task_ids[$i]);
		}
		
		// Test bulk cancel on remaining tasks
		$remaining_tasks = array_slice($task_ids, 3);
		$this->performBulkAction($remaining_tasks, 'cancel');
		
		// Verify cancellation
		foreach ($remaining_tasks as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			$this->assertEquals('cancelled', $task_data['status']);
		}
	}

	/**
	 * Test task status monitoring workflow
	 */
	public function testTaskStatusMonitoringWorkflow() {
		// Create task
		$post_ids = range(501, 510);
		$generation_id = $this->createGenerationTask($post_ids, 'summary');
		
		// Monitor status changes
		$status_history = [];
		
		// Initial status
		$_POST['nonce'] = wp_create_nonce('nuclen_task_action');
		$_POST['task_id'] = $generation_id;
		
		ob_start();
		$this->tasks_controller->get_task_status();
		$response = json_decode(ob_get_clean(), true);
		$status_history[] = $response['data'];
		
		// Start processing
		ob_start();
		$this->tasks_controller->run_task();
		ob_end_clean();
		
		// Check status during processing
		ob_start();
		$this->tasks_controller->get_task_status();
		$response = json_decode(ob_get_clean(), true);
		$status_history[] = $response['data'];
		
		// Process batches and monitor progress
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		foreach ($task_data['batch_jobs'] as $index => $batch_job) {
			$this->processBatch($batch_job['batch_id']);
			
			ob_start();
			$this->tasks_controller->get_task_status();
			$response = json_decode(ob_get_clean(), true);
			$status_history[] = $response['data'];
			
			// Verify progress increases
			if ($index > 0) {
				$this->assertGreaterThan(
					$status_history[count($status_history) - 2]['progress'],
					$status_history[count($status_history) - 1]['progress']
				);
			}
		}
		
		// Verify final status
		$final_status = end($status_history);
		$this->assertEquals('completed', $final_status['status']);
		$this->assertEquals(100, $final_status['progress']);
		$this->assertEquals(count($post_ids), $final_status['processed']);
	}

	/**
	 * Test error recovery workflow
	 */
	public function testErrorRecoveryWorkflow() {
		// Create task
		$post_ids = [601, 602, 603];
		$generation_id = $this->createGenerationTask($post_ids, 'quiz');
		
		// Simulate various errors
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		$batch_id = $task_data['batch_jobs'][0]['batch_id'];
		
		// Test 1: Timeout recovery
		$batch_data = TaskTransientManager::get_batch_transient($batch_id);
		$batch_data['status'] = 'processing';
		$batch_data['started_at'] = time() - 7200; // 2 hours ago
		TaskTransientManager::set_batch_transient($batch_id, $batch_data, DAY_IN_SECONDS);
		
		// Trigger timeout handler
		do_action('nuclen_check_timeouts');
		
		$batch_data = TaskTransientManager::get_batch_transient($batch_id);
		$this->assertEquals('timed_out', $batch_data['status']);
		
		// Recover from timeout
		$_POST['task_id'] = $batch_id;
		ob_start();
		$this->tasks_controller->run_task();
		ob_end_clean();
		
		// Test 2: Circuit breaker recovery
		// Simulate multiple failures to trip circuit breaker
		for ($i = 0; $i < 5; $i++) {
			$this->api_service->simulateFailure(true);
			$this->processBatch($batch_id);
		}
		
		// Verify circuit breaker is open
		$circuit_breaker = $this->container->get('circuit_breaker_service');
		$this->assertTrue($circuit_breaker->is_open('remote_api'));
		
		// Wait for circuit breaker to half-open
		sleep(1); // In real tests, we'd mock time
		
		// Clear failure and retry
		$this->api_service->simulateFailure(false);
		$this->processBatch($batch_id);
		
		// Verify recovery
		$this->assertFalse($circuit_breaker->is_open('remote_api'));
		$batch_data = TaskTransientManager::get_batch_transient($batch_id);
		$this->assertEquals('completed', $batch_data['status']);
	}

	/**
	 * Test concurrent task processing
	 */
	public function testConcurrentTaskProcessing() {
		// Create multiple tasks that will process concurrently
		$task_ids = [];
		for ($i = 0; $i < 3; $i++) {
			$post_ids = range($i * 100 + 1, $i * 100 + 20);
			$task_ids[] = $this->createGenerationTask($post_ids, 'summary');
		}
		
		// Start all tasks
		foreach ($task_ids as $task_id) {
			$_POST['task_id'] = $task_id;
			ob_start();
			$this->tasks_controller->run_task();
			ob_end_clean();
		}
		
		// Verify all are processing
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			$this->assertEquals('processing', $task_data['status']);
		}
		
		// Simulate concurrent batch processing
		$all_batches = [];
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			foreach ($task_data['batch_jobs'] as $batch_job) {
				$all_batches[] = [
					'task_id' => $task_id,
					'batch_id' => $batch_job['batch_id']
				];
			}
		}
		
		// Process batches in random order to simulate concurrency
		shuffle($all_batches);
		foreach ($all_batches as $batch_info) {
			$this->processBatch($batch_info['batch_id']);
		}
		
		// Verify all tasks completed successfully
		foreach ($task_ids as $task_id) {
			$task_data = TaskTransientManager::get_task_transient($task_id);
			$this->assertEquals('completed', $task_data['status']);
		}
	}

	/**
	 * Helper method to create a generation task
	 */
	private function createGenerationTask(array $post_ids, string $workflow_type): string {
		$generation_id = 'gen_' . wp_generate_uuid4();
		$batch_size = 5;
		$batches = array_chunk($post_ids, $batch_size);
		$batch_jobs = [];
		
		foreach ($batches as $index => $batch_posts) {
			$batch_id = $generation_id . '_batch_' . ($index + 1);
			$batch_jobs[] = [
				'batch_id' => $batch_id,
				'post_count' => count($batch_posts),
				'status' => 'pending'
			];
			
			// Create batch transient
			TaskTransientManager::set_batch_transient($batch_id, [
				'batch_id' => $batch_id,
				'status' => 'pending',
				'posts' => array_map(function($id) {
					return ['post_id' => $id, 'status' => 'pending'];
				}, $batch_posts)
			], DAY_IN_SECONDS);
		}
		
		// Create task transient
		TaskTransientManager::set_task_transient($generation_id, [
			'id' => $generation_id,
			'workflow_type' => $workflow_type,
			'status' => 'pending',
			'created_at' => time(),
			'total_posts' => count($post_ids),
			'batch_jobs' => $batch_jobs
		], DAY_IN_SECONDS);
		
		return $generation_id;
	}

	/**
	 * Helper method to process a batch
	 */
	private function processBatch(string $batch_id): void {
		$batch_data = TaskTransientManager::get_batch_transient($batch_id);
		if (!$batch_data) {
			return;
		}
		
		$batch_data['status'] = 'processing';
		$batch_data['started_at'] = time();
		TaskTransientManager::set_batch_transient($batch_id, $batch_data, DAY_IN_SECONDS);
		
		// Simulate API calls and processing
		foreach ($batch_data['posts'] as &$post) {
			if ($this->api_service->is_failing()) {
				$post['status'] = 'failed';
				$post['error'] = 'API failure';
			} else {
				$post['status'] = 'completed';
				$post['generated_at'] = time();
				
				// Store generated content
				$this->storage_service->store_generated_content(
					$post['post_id'],
					$this->getParentTaskWorkflowType($batch_id),
					['content' => 'Generated content for post ' . $post['post_id']]
				);
			}
		}
		
		// Update batch status
		$failed_count = count(array_filter($batch_data['posts'], fn($p) => $p['status'] === 'failed'));
		if ($failed_count === count($batch_data['posts'])) {
			$batch_data['status'] = 'failed';
		} elseif ($failed_count > 0) {
			$batch_data['status'] = 'completed_with_errors';
		} else {
			$batch_data['status'] = 'completed';
		}
		
		$batch_data['completed_at'] = time();
		TaskTransientManager::set_batch_transient($batch_id, $batch_data, DAY_IN_SECONDS);
		
		// Update parent task status
		$this->updateParentTaskStatus($batch_id);
	}

	/**
	 * Helper method to get parent task workflow type
	 */
	private function getParentTaskWorkflowType(string $batch_id): string {
		// Extract generation ID from batch ID
		$parts = explode('_batch_', $batch_id);
		$generation_id = $parts[0];
		
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		return $task_data['workflow_type'] ?? 'summary';
	}

	/**
	 * Helper method to update parent task status
	 */
	private function updateParentTaskStatus(string $batch_id): void {
		// Extract generation ID from batch ID
		$parts = explode('_batch_', $batch_id);
		$generation_id = $parts[0];
		
		$task_data = TaskTransientManager::get_task_transient($generation_id);
		if (!$task_data) {
			return;
		}
		
		// Check all batch statuses
		$all_completed = true;
		$any_failed = false;
		$processed_count = 0;
		$failed_count = 0;
		
		foreach ($task_data['batch_jobs'] as &$batch_job) {
			$batch_data = TaskTransientManager::get_batch_transient($batch_job['batch_id']);
			if ($batch_data) {
				$batch_job['status'] = $batch_data['status'];
				
				if (!in_array($batch_data['status'], ['completed', 'completed_with_errors', 'failed'])) {
					$all_completed = false;
				}
				
				if ($batch_data['status'] === 'failed') {
					$any_failed = true;
				}
				
				// Count processed posts
				foreach ($batch_data['posts'] as $post) {
					if ($post['status'] === 'completed') {
						$processed_count++;
					} elseif ($post['status'] === 'failed') {
						$failed_count++;
					}
				}
			} else {
				$all_completed = false;
			}
		}
		
		// Update task status
		if ($all_completed) {
			if ($any_failed) {
				$task_data['status'] = 'completed_with_errors';
			} else {
				$task_data['status'] = 'completed';
			}
			$task_data['completed_at'] = time();
		} elseif ($task_data['status'] === 'pending') {
			$task_data['status'] = 'processing';
			$task_data['started_at'] = time();
		}
		
		// Update progress
		$task_data['processed_count'] = $processed_count;
		$task_data['failed_count'] = $failed_count;
		$task_data['progress'] = $task_data['total_posts'] > 0 
			? round(($processed_count / $task_data['total_posts']) * 100) 
			: 0;
		
		TaskTransientManager::set_task_transient($generation_id, $task_data, DAY_IN_SECONDS);
	}

	/**
	 * Helper method to process all batches for a task
	 */
	private function processAllBatchesForTask(string $task_id): void {
		$task_data = TaskTransientManager::get_task_transient($task_id);
		foreach ($task_data['batch_jobs'] as $batch_job) {
			$this->processBatch($batch_job['batch_id']);
		}
	}

	/**
	 * Helper method to perform bulk actions
	 */
	private function performBulkAction(array $task_ids, string $action): void {
		foreach ($task_ids as $task_id) {
			$_POST['task_id'] = $task_id;
			ob_start();
			
			switch ($action) {
				case 'run':
					$this->tasks_controller->run_task();
					break;
				case 'cancel':
					$this->tasks_controller->cancel_task();
					break;
			}
			
			ob_end_clean();
		}
	}

	/**
	 * Helper method to create test posts
	 */
	private function createTestPosts(int $count): array {
		$post_ids = [];
		for ($i = 0; $i < $count; $i++) {
			$post_ids[] = 1000 + $i; // Mock post IDs
		}
		return $post_ids;
	}

	/**
	 * Setup WordPress mocks
	 */
	private function setupWordPressMocks(): void {
		\Brain\Monkey\Functions\when('get_transient')->alias(function($key) {
			return TaskTransientManager::$test_data[$key] ?? false;
		});
		
		\Brain\Monkey\Functions\when('set_transient')->alias(function($key, $value, $expiry) {
			TaskTransientManager::$test_data[$key] = $value;
			return true;
		});
		
		\Brain\Monkey\Functions\when('delete_transient')->alias(function($key) {
			unset(TaskTransientManager::$test_data[$key]);
			return true;
		});
		
		\Brain\Monkey\Functions\when('wp_generate_uuid4')->alias(function() {
			return 'uuid_' . uniqid();
		});
		
		\Brain\Monkey\Functions\when('wp_create_nonce')->justReturn('test_nonce');
		\Brain\Monkey\Functions\when('wp_verify_nonce')->justReturn(true);
		\Brain\Monkey\Functions\when('current_user_can')->justReturn(true);
		\Brain\Monkey\Functions\when('get_current_user_id')->justReturn(1);
		\Brain\Monkey\Functions\when('__')->returnArg();
		\Brain\Monkey\Functions\when('do_action')->justReturn(null);
		\Brain\Monkey\Functions\when('time')->alias(function() {
			return time();
		});
		
		// Define constants
		if (!defined('DAY_IN_SECONDS')) {
			define('DAY_IN_SECONDS', 86400);
		}
		if (!defined('HOUR_IN_SECONDS')) {
			define('HOUR_IN_SECONDS', 3600);
		}
	}

	/**
	 * Register services in container
	 */
	private function registerServices(): void {
		// Mock implementations for integration testing
		$this->container->register('settings_repository', function() {
			return $this->createMock(SettingsRepository::class);
		});
		
		$this->container->register('generation_service', function() {
			return $this->createMock(GenerationService::class);
		});
		
		$this->container->register('bulk_batch_processor', function() {
			return $this->createMock(BulkGenerationBatchProcessor::class);
		});
		
		$this->container->register('centralized_polling_queue', function() {
			$mock = $this->createMock(CentralizedPollingQueue::class);
			$mock->method('add_to_queue')->willReturn(true);
			$mock->method('mark_generation_complete')->willReturn(true);
			$mock->method('has_active_generation')->willReturn(false);
			return $mock;
		});
		
		$this->container->register('remote_api_service', function() {
			return new MockRemoteApiService();
		});
		
		$this->container->register('content_storage_service', function() {
			return new MockContentStorageService();
		});
		
		$this->container->register('auto_generation_service', function() {
			$mock = $this->createMock(AutoGenerationService::class);
			$mock->method('schedule_auto_generation')->willReturnCallback(function($post_ids) {
				return $this->createGenerationTask($post_ids, 'quiz');
			});
			$mock->method('get_next_scheduled_time')->willReturn(time() + DAY_IN_SECONDS);
			return $mock;
		});
		
		$this->container->register('circuit_breaker_service', function() {
			$mock = $this->createMock(\NuclearEngagement\Services\CircuitBreakerService::class);
			$mock->method('is_open')->willReturn(false);
			return $mock;
		});
		
		$this->container->register('admin_notice_service', function() {
			return $this->createMock(\NuclearEngagement\Services\AdminNoticeService::class);
		});
	}

	/**
	 * Setup test database
	 */
	private function setupTestDatabase(): void {
		// Mock TaskTransientManager storage
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
		
		TaskTransientManager::$test_data = [];
	}

	/**
	 * Cleanup test data
	 */
	private function cleanupTestData(): void {
		TaskTransientManager::$test_data = [];
	}
}

/**
 * Mock Remote API Service for testing
 */
class MockRemoteApiService extends RemoteApiService {
	private bool $should_fail = false;
	
	public function simulateFailure(bool $fail): void {
		$this->should_fail = $fail;
	}
	
	public function is_failing(): bool {
		return $this->should_fail;
	}
	
	public function send_posts_to_generate(array $data): array {
		if ($this->should_fail) {
			throw new \Exception('Simulated API failure');
		}
		
		return [
			'status' => 'success',
			'generation_id' => 'api_gen_' . uniqid()
		];
	}
}

/**
 * Mock Content Storage Service for testing
 */
class MockContentStorageService extends ContentStorageService {
	private array $storage = [];
	
	public function store_generated_content(int $post_id, string $type, array $content): bool {
		$this->storage[$post_id][$type] = $content;
		return true;
	}
	
	public function has_generated_content(int $post_id, string $type): bool {
		return isset($this->storage[$post_id][$type]);
	}
	
	public function get_generated_content(int $post_id, string $type): ?array {
		return $this->storage[$post_id][$type] ?? null;
	}
}