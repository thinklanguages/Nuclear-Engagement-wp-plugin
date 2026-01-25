<?php
/**
 * LockManager.php - Unified lock management service
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\DistributedLock;
use NuclearEngagement\Utils\ProcessIdentifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified lock management service.
 *
 * Provides a high-level interface for acquiring and managing distributed locks.
 * Wraps DistributedLock with context-specific methods, logging, and monitoring.
 *
 * Lock Types:
 * - BATCH: Locks for batch processing operations (5 min default)
 * - TASK: Locks for task execution (10 min default)
 * - API: Locks for API operations (1 min default)
 * - RESOURCE: Generic resource locks (5 min default)
 *
 * Usage:
 * ```php
 * $lock_manager = $container->get('lock_manager');
 *
 * // Acquire a batch lock
 * $lock = $lock_manager->acquire_batch_lock('batch_123');
 * if ($lock) {
 *     try {
 *         // Do batch work
 *     } finally {
 *         $lock_manager->release($lock);
 *     }
 * }
 *
 * // With context for better logging
 * $lock = $lock_manager->acquire('my_resource', LockManager::TYPE_RESOURCE, [
 *     'reason' => 'Processing user upload',
 * ]);
 * ```
 */
final class LockManager {

	/**
	 * Lock types with their default timeouts.
	 */
	public const TYPE_BATCH    = 'batch';
	public const TYPE_TASK     = 'task';
	public const TYPE_API      = 'api';
	public const TYPE_RESOURCE = 'resource';

	/**
	 * Default timeouts for each lock type (in seconds).
	 */
	private const DEFAULT_TIMEOUTS = array(
		self::TYPE_BATCH    => 300,  // 5 minutes
		self::TYPE_TASK     => 600,  // 10 minutes
		self::TYPE_API      => 60,   // 1 minute
		self::TYPE_RESOURCE => 300,  // 5 minutes
	);

	/**
	 * Active locks held by this process.
	 *
	 * @var array<string, array>
	 */
	private array $active_locks = array();

	/**
	 * Lock statistics.
	 *
	 * @var array
	 */
	private array $statistics = array(
		'acquired'   => 0,
		'released'   => 0,
		'failed'     => 0,
		'extended'   => 0,
		'contention' => 0,
	);

	/**
	 * Acquire a lock.
	 *
	 * @param string $resource  Resource name to lock.
	 * @param string $type      Lock type (one of TYPE_* constants).
	 * @param array  $context   Additional context for logging.
	 * @param int    $timeout   Optional custom timeout in seconds.
	 * @return array|null Lock handle array or null if failed.
	 */
	public function acquire( string $resource, string $type = self::TYPE_RESOURCE, array $context = array(), int $timeout = 0 ): ?array {
		$lock_name  = $this->get_lock_name( $resource, $type );
		$lock_value = $this->generate_lock_value();
		$timeout    = $timeout > 0 ? $timeout : ( self::DEFAULT_TIMEOUTS[ $type ] ?? 300 );

		// Log acquisition attempt.
		$this->log_debug(
			sprintf( 'Attempting to acquire %s lock: %s', $type, $lock_name ),
			$context
		);

		$acquired = DistributedLock::acquire( $lock_name, $lock_value, $timeout );

		if ( $acquired ) {
			$lock_handle = array(
				'name'        => $lock_name,
				'value'       => $lock_value,
				'type'        => $type,
				'resource'    => $resource,
				'timeout'     => $timeout,
				'acquired_at' => time(),
				'expires_at'  => time() + $timeout,
				'context'     => $context,
			);

			$this->active_locks[ $lock_name ] = $lock_handle;
			++$this->statistics['acquired'];

			$this->log_debug(
				sprintf( 'Lock acquired: %s (expires in %ds)', $lock_name, $timeout ),
				$context
			);

			return $lock_handle;
		}

		// Failed to acquire lock.
		++$this->statistics['failed'];
		++$this->statistics['contention'];

		$lock_info = DistributedLock::get_info( $lock_name );
		$this->log_warning(
			sprintf(
				'Failed to acquire %s lock: %s. Current holder: %s',
				$type,
				$lock_name,
				$lock_info['server'] ?? 'unknown'
			),
			array_merge( $context, array( 'lock_info' => $lock_info ) )
		);

		return null;
	}

