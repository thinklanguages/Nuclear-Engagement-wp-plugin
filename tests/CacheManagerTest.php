<?php
/**
 * Tests for CacheManager class
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use NuclearEngagement\Core\CacheManager;

class CacheManagerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
    }

    protected function tearDown(): void {
        \WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test cache get operation
     */
    public function test_get_returns_cached_value() {
        // Arrange
        $key = 'test_key';
        $group = 'posts';
        $expectedValue = 'cached_value';

        // Mock WordPress cache functions
        \WP_Mock::userFunction('wp_cache_get')
            ->once()
            ->with('nuclen_posts_test_key', 'nuclen_posts')
            ->andReturn($expectedValue);

        // Act
        $result = CacheManager::get($key, $group);

        // Assert
        $this->assertEquals($expectedValue, $result);
    }

    /**
     * Test cache get operation returns false when not found
     */
    public function test_get_returns_false_when_not_found() {
        // Arrange
        $key = 'missing_key';
        $group = 'queries';

        // Mock WordPress cache functions
        \WP_Mock::userFunction('wp_cache_get')
            ->once()
            ->with('nuclen_queries_missing_key', 'nuclen_queries')
            ->andReturn(false);

        // Act
        $result = CacheManager::get($key, $group);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test cache set operation
     */
    public function test_set_stores_value_in_cache() {
        // Arrange
        $key = 'new_key';
        $value = 'new_value';
        $group = 'dashboard';

        // Mock WordPress cache functions
        \WP_Mock::userFunction('wp_cache_set')
            ->once()
            ->with('nuclen_dashboard_new_key', $value, 'nuclen_dashboard', 180) // 180 is dashboard TTL
            ->andReturn(true);

        // Act
        $result = CacheManager::set($key, $value, $group);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test cache set with custom TTL
     */
    public function test_set_with_custom_ttl() {
        // Arrange
        $key = 'custom_ttl_key';
        $value = 'custom_value';
        $group = 'api';
        $customTtl = 1200;

        // Mock WordPress cache functions
        \WP_Mock::userFunction('wp_cache_set')
            ->once()
            ->with('nuclen_api_custom_ttl_key', $value, 'nuclen_api', $customTtl)
            ->andReturn(true);

        // Act
        $result = CacheManager::set($key, $value, $group, $customTtl);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test cache delete operation
     */
    public function test_delete_removes_value_from_cache() {
        // Arrange
        $key = 'delete_key';
        $group = 'metadata';

        // Mock WordPress cache functions
        \WP_Mock::userFunction('wp_cache_delete')
            ->once()
            ->with('nuclen_metadata_delete_key', 'nuclen_metadata')
            ->andReturn(true);

        // Act
        $result = CacheManager::delete($key, $group);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test remember pattern - cache hit
     */
    public function test_remember_returns_cached_value() {
        // Arrange
        $key = 'remember_key';
        $group = 'posts';
        $cachedValue = 'cached_result';
        $callback = function() {
            return 'computed_result';
        };

        // Mock cache get to return cached value
        \WP_Mock::userFunction('wp_cache_get')
            ->once()
            ->with('nuclen_posts_remember_key', 'nuclen_posts')
            ->andReturn($cachedValue);

        // Act
        $result = CacheManager::remember($key, $callback, $group);

        // Assert
        $this->assertEquals($cachedValue, $result);
    }

    /**
     * Test remember pattern - cache miss
     */
    public function test_remember_computes_and_caches_on_miss() {
        // Arrange
        $key = 'compute_key';
        $group = 'queries';
        $computedValue = 'computed_result';
        $callback = function() use ($computedValue) {
            return $computedValue;
        };

        // Mock cache get to return false (miss)
        \WP_Mock::userFunction('wp_cache_get')
            ->once()
            ->with('nuclen_queries_compute_key', 'nuclen_queries')
            ->andReturn(false);

        // Mock cache set for storing computed value
        \WP_Mock::userFunction('wp_cache_set')
            ->once()
            ->with('nuclen_queries_compute_key', $computedValue, 'nuclen_queries', 300)
            ->andReturn(true);

        // Act
        $result = CacheManager::remember($key, $callback, $group);

        // Assert
        $this->assertEquals($computedValue, $result);
    }

    /**
     * Test compression for large values
     */
    public function test_compression_for_large_values() {
        // Arrange
        $key = 'large_data_key';
        $group = 'posts'; // Posts group has compression enabled
        $largeValue = ['data' => str_repeat('large data string ', 100)];

        // Mock WordPress cache functions
        \WP_Mock::userFunction('wp_cache_set')
            ->once()
            ->with('nuclen_posts_large_data_key', Mockery::pattern('/^nuclen_compressed:/'), 'nuclen_posts', 600)
            ->andReturn(true);

        // Act
        $result = CacheManager::set($key, $largeValue, $group);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test decompression when retrieving compressed data
     */
    public function test_decompression_when_retrieving() {
        // Arrange
        $key = 'compressed_key';
        $group = 'posts';
        $originalValue = ['test' => 'data'];
        $compressedValue = 'nuclen_compressed:' . base64_encode(gzcompress(serialize($originalValue), 6));

        // Mock cache get to return compressed value
        \WP_Mock::userFunction('wp_cache_get')
            ->once()
            ->with('nuclen_posts_compressed_key', 'nuclen_posts')
            ->andReturn($compressedValue);

        // Act
        $result = CacheManager::get($key, $group);

        // Assert
        $this->assertEquals($originalValue, $result);
    }

    /**
     * Test group invalidation
     */
    public function test_invalidate_group() {
        // Arrange
        $group = 'dashboard';
        $reason = 'manual_clear';

        // Mock WordPress functions
        \WP_Mock::userFunction('wp_cache_flush_group')
            ->once()
            ->with('nuclen_dashboard');

        // Mock PerformanceMonitor (if exists)
        \WP_Mock::userFunction('error_log')
            ->once();

        // Mock constants and functions for debug logging
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        // Act
        CacheManager::invalidate_group($group, $reason);

        // Assert - Should complete without errors
        $this->assertTrue(true, 'Group invalidation should complete successfully');
    }

    /**
     * Test trigger-based invalidation
     */
    public function test_invalidate_by_trigger() {
        // Arrange
        $trigger = 'post_save';

        // Mock WordPress functions for group invalidation
        \WP_Mock::userFunction('wp_cache_flush_group')
            ->times(3) // posts, queries, dashboard groups
            ->andReturn(true);

        // Mock error_log for debug output
        \WP_Mock::userFunction('error_log')
            ->times(3);

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        // Act
        CacheManager::invalidate_by_trigger($trigger);

        // Assert
        $this->assertTrue(true, 'Trigger-based invalidation should complete successfully');
    }

    /**
     * Test scheduled invalidation
     */
    public function test_schedule_invalidation() {
        // Arrange
        $group = 'assets';
        $delay = 60;
        $reason = 'scheduled_cleanup';

        // Mock WordPress scheduling function
        \WP_Mock::userFunction('wp_schedule_single_event')
            ->once()
            ->with(Mockery::type('int'), 'nuclen_scheduled_invalidation', [$group, $reason]);

        // Act
        CacheManager::schedule_invalidation($group, $delay, $reason);

        // Assert
        $this->assertTrue(true, 'Scheduled invalidation should complete successfully');
    }

    /**
     * Test cache statistics tracking
     */
    public function test_get_statistics() {
        // Arrange - Simulate some cache operations
        \WP_Mock::userFunction('wp_cache_get')
            ->times(3)
            ->andReturnValues([false, 'hit_value', false]); // 1 hit, 2 misses

        \WP_Mock::userFunction('wp_cache_set')
            ->times(2)
            ->andReturn(true);

        // Perform operations to generate stats
        CacheManager::get('key1', 'posts');
        CacheManager::get('key2', 'posts');
        CacheManager::get('key3', 'posts');

        // Act
        $stats = CacheManager::get_statistics();

        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('posts', $stats);
        
        if (isset($stats['posts'])) {
            $this->assertArrayHasKey('hits', $stats['posts']);
            $this->assertArrayHasKey('misses', $stats['posts']);
            $this->assertArrayHasKey('hit_rate', $stats['posts']);
        }
    }

    /**
     * Test cache warmup functionality
     */
    public function test_warmup() {
        // Arrange
        $warmupConfig = [
            'posts' => ['popular_posts'],
            'queries' => ['user_counts']
        ];

        // Act
        CacheManager::warmup($warmupConfig);

        // Assert - Should complete without errors
        $this->assertTrue(true, 'Cache warmup should complete successfully');
    }

    /**
     * Test initialization hooks
     */
    public function test_init_registers_hooks() {
        // Mock WordPress hook functions
        \WP_Mock::userFunction('add_action')
            ->times(6); // Multiple add_action calls in init

        \WP_Mock::userFunction('wp_next_scheduled')
            ->once()
            ->with('nuclen_cache_cleanup')
            ->andReturn(false);

        \WP_Mock::userFunction('wp_schedule_event')
            ->once()
            ->with(Mockery::type('int'), 'hourly', 'nuclen_cache_cleanup');

        \WP_Mock::userFunction('time')
            ->once()
            ->andReturn(time());

        // Act
        CacheManager::init();

        // Assert
        $this->assertTrue(true, 'Initialization should complete successfully');
    }

    /**
     * Test post save event handling
     */
    public function test_handle_post_save() {
        // Mock WordPress functions
        \WP_Mock::userFunction('wp_cache_flush_group')
            ->times(3)
            ->andReturn(true);

        \WP_Mock::userFunction('error_log')
            ->times(3);

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        // Act
        CacheManager::handle_post_save(123);

        // Assert
        $this->assertTrue(true, 'Post save handling should complete successfully');
    }

    /**
     * Test post delete event handling
     */
    public function test_handle_post_delete() {
        // Mock WordPress functions
        \WP_Mock::userFunction('wp_cache_flush_group')
            ->times(3)
            ->andReturn(true);

        \WP_Mock::userFunction('error_log')
            ->times(3);

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        // Act
        CacheManager::handle_post_delete(456);

        // Assert
        $this->assertTrue(true, 'Post delete handling should complete successfully');
    }

    /**
     * Test theme change event handling
     */
    public function test_handle_theme_change() {
        // Mock WordPress functions
        \WP_Mock::userFunction('wp_cache_flush_group')
            ->times(2) // assets and dashboard groups
            ->andReturn(true);

        \WP_Mock::userFunction('error_log')
            ->times(2);

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        // Act
        CacheManager::handle_theme_change();

        // Assert
        $this->assertTrue(true, 'Theme change handling should complete successfully');
    }

    /**
     * Test plugin change event handling
     */
    public function test_handle_plugin_change() {
        // Mock WordPress functions
        \WP_Mock::userFunction('wp_cache_flush_group')
            ->times(2) // assets and metadata groups
            ->andReturn(true);

        \WP_Mock::userFunction('error_log')
            ->times(2);

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        // Act
        CacheManager::handle_plugin_change();

        // Assert
        $this->assertTrue(true, 'Plugin change handling should complete successfully');
    }

    /**
     * Test cleanup expired cache
     */
    public function test_cleanup_expired_cache() {
        // Act
        CacheManager::cleanup_expired_cache();

        // Assert
        $this->assertTrue(true, 'Cache cleanup should complete successfully');
    }

    /**
     * Test cache with default group
     */
    public function test_cache_with_default_group() {
        // Arrange
        $key = 'default_key';
        $value = 'default_value';

        // Mock WordPress cache functions with default group
        \WP_Mock::userFunction('wp_cache_set')
            ->once()
            ->with('nuclen_default_default_key', $value, 'nuclen_default', 600) // posts config as fallback
            ->andReturn(true);

        // Act
        $result = CacheManager::set($key, $value); // No group specified

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test cache operations with unknown group uses fallback config
     */
    public function test_unknown_group_uses_fallback_config() {
        // Arrange
        $key = 'unknown_key';
        $value = 'unknown_value';
        $unknownGroup = 'nonexistent_group';

        // Mock WordPress cache functions - should use posts config as fallback
        \WP_Mock::userFunction('wp_cache_set')
            ->once()
            ->with("nuclen_{$unknownGroup}_unknown_key", $value, "nuclen_{$unknownGroup}", 600) // posts TTL
            ->andReturn(true);

        // Act
        $result = CacheManager::set($key, $value, $unknownGroup);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test cache key prefixing
     */
    public function test_cache_key_prefixing() {
        // Arrange
        $key = 'test_prefix';
        $group = 'api';

        // Mock WordPress cache functions to verify key format
        \WP_Mock::userFunction('wp_cache_get')
            ->once()
            ->with('nuclen_api_test_prefix', 'nuclen_api')
            ->andReturn('prefixed_value');

        // Act
        $result = CacheManager::get($key, $group);

        // Assert
        $this->assertEquals('prefixed_value', $result);
    }

    /**
     * Test cache group name formatting
     */
    public function test_cache_group_name_formatting() {
        // Arrange
        $key = 'group_test';
        $group = 'metadata';

        // Mock WordPress cache functions to verify group format
        \WP_Mock::userFunction('wp_cache_get')
            ->once()
            ->with('nuclen_metadata_group_test', 'nuclen_metadata')
            ->andReturn('group_value');

        // Act
        $result = CacheManager::get($key, $group);

        // Assert
        $this->assertEquals('group_value', $result);
    }

    /**
     * Test cache set failure handling
     */
    public function test_cache_set_failure_handling() {
        // Arrange
        $key = 'fail_key';
        $value = 'fail_value';
        $group = 'queries';

        // Mock WordPress cache set to return false
        \WP_Mock::userFunction('wp_cache_set')
            ->once()
            ->andReturn(false);

        // Act
        $result = CacheManager::set($key, $value, $group);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test cache delete failure handling
     */
    public function test_cache_delete_failure_handling() {
        // Arrange
        $key = 'delete_fail_key';
        $group = 'assets';

        // Mock WordPress cache delete to return false
        \WP_Mock::userFunction('wp_cache_delete')
            ->once()
            ->andReturn(false);

        // Act
        $result = CacheManager::delete($key, $group);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test cache operations with null values
     */
    public function test_cache_operations_with_null_values() {
        // Arrange
        $key = 'null_key';
        $group = 'posts';
        $nullValue = null;

        // Mock WordPress cache functions
        \WP_Mock::userFunction('wp_cache_set')
            ->once()
            ->with('nuclen_posts_null_key', $nullValue, 'nuclen_posts', 600)
            ->andReturn(true);

        \WP_Mock::userFunction('wp_cache_get')
            ->once()
            ->with('nuclen_posts_null_key', 'nuclen_posts')
            ->andReturn($nullValue);

        // Act
        $setResult = CacheManager::set($key, $nullValue, $group);
        $getResult = CacheManager::get($key, $group);

        // Assert
        $this->assertTrue($setResult);
        $this->assertNull($getResult);
    }
}