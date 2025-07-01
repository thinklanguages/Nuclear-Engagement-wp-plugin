<?php

class DatabasePerformanceTest extends WP_UnitTestCase {

    private $test_posts = [];
    private $test_users = [];

    public function setUp(): void {
        parent::setUp();
        
        // Create test data
        for ($i = 0; $i < 1000; $i++) {
            $this->test_posts[] = $this->factory->post->create([
                'post_title' => "Performance Test Post {$i}",
                'post_content' => str_repeat("Test content for database performance testing. ", 20),
                'post_status' => 'publish'
            ]);
        }
        
        for ($i = 0; $i < 100; $i++) {
            $this->test_users[] = $this->factory->user->create([
                'user_login' => "testuser{$i}",
                'user_email' => "testuser{$i}@example.com"
            ]);
        }
    }

    public function tearDown(): void {
        // Clean up test data
        foreach ($this->test_posts as $post_id) {
            wp_delete_post($post_id, true);
        }
        
        foreach ($this->test_users as $user_id) {
            wp_delete_user($user_id);
        }
        
        parent::tearDown();
    }

    /**
     * Test bulk post meta operations performance
     */
    public function test_bulk_post_meta_operations() {
        global $wpdb;
        
        $start_time = microtime(true);
        $query_count_start = $wpdb->num_queries;
        
        // Test bulk meta updates
        foreach (array_slice($this->test_posts, 0, 100) as $post_id) {
            update_post_meta($post_id, 'nuclen_quiz_data', json_encode([
                'questions' => range(1, 10),
                'answers' => range(1, 10),
                'created' => time()
            ]));
            update_post_meta($post_id, 'nuclen_summary_data', 'Test summary content');
            update_post_meta($post_id, 'nuclen_processing_status', 'completed');
        }
        
        $execution_time = microtime(true) - $start_time;
        $query_count = $wpdb->num_queries - $query_count_start;
        
        // Performance assertions
        $this->assertLessThan(2.0, $execution_time, 
            'Bulk meta operations should complete within 2 seconds');
        
        $this->assertLessThan(400, $query_count, 
            'Should use reasonable number of queries for bulk operations');
    }

    /**
     * Test complex query performance
     */
    public function test_complex_query_performance() {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Complex query with joins and conditions
        $results = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm1.meta_value as quiz_data, pm2.meta_value as summary_data
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'nuclen_quiz_data'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'nuclen_summary_data'
            WHERE p.post_status = 'publish'
            AND p.post_type = 'post'
            ORDER BY p.post_date DESC
            LIMIT 100
        ");
        
        $execution_time = microtime(true) - $start_time;
        
        // Query should execute quickly
        $this->assertLessThan(0.5, $execution_time, 
            'Complex query should execute within 500ms');
        
        $this->assertNotEmpty($results, 'Query should return results');
        $this->assertLessThanOrEqual(100, count($results), 'Should respect LIMIT clause');
    }

