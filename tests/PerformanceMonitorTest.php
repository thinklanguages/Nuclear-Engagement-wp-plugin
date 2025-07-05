<?php

use NuclearEngagement\Core\PerformanceMonitor;
use PHPUnit\Framework\TestCase;

class PerformanceMonitorTest extends TestCase {

	private $monitor;

	public function setUp(): void {
		\WP_Mock::setUp();
		$this->monitor = new PerformanceMonitor();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	public function test_start_timer() {
		$timer_id = $this->monitor->start_timer( 'test_operation' );
		
		$this->assertIsString( $timer_id );
		$this->assertNotEmpty( $timer_id );
	}

	public function test_stop_timer() {
		$timer_id = $this->monitor->start_timer( 'test_operation' );
		usleep( 1000 ); // 1ms
		
		$elapsed = $this->monitor->stop_timer( $timer_id );
		
		$this->assertIsFloat( $elapsed );
		$this->assertGreaterThan( 0, $elapsed );
		$this->assertLessThan( 1, $elapsed ); // Should be less than 1 second
	}

	public function test_stop_nonexistent_timer() {
		$elapsed = $this->monitor->stop_timer( 'nonexistent_timer' );
		
		$this->assertNull( $elapsed );
	}

	public function test_memory_usage_tracking() {
		$initial_memory = $this->monitor->get_memory_usage();
		
		// Allocate some memory
		$data = array_fill( 0, 1000, 'test_data' );
		
		$current_memory = $this->monitor->get_memory_usage();
		
		$this->assertIsInt( $initial_memory );
		$this->assertIsInt( $current_memory );
		$this->assertGreaterThanOrEqual( $initial_memory, $current_memory );
		
		unset( $data ); // Clean up
	}

	public function test_peak_memory_usage() {
		$initial_peak = $this->monitor->get_peak_memory_usage();
		
		// Allocate and deallocate memory
		$data = array_fill( 0, 5000, 'large_data_chunk' );
		$peak_during = $this->monitor->get_peak_memory_usage();
		unset( $data );
		$peak_after = $this->monitor->get_peak_memory_usage();
		
		$this->assertIsInt( $initial_peak );
		$this->assertGreaterThanOrEqual( $initial_peak, $peak_during );
		$this->assertEquals( $peak_during, $peak_after ); // Peak should remain the same
	}

	public function test_query_count_tracking() {
		global $wpdb;
		$wpdb = \Mockery::mock( 'wpdb' );
		$wpdb->num_queries = 5;
		
		$query_count = $this->monitor->get_query_count();
		
		$this->assertEquals( 5, $query_count );
	}

	public function test_add_checkpoint() {
		$checkpoint_id = $this->monitor->add_checkpoint( 'database_query', array(
			'query' => 'SELECT * FROM posts',
			'execution_time' => 0.05,
		) );
		
		$this->assertIsString( $checkpoint_id );
	}

	public function test_get_performance_metrics() {
		$timer_id = $this->monitor->start_timer( 'test_op' );
		usleep( 1000 );
		$this->monitor->stop_timer( $timer_id );
		
		$this->monitor->add_checkpoint( 'test_checkpoint', array( 'data' => 'test' ) );
		
		$metrics = $this->monitor->get_performance_metrics();
		
		$this->assertIsArray( $metrics );
		$this->assertArrayHasKey( 'memory_usage', $metrics );
		$this->assertArrayHasKey( 'peak_memory', $metrics );
		$this->assertArrayHasKey( 'query_count', $metrics );
		$this->assertArrayHasKey( 'timers', $metrics );
		$this->assertArrayHasKey( 'checkpoints', $metrics );
	}

	public function test_timer_edge_cases() {
		// Test multiple timers with same name
		$timer1 = $this->monitor->start_timer( 'same_name' );
		$timer2 = $this->monitor->start_timer( 'same_name' );
		
		$this->assertNotEquals( $timer1, $timer2 );
		
		// Test stopping one doesn't affect the other
		$elapsed1 = $this->monitor->stop_timer( $timer1 );
		$elapsed2 = $this->monitor->stop_timer( $timer2 );
		
		$this->assertIsFloat( $elapsed1 );
		$this->assertIsFloat( $elapsed2 );
	}

	public function test_memory_threshold_alerts() {
		$threshold = 50 * 1024 * 1024; // 50MB
		$this->monitor->set_memory_threshold( $threshold );
		
		$current = $this->monitor->get_memory_usage();
		$is_exceeded = $this->monitor->is_memory_threshold_exceeded();
		
		if ( $current > $threshold ) {
			$this->assertTrue( $is_exceeded );
		} else {
			$this->assertFalse( $is_exceeded );
		}
	}

	public function test_slow_query_detection() {
		$slow_threshold = 0.1; // 100ms
		$this->monitor->set_slow_query_threshold( $slow_threshold );
		
		// Simulate slow query
		$this->monitor->add_checkpoint( 'slow_query', array(
			'query' => 'SELECT * FROM large_table',
			'execution_time' => 0.15, // 150ms
		) );
		
		$slow_queries = $this->monitor->get_slow_queries();
		
		$this->assertCount( 1, $slow_queries );
		$this->assertEquals( 0.15, $slow_queries[0]['execution_time'] );
	}

	public function test_reset_metrics() {
		$timer_id = $this->monitor->start_timer( 'test' );
		$this->monitor->stop_timer( $timer_id );
		$this->monitor->add_checkpoint( 'test', array() );
		
		$this->monitor->reset_metrics();
		
		$metrics = $this->monitor->get_performance_metrics();
		$this->assertEmpty( $metrics['timers'] );
		$this->assertEmpty( $metrics['checkpoints'] );
	}

	public function test_concurrent_timer_operations() {
		$timers = array();
		
		// Start multiple timers
		for ( $i = 0; $i < 10; $i++ ) {
			$timers[] = $this->monitor->start_timer( "operation_{$i}" );
		}
		
		// Stop them in different order
		shuffle( $timers );
		
		$elapsed_times = array();
		foreach ( $timers as $timer_id ) {
			$elapsed = $this->monitor->stop_timer( $timer_id );
			$this->assertIsFloat( $elapsed );
			$elapsed_times[] = $elapsed;
		}
		
		$this->assertCount( 10, $elapsed_times );
	}

	public function test_memory_leak_detection() {
		$initial_memory = $this->monitor->get_memory_usage();
		
		// Simulate potential memory leak
		$data_store = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$data_store[] = array_fill( 0, 100, "data_chunk_{$i}" );
		}
		
		$peak_memory = $this->monitor->get_memory_usage();
		$memory_increase = $peak_memory - $initial_memory;
		
		$this->assertGreaterThan( 0, $memory_increase );
		
		// Clean up
		unset( $data_store );
		
		// Memory should be released (though may not be immediate due to PHP's memory management)
		$final_memory = $this->monitor->get_memory_usage();
		$this->assertIsInt( $final_memory );
	}

