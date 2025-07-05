<?php

use NuclearEngagement\Events\EventDispatcher;
use NuclearEngagement\Events\Event;
use NuclearEngagement\Contracts\LoggerInterface;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase {

	private $logger;
	private $dispatcher;

	public function setUp(): void {
		\WP_Mock::setUp();
		
		$this->logger = \Mockery::mock( LoggerInterface::class );
		$this->dispatcher = new EventDispatcher( $this->logger );
		
		// Reset singleton for testing
		$reflection = new \ReflectionClass( EventDispatcher::class );
		$instance = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
		\Mockery::close();
	}

	public function test_get_instance_with_logger() {
		$instance = EventDispatcher::get_instance( $this->logger );
		$this->assertInstanceOf( EventDispatcher::class, $instance );
		
		// Should return same instance on subsequent calls
		$instance2 = EventDispatcher::get_instance();
		$this->assertSame( $instance, $instance2 );
	}

	public function test_get_instance_without_logger_throws_exception() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Logger required for first instantiation' );
		
		EventDispatcher::get_instance();
	}

	public function test_add_listener() {
		$listener = function( Event $event ) {};
		
		$this->dispatcher->add_listener( 'test_event', $listener );
		
		$this->assertTrue( $this->dispatcher->has_listeners( 'test_event' ) );
		$this->assertEquals( 1, $this->dispatcher->count_listeners( 'test_event' ) );
	}

	public function test_add_multiple_listeners_same_priority() {
		$listener1 = function( Event $event ) {};
		$listener2 = function( Event $event ) {};
		
		$this->dispatcher->add_listener( 'test_event', $listener1 );
		$this->dispatcher->add_listener( 'test_event', $listener2 );
		
		$this->assertEquals( 2, $this->dispatcher->count_listeners( 'test_event' ) );
	}

	public function test_add_listeners_different_priorities() {
		$listener1 = function( Event $event ) {};
		$listener2 = function( Event $event ) {};
		
		$this->dispatcher->add_listener( 'test_event', $listener1, 10 );
		$this->dispatcher->add_listener( 'test_event', $listener2, 5 );
		
		$this->assertEquals( 2, $this->dispatcher->count_listeners( 'test_event' ) );
	}

	public function test_remove_listener() {
		$listener1 = function( Event $event ) {};
		$listener2 = function( Event $event ) {};
		
		$this->dispatcher->add_listener( 'test_event', $listener1 );
		$this->dispatcher->add_listener( 'test_event', $listener2 );
		
		$this->assertEquals( 2, $this->dispatcher->count_listeners( 'test_event' ) );
		
		$this->dispatcher->remove_listener( 'test_event', $listener1 );
		
		$this->assertEquals( 1, $this->dispatcher->count_listeners( 'test_event' ) );
	}

	public function test_remove_listener_nonexistent_event() {
		$listener = function( Event $event ) {};
		
		// Should not throw exception
		$this->dispatcher->remove_listener( 'nonexistent_event', $listener );
		
		$this->assertFalse( $this->dispatcher->has_listeners( 'nonexistent_event' ) );
	}

	public function test_remove_last_listener_cleans_up() {
		$listener = function( Event $event ) {};
		
		$this->dispatcher->add_listener( 'test_event', $listener );
		$this->assertTrue( $this->dispatcher->has_listeners( 'test_event' ) );
		
		$this->dispatcher->remove_listener( 'test_event', $listener );
		$this->assertFalse( $this->dispatcher->has_listeners( 'test_event' ) );
		
		$registered_events = $this->dispatcher->get_registered_events();
		$this->assertNotContains( 'test_event', $registered_events );
	}

	public function test_dispatch_event_no_listeners() {
		$event = new Event( 'test_event', array( 'data' => 'value' ) );
		
		$result = $this->dispatcher->dispatch( $event );
		
		$this->assertSame( $event, $result );
	}

	public function test_dispatch_event_with_listeners() {
		$called = false;
		$listener = function( Event $event ) use ( &$called ) {
			$called = true;
		};
		
		$this->dispatcher->add_listener( 'test_event', $listener );
		
		$this->logger->shouldReceive( 'debug' )
			->once()
			->with( 
				'Dispatching event: test_event',
				\Mockery::type( 'array' )
			);
		
		$event = new Event( 'test_event' );
		$this->dispatcher->dispatch( $event );
		
		$this->assertTrue( $called );
	}

	public function test_dispatch_event_priority_order() {
		$execution_order = array();
		
		$listener_high = function( Event $event ) use ( &$execution_order ) {
			$execution_order[] = 'high';
		};
		
		$listener_low = function( Event $event ) use ( &$execution_order ) {
			$execution_order[] = 'low';
		};
		
		// Add in reverse priority order
		$this->dispatcher->add_listener( 'test_event', $listener_low, 20 );
		$this->dispatcher->add_listener( 'test_event', $listener_high, 5 );
		
		$this->logger->shouldReceive( 'debug' )->once();
		
		$event = new Event( 'test_event' );
		$this->dispatcher->dispatch( $event );
		
		$this->assertEquals( array( 'high', 'low' ), $execution_order );
	}

	public function test_dispatch_event_stops_propagation() {
		$called = array();
		
		$listener1 = function( Event $event ) use ( &$called ) {
			$called[] = 'listener1';
			$event->stop_propagation();
		};
		
		$listener2 = function( Event $event ) use ( &$called ) {
			$called[] = 'listener2';
		};
		
		$this->dispatcher->add_listener( 'test_event', $listener1, 5 );
		$this->dispatcher->add_listener( 'test_event', $listener2, 10 );
		
		$this->logger->shouldReceive( 'debug' )->once();
		
		$event = new Event( 'test_event' );
		$this->dispatcher->dispatch( $event );
		
		$this->assertEquals( array( 'listener1' ), $called );
	}

	public function test_dispatch_event_listener_exception() {
		$listener_error = function( Event $event ) {
			throw new \Exception( 'Test exception' );
		};
		
		$listener_success = function( Event $event ) {
			$event->set( 'success', true );
		};
		
		$this->dispatcher->add_listener( 'test_event', $listener_error, 5 );
		$this->dispatcher->add_listener( 'test_event', $listener_success, 10 );
		
		$this->logger->shouldReceive( 'debug' )->once();
		$this->logger->shouldReceive( 'error' )
			->once()
			->with(
				'Event listener error',
				\Mockery::type( 'array' )
			);
		
		$event = new Event( 'test_event' );
		$result = $this->dispatcher->dispatch( $event );
		
		// Should continue to next listener despite exception
		$this->assertTrue( $result->get( 'success' ) );
	}

	public function test_has_listeners() {
		$this->assertFalse( $this->dispatcher->has_listeners( 'test_event' ) );
		
		$listener = function( Event $event ) {};
		$this->dispatcher->add_listener( 'test_event', $listener );
		
		$this->assertTrue( $this->dispatcher->has_listeners( 'test_event' ) );
	}

	public function test_count_listeners() {
		$this->assertEquals( 0, $this->dispatcher->count_listeners( 'test_event' ) );
		
		$listener1 = function( Event $event ) {};
		$listener2 = function( Event $event ) {};
		
		$this->dispatcher->add_listener( 'test_event', $listener1 );
		$this->assertEquals( 1, $this->dispatcher->count_listeners( 'test_event' ) );
		
		$this->dispatcher->add_listener( 'test_event', $listener2 );
		$this->assertEquals( 2, $this->dispatcher->count_listeners( 'test_event' ) );
	}

	public function test_get_registered_events() {
		$this->assertEquals( array(), $this->dispatcher->get_registered_events() );
		
		$listener = function( Event $event ) {};
		$this->dispatcher->add_listener( 'event1', $listener );
		$this->dispatcher->add_listener( 'event2', $listener );
		
		$events = $this->dispatcher->get_registered_events();
		$this->assertContains( 'event1', $events );
		$this->assertContains( 'event2', $events );
		$this->assertCount( 2, $events );
	}

	public function test_clear_listeners() {
		$listener = function( Event $event ) {};
		$this->dispatcher->add_listener( 'test_event', $listener );
		$this->dispatcher->add_listener( 'other_event', $listener );
		
		$this->assertTrue( $this->dispatcher->has_listeners( 'test_event' ) );
		$this->assertTrue( $this->dispatcher->has_listeners( 'other_event' ) );
		
		$this->dispatcher->clear_listeners( 'test_event' );
		
		$this->assertFalse( $this->dispatcher->has_listeners( 'test_event' ) );
		$this->assertTrue( $this->dispatcher->has_listeners( 'other_event' ) );
	}

	public function test_clear_all_listeners() {
		$listener = function( Event $event ) {};
		$this->dispatcher->add_listener( 'event1', $listener );
		$this->dispatcher->add_listener( 'event2', $listener );
		
		$this->assertCount( 2, $this->dispatcher->get_registered_events() );
		
		$this->dispatcher->clear_all_listeners();
		
		$this->assertCount( 0, $this->dispatcher->get_registered_events() );
	}

	public function test_get_listener_info_string_function() {
		$reflection = new \ReflectionClass( $this->dispatcher );
		$method = $reflection->getMethod( 'get_listener_info' );
		$method->setAccessible( true );
		
		$info = $method->invoke( $this->dispatcher, 'strlen' );
		$this->assertEquals( 'strlen', $info );
	}

	public function test_get_listener_info_array_method() {
		$reflection = new \ReflectionClass( $this->dispatcher );
		$method = $reflection->getMethod( 'get_listener_info' );
		$method->setAccessible( true );
		
		$object = new stdClass();
		$info = $method->invoke( $this->dispatcher, array( $object, 'method' ) );
		$this->assertEquals( 'stdClass::method', $info );
		
		$info = $method->invoke( $this->dispatcher, array( 'ClassName', 'method' ) );
		$this->assertEquals( 'ClassName::method', $info );
	}

	public function test_get_listener_info_closure() {
		$reflection = new \ReflectionClass( $this->dispatcher );
		$method = $reflection->getMethod( 'get_listener_info' );
		$method->setAccessible( true );
		
		$closure = function() {};
		$info = $method->invoke( $this->dispatcher, $closure );
		$this->assertEquals( 'Closure', $info );
	}

	public function test_get_listener_info_invokable_object() {
		$reflection = new \ReflectionClass( $this->dispatcher );
		$method = $reflection->getMethod( 'get_listener_info' );
		$method->setAccessible( true );
		
		$invokable = new class {
			public function __invoke() {}
		};
		
		$info = $method->invoke( $this->dispatcher, $invokable );
		$this->assertStringContains( '::__invoke', $info );
	}

	public function test_listener_execution_order_same_priority() {
		$execution_order = array();
		
		$listener1 = function( Event $event ) use ( &$execution_order ) {
			$execution_order[] = 'first';
		};
		
		$listener2 = function( Event $event ) use ( &$execution_order ) {
			$execution_order[] = 'second';
		};
		
		// Both have same priority (default 10)
		$this->dispatcher->add_listener( 'test_event', $listener1 );
		$this->dispatcher->add_listener( 'test_event', $listener2 );
		
		$this->logger->shouldReceive( 'debug' )->once();
		
		$event = new Event( 'test_event' );
		$this->dispatcher->dispatch( $event );
		
		// Should execute in order added
		$this->assertEquals( array( 'first', 'second' ), $execution_order );
	}

	public function test_event_data_modification() {
		$listener = function( Event $event ) {
			$event->set( 'modified', true );
			$event->set( 'original_data', $event->get( 'data' ) );
		};
		
		$this->dispatcher->add_listener( 'test_event', $listener );
		
		$this->logger->shouldReceive( 'debug' )->once();
		
		$event = new Event( 'test_event', array( 'data' => 'original' ) );
		$result = $this->dispatcher->dispatch( $event );
		
		$this->assertTrue( $result->get( 'modified' ) );
		$this->assertEquals( 'original', $result->get( 'original_data' ) );
	}

	public function test_multiple_events_isolation() {
		$event1_called = false;
		$event2_called = false;
		
		$listener1 = function( Event $event ) use ( &$event1_called ) {
			$event1_called = true;
		};
		
		$listener2 = function( Event $event ) use ( &$event2_called ) {
			$event2_called = true;
		};
		
		$this->dispatcher->add_listener( 'event1', $listener1 );
		$this->dispatcher->add_listener( 'event2', $listener2 );
		
		$this->logger->shouldReceive( 'debug' )->twice();
		
		$event1 = new Event( 'event1' );
		$event2 = new Event( 'event2' );
		
		$this->dispatcher->dispatch( $event1 );
		$this->assertTrue( $event1_called );
		$this->assertFalse( $event2_called );
		
		$this->dispatcher->dispatch( $event2 );
		$this->assertTrue( $event2_called );
	}
}