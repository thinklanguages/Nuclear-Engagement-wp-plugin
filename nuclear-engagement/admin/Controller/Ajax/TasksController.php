<?php
/**
 * TasksController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Controller_Ajax
 */

declare(strict_types=1);
/**
 * File: admin/Controller/Ajax/TasksController.php
 *
 * AJAX controller for task management actions
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Services\TaskTransientManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for task management AJAX requests
 */
class TasksController extends BaseController {

	/**
	 * @var ServiceContainer
	 */
	private ServiceContainer $container;

	/**
	 * Constructor
	 *
	 * @param ServiceContainer $container
	 */
	public function __construct( ServiceContainer $container ) {
		$this->container = $container;
	}

	/**
	 * Handle run task request
	 */
	public function run_task(): void {
		// Custom nonce verification for tasks
		if ( ! current_user_can( 'manage_options' ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				'[TasksController::run_task] Access denied - insufficient permissions',
				'warning'
			);
			$this->send_error( __( 'Insufficient permissions', 'nuclear-engagement' ), 403 );
			return;
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclen_task_action' ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				'[TasksController::run_task] Security check failed - invalid nonce',
				'warning'
			);
			$this->send_error( __( 'Security check failed', 'nuclear-engagement' ), 403 );
			return;
		}

		// Rate limiting
		$user_id  = get_current_user_id();
		$rate_key = 'nuclen_task_action_' . $user_id;
		if ( get_transient( $rate_key ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::run_task] Rate limit hit for user %d', $user_id ),
				'warning'
			);
			$this->send_error( __( 'Please wait a few seconds before performing another action.', 'nuclear-engagement' ), 429 );
			return;
		}
		set_transient( $rate_key, true, 3 ); // 3-second cooldown

		$task_id = sanitize_text_field( $_POST['task_id'] ?? '' );
		if ( empty( $task_id ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				'[TasksController::run_task] Invalid task ID - empty value',
				'error'
			);
			$this->send_error( __( 'Invalid task ID', 'nuclear-engagement' ), 400 );
			return;
		}

		\NuclearEngagement\Services\LoggingService::log(
			sprintf( '[TasksController::run_task] Run task requested for ID: %s by user %d', $task_id, $user_id )
		);

		try {
			// Check if it's a batch task
			if ( strpos( $task_id, '_batch_' ) !== false ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::run_task] Processing batch task: %s', $task_id )
				);

				// Check batch status first
				$batch_data = TaskTransientManager::get_batch_transient( $task_id );
				if ( $batch_data && isset( $batch_data['status'] ) && $batch_data['status'] === 'processing' ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[TasksController::run_task] Batch %s is already processing - rejecting request', $task_id ),
						'warning'
					);
					$this->send_error( __( 'This batch is already processing. Please wait for it to complete.', 'nuclear-engagement' ), 400 );
					return;
				}

				// Trigger batch processing
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::run_task] Triggering batch processing for: %s', $task_id )
				);
				do_action( 'nuclen_process_batch', $task_id );

				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::run_task] SUCCESS: Batch %s triggered for processing', $task_id )
				);

				wp_send_json_success(
					array(
						'message' => sprintf( __( 'Batch %s has been triggered for immediate processing.', 'nuclear-engagement' ), $task_id ),
					)
				);
			} else {
				// Handle generation task
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::run_task] Processing generation task: %s', $task_id )
				);
				$this->run_generation_task( $task_id );
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::run_task] ERROR: Exception for task %s - %s', $task_id, $e->getMessage() ),
				'error'
			);
			$this->send_error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Handle cancel task request
	 */
	public function cancel_task(): void {
		// Custom nonce verification for tasks
		if ( ! current_user_can( 'manage_options' ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				'[TasksController::cancel_task] Access denied - insufficient permissions',
				'warning'
			);
			$this->send_error( __( 'Insufficient permissions', 'nuclear-engagement' ), 403 );
			return;
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclen_task_action' ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				'[TasksController::cancel_task] Security check failed - invalid nonce',
				'warning'
			);
			$this->send_error( __( 'Security check failed', 'nuclear-engagement' ), 403 );
			return;
		}

		$task_id = sanitize_text_field( $_POST['task_id'] ?? '' );
		if ( empty( $task_id ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				'[TasksController::cancel_task] Invalid task ID - empty value',
				'error'
			);
			$this->send_error( __( 'Invalid task ID', 'nuclear-engagement' ), 400 );
			return;
		}

		$user_id = get_current_user_id();
		\NuclearEngagement\Services\LoggingService::log(
			sprintf( '[TasksController::cancel_task] Cancel task requested for ID: %s by user %d', $task_id, $user_id )
		);

		try {
			// Check if it's a batch task
			if ( strpos( $task_id, '_batch_' ) !== false ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::cancel_task] Cancelling batch task: %s', $task_id )
				);
				$this->cancel_batch_task( $task_id );
			} else {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::cancel_task] Cancelling generation task: %s', $task_id )
				);
				$this->cancel_generation_task( $task_id );
			}

			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::cancel_task] SUCCESS: Task %s has been cancelled', $task_id )
			);

			wp_send_json_success(
				array(
					'message' => sprintf( __( 'Task %s has been cancelled.', 'nuclear-engagement' ), $task_id ),
				)
			);
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::cancel_task] ERROR: Exception for task %s - %s', $task_id, $e->getMessage() ),
				'error'
			);
			$this->send_error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Get task status
	 */
	public function get_task_status(): void {
		// Custom nonce verification for tasks
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'Insufficient permissions', 'nuclear-engagement' ), 403 );
			return;
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclen_task_action' ) ) {
			$this->send_error( __( 'Security check failed', 'nuclear-engagement' ), 403 );
			return;
		}

		$task_id = sanitize_text_field( $_POST['task_id'] ?? '' );
		if ( empty( $task_id ) ) {
			$this->send_error( __( 'Invalid task ID', 'nuclear-engagement' ), 400 );
			return;
		}

		try {
			$status = $this->get_task_current_status( $task_id );
			wp_send_json_success( $status );
		} catch ( \Exception $e ) {
			$this->send_error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Run a generation task
	 */
	private function run_generation_task( string $task_id ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf( '[TasksController::run_generation_task] Starting generation task: %s', $task_id )
		);

		try {
			$batch_data = TaskTransientManager::get_task_transient( $task_id );
			if ( ! $batch_data || ! isset( $batch_data['workflow_type'] ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::run_generation_task] ERROR: Task %s not found or invalid data', $task_id ),
					'error'
				);
				throw new \Exception( __( 'Task not found or invalid data', 'nuclear-engagement' ) );
			}

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[TasksController::run_generation_task] Task %s found - Workflow: %s, Status: %s, Batches: %d',
					$task_id,
					$batch_data['workflow_type'],
					$batch_data['status'] ?? 'unknown',
					count( $batch_data['batch_jobs'] ?? array() )
				)
			);

			// Check if task is already processing
			if ( isset( $batch_data['status'] ) && $batch_data['status'] === 'processing' ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::run_generation_task] Task %s is already processing - rejecting request', $task_id ),
					'warning'
				);
				throw new \Exception( __( 'This task is already processing. Please wait for it to complete or cancel it first.', 'nuclear-engagement' ) );
			}
		} catch ( \Exception $e ) {
			throw new \Exception( sprintf( __( 'Failed to retrieve task data: %s', 'nuclear-engagement' ), $e->getMessage() ) );
		}

		// Extract post IDs from batches
		$all_post_ids   = array();
		$active_batches = 0;
		foreach ( $batch_data['batch_jobs'] ?? array() as $batch ) {
			$batch_info = TaskTransientManager::get_batch_transient( $batch['batch_id'] );
			if ( $batch_info && isset( $batch_info['posts'] ) ) {
				foreach ( $batch_info['posts'] as $post ) {
					if ( isset( $post['post_id'] ) ) {
						$all_post_ids[] = $post['post_id'];
					} elseif ( isset( $post['id'] ) ) {
						$all_post_ids[] = $post['id'];
					}
				}
				if ( $batch['status'] !== 'completed' ) {
					++$active_batches;
				}
			}
		}

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[TasksController::run_generation_task] Task %s has %d total posts, %d active batches',
				$task_id,
				count( $all_post_ids ),
				$active_batches
			)
		);

		// Add to centralized polling queue if available
		if ( $this->container->has( 'centralized_polling_queue' ) ) {
			$queue = $this->container->get( 'centralized_polling_queue' );
			$queue->add_to_queue( $task_id, $batch_data['workflow_type'], $all_post_ids, 1 ); // High priority
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::run_generation_task] Added task %s to centralized polling queue', $task_id )
			);
		}

		// Trigger immediate processing for all batches
		$triggered_count = 0;
		foreach ( $batch_data['batch_jobs'] ?? array() as $batch ) {
			if ( $batch['status'] !== 'completed' ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[TasksController::run_generation_task] Triggering batch %s for task %s',
						$batch['batch_id'],
						$task_id
					)
				);
				do_action( 'nuclen_process_batch', $batch['batch_id'] );
				++$triggered_count;
			}
		}

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[TasksController::run_generation_task] SUCCESS: Task %s queued - Triggered %d batches for processing',
				$task_id,
				$triggered_count
			)
		);

		wp_send_json_success(
			array(
				'message' => sprintf( __( 'Generation %s has been queued for immediate processing.', 'nuclear-engagement' ), $task_id ),
			)
		);
	}

	/**
	 * Cancel a batch task
	 */
	private function cancel_batch_task( string $task_id ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf( '[TasksController::cancel_batch_task] Attempting to cancel batch: %s', $task_id )
		);

		$batch_data = TaskTransientManager::get_batch_transient( $task_id );
		if ( $batch_data ) {
			$old_status = $batch_data['status'] ?? 'unknown';
			$batch_data['status'] = 'cancelled';
			TaskTransientManager::set_batch_transient( $task_id, $batch_data, DAY_IN_SECONDS );

			// Clear any scheduled events
			wp_clear_scheduled_hook( 'nuclen_process_batch', array( $task_id ) );

			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::cancel_batch_task] Batch %s cancelled (was: %s)', $task_id, $old_status )
			);
		} else {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::cancel_batch_task] Batch %s not found', $task_id ),
				'warning'
			);
		}
	}

	/**
	 * Cancel a generation task
	 */
	private function cancel_generation_task( string $task_id ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf( '[TasksController::cancel_generation_task] Attempting to cancel generation: %s', $task_id )
		);

		$batch_data = TaskTransientManager::get_task_transient( $task_id );
		if ( $batch_data ) {
			$old_status = $batch_data['status'] ?? 'unknown';
			$batch_data['status'] = 'cancelled';
			TaskTransientManager::set_task_transient( $task_id, $batch_data, DAY_IN_SECONDS );

			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::cancel_generation_task] Generation %s status updated to cancelled (was: %s)', $task_id, $old_status )
			);

			// Cancel all batches
			$batch_count = count( $batch_data['batch_jobs'] ?? array() );
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::cancel_generation_task] Cancelling %d batches for generation %s', $batch_count, $task_id )
			);

			foreach ( $batch_data['batch_jobs'] ?? array() as &$batch ) {
				$this->cancel_batch_task( $batch['batch_id'] );
				// Update batch status in the parent data as well
				$batch['status'] = 'cancelled';
			}
			
			// Save the updated batch data back to the transient
			TaskTransientManager::set_task_transient( $task_id, $batch_data, DAY_IN_SECONDS );

			// Remove from polling queue
			if ( $this->container->has( 'centralized_polling_queue' ) ) {
				$queue = $this->container->get( 'centralized_polling_queue' );
				$queue->mark_generation_complete( $task_id );
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::cancel_generation_task] Removed generation %s from polling queue', $task_id )
				);
			}

			// Update task index
			if ( $this->container->has( 'task_index_service' ) ) {
				$task_index_service = $this->container->get( 'task_index_service' );
				$task_index_service->update_task_status( $task_id, 'cancelled' );
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[TasksController::cancel_generation_task] Updated task index for generation %s', $task_id )
				);
			}

			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::cancel_generation_task] Generation %s successfully cancelled', $task_id )
			);
		} else {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[TasksController::cancel_generation_task] Generation %s not found', $task_id ),
				'warning'
			);
		}
	}

	/**
	 * Get current task status
	 */
	private function get_task_current_status( string $task_id ): array {
		if ( strpos( $task_id, '_batch_' ) !== false ) {
			$batch_data = TaskTransientManager::get_batch_transient( $task_id );
			if ( ! $batch_data ) {
				throw new \Exception( __( 'Task not found', 'nuclear-engagement' ) );
			}

			return array(
				'status' => $batch_data['status'] ?? 'unknown',
				'type'   => 'batch',
			);
		} else {
			$job_data = TaskTransientManager::get_task_transient( $task_id );
			if ( ! $job_data ) {
				throw new \Exception( __( 'Task not found', 'nuclear-engagement' ) );
			}

			// Calculate progress
			$processed = 0;
			$failed    = 0;

			foreach ( $job_data['batch_jobs'] ?? array() as $batch ) {
				$batch_data = TaskTransientManager::get_batch_transient( $batch['batch_id'] );
				if ( $batch_data ) {
					if ( $batch_data['status'] === 'completed' ) {
						$processed += $batch['post_count'];
					} elseif ( $batch_data['status'] === 'failed' ) {
						$failed += $batch['post_count'];
					}
				}
			}

			$total    = $job_data['total_posts'] ?? 0;
			$progress = $total > 0 ? round( ( $processed / $total ) * 100 ) : 0;

			return array(
				'status'    => $job_data['status'] ?? 'unknown',
				'type'      => 'generation',
				'progress'  => $progress,
				'processed' => $processed,
				'failed'    => $failed,
				'total'     => $total,
			);
		}
	}

	/**
	 * Get recent task completions
	 */
	public function get_recent_completions(): void {
		// Custom nonce verification for tasks
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'Insufficient permissions', 'nuclear-engagement' ), 403 );
			return;
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclen_task_action' ) ) {
			$this->send_error( __( 'Security check failed', 'nuclear-engagement' ), 403 );
			return;
		}

		try {
			// Get and clear recent completions transient
			$recent_completions = get_transient( 'nuclen_recent_completions' );

			if ( $recent_completions && is_array( $recent_completions ) ) {
				// Clear the transient after reading
				delete_transient( 'nuclen_recent_completions' );

				wp_send_json_success( $recent_completions );
			} else {
				wp_send_json_success( array() );
			}
		} catch ( \Exception $e ) {
			$this->send_error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Get all tasks data for refresh
	 */
	public function refresh_tasks_data(): void {
		// Custom nonce verification for tasks
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'Insufficient permissions', 'nuclear-engagement' ), 403 );
			return;
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclen_task_action' ) ) {
			$this->send_error( __( 'Security check failed', 'nuclear-engagement' ), 403 );
			return;
		}

		try {
			// Get the TaskIndexService to fetch updated task data
			if ( ! $this->container->has( 'task_index_service' ) ) {
				throw new \Exception( __( 'Task service not available', 'nuclear-engagement' ) );
			}

			$task_service = $this->container->get( 'task_index_service' );
			$page         = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
			$per_page     = 20;

			// Get updated task data
			$generation_tasks = $task_service->get_generation_tasks( $page, $per_page );

			// Format the response data
			$tasks_data = array();
			foreach ( $generation_tasks['tasks'] as $task ) {
				$tasks_data[] = array(
					'id'             => $task['id'],
					'workflow_type'  => $task['workflow_type'],
					'status'         => $task['status'],
					'progress'       => $task['progress'],
					'details'        => $task['details'],
					'created_at'     => $task['created_at'],
					'failed'         => $task['failed'] ?? 0,
				);
			}

			wp_send_json_success(
				array(
					'tasks'      => $tasks_data,
					'pagination' => $generation_tasks['pagination'],
					'timestamp'  => current_time( 'timestamp' ),
				)
			);
		} catch ( \Exception $e ) {
			$this->send_error( $e->getMessage(), 500 );
		}
	}
}