	/**
	 * Release a lock.
	 *
	 * @param array $lock_handle Lock handle from acquire().
	 * @return bool True if released.
	 */
	public function release( array $lock_handle ): bool {
		if ( ! isset( $lock_handle['name'], $lock_handle['value'] ) ) {
			$this->log_warning( 'Invalid lock handle provided to release()' );
			return false;
		}

		$lock_name  = $lock_handle['name'];
		$lock_value = $lock_handle['value'];

		$released = DistributedLock::release( $lock_name, $lock_value );

		if ( $released ) {
			unset( $this->active_locks[ $lock_name ] );
			++$this->statistics['released'];

			$held_duration = time() - ( $lock_handle['acquired_at'] ?? time() );
			$this->log_debug(
				sprintf( 'Lock released: %s (held for %ds)', $lock_name, $held_duration ),
				$lock_handle['context'] ?? array()
			);

			return true;
		}

		$this->log_warning(
			sprintf( 'Failed to release lock: %s (may have expired or been taken over)', $lock_name )
		);

		return false;
	}

	/**
	 * Extend a lock's timeout.
	 *
	 * @param array $lock_handle Lock handle from acquire().
	 * @param int   $extend_by   Seconds to extend by.
	 * @return bool True if extended.
	 */
	public function extend( array $lock_handle, int $extend_by ): bool {
		if ( ! isset( $lock_handle['name'], $lock_handle['value'] ) ) {
			return false;
		}

		$lock_name  = $lock_handle['name'];
		$lock_value = $lock_handle['value'];

		$extended = DistributedLock::extend( $lock_name, $lock_value, $extend_by );

		if ( $extended ) {
			if ( isset( $this->active_locks[ $lock_name ] ) ) {
				$this->active_locks[ $lock_name ]['expires_at'] = time() + $extend_by;
			}
			++$this->statistics['extended'];

			$this->log_debug(
				sprintf( 'Lock extended: %s (new expiry in %ds)', $lock_name, $extend_by )
			);

			return true;
		}

		return false;
	}

	/**
	 * Check if a resource is locked.
	 *
	 * @param string $resource Resource name.
	 * @param string $type     Lock type.
	 * @return bool True if locked.
	 */
	public function is_locked( string $resource, string $type = self::TYPE_RESOURCE ): bool {
		$lock_name = $this->get_lock_name( $resource, $type );
		return DistributedLock::is_locked( $lock_name );
	}

	/**
	 * Get lock information.
	 *
	 * @param string $resource Resource name.
	 * @param string $type     Lock type.
	 * @return array|null Lock info or null.
	 */
	public function get_lock_info( string $resource, string $type = self::TYPE_RESOURCE ): ?array {
		$lock_name = $this->get_lock_name( $resource, $type );
		return DistributedLock::get_info( $lock_name );
	}

	/**
	 * Acquire a batch processing lock.
	 *
	 * Convenience method for batch operations.
	 *
	 * @param string $batch_id Batch ID.
	 * @param int    $timeout  Optional custom timeout.
	 * @return array|null Lock handle or null.
	 */
	public function acquire_batch_lock( string $batch_id, int $timeout = 0 ): ?array {
		return $this->acquire(
			'batch_' . $batch_id,
			self::TYPE_BATCH,
			array( 'batch_id' => $batch_id ),
			$timeout
		);
	}

