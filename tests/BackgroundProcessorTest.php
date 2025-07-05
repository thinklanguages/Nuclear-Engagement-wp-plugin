<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions for background processing
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        $GLOBALS['wp_scheduled_events'][$hook] = compact('timestamp', 'recurrence', 'args');
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        return isset($GLOBALS['wp_scheduled_events'][$hook]) ? time() + 60 : false;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['wp_filters'][$hook][] = compact('callback', 'priority', 'accepted_args');
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['wp_transients'][$key]['value'] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['wp_transients'][$key] = compact('value', 'expiration');
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['wp_transients'][$key]);
        return true;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

// Mock the dependent classes
class MockJobHandler {
    public static $handlers = [];
    public static $processed_jobs = [];
    
    public static function register_default_handlers() {
        self::$handlers = ['default' => function() {}];
    }
    
    public static function register_handler($type, $handler) {
        self::$handlers[$type] = $handler;
    }
    
    public static function process_job($job) {
        self::$processed_jobs[] = $job;
        return true;
    }
}

class MockJobQueue {
    public static $jobs = [];
    public static $statistics = ['pending' => 0, 'completed' => 0, 'failed' => 0];
    
    public static function queue_job($type, $data = [], $priority = 10, $delay = 0) {
        $job_id = uniqid('job_', true);
        self::$jobs[$job_id] = compact('type', 'data', 'priority', 'delay');
        self::$statistics['pending']++;
        return $job_id;
    }
    
    public static function get_ready_jobs() {
        return array_slice(self::$jobs, 0, 3); // Return max 3 jobs
    }
    
    public static function cancel_job($job_id) {
        if (isset(self::$jobs[$job_id])) {
            unset(self::$jobs[$job_id]);
            return true;
        }
        return false;
    }
    
    public static function cleanup_completed_jobs() {
        // Mock cleanup
    }
    
    public static function get_statistics() {
        return self::$statistics;
    }
}

class MockJobStatus {
    public static $statuses = [];
    
    public static function get_job_status($job_id) {
        return self::$statuses[$job_id] ?? null;
    }
    
    public static function update_progress($job_id, $progress, $message = '') {
        self::$statuses[$job_id] = compact('progress', 'message');
    }
}

// Replace the actual classes with mocks
if (!class_exists('NuclearEngagement\Core\JobHandler')) {
    class_alias('MockJobHandler', 'NuclearEngagement\Core\JobHandler');
}
if (!class_exists('NuclearEngagement\Core\JobQueue')) {
    class_alias('MockJobQueue', 'NuclearEngagement\Core\JobQueue');
}
if (!class_exists('NuclearEngagement\Core\JobStatus')) {
    class_alias('MockJobStatus', 'NuclearEngagement\Core\JobStatus');
}

require_once __DIR__ . '/../nuclear-engagement/inc/Core/BackgroundProcessor.php';

class BackgroundProcessorTest extends TestCase {
    
    private $originalGlobals;
    
    protected function setUp(): void {
        // Store original globals
        $this->originalGlobals = [
            'wp_scheduled_events' => $GLOBALS['wp_scheduled_events'] ?? [],
            'wp_filters' => $GLOBALS['wp_filters'] ?? [],
            'wp_transients' => $GLOBALS['wp_transients'] ?? []
        ];
        
        // Reset globals
        $GLOBALS['wp_scheduled_events'] = [];
        $GLOBALS['wp_filters'] = [];
        $GLOBALS['wp_transients'] = [];
        
        // Reset mock classes
        MockJobHandler::$handlers = [];
        MockJobHandler::$processed_jobs = [];
        MockJobQueue::$jobs = [];
        MockJobQueue::$statistics = ['pending' => 0, 'completed' => 0, 'failed' => 0];
        MockJobStatus::$statuses = [];
    }
    
