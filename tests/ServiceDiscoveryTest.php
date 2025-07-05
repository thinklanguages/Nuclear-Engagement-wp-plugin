<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('NUCLEN_PLUGIN_DIR')) {
    define('NUCLEN_PLUGIN_DIR', __DIR__ . '/../nuclear-engagement/');
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// Mock WordPress functions for service discovery
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        return isset($GLOBALS['wp_scheduled_events'][$hook]) ? time() + 60 : false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        $GLOBALS['wp_scheduled_events'][$hook] = compact('timestamp', 'recurrence', 'args');
        return true;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        return $GLOBALS['wp_cache'][$group][$key] ?? false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        $GLOBALS['wp_cache'][$group][$key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_flush_group')) {
    function wp_cache_flush_group($group) {
        unset($GLOBALS['wp_cache'][$group]);
        return true;
    }
}

// Mock classes
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

class MockErrorRecovery {
    public static $errors = [];
    
    public static function addErrorContext($message, $context = [], $level = 'error') {
        self::$errors[] = compact('message', 'context', 'level');
    }
}

class MockServiceContainer {
    public static $bindings = [];
    public static $instances = [];
    public static $interfaces = [];
    
    public static function bound($id) {
        return isset(self::$bindings[$id]) || isset(self::$instances[$id]);
    }
    
    public static function resolve($id) {
        if (isset(self::$instances[$id])) {
            return self::$instances[$id];
        }
        
        if (isset(self::$bindings[$id])) {
            $factory = self::$bindings[$id];
            $instance = is_callable($factory) ? call_user_func($factory) : new $factory();
            return $instance;
        }
        
        if (class_exists($id)) {
            return new $id();
        }
        
        throw new Exception("Service not found: $id");
    }
    
    public static function singleton($id, $factory) {
        self::$bindings[$id] = $factory;
    }
    
    public static function bind($id, $factory) {
        self::$bindings[$id] = $factory;
    }
    
    public static function interface($interface, $implementation) {
        self::$interfaces[$interface] = $implementation;
    }
    
    public static function getServices() {
        return [
            'bindings' => array_keys(self::$bindings),
            'instances' => array_keys(self::$instances)
        ];
    }
}

// Mock test service classes
class TestService {
    public function healthCheck() {
        return ['status' => 'healthy', 'message' => 'Test service is operational'];
    }
}

class TestServiceWithDependency {
    private $dependency;
    
    public function __construct(TestService $dependency) {
        $this->dependency = $dependency;
    }
}

class TestServiceUnhealthy {
    public function healthCheck() {
        throw new Exception('Service is down');
    }
}

// Replace the actual classes with mocks
if (!class_exists('NuclearEngagement\Core\PerformanceMonitor')) {
    class_alias('MockPerformanceMonitor', 'NuclearEngagement\Core\PerformanceMonitor');
}
if (!class_exists('NuclearEngagement\Core\ErrorRecovery')) {
    class_alias('MockErrorRecovery', 'NuclearEngagement\Core\ErrorRecovery');
}
if (!class_exists('NuclearEngagement\Core\ServiceContainer')) {
    class_alias('MockServiceContainer', 'NuclearEngagement\Core\ServiceContainer');
}

require_once __DIR__ . '/../nuclear-engagement/inc/Core/ServiceDiscovery.php';

class ServiceDiscoveryTest extends TestCase {
    
    private $originalGlobals;
    private $testDir;
    
    protected function setUp(): void {
        // Store original globals
        $this->originalGlobals = [
            'wp_scheduled_events' => $GLOBALS['wp_scheduled_events'] ?? [],
            'wp_cache' => $GLOBALS['wp_cache'] ?? []
        ];
        
        // Reset globals
        $GLOBALS['wp_scheduled_events'] = [];
        $GLOBALS['wp_cache'] = [];
        
        // Reset mock classes
        MockPerformanceMonitor::$timers = [];
        MockErrorRecovery::$errors = [];
        MockServiceContainer::$bindings = [];
        MockServiceContainer::$instances = [];
        MockServiceContainer::$interfaces = [];
        
        // Create temporary test directory structure
        $this->testDir = sys_get_temp_dir() . '/nuclen_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
        mkdir($this->testDir . '/Services', 0777, true);
        
        // Create test service files
        $this->createTestServiceFile();
        
        // Reset static properties using reflection
        $reflection = new ReflectionClass(\NuclearEngagement\Core\ServiceDiscovery::class);
        
        $discoveredServices = $reflection->getProperty('discovered_services');
        $discoveredServices->setAccessible(true);
        $discoveredServices->setValue([]);
        
        $serviceProviders = $reflection->getProperty('service_providers');
        $serviceProviders->setAccessible(true);
        $serviceProviders->setValue([]);
        
        $healthStatus = $reflection->getProperty('health_status');
        $healthStatus->setAccessible(true);
        $healthStatus->setValue([]);
    }
    
    protected function tearDown(): void {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        
        // Clean up test directory
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }
    
    private function createTestServiceFile() {
        $serviceContent = '<?php
namespace NuclearEngagement\Services;

/**
 * Test service for discovery.
 * @singleton
 * @tag test
 * @priority 5
 */
class DiscoverableService {
    public function healthCheck() {
        return ["status" => "healthy", "message" => "Service is running"];
    }
}';
        
        file_put_contents($this->testDir . '/Services/DiscoverableService.php', $serviceContent);
    }
    
    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    public function testInitSchedulesHealthChecks() {
        \NuclearEngagement\Core\ServiceDiscovery::init();
        
        $this->assertArrayHasKey('nuclen_service_health_check', $GLOBALS['wp_scheduled_events']);
        $this->assertEquals('hourly', $GLOBALS['wp_scheduled_events']['nuclen_service_health_check']['recurrence']);
    }
    
    public function testDiscoverServicesWithEmptyDirectories() {
        $services = \NuclearEngagement\Core\ServiceDiscovery::discoverServices([]);
        
        $this->assertIsArray($services);
    }
    
    public function testDiscoverServicesWithValidDirectory() {
        $services = \NuclearEngagement\Core\ServiceDiscovery::discoverServices([$this->testDir]);
        
        $this->assertIsArray($services);
        // The service discovery should find our test service file
        $this->assertNotEmpty($services);
    }
    
    public function testDiscoverServicesUsesCache() {
        $directories = [$this->testDir];
        
        // First call should populate cache
        $services1 = \NuclearEngagement\Core\ServiceDiscovery::discoverServices($directories);
        
        // Set a different value in cache
        $cache_key = 'nuclen_discovered_services_' . hash('xxh3', implode('|', $directories));
        wp_cache_set($cache_key, ['cached' => 'value'], 'nuclen_services', HOUR_IN_SECONDS);
        
        // Second call should return cached value
        $services2 = \NuclearEngagement\Core\ServiceDiscovery::discoverServices($directories);
        
        $this->assertEquals(['cached' => 'value'], $services2);
    }
    
    public function testRegisterProvider() {
        $providerCalled = false;
        $provider = function() use (&$providerCalled) {
            $providerCalled = true;
        };
        
        \NuclearEngagement\Core\ServiceDiscovery::registerProvider('test_provider', $provider);
        \NuclearEngagement\Core\ServiceDiscovery::loadProviders();
        
        $this->assertTrue($providerCalled);
    }
    
    public function testLoadProvidersHandlesExceptions() {
        $provider = function() {
            throw new Exception('Provider failed');
        };
        
        \NuclearEngagement\Core\ServiceDiscovery::registerProvider('failing_provider', $provider);
        \NuclearEngagement\Core\ServiceDiscovery::loadProviders();
        
        // Should have recorded the error
        $this->assertNotEmpty(MockErrorRecovery::$errors);
        $this->assertStringContainsString('Failed to load service provider', MockErrorRecovery::$errors[0]['message']);
    }
    
    public function testCheckServiceHealthForHealthyService() {
        MockServiceContainer::$instances['test_service'] = new TestService();
        
        $health = \NuclearEngagement\Core\ServiceDiscovery::checkServiceHealth('test_service');
        
        $this->assertEquals('healthy', $health['status']);
        $this->assertEquals('Test service is operational', $health['message']);
        $this->assertArrayHasKey('timestamp', $health);
    }
    
    public function testCheckServiceHealthForUnhealthyService() {
        MockServiceContainer::$instances['unhealthy_service'] = new TestServiceUnhealthy();
        
        $health = \NuclearEngagement\Core\ServiceDiscovery::checkServiceHealth('unhealthy_service');
        
        $this->assertEquals('unhealthy', $health['status']);
        $this->assertEquals('Service is down', $health['message']);
    }
    
    public function testCheckServiceHealthForNonExistentService() {
        $health = \NuclearEngagement\Core\ServiceDiscovery::checkServiceHealth('non_existent');
        
        $this->assertEquals('unknown', $health['status']);
        $this->assertEquals('Service not found', $health['message']);
    }
    
    public function testCheckServiceHealthForServiceWithoutHealthCheck() {
        // Create a simple object without healthCheck method
        MockServiceContainer::$instances['simple_service'] = new stdClass();
        
        $health = \NuclearEngagement\Core\ServiceDiscovery::checkServiceHealth('simple_service');
        
        $this->assertEquals('healthy', $health['status']);
        $this->assertEquals('Service is available', $health['message']);
    }
    
    public function testGetHealthStatus() {
        // Add some health status data
        MockServiceContainer::$instances['test_service'] = new TestService();
        \NuclearEngagement\Core\ServiceDiscovery::checkServiceHealth('test_service');
        
        $status = \NuclearEngagement\Core\ServiceDiscovery::getHealthStatus();
        
        $this->assertArrayHasKey('test_service', $status);
        $this->assertEquals('healthy', $status['test_service']['status']);
        $this->assertArrayHasKey('last_check', $status['test_service']);
        $this->assertArrayHasKey('message', $status['test_service']);
    }
    
    public function testRunHealthChecks() {
        MockServiceContainer::$instances['test_service'] = new TestService();
        
        \NuclearEngagement\Core\ServiceDiscovery::run_health_checks();
        
        $status = \NuclearEngagement\Core\ServiceDiscovery::getHealthStatus();
        $this->assertArrayHasKey('test_service', $status);
    }
    
    public function testGetDependencyGraph() {
        // Add some discovered services with dependencies
        $reflection = new ReflectionClass(\NuclearEngagement\Core\ServiceDiscovery::class);
        $discoveredServices = $reflection->getProperty('discovered_services');
        $discoveredServices->setAccessible(true);
        $discoveredServices->setValue([
            'ServiceA' => ['dependencies' => ['ServiceB', 'ServiceC']],
            'ServiceB' => ['dependencies' => []],
            'ServiceC' => ['dependencies' => ['ServiceB']]
        ]);
        
        $graph = \NuclearEngagement\Core\ServiceDiscovery::getDependencyGraph();
        
        $this->assertArrayHasKey('ServiceA', $graph);
        $this->assertEquals(['ServiceB', 'ServiceC'], $graph['ServiceA']);
        $this->assertEquals([], $graph['ServiceB']);
        $this->assertEquals(['ServiceB'], $graph['ServiceC']);
    }
    
    public function testValidateDependencies() {
        // Set up discovered services with some missing dependencies
        $reflection = new ReflectionClass(\NuclearEngagement\Core\ServiceDiscovery::class);
        $discoveredServices = $reflection->getProperty('discovered_services');
        $discoveredServices->setAccessible(true);
        $discoveredServices->setValue([
            'ServiceA' => ['dependencies' => ['ServiceB', 'NonExistentService']],
            'ServiceB' => ['dependencies' => ['AnotherMissingService']]
        ]);
        
        $missing = \NuclearEngagement\Core\ServiceDiscovery::validateDependencies();
        
        $this->assertArrayHasKey('ServiceA', $missing);
        $this->assertContains('NonExistentService', $missing['ServiceA']);
        $this->assertArrayHasKey('ServiceB', $missing);
        $this->assertContains('AnotherMissingService', $missing['ServiceB']);
    }
    
    public function testSetAutoDiscovery() {
        \NuclearEngagement\Core\ServiceDiscovery::setAutoDiscovery(false);
        
        // When auto-discovery is disabled, init should not schedule events
        \NuclearEngagement\Core\ServiceDiscovery::init();
        $this->assertArrayNotHasKey('nuclen_service_health_check', $GLOBALS['wp_scheduled_events']);
        
        // Re-enable for other tests
        \NuclearEngagement\Core\ServiceDiscovery::setAutoDiscovery(true);
    }
    
    public function testClearCache() {
        // Add something to cache
        wp_cache_set('test_key', 'test_value', 'nuclen_services');
        
        \NuclearEngagement\Core\ServiceDiscovery::clearCache();
        
        // Cache should be cleared
        $this->assertFalse(wp_cache_get('test_key', 'nuclen_services'));
    }
    
    public function testAutoRegister() {
        // This test is more complex as it involves file scanning and class loading
        // We'll test the basic functionality
        
        \NuclearEngagement\Core\ServiceDiscovery::autoRegister([$this->testDir]);
        
        // The method should complete without errors
        $this->assertTrue(true);
    }
    
    public function testScanDirectoryWithNonExistentDirectory() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\ServiceDiscovery::class);
        $method = $reflection->getMethod('scanDirectory');
        $method->setAccessible(true);
        
        $services = $method->invoke(null, '/non/existent/directory');
        
        $this->assertIsArray($services);
        $this->assertEmpty($services);
    }
    
    public function testAnalyzeFileWithInvalidFile() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\ServiceDiscovery::class);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke(null, '/non/existent/file.php');
        
        $this->assertNull($result);
    }
    
    public function testAnalyzeFileWithNonPhpFile() {
        $txtFile = $this->testDir . '/test.txt';
        file_put_contents($txtFile, 'This is not PHP');
        
        $reflection = new ReflectionClass(\NuclearEngagement\Core\ServiceDiscovery::class);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke(null, $txtFile);
        
        $this->assertNull($result);
    }
    
    public function testExtractDependencies() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\ServiceDiscovery::class);
        $method = $reflection->getMethod('extractDependencies');
        $method->setAccessible(true);
        
        $classReflection = new ReflectionClass(TestServiceWithDependency::class);
        $dependencies = $method->invoke(null, $classReflection);
        
        $this->assertContains('TestService', $dependencies);
    }
    
    public function testExtractMetadata() {
        $reflection = new ReflectionClass(\NuclearEngagement\Core\ServiceDiscovery::class);
        $method = $reflection->getMethod('extractMetadata');
        $method->setAccessible(true);
        
        // Create a temporary class with metadata
        $classContent = '<?php
/**
 * Test class with metadata
 * @singleton
 * @lazy
 * @priority 5
 * @tag test
 * @tag service
 */
class TestMetadataClass {
    public function register_hooks() {}
}';
        
        eval(substr($classContent, 5)); // Remove <?php
        
        $classReflection = new ReflectionClass('TestMetadataClass');
        $metadata = $method->invoke(null, $classReflection, $classContent);
        
        $this->assertTrue($metadata['singleton']);
        $this->assertTrue($metadata['lazy']);
        $this->assertEquals(5, $metadata['priority']);
        $this->assertContains('test', $metadata['tags']);
        $this->assertContains('service', $metadata['tags']);
        $this->assertContains('hook_provider', $metadata['tags']);
    }
}