	/**
	 * Acquire a task execution lock.
	 *
	 * Convenience method for task operations.
	 *
	 * @param string $task_id Task ID.
	 * @param int    $timeout Optional custom timeout.
	 * @return array|null Lock handle or null.
	 */
	public function acquire_task_lock( string $task_id, int $timeout = 0 ): ?array {
		return $this->acquire(
			'task_' . $task_id,
			self::TYPE_TASK,
			array( 'task_id' => $task_id ),
			$timeout
		);
	}

	/**
	 * Acquire an API operation lock.
	 *
	 * Convenience method for API operations.
	 *
	 * @param string $operation Operation identifier.
	 * @param int    $timeout   Optional custom timeout.
	 * @return array|null Lock handle or null.
	 */
	public function acquire_api_lock( string $operation, int $timeout = 0 ): ?array {
		return $this->acquire(
			'api_' . $operation,
			self::TYPE_API,
			array( 'operation' => $operation ),
			$timeout
		);
	}

	/**
	 * Execute a callback with a lock.
	 *
	 * Automatically acquires and releases the lock.
	 *
	 * @param string   $resource Resource name.
	 * @param callable $callback Callback to execute.
	 * @param string   $type     Lock type.
	 * @param int      $timeout  Optional custom timeout.
	 * @return mixed Callback result or null if lock failed.
	 */
	public function with_lock( string $resource, callable $callback, string $type = self::TYPE_RESOURCE, int $timeout = 0 ) {
		$lock = $this->acquire( $resource, $type, array(), $timeout );

		if ( ! $lock ) {
			return null;
		}

		try {
			return $callback();
		} finally {
			$this->release( $lock );
		}
	}

	/**
	 * Release all locks held by this process.
	 *
	 * Useful for cleanup during shutdown.
	 *
	 * @return int Number of locks released.
	 */
	public function release_all(): int {
		$released_count = 0;

		foreach ( $this->active_locks as $lock_handle ) {
			if ( $this->release( $lock_handle ) ) {
				++$released_count;
			}
		}

		$this->log_debug( sprintf( 'Released %d active locks during cleanup', $released_count ) );

		return $released_count;
	}

	/**
	 * Get active locks held by this process.
	 *
	 * @return array Active locks.
	 */
	public function get_active_locks(): array {
		return $this->active_locks;
	}

	/**
	 * Get lock statistics.
	 *
	 * @return array Lock statistics.
	 */
	public function get_statistics(): array {
		return array_merge(
			$this->statistics,
			array(
				'active_count' => count( $this->active_locks ),
				'process_id'   => ProcessIdentifier::get(),
			)
		);
	}

	/**
	 * Cleanup expired locks.
	 *
	 * @return int Number of locks cleaned.
	 */
	public function cleanup_expired(): int {
		return DistributedLock::cleanup_expired();
	}

	/**
	 * Generate lock name with type prefix.
	 *
	 * @param string $resource Resource name.
	 * @param string $type     Lock type.
	 * @return string Lock name.
	 */
	private function get_lock_name( string $resource, string $type ): string {
		return $type . '_' . sanitize_key( $resource );
	}

	/**
	 * Generate unique lock value.
	 *
	 * @return string Lock value.
	 */
	private function generate_lock_value(): string {
		return ProcessIdentifier::get() . '_' . uniqid( '', true );
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message.
	 * @param array  $context Additional context.
	 */
	private function log_debug( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( class_exists( 'NuclearEngagement\\Services\\LoggingService' ) ) {
				LoggingService::log( '[LockManager] ' . $message, 'debug' );
			}
		}
	}

	/**
	 * Log warning message.
	 *
	 * @param string $message Message.
	 * @param array  $context Additional context.
	 */
	private function log_warning( string $message, array $context = array() ): void {
		if ( class_exists( 'NuclearEngagement\\Services\\LoggingService' ) ) {
			LoggingService::log( '[LockManager] ' . $message, 'warning' );
		}
	}

	/**
	 * Register shutdown handler to release locks.
	 */
	public function register_shutdown_handler(): void {
		register_shutdown_function( array( $this, 'release_all' ) );
	}
}