	public function test_performance_profiling() {
		$profile_id = $this->monitor->start_profiling( 'complex_operation' );
		
		// Simulate complex operation
		$timer1 = $this->monitor->start_timer( 'database_query' );
		usleep( 2000 ); // 2ms
		$this->monitor->stop_timer( $timer1 );
		
		$timer2 = $this->monitor->start_timer( 'api_call' );
		usleep( 5000 ); // 5ms
		$this->monitor->stop_timer( $timer2 );
		
		$profile_data = $this->monitor->stop_profiling( $profile_id );
		
		$this->assertIsArray( $profile_data );
		$this->assertArrayHasKey( 'total_time', $profile_data );
		$this->assertArrayHasKey( 'memory_delta', $profile_data );
		$this->assertArrayHasKey( 'operations', $profile_data );
		$this->assertCount( 2, $profile_data['operations'] );
	}

	public function test_error_handling_invalid_operations() {
		// Test stopping timer that was never started
		$result = $this->monitor->stop_timer( 'never_started' );
		$this->assertNull( $result );
		
		// Test stopping profiling that was never started
		$result = $this->monitor->stop_profiling( 'never_started' );
		$this->assertNull( $result );
		
		// Test adding checkpoint with invalid data
		$checkpoint_id = $this->monitor->add_checkpoint( '', array() );
		$this->assertNull( $checkpoint_id );
	}

	public function test_memory_optimization_suggestions() {
		// Simulate high memory usage scenario
		$large_data = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$large_data[] = str_repeat( 'x', 1000 );
		}
		
		$suggestions = $this->monitor->get_optimization_suggestions();
		
		$this->assertIsArray( $suggestions );
		
		// Clean up
		unset( $large_data );
	}

	public function test_query_optimization_analysis() {
		// Add various query checkpoints
		$this->monitor->add_checkpoint( 'fast_query', array(
			'query' => 'SELECT id FROM posts WHERE id = 1',
			'execution_time' => 0.001,
		) );
		
		$this->monitor->add_checkpoint( 'slow_query', array(
			'query' => 'SELECT * FROM posts ORDER BY date DESC',
			'execution_time' => 0.250,
		) );
		
		$this->monitor->add_checkpoint( 'complex_query', array(
			'query' => 'SELECT p.*, m.* FROM posts p JOIN postmeta m ON p.ID = m.post_id',
			'execution_time' => 0.100,
		) );
		
		$analysis = $this->monitor->analyze_query_performance();
		
		$this->assertIsArray( $analysis );
		$this->assertArrayHasKey( 'total_queries', $analysis );
		$this->assertArrayHasKey( 'total_time', $analysis );
		$this->assertArrayHasKey( 'average_time', $analysis );
		$this->assertArrayHasKey( 'slow_queries', $analysis );
		
		$this->assertEquals( 3, $analysis['total_queries'] );
		$this->assertGreaterThan( 0, $analysis['total_time'] );
	}

	public function test_resource_monitoring_alerts() {
		$config = array(
			'memory_threshold' => 64 * 1024 * 1024, // 64MB
			'time_threshold' => 1.0, // 1 second
			'query_threshold' => 100, // 100 queries
		);
		
		$this->monitor->configure_alerts( $config );
		
		// Test memory alert
		$memory_alert = $this->monitor->check_memory_alert();
		$this->assertIsBool( $memory_alert );
		
		// Test time alert for long-running operation
		$timer_id = $this->monitor->start_timer( 'long_operation' );
		usleep( 10000 ); // 10ms (simulating longer operation)
		$elapsed = $this->monitor->stop_timer( $timer_id );
		
		$time_alert = $this->monitor->check_time_alert( $elapsed, 'long_operation' );
		$this->assertIsBool( $time_alert );
	}
}