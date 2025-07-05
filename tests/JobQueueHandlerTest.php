<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions for job queue
if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Mock WordPress database
if (!class_exists('MockWpdb')) {
    class MockWpdb {
        public $prefix = 'wp_';
        public $queries = [];
        public $insert_id = 1;
        
        public function update($table, $data, $where, $format = null, $where_format = null) {
            $this->queries[] = ['update', $table, $data, $where];
            return 1; // Affected rows
        }
        
        public function insert($table, $data, $format = null) {
            $this->queries[] = ['insert', $table, $data];
            $this->insert_id++;
            return true;
        }
        
        public function get_results($query, $output = OBJECT) {
            $this->queries[] = ['select', $query];
            
            // Return mock job data for testing
            return [
                (object) [
                    'job_id' => 'test-job-1',
                    'type' => 'test_job',
                    'data' => '{"test":"data"}',
                    'priority' => 10,
                    'attempts' => 0,
                    'scheduled' => time(),
                    'status' => 'queued'
                ]
            ];
        }
        
        public function get_var($query) {
            $this->queries[] = ['get_var', $query];
            return 5; // Mock count
        }
        
        public function prepare($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", $query), $args);
        }
    }
}

// Mock classes
if (!class_exists('MockJobStatus')) {
    class MockJobStatus {
        public static $statuses = [];
        public static $retries = [];
        
        public static function update_job_status($job_id, $status, $progress = 0, $message = '') {
            self::$statuses[$job_id] = compact('status', 'progress', 'message');
        }
        
        public static function retry_job($job_id, $attempts, $delay) {
            self::$retries[$job_id] = compact('attempts', 'delay');
        }
    }
}

if (!class_exists('MockPerformanceMonitor')) {
    class MockPerformanceMonitor {
        public static $timers = [];
        
        public static function start($name) {
            self::$timers[$name] = microtime(true);
        }
        
        public static function stop($name) {
            if (isset(self::$timers[$name])) {
                return microtime(true) - self::$timers[$name];
            }
            return 0;
        }
    }
}

class MockBackgroundJobContext {
    private $job_id;
    private $data;
    
    public function __construct($job_id, $data) {
        $this->job_id = $job_id;
        $this->data = $data;
    }
    
    public function get_job_id() {
        return $this->job_id;
    }
    
    public function get_data() {
        return $this->data;
    }
}

// Replace the actual classes with mocks
if (!class_exists('NuclearEngagement\Core\JobStatus')) {
    class_alias('MockJobStatus', 'NuclearEngagement\Core\JobStatus');
}
if (!class_exists('NuclearEngagement\Core\PerformanceMonitor')) {
    class_alias('MockPerformanceMonitor', 'NuclearEngagement\Core\PerformanceMonitor');
}
if (!class_exists('NuclearEngagement\Core\BackgroundJobContext')) {
    class_alias('MockBackgroundJobContext', 'NuclearEngagement\Core\BackgroundJobContext');
}

// Set up global wpdb mock
$GLOBALS['wpdb'] = new MockWpdb();

require_once __DIR__ . '/../nuclear-engagement/inc/Core/JobQueue.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Core/JobHandler.php';

class JobQueueHandlerTest extends TestCase {
    
    private $originalGlobals;
    
    protected function setUp(): void {
        // Store original globals
        $this->originalGlobals = [
            'wpdb' => $GLOBALS['wpdb'] ?? null
        ];
        
        // Set up fresh mock database
        $GLOBALS['wpdb'] = new MockWpdb();
        
        // Reset mock classes
        MockJobStatus::$statuses = [];
        MockJobStatus::$retries = [];
        MockPerformanceMonitor::$timers = [];
        
        // Reset static properties using reflection
        $queueReflection = new ReflectionClass(\NuclearEngagement\Core\JobQueue::class);
        $queueProperty = $queueReflection->getProperty('job_queue');
        $queueProperty->setAccessible(true);
        $queueProperty->setValue([]);
        
        $handlerReflection = new ReflectionClass(\NuclearEngagement\Core\JobHandler::class);
        $handlerProperty = $handlerReflection->getProperty('job_handlers');
        $handlerProperty->setAccessible(true);
        $handlerProperty->setValue([]);
    }
    