    /**
     * Test index performance
     */
    public function test_index_performance() {
        global $wpdb;
        
        // Test query with index
        $start_time = microtime(true);
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            LIMIT 100
        ", 'nuclen_processing_status'));
        
        $indexed_time = microtime(true) - $start_time;
        
        // Test query without index (full table scan)
        $start_time = microtime(true);
        
        $results2 = $wpdb->get_results("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_value LIKE '%completed%'
            LIMIT 100
        ");
        
        $unindexed_time = microtime(true) - $start_time;
        
        // Indexed query should be faster
        $this->assertLessThan($unindexed_time, $indexed_time, 
            'Indexed query should be faster than unindexed query');
        
        $this->assertLessThan(0.1, $indexed_time, 
            'Indexed query should execute within 100ms');
    }

    /**
     * Test transaction performance
     */
    public function test_transaction_performance() {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Test with transaction
        $wpdb->query('START TRANSACTION');
        
        foreach (array_slice($this->test_posts, 0, 50) as $post_id) {
            $wpdb->insert(
                $wpdb->postmeta,
                [
                    'post_id' => $post_id,
                    'meta_key' => 'nuclen_test_transaction',
                    'meta_value' => 'transaction_test_' . time()
                ],
                ['%d', '%s', '%s']
            );
        }
        
        $wpdb->query('COMMIT');
        
        $transaction_time = microtime(true) - $start_time;
        
        // Now test without transaction
        $start_time = microtime(true);
        
        foreach (array_slice($this->test_posts, 50, 50) as $post_id) {
            $wpdb->insert(
                $wpdb->postmeta,
                [
                    'post_id' => $post_id,
                    'meta_key' => 'nuclen_test_no_transaction',
                    'meta_value' => 'no_transaction_test_' . time()
                ],
                ['%d', '%s', '%s']
            );
        }
        
        $no_transaction_time = microtime(true) - $start_time;
        
        // Transaction should be faster for bulk operations
        $this->assertLessThan($no_transaction_time, $transaction_time, 
            'Transaction should be faster for bulk operations');
    }

    /**
     * Test cache performance impact
     */
    public function test_cache_performance_impact() {
        $post_ids = array_slice($this->test_posts, 0, 100);
        
        // Test without cache
        wp_cache_flush();
        $start_time = microtime(true);
        
        foreach ($post_ids as $post_id) {
            get_post($post_id);
            get_post_meta($post_id);
        }
        
        $no_cache_time = microtime(true) - $start_time;
        
        // Test with cache (second run should be cached)
        $start_time = microtime(true);
        
        foreach ($post_ids as $post_id) {
            get_post($post_id);
            get_post_meta($post_id);
        }
        
        $cached_time = microtime(true) - $start_time;
        
        // Cached version should be significantly faster
        $this->assertLessThan($no_cache_time * 0.5, $cached_time, 
            'Cached queries should be at least 50% faster');
    }

    /**
     * Test memory usage during large data operations
     */
    public function test_memory_usage_large_operations() {
        $memory_start = memory_get_usage();
        
        // Load large dataset
        $large_dataset = [];
        foreach (array_slice($this->test_posts, 0, 500) as $post_id) {
            $large_dataset[] = [
                'post' => get_post($post_id),
                'meta' => get_post_meta($post_id),
                'comments' => get_comments(['post_id' => $post_id])
            ];
        }
        
        $memory_peak = memory_get_peak_usage();
        $memory_used = $memory_peak - $memory_start;
        
        // Memory usage should be reasonable
        $this->assertLessThan(50 * 1024 * 1024, $memory_used, 
            'Large data operations should use less than 50MB memory');
        
        // Clean up
        unset($large_dataset);
    }

    /**
     * Test pagination performance
     */
    public function test_pagination_performance() {
        global $wpdb;
        
        $page_size = 20;
        $total_pages = 10;
        $times = [];
        
        for ($page = 1; $page <= $total_pages; $page++) {
            $offset = ($page - 1) * $page_size;
            
            $start_time = microtime(true);
            
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, p.post_title, p.post_date
                FROM {$wpdb->posts} p
                WHERE p.post_status = 'publish'
                AND p.post_type = 'post'
                ORDER BY p.post_date DESC
                LIMIT %d OFFSET %d
            ", $page_size, $offset));
            
            $times[] = microtime(true) - $start_time;
        }
        
        // All pages should load within reasonable time
        foreach ($times as $time) {
            $this->assertLessThan(0.1, $time, 
                'Each page should load within 100ms');
        }
        
        // Later pages shouldn't be significantly slower
        $first_page_time = $times[0];
        $last_page_time = end($times);
        
        $this->assertLessThan($first_page_time * 3, $last_page_time, 
            'Later pages should not be more than 3x slower than first page');
    }

    /**
     * Test concurrent database operations
     */
    public function test_concurrent_database_operations() {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Simulate concurrent operations
        $operations = [];
        
        // Read operations
        for ($i = 0; $i < 10; $i++) {
            $post_id = $this->test_posts[rand(0, count($this->test_posts) - 1)];
            $operations[] = function() use ($post_id) {
                return get_post($post_id);
            };
        }
        
        // Write operations
        for ($i = 0; $i < 10; $i++) {
            $post_id = $this->test_posts[rand(0, count($this->test_posts) - 1)];
            $operations[] = function() use ($post_id, $i) {
                return update_post_meta($post_id, "nuclen_concurrent_test_{$i}", time());
            };
        }
        
        // Execute operations
        foreach ($operations as $operation) {
            $operation();
        }
        
        $execution_time = microtime(true) - $start_time;
        
        // Concurrent operations should complete efficiently
        $this->assertLessThan(1.0, $execution_time, 
            'Concurrent operations should complete within 1 second');
    }

    /**
     * Test database cleanup performance
     */
    public function test_database_cleanup_performance() {
        global $wpdb;
        
        // Create test data to clean up
        $cleanup_post_ids = [];
        for ($i = 0; $i < 100; $i++) {
            $post_id = $this->factory->post->create([
                'post_title' => "Cleanup Test Post {$i}",
                'post_status' => 'publish'
            ]);
            $cleanup_post_ids[] = $post_id;
            
            // Add meta data
            update_post_meta($post_id, 'nuclen_cleanup_test', 'test_data');
            update_post_meta($post_id, 'nuclen_temp_data', 'temporary');
        }
        
        $start_time = microtime(true);
        
        // Test bulk cleanup
        $post_ids_string = implode(',', array_map('intval', $cleanup_post_ids));
        
        // Clean up meta data
        $wpdb->query("
            DELETE FROM {$wpdb->postmeta} 
            WHERE post_id IN ({$post_ids_string})
            AND meta_key LIKE 'nuclen_%'
        ");
        
        // Clean up posts
        $wpdb->query("
            DELETE FROM {$wpdb->posts} 
            WHERE ID IN ({$post_ids_string})
        ");
        
        $cleanup_time = microtime(true) - $start_time;
        
        // Cleanup should be efficient
        $this->assertLessThan(1.0, $cleanup_time, 
            'Database cleanup should complete within 1 second');
        
        // Verify cleanup worked
        $remaining_posts = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE ID IN ({$post_ids_string})
        ");
        
        $this->assertEquals(0, $remaining_posts, 'All test posts should be cleaned up');
    }
}