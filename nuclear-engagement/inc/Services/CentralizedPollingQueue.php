<?php
/**
 * CentralizedPollingQueue.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\BaseService;
use NuclearEngagement\Utils\ProcessIdentifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized polling queue manager to prevent duplicate polling events
 */
class CentralizedPollingQueue extends BaseService {

	/**
	 * Option name for the polling queue
	 */
	private const QUEUE_OPTION = 'nuclen_polling_queue';

	/**
	 * Lock option for queue operations
	 */
	private const LOCK_OPTION = 'nuclen_polling_queue_lock';

	/**
	 * Single cron hook for polling
	 */
	private const CRON_HOOK = 'nuclen_process_polling_queue';

	/**
	 * Maximum items to process per run
	 */
	private const BATCH_SIZE = 10;

	/**
	 * Polling interval in seconds
	 */
	private const POLL_INTERVAL = 30;

	/**
	 * @var GenerationPoller
	 */
	private GenerationPoller $poller;

	/**
	 * Lock value for queue operations
	 *
	 * @var string|null
	 */
	private ?string $lock_value = null;

	/**
	 * Constructor
	 *
	 * @param GenerationPoller $poller
	 */
	public function __construct( GenerationPoller $poller ) {
		parent::__construct();
		$this->poller = $poller;

		// Set service-specific cache TTL
		$this->cache_ttl = 300; // 5 minutes for queue data
	}

