<?php
/**
 * TaskTimeoutHandler.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\BaseService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles task timeouts and stuck task detection
 */
class TaskTimeoutHandler extends BaseService {

	/**
	 * Default timeouts in seconds
	 */
	private const DEFAULT_TIMEOUTS = array(
		'pending'    => 1800,      // 30 minutes for pending tasks
		'processing' => 3600,   // 1 hour for processing tasks
		'polling'    => 1800,      // 30 minutes for polling operations
	);

	/**
	 * Maximum allowed processing time before forced failure
	 */
	private const MAX_PROCESSING_TIME = 7200; // 2 hours

	/**
	 * Hook name for timeout check
	 */
	private const TIMEOUT_CHECK_HOOK = 'nuclen_check_task_timeouts';

	/**
	 * Track if hooks are already registered
	 */
	private static $hooks_registered = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->cache_ttl = 600; // 10 minutes
	}

	/**
	 * Register hooks
	 */
	public function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		// Schedule regular timeout checks
		if ( ! wp_next_scheduled( self::TIMEOUT_CHECK_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::TIMEOUT_CHECK_HOOK );
		}

		add_action( self::TIMEOUT_CHECK_HOOK, array( $this, 'check_timeouts' ) );
		add_action( 'nuclen_task_started', array( $this, 'record_task_start' ), 10, 2 );
		add_action( 'nuclen_task_completed', array( $this, 'clear_task_timeout' ), 10, 1 );

		self::$hooks_registered = true;
	}

	/**
	 * Check for timed out tasks
	 */
	public function check_timeouts(): void {
		LoggingService::log( '[TaskTimeoutHandler] Starting timeout check' );

		$timed_out_count = 0;

		// Check generation tasks
		$timed_out_count += $this->check_generation_timeouts();

		// Check batch tasks
		$timed_out_count += $this->check_batch_timeouts();

		// Clean up old timeout records
		$this->cleanup_old_records();

		LoggingService::log( sprintf( '[TaskTimeoutHandler] Timeout check complete. Found %d timed out tasks', $timed_out_count ) );
	}

	/**
	 * Check generation tasks for timeouts
	 *
	 * @return int Number of timed out tasks
	 */
	private function check_generation_timeouts(): int {
		global $wpdb;

		$count = 0;

		// Find all generation tasks
		$tasks = $wpdb->get_results(
			"SELECT option_name, option_value FROM $wpdb->options 
			WHERE option_name LIKE '_transient_nuclen_bulk_job_%' 
			AND option_name NOT LIKE '_transient_timeout_%'
			LIMIT 100"
		);

		foreach ( $tasks as $task ) {
			$task_id   = str_replace( '_transient_nuclen_bulk_job_', '', $task->option_name );
			$task_data = maybe_unserialize( $task->option_value );

			if ( ! is_array( $task_data ) ) {
				continue;
			}

			if ( $this->is_task_timed_out( $task_id, $task_data ) ) {
				$this->handle_timed_out_task( $task_id, $task_data, 'generation' );
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Check batch tasks for timeouts
	 *
	 * @return int Number of timed out tasks
	 */
	private function check_batch_timeouts(): int {
		global $wpdb;

		$count = 0;

		// Find all batch tasks
		$batches = $wpdb->get_results(
			"SELECT option_name, option_value FROM $wpdb->options 
			WHERE option_name LIKE '_transient_nuclen_batch_%' 
			AND option_name NOT LIKE '_transient_timeout_%'
			AND option_name NOT LIKE '%_results_%'
			LIMIT 200"
		);

		foreach ( $batches as $batch ) {
			$batch_id   = str_replace( '_transient_nuclen_batch_', '', $batch->option_name );
			$batch_data = maybe_unserialize( $batch->option_value );

			if ( ! is_array( $batch_data ) ) {
				continue;
			}

			if ( $this->is_batch_timed_out( $batch_id, $batch_data ) ) {
				$this->handle_timed_out_batch( $batch_id, $batch_data );
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Check if a task is timed out
	 *
	 * @param string $task_id Task ID
	 * @param array  $task_data Task data
	 * @return bool
	 */
	private function is_task_timed_out( string $task_id, array $task_data ): bool {
		$status = $task_data['status'] ?? 'unknown';

		// Only check active statuses
		if ( ! in_array( $status, array( 'pending', 'processing' ), true ) ) {
			return false;
		}

		// Get timeout record
		$timeout_data = $this->get_timeout_record( $task_id );

		// If no timeout record, check based on created_at
		if ( ! $timeout_data ) {
			$created_at = $task_data['created_at'] ?? 0;
			if ( $created_at === 0 ) {
				return false;
			}

			$age     = time() - $created_at;
			$timeout = $this->get_timeout_for_status( $status );

			return $age > $timeout;
		}

		// Check based on timeout record
		$started_at = $timeout_data['started_at'] ?? 0;
		$timeout    = $timeout_data['timeout'] ?? $this->get_timeout_for_status( $status );

		return ( time() - $started_at ) > $timeout;
	}

	/**
	 * Check if a batch is timed out
	 *
	 * @param string $batch_id Batch ID
	 * @param array  $batch_data Batch data
	 * @return bool
	 */
	private function is_batch_timed_out( string $batch_id, array $batch_data ): bool {
		$status = $batch_data['status'] ?? 'unknown';

		// Only check active statuses
		if ( ! in_array( $status, array( 'pending', 'processing' ), true ) ) {
			return false;
		}

		// Check if batch has been stuck for too long
		$updated_at = $batch_data['updated_at'] ?? $batch_data['created_at'] ?? 0;
		if ( $updated_at === 0 ) {
			return false;
		}

		$age = time() - $updated_at;

		// Different timeouts based on status
		if ( $status === 'processing' ) {
			// Processing batches should complete within 1 hour
			return $age > 3600;
		} else {
			// Pending batches should start within 30 minutes
			return $age > 1800;
		}
	}

	/**
	 * Handle a timed out task
	 *
	 * @param string $task_id Task ID
	 * @param array  $task_data Task data
	 * @param string $type Task type
	 */
	private function handle_timed_out_task( string $task_id, array $task_data, string $type ): void {
		try {
			LoggingService::log(
				sprintf(
					'[TaskTimeoutHandler] Task %s (type: %s) timed out. Status: %s, Age: %d seconds',
					$task_id,
					$type,
					$task_data['status'] ?? 'unknown',
					time() - ( $task_data['created_at'] ?? 0 )
				),
				'error'
			);

			// Update task status to failed
			$task_data['status']       = 'failed';
			$task_data['error']        = 'Task timed out';
			$task_data['timed_out_at'] = time();

			$result = TaskTransientManager::set_task_transient( $task_id, $task_data, DAY_IN_SECONDS );

			if ( ! $result ) {
				LoggingService::log(
					sprintf( '[TaskTimeoutHandler] Failed to update task status for %s', $task_id ),
					'error'
				);
			}

			// Update task index
			try {
				$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
				if ( $container->has( 'task_index_service' ) ) {
					$index_service = $container->get( 'task_index_service' );
					$index_service->update_task_status(
						$task_id,
						'failed',
						array(
							'error'        => 'Task timed out',
							'timed_out_at' => time(),
						)
					);
				}
			} catch ( \Throwable $e ) {
				LoggingService::log(
					sprintf( '[TaskTimeoutHandler] Failed to update task index: %s', $e->getMessage() ),
					'error'
				);
			}

			// Add admin notice
			try {
				if ( $container && $container->has( 'admin_notice_service' ) ) {
					$notice_service = $container->get( 'admin_notice_service' );
					$notice_service->add(
						sprintf( __( 'Generation task %s timed out and was marked as failed.', 'nuclear-engagement' ), $task_id ),
						'error'
					);
				}
			} catch ( \Throwable $e ) {
				// Log but don't fail the whole operation
				LoggingService::log(
					sprintf( '[TaskTimeoutHandler] Failed to add admin notice: %s', $e->getMessage() )
				);
			}

			// Clear timeout record
			$this->clear_task_timeout( $task_id );

		} catch ( \Throwable $e ) {
			LoggingService::log(
				sprintf(
					'[TaskTimeoutHandler] Critical error handling timed out task %s: %s',
					$task_id,
					$e->getMessage()
				),
				'error'
			);
		}
	}

	/**
	 * Handle a timed out batch
	 *
	 * @param string $batch_id Batch ID
	 * @param array  $batch_data Batch data
	 */
	private function handle_timed_out_batch( string $batch_id, array $batch_data ): void {
		LoggingService::log(
			sprintf(
				'[TaskTimeoutHandler] Batch %s timed out. Status: %s, Age: %d seconds',
				$batch_id,
				$batch_data['status'] ?? 'unknown',
				time() - ( $batch_data['updated_at'] ?? $batch_data['created_at'] ?? 0 )
			),
			'error'
		);

		// Get batch processor to handle the failure
		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		if ( $container->has( 'bulk_generation_batch_processor' ) ) {
			$processor = $container->get( 'bulk_generation_batch_processor' );
			$processor->update_batch_status(
				$batch_id,
				'failed',
				array(
					'error'     => 'Batch processing timed out',
					'timed_out' => true,
				)
			);
		}
	}

	/**
	 * Record task start time
	 *
	 * @param string $task_id Task ID
	 * @param int    $timeout Timeout in seconds
	 */
	public function record_task_start( string $task_id, int $timeout = 0 ): void {
		if ( $timeout === 0 ) {
			$timeout = self::DEFAULT_TIMEOUTS['processing'];
		}

		$timeout_data = array(
			'task_id'    => $task_id,
			'started_at' => time(),
			'timeout'    => $timeout,
		);

		set_transient( 'nuclen_timeout_' . $task_id, $timeout_data, $timeout * 2 );
	}

	/**
	 * Clear task timeout record
	 *
	 * @param string $task_id Task ID
	 */
	public function clear_task_timeout( string $task_id ): void {
		delete_transient( 'nuclen_timeout_' . $task_id );
	}

	/**
	 * Get timeout record for a task
	 *
	 * @param string $task_id Task ID
	 * @return array|false
	 */
	private function get_timeout_record( string $task_id ) {
		return get_transient( 'nuclen_timeout_' . $task_id );
	}

	/**
	 * Get timeout for a given status
	 *
	 * @param string $status Task status
	 * @return int Timeout in seconds
	 */
	private function get_timeout_for_status( string $status ): int {
		return self::DEFAULT_TIMEOUTS[ $status ] ?? self::DEFAULT_TIMEOUTS['processing'];
	}

	/**
	 * Clean up old timeout records
	 */
	private function cleanup_old_records(): void {
		global $wpdb;

		// Clean up timeout records older than 24 hours
		$cutoff = time() - DAY_IN_SECONDS;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_value LIKE %s",
				'_transient_nuclen_timeout_%',
				'%"started_at";i:' . $cutoff . '%'
			)
		);
	}

	/**
	 * Validate task state transitions
	 *
	 * @param string $current_state Current state
	 * @param string $new_state New state
	 * @return bool Whether transition is valid
	 */
	public function validate_state_transition( string $current_state, string $new_state ): bool {
		// Define valid state transitions
		$valid_transitions = array(
			'pending'               => array( 'processing', 'failed', 'cancelled' ),
			'processing'            => array( 'completed', 'completed_with_errors', 'failed' ),
			'completed'             => array(), // Terminal state
			'completed_with_errors' => array(), // Terminal state
			'failed'                => array( 'pending' ), // Can retry
			'cancelled'             => array(), // Terminal state
		);

		// Check if transition is valid
		if ( ! isset( $valid_transitions[ $current_state ] ) ) {
			return false; // Unknown current state
		}

		return in_array( $new_state, $valid_transitions[ $current_state ], true );
	}

	/**
	 * Get service name for logging and caching.
	 *
	 * @return string Service name.
	 */
	protected function get_service_name(): string {
		return 'task_timeout_handler';
	}

	/**
	 * Force expire stuck tasks
	 *
	 * @param int $age_hours Age in hours
	 * @return int Number of tasks expired
	 */
	public function force_expire_stuck_tasks( int $age_hours = 24 ): int {
		$count  = 0;
		$cutoff = time() - ( $age_hours * HOUR_IN_SECONDS );

		// Get task index
		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		if ( ! $container->has( 'task_index_service' ) ) {
			return 0;
		}

		$index_service = $container->get( 'task_index_service' );
		$all_tasks     = $index_service->get_paginated_tasks( 1, 1000 );

		foreach ( $all_tasks['tasks'] as $task ) {
			// Only check non-terminal states
			if ( ! in_array( $task['status'], array( 'pending', 'processing' ), true ) ) {
				continue;
			}

			$created_at = $task['created_at'] ?? 0;
			if ( $created_at < $cutoff ) {
				$this->handle_timed_out_task( $task['id'], $task, 'forced' );
				++$count;
			}
		}

		LoggingService::log( sprintf( '[TaskTimeoutHandler] Force expired %d stuck tasks older than %d hours', $count, $age_hours ) );

		return $count;
	}
}
