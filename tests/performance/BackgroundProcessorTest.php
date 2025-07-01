<?php

class BackgroundProcessorPerformanceTest extends WP_UnitTestCase {

    private $background_processor;
    private $test_posts = [];

    public function setUp(): void {
        parent::setUp();
        
        // Create test posts for processing
        for ($i = 0; $i < 100; $i++) {
            $this->test_posts[] = $this->factory->post->create([
                'post_title' => "Test Post {$i}",
                'post_content' => str_repeat("This is test content for performance testing. ", 50),
                'post_status' => 'publish'
            ]);
        }
        
        // Mock plugin settings
        update_option('nuclen_api_key', 'test_api_key_123');
        update_option('nuclen_batch_size', 10);
        update_option('nuclen_processing_timeout', 30);
    }

    public function tearDown(): void {
        // Clean up test data
        foreach ($this->test_posts as $post_id) {
            wp_delete_post($post_id, true);
        }
        
        delete_option('nuclen_api_key');
        delete_option('nuclen_batch_size');
        delete_option('nuclen_processing_timeout');
        
        parent::tearDown();
    }

    /**
     * Test background processor memory usage
     */
    public function test_background_processor_memory_usage() {
        $memory_start = memory_get_usage();
        $memory_peak_start = memory_get_peak_usage();

        // Process multiple posts to test memory usage
        foreach (array_slice($this->test_posts, 0, 50) as $post_id) {
            // Simulate background processing
            $this->simulate_background_processing($post_id);
        }

        $memory_end = memory_get_usage();
        $memory_peak_end = memory_get_peak_usage();
        
        $memory_used = $memory_end - $memory_start;
        $memory_peak_increase = $memory_peak_end - $memory_peak_start;

        // Memory usage should be reasonable (less than 50MB for 50 posts)
        $this->assertLessThan(50 * 1024 * 1024, $memory_used, 'Memory usage should be under 50MB');
        $this->assertLessThan(100 * 1024 * 1024, $memory_peak_increase, 'Peak memory increase should be under 100MB');
    }

    /**
     * Test background processor execution time
     */
    public function test_background_processor_execution_time() {
        $start_time = microtime(true);

        // Process batch of posts
        $batch_size = 20;
        foreach (array_slice($this->test_posts, 0, $batch_size) as $post_id) {
            $this->simulate_background_processing($post_id);
        }

        $execution_time = microtime(true) - $start_time;

        // Should process posts efficiently (less than 2 seconds for 20 posts)
        $this->assertLessThan(2.0, $execution_time, 'Batch processing should complete within 2 seconds');
        
        // Average time per post should be reasonable
        $avg_time_per_post = $execution_time / $batch_size;
        $this->assertLessThan(0.1, $avg_time_per_post, 'Average processing time per post should be under 100ms');
    }

    /**
     * Test concurrent background processes
     */
    public function test_concurrent_background_processes() {
        $start_time = microtime(true);
        
        // Simulate multiple concurrent processes
        $processes = [];
        for ($i = 0; $i < 5; $i++) {
            $processes[] = $this->simulate_concurrent_processing($i * 10, 10);
        }

        $execution_time = microtime(true) - $start_time;

        // Concurrent processes should not significantly increase total time
        $this->assertLessThan(3.0, $execution_time, 'Concurrent processes should complete within 3 seconds');
        
        // Verify all processes completed
        foreach ($processes as $process_result) {
            $this->assertTrue($process_result, 'All concurrent processes should complete successfully');
        }
    }

    /**
     * Test batch processing performance
     */
    public function test_batch_processing_performance() {
        $batch_sizes = [5, 10, 20, 50];
        $results = [];

        foreach ($batch_sizes as $batch_size) {
            $start_time = microtime(true);
            $memory_start = memory_get_usage();

            // Process batch
            $this->process_batch($batch_size);

            $execution_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage() - $memory_start;

            $results[$batch_size] = [
                'time' => $execution_time,
                'memory' => $memory_used,
                'time_per_item' => $execution_time / $batch_size
            ];
        }

        // Verify performance scales reasonably with batch size
        foreach ($results as $batch_size => $metrics) {
            $this->assertLessThan(0.05, $metrics['time_per_item'], 
                "Time per item for batch size {$batch_size} should be under 50ms");
            
            $this->assertLessThan(10 * 1024 * 1024, $metrics['memory'], 
                "Memory usage for batch size {$batch_size} should be under 10MB");
        }
    }

    /**
     * Test database query optimization
     */
    public function test_database_query_optimization() {
        global $wpdb;
        
        $query_count_start = $wpdb->num_queries;

        // Process posts and monitor database queries
        foreach (array_slice($this->test_posts, 0, 20) as $post_id) {
            $this->simulate_database_operations($post_id);
        }

        $query_count = $wpdb->num_queries - $query_count_start;

        // Should use efficient queries (not too many per post)
        $queries_per_post = $query_count / 20;
        $this->assertLessThan(10, $queries_per_post, 
            'Should use less than 10 database queries per post on average');
    }