    protected function tearDown(): void {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
    
    public function testQueueJobCreatesJob() {
        $job_id = \NuclearEngagement\Core\JobQueue::queue_job(
            'test_job',
            ['key' => 'value'],
            5,
            10
        );
        
        $this->assertIsString($job_id);
        $this->assertNotEmpty($job_id);
        
        // Check if store_job was called (via insert query)
        $queries = $GLOBALS['wpdb']->queries;
        $this->assertNotEmpty($queries);
        
        // Should have called store_job which does an insert
        $insertQueries = array_filter($queries, function($query) {
            return $query[0] === 'insert';
        });
        $this->assertNotEmpty($insertQueries);
    }
    
    public function testCancelJobUpdatesDatabase() {
        $job_id = 'test-job-123';
        
        $result = \NuclearEngagement\Core\JobQueue::cancel_job($job_id);
        
        $this->assertTrue($result);
        
        // Check if update query was executed
        $queries = $GLOBALS['wpdb']->queries;
        $updateQueries = array_filter($queries, function($query) {
            return $query[0] === 'update' && $query[3]['job_id'] === 'test-job-123';
        });
        $this->assertNotEmpty($updateQueries);
    }
    
    public function testGetReadyJobsReturnsJobs() {
        $jobs = \NuclearEngagement\Core\JobQueue::get_ready_jobs();
        
        $this->assertIsArray($jobs);
        $this->assertNotEmpty($jobs);
        
        // Check if select query was executed
        $queries = $GLOBALS['wpdb']->queries;
        $selectQueries = array_filter($queries, function($query) {
            return $query[0] === 'select';
        });
        $this->assertNotEmpty($selectQueries);
    }
    
    public function testGetStatisticsReturnsData() {
        $stats = \NuclearEngagement\Core\JobQueue::get_statistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('queued', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('failed', $stats);
    }
    
    public function testJobHandlerRegisterHandler() {
        $handler = function($context) {
            return 'handled';
        };
        
        \NuclearEngagement\Core\JobHandler::register_handler('test_job', $handler);
        
        // Access private property to verify handler was registered
        $reflection = new ReflectionClass(\NuclearEngagement\Core\JobHandler::class);
        $property = $reflection->getProperty('job_handlers');
        $property->setAccessible(true);
        $handlers = $property->getValue();
        
        $this->assertArrayHasKey('test_job', $handlers);
        $this->assertEquals($handler, $handlers['test_job']);
    }
    
    public function testProcessJobWithValidHandler() {
        $handlerCalled = false;
        $handler = function($context) use (&$handlerCalled) {
            $handlerCalled = true;
            return true;
        };
        
        \NuclearEngagement\Core\JobHandler::register_handler('test_job', $handler);
        
        $job = [
            'id' => 'test-job-123',
            'type' => 'test_job',
            'data' => ['test' => 'data'],
            'attempts' => 0
        ];
        
        \NuclearEngagement\Core\JobHandler::process_job($job);
        
        $this->assertTrue($handlerCalled);
        
        // Check job status was updated
        $this->assertArrayHasKey('test-job-123', MockJobStatus::$statuses);
        $this->assertEquals('completed', MockJobStatus::$statuses['test-job-123']['status']);
        $this->assertEquals(100, MockJobStatus::$statuses['test-job-123']['progress']);
    }
    
    public function testProcessJobWithUnregisteredHandler() {
        $job = [
            'id' => 'test-job-123',
            'type' => 'unregistered_job',
            'data' => ['test' => 'data'],
            'attempts' => 0
        ];
        
        \NuclearEngagement\Core\JobHandler::process_job($job);
        
        // Job should be marked for retry
        $this->assertArrayHasKey('test-job-123', MockJobStatus::$statuses);
        $this->assertEquals('retrying', MockJobStatus::$statuses['test-job-123']['status']);
        $this->assertArrayHasKey('test-job-123', MockJobStatus::$retries);
    }
    
    public function testProcessJobWithFailingHandler() {
        $handler = function($context) {
            throw new Exception('Handler failed');
        };
        
        \NuclearEngagement\Core\JobHandler::register_handler('failing_job', $handler);
        
        $job = [
            'id' => 'test-job-123',
            'type' => 'failing_job',
            'data' => ['test' => 'data'],
            'attempts' => 0
        ];
        
        \NuclearEngagement\Core\JobHandler::process_job($job);
        
        // Job should be marked for retry
        $this->assertArrayHasKey('test-job-123', MockJobStatus::$statuses);
        $this->assertEquals('retrying', MockJobStatus::$statuses['test-job-123']['status']);
        $this->assertArrayHasKey('test-job-123', MockJobStatus::$retries);
        $this->assertEquals(1, MockJobStatus::$retries['test-job-123']['attempts']);
    }
    
    public function testProcessJobMaxRetriesReached() {
        $handler = function($context) {
            throw new Exception('Handler always fails');
        };
        
        \NuclearEngagement\Core\JobHandler::register_handler('always_failing_job', $handler);
        
        $job = [
            'id' => 'test-job-123',
            'type' => 'always_failing_job',
            'data' => ['test' => 'data'],
            'attempts' => 3 // Already at max attempts
        ];
        
        \NuclearEngagement\Core\JobHandler::process_job($job);
        
        // Job should be marked as failed
        $this->assertArrayHasKey('test-job-123', MockJobStatus::$statuses);
        $this->assertEquals('failed', MockJobStatus::$statuses['test-job-123']['status']);
        $this->assertArrayNotHasKey('test-job-123', MockJobStatus::$retries);
    }
    
    public function testJobHandlerDefaultHandlers() {
        \NuclearEngagement\Core\JobHandler::register_default_handlers();
        
        // Access private property to verify default handlers were registered
        $reflection = new ReflectionClass(\NuclearEngagement\Core\JobHandler::class);
        $property = $reflection->getProperty('job_handlers');
        $property->setAccessible(true);
        $handlers = $property->getValue();
        
        $this->assertNotEmpty($handlers);
        // Should have at least some default handlers
        $this->assertArrayHasKey('generation', $handlers);
    }
    
    public function testExecuteWithTimeoutMethod() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\JobHandler::class);
        $method = $reflection->getMethod('execute_with_timeout');
        $method->setAccessible(true);
        
        $quickHandler = function() { return 'quick'; };
        
        $result = $method->invoke(null, $quickHandler, [], 1);
        
        $this->assertEquals('quick', $result);
    }
    
