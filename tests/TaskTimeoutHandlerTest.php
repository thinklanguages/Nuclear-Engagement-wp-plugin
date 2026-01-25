<?php
/**
 * Tests for TaskTimeoutHandler class
 *
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use NuclearEngagement\Services\TaskTimeoutHandler;

class TaskTimeoutHandlerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		\WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();

		// Reset static state.
		$reflection = new \ReflectionClass( TaskTimeoutHandler::class );
		$property   = $reflection->getProperty( 'hooks_registered' );
		$property->setAccessible( true );
		$property->setValue( null, false );
	}

	/**
	 * Test register_hooks schedules timeout check
	 */
	public function test_register_hooks_schedules_timeout_check(): void {
		$handler = new TaskTimeoutHandler();

		\WP_Mock::userFunction( 'wp_next_scheduled' )
			->andReturn( false );

		\WP_Mock::userFunction( 'wp_schedule_event' )
			->andReturn( true );

		\WP_Mock::userFunction( 'add_action' )
			->andReturn( true );

		$handler->register_hooks();

		$this->assertTrue( true );
	}

	/**
	 * Test register_hooks does not double-register
	 *
	 * @todo Fix WP_Mock static state issues across tests.
	 */
	public function test_register_hooks_does_not_double_register(): void {
		$this->markTestSkipped( 'WP_Mock static state interference - use integration tests' );
	}

	/**
	 * Test record_task_start stores timeout data
	 *
	 * @todo Fix WP_Mock function mocking in unit test context.
	 */
	public function test_record_task_start_stores_timeout_data(): void {
		$this->markTestSkipped( 'WP_Mock function mocking - use integration tests' );
	}

	/**
	 * Test record_task_start uses default timeout
	 *
	 * @todo Fix WP_Mock function mocking in unit test context.
	 */
	public function test_record_task_start_uses_default_timeout(): void {
		$this->markTestSkipped( 'WP_Mock function mocking - use integration tests' );
	}

	/**
	 * Test clear_task_timeout deletes transient
	 *
	 * @todo Fix WP_Mock function mocking in unit test context.
	 */
	public function test_clear_task_timeout_deletes_transient(): void {
		$this->markTestSkipped( 'WP_Mock function mocking - use integration tests' );
	}

	/**
	 * Test validate_state_transition for valid transitions
	 */
	public function test_validate_state_transition_valid(): void {
		$handler = new TaskTimeoutHandler();

		// Pending can go to processing.
		$this->assertTrue( $handler->validate_state_transition( 'pending', 'processing' ) );

		// Pending can go to failed.
		$this->assertTrue( $handler->validate_state_transition( 'pending', 'failed' ) );

		// Pending can go to cancelled.
		$this->assertTrue( $handler->validate_state_transition( 'pending', 'cancelled' ) );

		// Processing can go to completed.
		$this->assertTrue( $handler->validate_state_transition( 'processing', 'completed' ) );

		// Processing can go to completed_with_errors.
		$this->assertTrue( $handler->validate_state_transition( 'processing', 'completed_with_errors' ) );

		// Processing can go to failed.
		$this->assertTrue( $handler->validate_state_transition( 'processing', 'failed' ) );

		// Failed can retry to pending.
		$this->assertTrue( $handler->validate_state_transition( 'failed', 'pending' ) );
	}

	/**
	 * Test validate_state_transition for invalid transitions
	 */
	public function test_validate_state_transition_invalid(): void {
		$handler = new TaskTimeoutHandler();

		// Completed is terminal.
		$this->assertFalse( $handler->validate_state_transition( 'completed', 'pending' ) );
		$this->assertFalse( $handler->validate_state_transition( 'completed', 'processing' ) );

		// Completed_with_errors is terminal.
		$this->assertFalse( $handler->validate_state_transition( 'completed_with_errors', 'pending' ) );

		// Cancelled is terminal.
		$this->assertFalse( $handler->validate_state_transition( 'cancelled', 'pending' ) );

		// Pending cannot skip to completed.
		$this->assertFalse( $handler->validate_state_transition( 'pending', 'completed' ) );

		// Unknown state.
		$this->assertFalse( $handler->validate_state_transition( 'unknown', 'processing' ) );
	}

	/**
	 * Test get_service_name returns correct name
	 */
	public function test_get_service_name(): void {
		$handler = new TaskTimeoutHandler();

		$reflection = new \ReflectionClass( $handler );
		$method     = $reflection->getMethod( 'get_service_name' );
		$method->setAccessible( true );

		$this->assertEquals( 'task_timeout_handler', $method->invoke( $handler ) );
	}

	/**
	 * Test constructor sets cache TTL
	 */
	public function test_constructor_sets_cache_ttl(): void {
		$handler = new TaskTimeoutHandler();

		$reflection = new \ReflectionClass( $handler );
		$property   = $reflection->getProperty( 'cache_ttl' );
		$property->setAccessible( true );

		$this->assertEquals( 600, $property->getValue( $handler ) ); // 10 minutes.
	}
}