    protected function tearDown(): void {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
    
    public function testInitSchedulesCronJobs() {
        \NuclearEngagement\Core\BackgroundProcessor::init();
        
        $this->assertArrayHasKey('nuclen_process_background_jobs', $GLOBALS['wp_scheduled_events']);
        $this->assertArrayHasKey('nuclen_cleanup_completed_jobs', $GLOBALS['wp_scheduled_events']);
    }
    
    public function testInitRegistersDefaultHandlers() {
        \NuclearEngagement\Core\BackgroundProcessor::init();
        
        $this->assertNotEmpty(MockJobHandler::$handlers);
        $this->assertArrayHasKey('default', MockJobHandler::$handlers);
    }
    
    public function testInitAddsCronSchedules() {
        \NuclearEngagement\Core\BackgroundProcessor::init();
        
        $this->assertArrayHasKey('cron_schedules', $GLOBALS['wp_filters']);
    }
    
    public function testQueueJobCreatesJob() {
        $job_id = \NuclearEngagement\Core\BackgroundProcessor::queue_job(
            'test_job',
            ['key' => 'value'],
            5,
            10
        );
        
        $this->assertIsString($job_id);
        $this->assertArrayHasKey($job_id, MockJobQueue::$jobs);
        $this->assertEquals('test_job', MockJobQueue::$jobs[$job_id]['type']);
        $this->assertEquals(['key' => 'value'], MockJobQueue::$jobs[$job_id]['data']);
        $this->assertEquals(5, MockJobQueue::$jobs[$job_id]['priority']);
        $this->assertEquals(10, MockJobQueue::$jobs[$job_id]['delay']);
    }
    
    public function testRegisterHandlerAddsHandler() {
        $handler = function($context) { return 'handled'; };
        
        \NuclearEngagement\Core\BackgroundProcessor::register_handler('custom_job', $handler);
        
        $this->assertArrayHasKey('custom_job', MockJobHandler::$handlers);
        $this->assertEquals($handler, MockJobHandler::$handlers['custom_job']);
    }
    
    public function testGetJobStatusReturnsStatus() {
        $job_id = 'test_job_123';
        MockJobStatus::$statuses[$job_id] = ['progress' => 50, 'message' => 'Processing'];
        
        $status = \NuclearEngagement\Core\BackgroundProcessor::get_job_status($job_id);
        
        $this->assertEquals(['progress' => 50, 'message' => 'Processing'], $status);
    }
    
    public function testGetJobStatusReturnsNullForNonExistentJob() {
        $status = \NuclearEngagement\Core\BackgroundProcessor::get_job_status('non_existent');
        
        $this->assertNull($status);
    }
    
    public function testCancelJobRemovesJob() {
        $job_id = MockJobQueue::queue_job('test_job');
        
        $result = \NuclearEngagement\Core\BackgroundProcessor::cancel_job($job_id);
        
        $this->assertTrue($result);
        $this->assertArrayNotHasKey($job_id, MockJobQueue::$jobs);
    }
    
    public function testCancelJobReturnsFalseForNonExistentJob() {
        $result = \NuclearEngagement\Core\BackgroundProcessor::cancel_job('non_existent');
        
        $this->assertFalse($result);
    }
    
    public function testProcessJobsAcquiresLock() {
        $lock_key = 'nuclen_job_processing_lock';
        
        \NuclearEngagement\Core\BackgroundProcessor::process_jobs();
        
        $this->assertArrayHasKey($lock_key, $GLOBALS['wp_transients']);
    }
    
    public function testProcessJobsSkipsWhenLockExists() {
        $lock_key = 'nuclen_job_processing_lock';
        $GLOBALS['wp_transients'][$lock_key] = ['value' => time(), 'expiration' => 300];
        
        // Add jobs to queue
        MockJobQueue::$jobs = [
            'job1' => ['type' => 'test'],
            'job2' => ['type' => 'test']
        ];
        
        \NuclearEngagement\Core\BackgroundProcessor::process_jobs();
        
        // Jobs should not be processed due to existing lock
        $this->assertEmpty(MockJobHandler::$processed_jobs);
    }
    
    public function testProcessJobsLimitsMaxConcurrentJobs() {
        // Create more jobs than MAX_CONCURRENT_JOBS (3)
        MockJobQueue::$jobs = [
            'job1' => ['type' => 'test'],
            'job2' => ['type' => 'test'],
            'job3' => ['type' => 'test'],
            'job4' => ['type' => 'test'],
            'job5' => ['type' => 'test']
        ];
        
        \NuclearEngagement\Core\BackgroundProcessor::process_jobs();
        
        // Should only process 3 jobs
        $this->assertCount(3, MockJobHandler::$processed_jobs);
    }
    
    public function testUpdateProgressUpdatesJobStatus() {
        $job_id = 'test_job_123';
        
        \NuclearEngagement\Core\BackgroundProcessor::update_progress($job_id, 75, 'Almost done');
        
        $this->assertArrayHasKey($job_id, MockJobStatus::$statuses);
        $this->assertEquals(75, MockJobStatus::$statuses[$job_id]['progress']);
        $this->assertEquals('Almost done', MockJobStatus::$statuses[$job_id]['message']);
    }
    
    public function testGetStatisticsReturnsJobStats() {
        MockJobQueue::$statistics = ['pending' => 5, 'completed' => 10, 'failed' => 2];
        
        $stats = \NuclearEngagement\Core\BackgroundProcessor::get_statistics();
        
        $this->assertEquals(['pending' => 5, 'completed' => 10, 'failed' => 2], $stats);
    }
    
    public function testAcquireLockReturnsFalseWhenLockExists() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\BackgroundProcessor::class);
        $method = $reflection->getMethod('acquire_lock');
        $method->setAccessible(true);
        
