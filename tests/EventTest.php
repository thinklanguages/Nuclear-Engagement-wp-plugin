<?php

use NuclearEngagement\Events\Event;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase {

	public function setUp(): void {
		\WP_Mock::setUp();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	public function test_construct_with_name_only() {
		$event = new Event( 'test_event' );
		
		$this->assertEquals( 'test_event', $event->get_name() );
		$this->assertEquals( array(), $event->get_data() );
		$this->assertFalse( $event->is_propagation_stopped() );
	}

	public function test_construct_with_data() {
		$data = array( 'key' => 'value', 'number' => 42 );
		$event = new Event( 'test_event', $data );
		
		$this->assertEquals( 'test_event', $event->get_name() );
		$this->assertEquals( $data, $event->get_data() );
	}

	public function test_get_name() {
		$event = new Event( 'my_event' );
		$this->assertEquals( 'my_event', $event->get_name() );
	}

	public function test_get_data() {
		$data = array( 'foo' => 'bar', 'baz' => 123 );
		$event = new Event( 'test_event', $data );
		
		$this->assertEquals( $data, $event->get_data() );
	}

	public function test_set_data() {
		$event = new Event( 'test_event' );
		$data = array( 'new' => 'data' );
		
		$event->set_data( $data );
		
		$this->assertEquals( $data, $event->get_data() );
	}

	public function test_get_with_existing_key() {
		$event = new Event( 'test_event', array( 'key' => 'value' ) );
		
		$this->assertEquals( 'value', $event->get( 'key' ) );
	}

	public function test_get_with_nonexistent_key() {
		$event = new Event( 'test_event' );
		
		$this->assertNull( $event->get( 'nonexistent' ) );
	}

	public function test_get_with_default() {
		$event = new Event( 'test_event' );
		
		$this->assertEquals( 'default_value', $event->get( 'nonexistent', 'default_value' ) );
	}

	public function test_set() {
		$event = new Event( 'test_event' );
		
		$event->set( 'key', 'value' );
		
		$this->assertEquals( 'value', $event->get( 'key' ) );
		$this->assertEquals( array( 'key' => 'value' ), $event->get_data() );
	}

	public function test_set_overwrites_existing() {
		$event = new Event( 'test_event', array( 'key' => 'old_value' ) );
		
		$event->set( 'key', 'new_value' );
		
		$this->assertEquals( 'new_value', $event->get( 'key' ) );
	}

	public function test_has_existing_key() {
		$event = new Event( 'test_event', array( 'key' => 'value' ) );
		
		$this->assertTrue( $event->has( 'key' ) );
	}

	public function test_has_nonexistent_key() {
		$event = new Event( 'test_event' );
		
		$this->assertFalse( $event->has( 'nonexistent' ) );
	}

	public function test_has_with_null_value() {
		$event = new Event( 'test_event', array( 'key' => null ) );
		
		$this->assertTrue( $event->has( 'key' ) );
	}

	public function test_remove() {
		$event = new Event( 'test_event', array( 'key1' => 'value1', 'key2' => 'value2' ) );
		
		$event->remove( 'key1' );
		
		$this->assertFalse( $event->has( 'key1' ) );
		$this->assertTrue( $event->has( 'key2' ) );
		$this->assertEquals( array( 'key2' => 'value2' ), $event->get_data() );
	}

	public function test_remove_nonexistent_key() {
		$event = new Event( 'test_event', array( 'key' => 'value' ) );
		
		// Should not throw exception
		$event->remove( 'nonexistent' );
		
		$this->assertEquals( array( 'key' => 'value' ), $event->get_data() );
	}

	public function test_stop_propagation() {
		$event = new Event( 'test_event' );
		
		$this->assertFalse( $event->is_propagation_stopped() );
		
		$event->stop_propagation();
		
		$this->assertTrue( $event->is_propagation_stopped() );
	}

	public function test_timestamp_set_on_construct() {
		$before = microtime( true );
		$event = new Event( 'test_event' );
		$after = microtime( true );
		
		$timestamp = $event->get_timestamp();
		
		$this->assertGreaterThanOrEqual( $before, $timestamp );
		$this->assertLessThanOrEqual( $after, $timestamp );
	}

	public function test_get_age() {
		$event = new Event( 'test_event' );
		
		// Sleep for a small amount to ensure age > 0
		usleep( 1000 ); // 1ms
		
		$age = $event->get_age();
		
		$this->assertGreaterThan( 0, $age );
		$this->assertLessThan( 1, $age ); // Should be less than 1 second
	}

	public function test_to_array() {
		$data = array( 'key' => 'value' );
		$event = new Event( 'test_event', $data );
		$event->stop_propagation();
		
		$array = $event->to_array();
		
		$this->assertEquals( 'test_event', $array['name'] );
		$this->assertEquals( $data, $array['data'] );
		$this->assertTrue( $array['propagation_stopped'] );
		$this->assertIsFloat( $array['timestamp'] );
	}

	public function test_data_types_preservation() {
		$data = array(
			'string' => 'hello',
			'int' => 42,
			'float' => 3.14,
			'bool' => true,
			'null' => null,
			'array' => array( 1, 2, 3 ),
			'object' => new stdClass(),
		);
		
		$event = new Event( 'test_event', $data );
		
		$this->assertEquals( 'hello', $event->get( 'string' ) );
		$this->assertEquals( 42, $event->get( 'int' ) );
		$this->assertEquals( 3.14, $event->get( 'float' ) );
		$this->assertTrue( $event->get( 'bool' ) );
		$this->assertNull( $event->get( 'null' ) );
		$this->assertEquals( array( 1, 2, 3 ), $event->get( 'array' ) );
		$this->assertInstanceOf( 'stdClass', $event->get( 'object' ) );
	}

	public function test_complex_data_manipulation() {
		$event = new Event( 'test_event' );
		
		// Set multiple values
		$event->set( 'user_id', 123 );
		$event->set( 'action', 'login' );
		$event->set( 'metadata', array( 'ip' => '127.0.0.1', 'browser' => 'Chrome' ) );
		
		// Verify data
		$this->assertEquals( 123, $event->get( 'user_id' ) );
		$this->assertEquals( 'login', $event->get( 'action' ) );
		$this->assertEquals( array( 'ip' => '127.0.0.1', 'browser' => 'Chrome' ), $event->get( 'metadata' ) );
		
		// Modify metadata
		$metadata = $event->get( 'metadata' );
		$metadata['timestamp'] = time();
		$event->set( 'metadata', $metadata );
		
		$this->assertArrayHasKey( 'timestamp', $event->get( 'metadata' ) );
		
		// Remove action
		$event->remove( 'action' );
		$this->assertFalse( $event->has( 'action' ) );
		
		// Verify remaining data
		$expected_data = array(
			'user_id' => 123,
			'metadata' => $metadata,
		);
		$this->assertEquals( $expected_data, $event->get_data() );
	}

	public function test_event_immutability_of_name() {
		$event = new Event( 'original_name' );
		
		// There's no setter for name, so it should remain unchanged
		$this->assertEquals( 'original_name', $event->get_name() );
		
		// Even after data manipulation
		$event->set( 'key', 'value' );
		$this->assertEquals( 'original_name', $event->get_name() );
	}

	public function test_propagation_state_isolation() {
		$event1 = new Event( 'event1' );
		$event2 = new Event( 'event2' );
		
		$event1->stop_propagation();
		
		$this->assertTrue( $event1->is_propagation_stopped() );
		$this->assertFalse( $event2->is_propagation_stopped() );
	}

	public function test_data_isolation_between_events() {
		$event1 = new Event( 'event1', array( 'shared_key' => 'value1' ) );
		$event2 = new Event( 'event2', array( 'shared_key' => 'value2' ) );
		
		$event1->set( 'shared_key', 'modified1' );
		
		$this->assertEquals( 'modified1', $event1->get( 'shared_key' ) );
		$this->assertEquals( 'value2', $event2->get( 'shared_key' ) );
	}

	public function test_timestamp_precision() {
		$event1 = new Event( 'event1' );
		usleep( 100 ); // 0.1ms
		$event2 = new Event( 'event2' );
		
		$this->assertLessThan( $event2->get_timestamp(), $event1->get_timestamp() );
	}

	/**
	 * @dataProvider invalidDataProvider
	 */
	public function test_set_data_handles_any_type( $data ) {
		$event = new Event( 'test_event' );
		
		// Should not throw exception for any data type
		$event->set_data( $data );
		$this->assertEquals( $data, $event->get_data() );
	}

	public function invalidDataProvider(): array {
		return [
			'empty array' => [ array() ],
			'null' => [ null ],
			'string' => [ 'not_an_array' ],
			'integer' => [ 42 ],
			'nested array' => [ array( 'level1' => array( 'level2' => 'value' ) ) ],
		];
	}
}