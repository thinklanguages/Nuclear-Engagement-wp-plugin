<?php
/**
 * TasksAdminTest.php - Tests for Tasks admin class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Tasks;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Services\AdminNoticeService;
use NuclearEngagement\Services\CentralizedPollingQueue;

/**
 * Test Tasks admin class
 */
class TasksAdminTest extends TestCase {
	private Tasks $tasks;
	private SettingsRepository $settings_repo;
	private ServiceContainer $container;
	private $mock_polling_queue;
	private $mock_notice_service;
	private $wpdb_mock;

	public function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions
		\Brain\Monkey\setUp();
		
		// Setup mocks
		$this->settings_repo = SettingsRepository::get_instance();
		$this->container = $this->createMock(ServiceContainer::class);
		$this->mock_polling_queue = $this->createMock(CentralizedPollingQueue::class);
		$this->mock_notice_service = $this->createMock(AdminNoticeService::class);
		
		// Setup wpdb mock
		$this->wpdb_mock = $this->createMock(stdClass::class);
		$this->wpdb_mock->options = 'wp_options';
		$GLOBALS['wpdb'] = $this->wpdb_mock;
		
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
		
		$this->tasks = new Tasks($this->settings_repo, $this->container);
		
		// Mock global functions
		\Brain\Monkey\Functions\when('current_user_can')
			->justReturn(true);
		\Brain\Monkey\Functions\when('wp_verify_nonce')
			->justReturn(true);
		\Brain\Monkey\Functions\when('sanitize_text_field')
			->returnArg();
		\Brain\Monkey\Functions\when('__')
			->returnArg();
		\Brain\Monkey\Functions\when('esc_html')
			->returnArg();
		\Brain\Monkey\Functions\when('esc_url')
			->returnArg();
		\Brain\Monkey\Functions\when('remove_query_arg')
			->justReturn('http://example.com/wp-admin/admin.php?page=nuclen-tasks');
		\Brain\Monkey\Functions\when('add_query_arg')
			->justReturn('http://example.com/wp-admin/admin.php?page=nuclen-tasks&paged=2');
		\Brain\Monkey\Functions\when('wp_safe_redirect')
			->justReturn(null);
		\Brain\Monkey\Functions\when('wp_die')
			->alias(function($msg) {
				throw new \Exception($msg);
			});
		\Brain\Monkey\Functions\when('get_transient')
			->justReturn(false);
		\Brain\Monkey\Functions\when('set_transient')
			->justReturn(true);
		\Brain\Monkey\Functions\when('wp_cache_get')
			->justReturn(false);
		\Brain\Monkey\Functions\when('wp_cache_set')
			->justReturn(true);
		\Brain\Monkey\Functions\when('wp_clear_scheduled_hook')
			->justReturn(null);
		\Brain\Monkey\Functions\when('do_action')
			->justReturn(null);
		\Brain\Monkey\Functions\when('wp_next_scheduled')
			->justReturn(false);
		\Brain\Monkey\Functions\when('_get_cron_array')
			->justReturn([]);
		\Brain\Monkey\Functions\when('intval')
			->returnArg();
		\Brain\Monkey\Functions\when('time')
			->justReturn(1234567890);
		