    public function testCleanupCompletedJobs() {
        \NuclearEngagement\Core\JobQueue::cleanup_completed_jobs();
        
        // Check if cleanup query was executed
        $queries = $GLOBALS['wpdb']->queries;
        $this->assertNotEmpty($queries);
        
        // Should execute some cleanup query
        $this->assertTrue(count($queries) > 0);
    }
    
    public function testJobQueueStoreJob() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\JobQueue::class);
        $method = $reflection->getMethod('store_job');
        $method->setAccessible(true);
        
        $job = [
            'id' => 'test-job-123',
            'type' => 'test_job',
            'data' => ['key' => 'value'],
            'priority' => 10,
            'attempts' => 0,
            'scheduled' => time(),
            'status' => 'queued'
        ];
        
        $method->invoke(null, $job);
        
        // Check if insert query was executed
        $queries = $GLOBALS['wpdb']->queries;
        $insertQueries = array_filter($queries, function($query) {
            return $query[0] === 'insert';
        });
        $this->assertNotEmpty($insertQueries);
    }
    
    public function testJobHandlerPerformanceMonitoring() {
        $handler = function($context) {
            return true;
        };
        
        \NuclearEngagement\Core\JobHandler::register_handler('monitored_job', $handler);
        
        $job = [
            'id' => 'test-job-123',
            'type' => 'monitored_job',
            'data' => ['test' => 'data'],
            'attempts' => 0
        ];
        
        \NuclearEngagement\Core\JobHandler::process_job($job);
        
        // Check if performance monitoring was started
        $this->assertArrayHasKey('background_job_monitored_job', MockPerformanceMonitor::$timers);
    }
    
    public function testJobHandlerContextPassed() {
        $receivedContext = null;
        $handler = function($context) use (&$receivedContext) {
            $receivedContext = $context;
            return true;
        };
        
        \NuclearEngagement\Core\JobHandler::register_handler('context_job', $handler);
        
        $job = [
            'id' => 'test-job-123',
            'type' => 'context_job',
            'data' => ['test' => 'data'],
            'attempts' => 0
        ];
        
        \NuclearEngagement\Core\JobHandler::process_job($job);
        
        $this->assertNotNull($receivedContext);
        $this->assertInstanceOf('NuclearEngagement\Core\BackgroundJobContext', $receivedContext);
        $this->assertEquals('test-job-123', $receivedContext->get_job_id());
        $this->assertEquals(['test' => 'data'], $receivedContext->get_data());
    }
}