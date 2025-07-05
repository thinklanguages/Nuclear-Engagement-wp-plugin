<?php
/**
 * Event.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Events
 */

declare(strict_types=1);
/**
 * File: inc/Events/Event.php
 *
 * Base event class.
 *
 * @package NuclearEngagement\Events
 */

namespace NuclearEngagement\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base event class for all plugin events.
 */
class Event {

	/** @var string */
	private string $name;

	/** @var array */
	private array $data;

	/** @var bool */
	private bool $propagation_stopped = false;

	/** @var float */
	private float $timestamp;

	public function __construct( string $name, array $data = array() ) {
		$this->name      = $name;
		$this->data      = $data;
		$this->timestamp = microtime( true );
	}

	/**
	 * Get event name.
	 *
	 * @return string Event name.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get event data.
	 *
	 * @return array Event data.
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Set event data.
	 *
	 * @param array $data Event data.
	 */
	public function set_data( array $data ): void {
		$this->data = $data;
	}

	/**
	 * Get specific data item.
	 *
	 * @param string $key     Data key.
	 * @param mixed  $default Default value.
	 * @return mixed Data value.
	 */
	public function get( string $key, $default = null ) {
		return $this->data[ $key ] ?? $default;
	}

	/**
	 * Set specific data item.
	 *
	 * @param string $key   Data key.
	 * @param mixed  $value Data value.
	 */
	public function set( string $key, $value ): void {
		$this->data[ $key ] = $value;
	}

	/**
	 * Check if data key exists.
	 *
	 * @param string $key Data key.
	 * @return bool Whether key exists.
	 */
	public function has( string $key ): bool {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Remove data item.
	 *
	 * @param string $key Data key.
	 */
	public function remove( string $key ): void {
		unset( $this->data[ $key ] );
	}

	/**
	 * Stop event propagation.
	 */
	public function stop_propagation(): void {
		$this->propagation_stopped = true;
	}

	/**
	 * Check if propagation is stopped.
	 *
	 * @return bool Whether propagation is stopped.
	 */
	public function is_propagation_stopped(): bool {
		return $this->propagation_stopped;
	}

	/**
	 * Get event timestamp.
	 *
	 * @return float Event timestamp.
	 */
	public function get_timestamp(): float {
		return $this->timestamp;
	}

	/**
	 * Get event age in seconds.
	 *
	 * @return float Event age.
	 */
	public function get_age(): float {
		return microtime( true ) - $this->timestamp;
	}

	/**
	 * Convert to array.
	 *
	 * @return array Event as array.
	 */
	public function to_array(): array {
		return array(
			'name'                => $this->name,
			'data'                => $this->data,
			'timestamp'           => $this->timestamp,
			'propagation_stopped' => $this->propagation_stopped,
		);
	}
}