    /**
     * Test cache performance
     */
    public function test_cache_performance() {
        // Test cache hit performance
        $cache_key = 'nuclen_test_cache_key';
        $cache_data = ['test' => 'data', 'items' => range(1, 1000)];
        
        // Set cache
        wp_cache_set($cache_key, $cache_data, 'nuclen');
        
        $start_time = microtime(true);
        
        // Multiple cache reads
        for ($i = 0; $i < 100; $i++) {
            $result = wp_cache_get($cache_key, 'nuclen');
            $this->assertNotFalse($result, 'Cache should return data');
        }
        
        $cache_read_time = microtime(true) - $start_time;
        
        // Cache reads should be very fast
        $this->assertLessThan(0.01, $cache_read_time, 
            'Cache reads should complete in under 10ms');
    }

    /**
     * Test API rate limiting performance
     */
    public function test_api_rate_limiting_performance() {
        $start_time = microtime(true);
        
        // Simulate rapid API calls
        $api_calls = 0;
        $rate_limited_calls = 0;
        
        for ($i = 0; $i < 100; $i++) {
            $api_calls++;
            
            if ($this->simulate_api_call_with_rate_limiting()) {
                // Call succeeded
                continue;
            } else {
                // Call was rate limited
                $rate_limited_calls++;
            }
        }
        
        $execution_time = microtime(true) - $start_time;
        
        // Rate limiting should be efficient
        $this->assertLessThan(1.0, $execution_time, 
            'Rate limiting checks should complete quickly');
        
        // Some calls should be rate limited under load
        $this->assertGreaterThan(0, $rate_limited_calls, 
            'Rate limiting should activate under high load');
    }

    /**
     * Test large content processing
     */
    public function test_large_content_processing() {
        // Create post with large content
        $large_content = str_repeat("This is a very long piece of content for testing large content processing performance. ", 1000);
        $large_post_id = $this->factory->post->create([
            'post_title' => 'Large Content Test Post',
            'post_content' => $large_content,
            'post_status' => 'publish'
        ]);

        $start_time = microtime(true);
        $memory_start = memory_get_usage();

        // Process large content
        $this->simulate_background_processing($large_post_id);

        $execution_time = microtime(true) - $start_time;
        $memory_used = memory_get_usage() - $memory_start;

        // Should handle large content efficiently
        $this->assertLessThan(5.0, $execution_time, 
            'Large content processing should complete within 5 seconds');
        
        $this->assertLessThan(20 * 1024 * 1024, $memory_used, 
            'Large content processing should use less than 20MB memory');

        // Clean up
        wp_delete_post($large_post_id, true);
    }

    // Helper methods

    private function simulate_background_processing($post_id) {
        // Simulate processing operations
        $post = get_post($post_id);
        if (!$post) return false;

        // Simulate content analysis
        $content_length = strlen($post->post_content);
        $word_count = str_word_count($post->post_content);
        
        // Simulate API processing time
        usleep(rand(1000, 5000)); // 1-5ms
        
        // Simulate database updates
        update_post_meta($post_id, 'nuclen_processed', time());
        update_post_meta($post_id, 'nuclen_word_count', $word_count);
        
        return true;
    }

    private function simulate_concurrent_processing($start_index, $count) {
        $post_batch = array_slice($this->test_posts, $start_index, $count);
        
        foreach ($post_batch as $post_id) {
            $this->simulate_background_processing($post_id);
        }
        
        return true;
    }

    private function process_batch($batch_size) {
        $batch = array_slice($this->test_posts, 0, $batch_size);
        
        foreach ($batch as $post_id) {
            $this->simulate_background_processing($post_id);
        }
    }

    private function simulate_database_operations($post_id) {
        // Simulate typical database operations during processing
        $post = get_post($post_id);
        $meta = get_post_meta($post_id);
        
        update_post_meta($post_id, 'nuclen_processing_status', 'active');
        update_post_meta($post_id, 'nuclen_last_processed', current_time('mysql'));
        
        // Simulate some queries
        global $wpdb;
        $wpdb->get_results($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id = %d",
            'nuclen_processed',
            $post_id
        ));
    }

    private function simulate_api_call_with_rate_limiting() {
        // Simulate rate limiting logic
        static $call_times = [];
        $current_time = microtime(true);
        
        // Remove old calls (older than 1 minute)
        $call_times = array_filter($call_times, function($time) use ($current_time) {
            return ($current_time - $time) < 60;
        });
        
        // Check rate limit (max 50 calls per minute)
        if (count($call_times) >= 50) {
            return false; // Rate limited
        }
        
        $call_times[] = $current_time;
        return true; // Call allowed
    }
}