        // Set existing lock
        $GLOBALS['wp_transients']['test_lock'] = ['value' => time(), 'expiration' => 300];
        
        $result = $method->invoke(null, 'test_lock', time());
        
        $this->assertFalse($result);
    }
    
    public function testAcquireLockReturnsTrueWhenNoLock() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\BackgroundProcessor::class);
        $method = $reflection->getMethod('acquire_lock');
        $method->setAccessible(true);
        
        $result = $method->invoke(null, 'test_lock', time());
        
        $this->assertTrue($result);
        $this->assertArrayHasKey('test_lock', $GLOBALS['wp_transients']);
    }
    
    public function testReleaseLockDeletesTransient() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\BackgroundProcessor::class);
        $method = $reflection->getMethod('release_lock');
        $method->setAccessible(true);
        
        $lock_value = time();
        $GLOBALS['wp_transients']['test_lock'] = ['value' => $lock_value, 'expiration' => 300];
        
        $method->invoke(null, 'test_lock', $lock_value);
        
        $this->assertArrayNotHasKey('test_lock', $GLOBALS['wp_transients']);
    }
    
    public function testReleaseLockDoesNotDeleteWrongValue() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\BackgroundProcessor::class);
        $method = $reflection->getMethod('release_lock');
        $method->setAccessible(true);
        
        $correct_value = time();
        $wrong_value = $correct_value + 1;
        $GLOBALS['wp_transients']['test_lock'] = ['value' => $correct_value, 'expiration' => 300];
        
        $method->invoke(null, 'test_lock', $wrong_value);
        
        $this->assertArrayHasKey('test_lock', $GLOBALS['wp_transients']);
    }
}

class BackgroundJobContextTest extends TestCase {
    
    public function testConstructorSetsProperties() {
        $job_id = 'test_job_123';
        $data = ['key' => 'value', 'number' => 42];
        
        $context = new \NuclearEngagement\Core\BackgroundJobContext($job_id, $data);
        
        $this->assertEquals($job_id, $context->get_job_id());
        $this->assertEquals($data, $context->get_data());
    }
    
    public function testUpdateProgressCallsBackgroundProcessor() {
        $job_id = 'test_job_123';
        $data = ['test' => 'data'];
        
        $context = new \NuclearEngagement\Core\BackgroundJobContext($job_id, $data);
        $context->update_progress(50, 'Halfway done');
        
        $this->assertArrayHasKey($job_id, MockJobStatus::$statuses);
        $this->assertEquals(50, MockJobStatus::$statuses[$job_id]['progress']);
        $this->assertEquals('Halfway done', MockJobStatus::$statuses[$job_id]['message']);
    }
}