<?php
/**
 * Tests for BatchCleanupService class
 *
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\BatchCleanupService;

class BatchCleanupServiceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		\WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Test that BatchCleanupService class exists
	 */
	public function test_class_exists(): void {
		$this->assertTrue( class_exists( BatchCleanupService::class ) );
	}

	/**
	 * Test cleanup_orphaned_batches method exists
	 */
	public function test_cleanup_orphaned_batches_method_exists(): void {
		$this->assertTrue( method_exists( BatchCleanupService::class, 'cleanup_orphaned_batches' ) );
	}

	/**
	 * Test cleanup_old_batches method exists
	 */
	public function test_cleanup_old_batches_method_exists(): void {
		$this->assertTrue( method_exists( BatchCleanupService::class, 'cleanup_old_batches' ) );
	}

	/**
	 * Test run_full_cleanup method exists
	 */
	public function test_run_full_cleanup_method_exists(): void {
		$this->assertTrue( method_exists( BatchCleanupService::class, 'run_full_cleanup' ) );
	}

	/**
	 * Test get_service_name returns correct name
	 */
	public function test_get_service_name(): void {
		$service = new BatchCleanupService();

		$reflection = new \ReflectionClass( $service );
		$method     = $reflection->getMethod( 'get_service_name' );
		$method->setAccessible( true );

		$this->assertEquals( 'batch_cleanup', $method->invoke( $service ) );
	}

	/**
	 * Test constructor sets cache TTL
	 */
	public function test_constructor_sets_cache_ttl(): void {
		$service = new BatchCleanupService();

		$reflection = new \ReflectionClass( $service );
		$property   = $reflection->getProperty( 'cache_ttl' );
		$property->setAccessible( true );

		$this->assertEquals( 600, $property->getValue( $service ) ); // 10 minutes.
	}

	/**
	 * Test default retention constants are set correctly
	 */
	public function test_default_retention_constants(): void {
		$reflection = new \ReflectionClass( BatchCleanupService::class );

		$batch_retention = $reflection->getConstant( 'DEFAULT_BATCH_RETENTION_HOURS' );
		$this->assertEquals( 24, $batch_retention );

		$bulk_job_retention = $reflection->getConstant( 'DEFAULT_BULK_JOB_RETENTION_HOURS' );
		$this->assertEquals( 168, $bulk_job_retention ); // 7 days.
	}

	/**
	 * Test cleanup batch size constant
	 */
	public function test_cleanup_batch_size_constant(): void {
		$reflection = new \ReflectionClass( BatchCleanupService::class );

		$batch_size = $reflection->getConstant( 'CLEANUP_BATCH_SIZE' );
		$this->assertEquals( 50, $batch_size );
	}

	/**
	 * Test max cleanup iterations constant
	 */
	public function test_max_cleanup_iterations_constant(): void {
		$reflection = new \ReflectionClass( BatchCleanupService::class );

		$max_iterations = $reflection->getConstant( 'MAX_CLEANUP_ITERATIONS' );
		$this->assertEquals( 1000, $max_iterations );
	}

	/**
	 * Skip test: cleanup operations require database mocking
	 */
	public function test_cleanup_orphaned_batches_removes_orphans(): void {
		$this->markTestSkipped( 'Requires database mocking - use integration tests' );
	}

	/**
	 * Skip test: cleanup operations require database mocking
	 */
	public function test_cleanup_old_batches_respects_retention(): void {
		$this->markTestSkipped( 'Requires database mocking - use integration tests' );
	}

	/**
	 * Skip test: cleanup operations require database mocking
	 */
	public function test_run_full_cleanup_returns_stats(): void {
		$this->markTestSkipped( 'Requires database mocking - use integration tests' );
	}
}