	/**
	 * Register hooks
	 */
	public function register_hooks(): void {
		add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );
	}

	/**
	 * Add a generation to the polling queue
	 *
	 * @param string $generation_id Generation ID
	 * @param string $workflow_type Workflow type
	 * @param array  $post_ids Post IDs in this generation
	 * @param int    $priority Priority (1-10, lower is higher priority)
	 * @return bool Success status
	 */
	public function add_to_queue( string $generation_id, string $workflow_type, array $post_ids, int $priority = 5 ): bool {
		if ( ! $this->acquire_lock() ) {
			return false;
		}

		try {
			$queue = $this->get_queue();

			// Check if already in queue
			if ( isset( $queue[ $generation_id ] ) ) {
				return true;
			}

			// Add to queue
			$queue[ $generation_id ] = array(
				'generation_id' => $generation_id,
				'workflow_type' => $workflow_type,
				'post_ids'      => $post_ids,
				'priority'      => max( 1, min( 10, $priority ) ),
				'attempts'      => 0,
				'added_at'      => time(),
				'last_poll'     => 0,
				'status'        => 'pending',
			);

			$this->save_queue( $queue );
			$this->ensure_cron_scheduled();

			return true;

		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Process the polling queue
	 */
	public function process_queue(): void {
		if ( ! $this->acquire_lock() ) {
			return;
		}

		try {
			$queue = $this->get_queue();
			if ( empty( $queue ) ) {
				return;
			}

			// Sort by priority and age
			$sorted_queue = $this->sort_queue( $queue );

			// Process up to BATCH_SIZE items
			$processed    = 0;
			$current_time = time();

			foreach ( $sorted_queue as $generation_id => $item ) {
				// Skip if polled recently
				if ( $current_time - $item['last_poll'] < self::POLL_INTERVAL ) {
					continue;
				}

				// Skip if too many attempts
				if ( $item['attempts'] >= NUCLEN_MAX_POLL_ATTEMPTS ) {
					$queue[ $generation_id ]['status'] = 'failed';
					continue;
				}

				// Update queue before polling to prevent race conditions
				$queue[ $generation_id ]['last_poll'] = $current_time;
				++$queue[ $generation_id ]['attempts'];
				$queue[ $generation_id ]['status'] = 'polling';
				$this->save_queue( $queue );

				// Release lock during polling to prevent blocking
				$this->release_lock();

				// Perform the actual poll
				$this->poll_single_generation( $item );

				// Re-acquire lock
				if ( ! $this->acquire_lock() ) {
					return;
				}

				// Reload queue in case it changed
				$queue = $this->get_queue();

				++$processed;
				if ( $processed >= self::BATCH_SIZE ) {
					break;
				}
			}

			// Clean up completed/failed items
			$this->cleanup_queue( $queue );

			// Reschedule if items remain
			if ( ! empty( $queue ) ) {
				$this->ensure_cron_scheduled();
			}
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Poll a single generation
	 *
	 * @param array $item Queue item
	 */
	private function poll_single_generation( array $item ): void {
		try {
			// Call the poller directly
			$this->poller->poll_generation(
				$item['generation_id'],
				$item['workflow_type'],
				$item['post_ids'],
				$item['attempts']
			);

			// If we get here without exception, mark as complete
			$this->mark_generation_complete( $item['generation_id'] );

		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Error polling generation %s: %s',
					$item['generation_id'],
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Mark a generation as complete
	 *
	 * @param string $generation_id Generation ID
	 */
	public function mark_generation_complete( string $generation_id ): void {
		if ( ! $this->acquire_lock() ) {
			return;
		}

		try {
			$queue = $this->get_queue();
			if ( isset( $queue[ $generation_id ] ) ) {
				unset( $queue[ $generation_id ] );
				$this->save_queue( $queue );
			}
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Get the polling queue
	 *
	 * @return array
	 */
	private function get_queue(): array {
		$queue = get_option( self::QUEUE_OPTION, array() );
		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Save the polling queue
	 *
	 * @param array $queue Queue data
	 */
	private function save_queue( array $queue ): void {
		update_option( self::QUEUE_OPTION, $queue, 'no' );
	}

	/**
	 * Sort queue by priority and age
	 *
	 * @param array $queue Queue to sort
	 * @return array Sorted queue
	 */
	private function sort_queue( array $queue ): array {
		uasort(
			$queue,
			function ( $a, $b ) {
				// First sort by priority (lower number = higher priority)
				if ( $a['priority'] !== $b['priority'] ) {
					return $a['priority'] - $b['priority'];
				}

				// Then by age (older first)
				return $a['added_at'] - $b['added_at'];
			}
		);

		return $queue;
	}

	/**
	 * Clean up completed and failed items
	 *
	 * @param array &$queue Queue to clean
	 */
	private function cleanup_queue( array &$queue ): void {
		$current_time = time();
		$cleaned      = 0;

		foreach ( $queue as $generation_id => $item ) {
			// Remove failed items after 1 hour
			if ( $item['status'] === 'failed' && $current_time - $item['added_at'] > 3600 ) {
				unset( $queue[ $generation_id ] );
				++$cleaned;
				continue;
			}

			// Remove stale items after 24 hours
			if ( $current_time - $item['added_at'] > 86400 ) {
				unset( $queue[ $generation_id ] );
				++$cleaned;
			}
		}

		if ( $cleaned > 0 ) {
			$this->save_queue( $queue );
			// Queue cleanup completed
		}
	}

	/**
	 * Ensure cron is scheduled
	 */
	private function ensure_cron_scheduled(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + self::POLL_INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * Acquire lock for queue operations
	 *
	 * @return bool Success status
	 */
	private function acquire_lock(): bool {
		$lock_value = wp_generate_password( 12, false );

		// Try to acquire lock with atomic operation
		if ( add_option(
			self::LOCK_OPTION,
			array(
				'value'      => $lock_value,
				'time'       => time(),
				'process_id' => ProcessIdentifier::get(),
			),
			'',
			'no'
		) ) {
			$this->lock_value = $lock_value;
			return true;
		}

		// Check if existing lock is expired
		$existing = get_option( self::LOCK_OPTION );
		if ( is_array( $existing ) && isset( $existing['time'] ) ) {
			if ( time() - $existing['time'] > 60 ) { // 1 minute timeout
				// Try to take over expired lock
				if ( update_option(
					self::LOCK_OPTION,
					array(
						'value'      => $lock_value,
						'time'       => time(),
						'process_id' => ProcessIdentifier::get(),
					)
				) ) {
					$this->lock_value = $lock_value;
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Release lock
	 */
	private function release_lock(): void {
		if ( ! isset( $this->lock_value ) ) {
			return;
		}

		$current = get_option( self::LOCK_OPTION );
		if ( is_array( $current ) && isset( $current['value'] ) &&
			$current['value'] === $this->lock_value ) {
			delete_option( self::LOCK_OPTION );
		}

		unset( $this->lock_value );
	}

	/**
	 * Get queue status
	 *
	 * @return array
	 */
	public function get_queue_status(): array {
		$queue = $this->get_queue();

		$status = array(
			'total'   => count( $queue ),
			'pending' => 0,
			'polling' => 0,
			'failed'  => 0,
		);

		foreach ( $queue as $item ) {
			if ( isset( $item['status'] ) ) {
				$status_key = $item['status'];
				if ( isset( $status[ $status_key ] ) ) {
					++$status[ $status_key ];
				}
			}
		}

		return $status;
	}

	/**
	 * Clear the entire queue (for debugging/recovery)
	 */
	public function clear_queue(): void {
		if ( $this->acquire_lock() ) {
			try {
				update_option( self::QUEUE_OPTION, array(), 'no' );
				wp_clear_scheduled_hook( self::CRON_HOOK );

				// Queue cleared
			} finally {
				$this->release_lock();
			}
		}
	}

	/**
	 * Get service name for logging and caching.
	 *
	 * @return string Service name.
	 */
	protected function get_service_name(): string {
		return 'centralized_polling_queue';
	}
}
