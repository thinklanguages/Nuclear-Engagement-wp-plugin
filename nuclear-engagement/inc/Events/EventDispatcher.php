<?php
/**
 * EventDispatcher.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Events
 */

declare(strict_types=1);
/**
 * File: inc/Events/EventDispatcher.php
 *
 * Event dispatcher for plugin events.
 *
 * @package NuclearEngagement\Events
 */

namespace NuclearEngagement\Events;

use NuclearEngagement\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event dispatcher for handling plugin events.
 */
class EventDispatcher {

	/** @var array */
	private array $listeners = array();

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/** @var EventDispatcher */
	private static ?EventDispatcher $instance = null;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get singleton instance.
	 *
	 * @param LoggerInterface|null $logger Logger instance.
	 * @return EventDispatcher Instance.
	 */
	public static function get_instance( ?LoggerInterface $logger = null ): EventDispatcher {
		if ( self::$instance === null ) {
			if ( $logger === null ) {
				throw new \InvalidArgumentException( 'Logger required for first instantiation' );
			}
			self::$instance = new self( $logger );
		}

		return self::$instance;
	}

	/**
	 * Add event listener.
	 *
	 * @param string   $event_name Event name.
	 * @param callable $listener   Event listener.
	 * @param int      $priority   Listener priority.
	 */
	public function add_listener( string $event_name, callable $listener, int $priority = 10 ): void {
		if ( ! isset( $this->listeners[ $event_name ] ) ) {
			$this->listeners[ $event_name ] = array();
		}

		if ( ! isset( $this->listeners[ $event_name ][ $priority ] ) ) {
			$this->listeners[ $event_name ][ $priority ] = array();
		}

		$this->listeners[ $event_name ][ $priority ][] = $listener;
	}

	/**
	 * Remove event listener.
	 *
	 * @param string   $event_name Event name.
	 * @param callable $listener   Event listener.
	 */
	public function remove_listener( string $event_name, callable $listener ): void {
		if ( ! isset( $this->listeners[ $event_name ] ) ) {
			return;
		}

		foreach ( $this->listeners[ $event_name ] as $priority => $listeners ) {
			foreach ( $listeners as $index => $registered_listener ) {
				if ( $registered_listener === $listener ) {
					unset( $this->listeners[ $event_name ][ $priority ][ $index ] );

					// Clean up empty arrays.
					if ( empty( $this->listeners[ $event_name ][ $priority ] ) ) {
						unset( $this->listeners[ $event_name ][ $priority ] );
					}

					if ( empty( $this->listeners[ $event_name ] ) ) {
						unset( $this->listeners[ $event_name ] );
					}

					return;
				}
			}
		}
	}

	/**
	 * Dispatch event to listeners.
	 *
	 * @param Event $event Event object.
	 * @return Event Modified event object.
	 */
	public function dispatch( Event $event ): Event {
		$event_name = $event->get_name();

		if ( ! isset( $this->listeners[ $event_name ] ) ) {
			return $event;
		}

		// Sort by priority (lower numbers = higher priority).
		$listeners = $this->listeners[ $event_name ];
		ksort( $listeners );

		$this->logger->debug(
			"Dispatching event: {$event_name}",
			array(
				'event_data'     => $event->get_data(),
				'listener_count' => $this->count_listeners( $event_name ),
			)
		);

		foreach ( $listeners as $priority_listeners ) {
			foreach ( $priority_listeners as $listener ) {
				if ( $event->is_propagation_stopped() ) {
					break 2;
				}

				try {
					$listener( $event );
				} catch ( \Throwable $e ) {
					$this->logger->error(
						'Event listener error',
						array(
							'event_name' => $event_name,
							'listener'   => $this->get_listener_info( $listener ),
							'error'      => $e->getMessage(),
							'trace'      => $e->getTraceAsString(),
						)
					);
				}
			}
		}

		return $event;
	}

	/**
	 * Check if event has listeners.
	 *
	 * @param string $event_name Event name.
	 * @return bool Whether event has listeners.
	 */
	public function has_listeners( string $event_name ): bool {
		return isset( $this->listeners[ $event_name ] ) && ! empty( $this->listeners[ $event_name ] );
	}

	/**
	 * Get listener count for event.
	 *
	 * @param string $event_name Event name.
	 * @return int Listener count.
	 */
	public function count_listeners( string $event_name ): int {
		if ( ! isset( $this->listeners[ $event_name ] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $this->listeners[ $event_name ] as $priority_listeners ) {
			$count += count( $priority_listeners );
		}

		return $count;
	}

	/**
	 * Get all registered events.
	 *
	 * @return array Event names.
	 */
	public function get_registered_events(): array {
		return array_keys( $this->listeners );
	}

	/**
	 * Clear all listeners for event.
	 *
	 * @param string $event_name Event name.
	 */
	public function clear_listeners( string $event_name ): void {
		unset( $this->listeners[ $event_name ] );
	}

	/**
	 * Clear all listeners.
	 */
	public function clear_all_listeners(): void {
		$this->listeners = array();
	}

	/**
	 * Get listener information for debugging.
	 *
	 * @param callable $listener Listener callable.
	 * @return string Listener info.
	 */
	private function get_listener_info( callable $listener ): string {
		if ( is_string( $listener ) ) {
			return $listener;
		}

		if ( is_array( $listener ) ) {
			if ( is_object( $listener[0] ) ) {
				return get_class( $listener[0] ) . '::' . $listener[1];
			}
			return $listener[0] . '::' . $listener[1];
		}

		if ( $listener instanceof \Closure ) {
			return 'Closure';
		}

		if ( is_object( $listener ) ) {
			return get_class( $listener ) . '::__invoke';
		}

		return 'Unknown';
	}
}
