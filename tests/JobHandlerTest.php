<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\JobHandler;

class JobHandlerTest extends TestCase {

	protected function setUp(): void {
		// Reset static state
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$property->setValue([]);
		
		// Reset global state
		$GLOBALS['job_status_updates'] = [];
		$GLOBALS['performance_monitors'] = [];
		$GLOBALS['error_recovery_calls'] = [];
		
		// Mock global $wpdb first
		$this->mockWpdb();
		
		// Mock required classes
		$this->mockJobStatus();
		$this->mockPerformanceMonitor();
		$this->mockErrorRecovery();
		$this->mockQueryOptimizer();
		$this->mockCacheManager();
		$this->mockBackgroundJobContext();
	}
	
	private function mockWpdb(): void {
		global $wpdb;
		$wpdb = new class {
			public $prefix = 'wp_';
			
			public function update($table, $data, $where, $data_format, $where_format) {
				return 1;
			}
		};
	}

	protected function tearDown(): void {
		// Clean up global state
		unset($GLOBALS['job_status_updates']);
		unset($GLOBALS['performance_monitors']);
		unset($GLOBALS['error_recovery_calls']);
	}

	private function mockJobStatus(): void {
		if (!class_exists('NuclearEngagement\Core\JobStatus')) {
			eval('
				namespace NuclearEngagement\Core {
					class JobStatus {
						public static function update_job_status($id, $status, $progress, $message) {
							$GLOBALS["job_status_updates"][] = [
								"id" => $id,
								"status" => $status,
								"progress" => $progress,
								"message" => $message
							];
						}
						
						public static function retry_job($id, $attempts, $delay) {
							$GLOBALS["job_status_updates"][] = [
								"id" => $id,
								"action" => "retry",
								"attempts" => $attempts,
								"delay" => $delay
							];
						}
					}
				}
			');
		}
	}

	private function mockPerformanceMonitor(): void {
		if (!class_exists('NuclearEngagement\Core\PerformanceMonitor')) {
			eval('
				namespace NuclearEngagement\Core {
					class PerformanceMonitor {
						public static function start($key) {
							$GLOBALS["performance_monitors"][] = ["action" => "start", "key" => $key];
						}
						
						public static function stop($key) {
							$GLOBALS["performance_monitors"][] = ["action" => "stop", "key" => $key];
						}
					}
				}
			');
		}
	}

	private function mockErrorRecovery(): void {
		if (!class_exists('NuclearEngagement\Core\ErrorRecovery')) {
			eval('
				namespace NuclearEngagement\Core {
					class ErrorRecovery {
						public static function addErrorContext($message, $context, $level) {
							$GLOBALS["error_recovery_calls"][] = [
								"message" => $message,
								"context" => $context,
								"level" => $level
							];
						}
					}
				}
			');
		}
	}

	private function mockQueryOptimizer(): void {
		if (!class_exists('NuclearEngagement\Core\QueryOptimizer')) {
			eval('
				namespace NuclearEngagement\Core {
					class QueryOptimizer {
						public static function warmup_queries() {
							// Mock implementation
						}
					}
				}
			');
		}
	}

	private function mockCacheManager(): void {
		if (!class_exists('NuclearEngagement\Core\CacheManager')) {
			eval('
				namespace NuclearEngagement\Core {
					class CacheManager {
						public static function warmup() {
							// Mock implementation
						}
					}
				}
			');
		}
	}

	private function mockBackgroundJobContext(): void {
		if (!class_exists('NuclearEngagement\Core\BackgroundJobContext')) {
			eval('
				namespace NuclearEngagement\Core {
					class BackgroundJobContext {
						private $job_id;
						private $data;
						
						public function __construct($job_id, $data) {
							$this->job_id = $job_id;
							$this->data = $data;
						}
						
						public function get_data() {
							return $this->data;
						}
						
						public function update_progress($progress, $message) {
							$GLOBALS["job_status_updates"][] = [
								"id" => $this->job_id,
								"progress" => $progress,
								"message" => $message,
								"action" => "progress_update"
							];
						}
					}
				}
			');
		}
	}

	public function test_register_handler(): void {
		$handler = function($context) {
			return 'test_result';
		};
		
		JobHandler::register_handler('test_job', $handler);
		
		// Use reflection to verify the handler was registered
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$handlers = $property->getValue();
		
		$this->assertArrayHasKey('test_job', $handlers);
		$this->assertSame($handler, $handlers['test_job']);
	}

	public function test_process_job_success(): void {
		$handler = function($context) {
			return 'success';
		};
		
		JobHandler::register_handler('test_job', $handler);
		
		$job = [
			'id' => 'job_123',
			'type' => 'test_job',
			'data' => ['test' => 'data'],
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Verify job status updates
		$updates = $GLOBALS['job_status_updates'];
		$this->assertCount(2, $updates);
		
		// First update should be starting
		$this->assertEquals('job_123', $updates[0]['id']);
		$this->assertEquals('processing', $updates[0]['status']);
		
		// Second update should be completed
		$this->assertEquals('job_123', $updates[1]['id']);
		$this->assertEquals('completed', $updates[1]['status']);
		$this->assertEquals(100, $updates[1]['progress']);
	}

	public function test_process_job_with_unregistered_handler(): void {
		$job = [
			'id' => 'job_123',
			'type' => 'unknown_job',
			'data' => [],
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Should have error recovery call
		$this->assertNotEmpty($GLOBALS['error_recovery_calls']);
		$errorCall = $GLOBALS['error_recovery_calls'][0];
		$this->assertStringContainsString('Background job failed', $errorCall['message']);
		$this->assertEquals('job_123', $errorCall['context']['job_id']);
	}

	public function test_process_job_retry_on_failure(): void {
		$handler = function($context) {
			throw new Exception('Job failed');
		};
		
		JobHandler::register_handler('failing_job', $handler);
		
		$job = [
			'id' => 'job_123',
			'type' => 'failing_job',
			'data' => [],
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Should have retry status update
		$updates = $GLOBALS['job_status_updates'];
		$retryUpdate = null;
		foreach ($updates as $update) {
			if (isset($update['action']) && $update['action'] === 'retry') {
				$retryUpdate = $update;
				break;
			}
		}
		
		$this->assertNotNull($retryUpdate);
		$this->assertEquals('job_123', $retryUpdate['id']);
		$this->assertEquals(1, $retryUpdate['attempts']);
	}

	public function test_process_job_fails_after_max_retries(): void {
		$handler = function($context) {
			throw new Exception('Job failed');
		};
		
		JobHandler::register_handler('failing_job', $handler);
		
		$job = [
			'id' => 'job_123',
			'type' => 'failing_job',
			'data' => [],
			'attempts' => 3 // Already at max retries
		];
		
		JobHandler::process_job($job);
		
		// Should mark as failed
		$updates = $GLOBALS['job_status_updates'];
		$failedUpdate = null;
		foreach ($updates as $update) {
			if (isset($update['status']) && $update['status'] === 'failed') {
				$failedUpdate = $update;
				break;
			}
		}
		
		$this->assertNotNull($failedUpdate);
		$this->assertEquals('job_123', $failedUpdate['id']);
		$this->assertEquals('failed', $failedUpdate['status']);
	}

	public function test_register_default_handlers(): void {
		JobHandler::register_default_handlers();
		
		// Use reflection to verify handlers were registered
		$reflection = new ReflectionClass(JobHandler::class);
		$property = $reflection->getProperty('job_handlers');
		$property->setAccessible(true);
		$handlers = $property->getValue();
		
		$this->assertArrayHasKey('api_generation', $handlers);
		$this->assertArrayHasKey('cache_warmup', $handlers);
		$this->assertArrayHasKey('data_export', $handlers);
	}

	public function test_api_generation_handler(): void {
		JobHandler::register_default_handlers();
		
		$job = [
			'id' => 'job_123',
			'type' => 'api_generation',
			'data' => [
				'post_ids' => [1, 2, 3]
			],
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Should complete successfully
		$updates = $GLOBALS['job_status_updates'];
		$completedUpdate = null;
		foreach ($updates as $update) {
			if (isset($update['status']) && $update['status'] === 'completed') {
				$completedUpdate = $update;
				break;
			}
		}
		
		$this->assertNotNull($completedUpdate);
		$this->assertEquals('job_123', $completedUpdate['id']);
	}

	public function test_api_generation_handler_missing_post_ids(): void {
		JobHandler::register_default_handlers();
		
		$job = [
			'id' => 'job_123',
			'type' => 'api_generation',
			'data' => [], // Missing post_ids
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Should have error recovery call
		$this->assertNotEmpty($GLOBALS['error_recovery_calls']);
		$errorCall = $GLOBALS['error_recovery_calls'][0];
		$this->assertStringContainsString('post_ids required', $errorCall['context']['error']);
	}

	public function test_cache_warmup_handler(): void {
		JobHandler::register_default_handlers();
		
		$job = [
			'id' => 'job_123',
			'type' => 'cache_warmup',
			'data' => [],
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Should complete successfully
		$updates = $GLOBALS['job_status_updates'];
		$completedUpdate = null;
		foreach ($updates as $update) {
			if (isset($update['status']) && $update['status'] === 'completed') {
				$completedUpdate = $update;
				break;
			}
		}
		
		$this->assertNotNull($completedUpdate);
		$this->assertEquals('job_123', $completedUpdate['id']);
	}

	public function test_data_export_handler(): void {
		JobHandler::register_default_handlers();
		
		$job = [
			'id' => 'job_123',
			'type' => 'data_export',
			'data' => [
				'type' => 'csv'
			],
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Should complete successfully
		$updates = $GLOBALS['job_status_updates'];
		$completedUpdate = null;
		foreach ($updates as $update) {
			if (isset($update['status']) && $update['status'] === 'completed') {
				$completedUpdate = $update;
				break;
			}
		}
		
		$this->assertNotNull($completedUpdate);
		$this->assertEquals('job_123', $completedUpdate['id']);
	}

	public function test_performance_monitoring(): void {
		$handler = function($context) {
			return 'success';
		};
		
		JobHandler::register_handler('monitored_job', $handler);
		
		$job = [
			'id' => 'job_123',
			'type' => 'monitored_job',
			'data' => [],
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Should have performance monitoring calls
		$monitors = $GLOBALS['performance_monitors'];
		$this->assertCount(2, $monitors);
		
		$this->assertEquals('start', $monitors[0]['action']);
		$this->assertEquals('background_job_monitored_job', $monitors[0]['key']);
		
		$this->assertEquals('stop', $monitors[1]['action']);
		$this->assertEquals('background_job_monitored_job', $monitors[1]['key']);
	}

	public function test_progress_updates_in_api_generation(): void {
		JobHandler::register_default_handlers();
		
		$job = [
			'id' => 'job_123',
			'type' => 'api_generation',
			'data' => [
				'post_ids' => [1, 2]
			],
			'attempts' => 0
		];
		
		JobHandler::process_job($job);
		
		// Should have progress updates
		$updates = $GLOBALS['job_status_updates'];
		$progressUpdates = array_filter($updates, function($update) {
			return isset($update['action']) && $update['action'] === 'progress_update';
		});
		
		$this->assertNotEmpty($progressUpdates);
		
		// Should have initial progress update
		$initialProgress = null;
		foreach ($progressUpdates as $update) {
			if ($update['progress'] === 10) {
				$initialProgress = $update;
				break;
			}
		}
		
		$this->assertNotNull($initialProgress);
		$this->assertStringContainsString('Preparing data', $initialProgress['message']);
	}
}