		// Define constants
		if (!defined('NUCLEN_PLUGIN_DIR')) {
			define('NUCLEN_PLUGIN_DIR', '/path/to/plugin/');
		}
		if (!defined('DAY_IN_SECONDS')) {
			define('DAY_IN_SECONDS', 86400);
		}
		if (!defined('WP_DEBUG')) {
			define('WP_DEBUG', false);
		}
	}

	public function tearDown(): void {
		\Brain\Monkey\tearDown();
		unset($GLOBALS['wpdb']);
		parent::tearDown();
	}


	/**
	 * Test handle task action - run now
	 */
	public function testHandleTaskActionRunNow() {
		$_GET['action'] = 'run_now';
		$_GET['task_id'] = 'nuclen_batch_123';
		$_GET['_wpnonce'] = 'valid_nonce';
		$_GET['paged'] = '2';
		
		// Mock batch data
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_batch_nuclen_batch_123')
			->justReturn(['status' => 'pending']);
		
		// Expect action to be triggered
		\Brain\Monkey\Actions\expectDone('nuclen_process_batch')
			->once()
			->with('nuclen_batch_123');
		
		// Expect admin notice
		$this->mock_notice_service->expects($this->once())
			->method('add')
			->with(
				$this->stringContains('Batch nuclen_batch_123 has been triggered')
			);
		
		// Expect redirect
		\Brain\Monkey\Functions\expectOnce('wp_safe_redirect');
		
		$this->expectException(\Exception::class); // From exit
		
		$this->tasks->render();
	}

	/**
	 * Test handle task action - run generation task
	 */
	public function testHandleTaskActionRunGenerationTask() {
		$_GET['action'] = 'run_now';
		$_GET['task_id'] = 'gen_123';
		$_GET['_wpnonce'] = 'valid_nonce';
		
		// Mock generation data
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_bulk_job_gen_123')
			->justReturn([
				'workflow_type' => 'summary',
				'batch_jobs' => [
					['batch_id' => 'batch_1'],
					['batch_id' => 'batch_2']
				]
			]);
		
		// Mock batch data
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_batch_batch_1')
			->justReturn([
				'posts' => [
					['post_id' => 1],
					['post_id' => 2]
				]
			]);
		
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_batch_batch_2')
			->justReturn([
				'posts' => [
					['id' => 3],
					['id' => 4]
				]
			]);
		
		// Expect polling queue to be called
		$this->mock_polling_queue->expects($this->once())
			->method('add_to_queue')
			->with('gen_123', 'summary', [1, 2, 3, 4], 1);
		
		// Expect redirect
		\Brain\Monkey\Functions\expectOnce('wp_safe_redirect');
		
		$this->expectException(\Exception::class); // From exit
		
		$this->tasks->render();
	}

	/**
	 * Test handle task action - cancel batch
	 */
	public function testHandleTaskActionCancelBatch() {
		$_GET['action'] = 'cancel';
		$_GET['task_id'] = 'nuclen_batch_123';
		$_GET['_wpnonce'] = 'valid_nonce';
		
		// Mock batch data
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_batch_nuclen_batch_123')
			->justReturn(['status' => 'processing']);
		
		// Expect scheduled hook to be cleared
		\Brain\Monkey\Functions\expectOnce('wp_clear_scheduled_hook')
			->with('nuclen_process_batch', ['nuclen_batch_123']);
		
		// Expect admin notice
		$this->mock_notice_service->expects($this->once())
			->method('add')
			->with(
				$this->stringContains('Batch nuclen_batch_123 has been cancelled'),
				'info'
			);
		
		// Expect redirect
		\Brain\Monkey\Functions\expectOnce('wp_safe_redirect');
		
		$this->expectException(\Exception::class); // From exit
		
		$this->tasks->render();
	}

	/**
	 * Test handle task action - cancel generation
	 */
	public function testHandleTaskActionCancelGeneration() {
		$_GET['action'] = 'cancel';
		$_GET['task_id'] = 'gen_123';
		$_GET['_wpnonce'] = 'valid_nonce';
		
		// Mock generation data
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_bulk_job_gen_123')
			->justReturn([
				'status' => 'processing',
				'batch_jobs' => [
					['batch_id' => 'batch_1'],
					['batch_id' => 'batch_2']
				]
			]);
		
		// Mock batch data
		\Brain\Monkey\Functions\when('get_transient')
			->withArgs(function($key) {
				return in_array($key, ['nuclen_batch_batch_1', 'nuclen_batch_batch_2']);
			})
			->justReturn(['status' => 'processing']);
		
		// Expect polling queue to be called
		$this->mock_polling_queue->expects($this->once())
			->method('mark_generation_complete')
			->with('gen_123');
		
		// Expect scheduled hooks to be cleared
		\Brain\Monkey\Functions\expect('wp_clear_scheduled_hook')
			->times(2);
		
		// Expect admin notice
		$this->mock_notice_service->expects($this->once())
			->method('add')
			->with(
				$this->stringContains('Generation gen_123 has been cancelled'),
				'info'
			);
		
		// Expect redirect
		\Brain\Monkey\Functions\expectOnce('wp_safe_redirect');
		
		$this->expectException(\Exception::class); // From exit
		
		$this->tasks->render();
	}

	/**
	 * Test handle task action with invalid nonce
	 */
	public function testHandleTaskActionInvalidNonce() {
		$_GET['action'] = 'run_now';
		$_GET['task_id'] = 'test_task';
		$_GET['_wpnonce'] = 'invalid_nonce';
		
		\Brain\Monkey\Functions\when('wp_verify_nonce')
			->justReturn(false);
		
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Security check failed.');
		
		$this->tasks->render();
	}

	/**
	 * Test gather tasks data - empty results
	 */
	public function testGatherTasksDataEmpty() {
		// Mock wpdb query results
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->willReturn('0');
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->willReturn([]);
		
		$this->wpdb_mock->expects($this->exactly(2))
			->method('prepare')
			->willReturn('SQL QUERY');
		
		ob_start();
		$this->tasks->render();
		$output = ob_get_clean();
		
		$this->assertStringContainsString('Nuclear Engagement - Tasks', $output);
	}

	/**
	 * Test gather tasks data with generation tasks
	 */
	public function testGatherTasksDataWithTasks() {
		// Mock wpdb query results
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->willReturn('2');
		
		// Mock job data
		$job1_data = serialize([
			'created_at' => time() - 3600,
			'workflow_type' => 'summary',
			'status' => 'processing',
			'total_posts' => 10,
			'batch_jobs' => [
				['batch_id' => 'batch_1', 'post_count' => 5],
				['batch_id' => 'batch_2', 'post_count' => 5]
			]
		]);
		
		$job2_data = serialize([
			'created_at' => time() - 7200,
			'workflow_type' => 'quiz',
			'status' => 'completed',
			'total_posts' => 20,
			'batch_jobs' => [
				['batch_id' => 'batch_3', 'post_count' => 20]
			]
		]);
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->willReturn([
				(object)['option_name' => '_transient_nuclen_bulk_job_gen_1', 'option_value' => $job1_data],
				(object)['option_name' => '_transient_nuclen_bulk_job_gen_2', 'option_value' => $job2_data]
			]);
		
		$this->wpdb_mock->expects($this->exactly(2))
			->method('prepare')
			->willReturn('SQL QUERY');
		
		// Mock batch transients
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_batch_batch_1')
			->justReturn(['status' => 'completed']);
		
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_batch_batch_2')
			->justReturn(['status' => 'failed']);
		
		\Brain\Monkey\Functions\when('get_transient')
			->with('nuclen_batch_batch_3')
			->justReturn(['status' => 'completed']);
		
		// Mock scheduled events
		\Brain\Monkey\Functions\when('wp_next_scheduled')
			->with('nuclen_process_batch', ['batch_2'])
			->justReturn(time() + 300);
		
		ob_start();
		$this->tasks->render();
		$output = ob_get_clean();
		
		$this->assertStringContainsString('Nuclear Engagement - Tasks', $output);
	}

	/**
	 * Test gather tasks data with pagination
	 */
	public function testGatherTasksDataWithPagination() {
		$_GET['paged'] = '2';
		
		// Mock wpdb query results
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->willReturn('50'); // Total tasks
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->willReturn([]); // Empty for page 2
		
		$this->wpdb_mock->expects($this->exactly(2))
			->method('prepare')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				20, // LIMIT
				20  // OFFSET for page 2
			)
			->willReturn('SQL QUERY');
		
		ob_start();
		$this->tasks->render();
		$output = ob_get_clean();
		
		$this->assertStringContainsString('Nuclear Engagement - Tasks', $output);
	}

	/**
	 * Test gather tasks data with cache hit
	 */
	public function testGatherTasksDataWithCache() {
		$cached_data = [
			'tasks' => [
				[
					'id' => 'cached_gen_1',
					'created_at' => time() - 100,
					'workflow_type' => 'summary',
					'status' => 'completed',
					'total_posts' => 5,
					'processed' => 5,
					'failed' => 0,
					'progress' => 100,
					'next_scheduled' => null,
					'details' => '5 of 5 posts processed'
				]
			],
			'pagination' => [
				'total_items' => 1,
				'total_pages' => 1,
				'current_page' => 1,
				'per_page' => 20
			]
		];
		
		\Brain\Monkey\Functions\when('wp_cache_get')
			->with('nuclen_tasks_1_page_1')
			->justReturn($cached_data);
		
		// Should not call wpdb methods when cache hit
		$this->wpdb_mock->expects($this->never())
			->method('get_var');
		
		$this->wpdb_mock->expects($this->never())
			->method('get_results');
		
		ob_start();
		$this->tasks->render();
		$output = ob_get_clean();
		
		$this->assertStringContainsString('Nuclear Engagement - Tasks', $output);
	}

	/**
	 * Test cron status check
	 */
	public function testCronStatusCheck() {
		// Test with DISABLE_WP_CRON not defined
		ob_start();
		$this->tasks->render();
		$output = ob_get_clean();
		
		$this->assertStringContainsString('Nuclear Engagement - Tasks', $output);
		
		// Test with DISABLE_WP_CRON = true
		if (!defined('DISABLE_WP_CRON')) {
			define('DISABLE_WP_CRON', true);
		}
		
		// Mock cron array
		\Brain\Monkey\Functions\when('_get_cron_array')
			->justReturn([
				time() + 300 => ['some_hook' => []],
				time() + 600 => ['another_hook' => []]
			]);
		
		ob_start();
		$this->tasks->render();
		$output = ob_get_clean();
		
		$this->assertStringContainsString('Nuclear Engagement - Tasks', $output);
	}

	/**
	 * Test error handling for invalid job data
	 */
	public function testInvalidJobDataHandling() {
		// Mock wpdb query results with invalid serialized data
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->willReturn('1');
		
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->willReturn([
				(object)['option_name' => '_transient_nuclen_bulk_job_bad', 'option_value' => 'invalid_serialized_data']
			]);
		
		$this->wpdb_mock->expects($this->exactly(2))
			->method('prepare')
			->willReturn('SQL QUERY');
		
		// Should handle error gracefully
		ob_start();
		$this->tasks->render();
		$output = ob_get_clean();
		
		$this->assertStringContainsString('Nuclear Engagement - Tasks', $output);
	}
}