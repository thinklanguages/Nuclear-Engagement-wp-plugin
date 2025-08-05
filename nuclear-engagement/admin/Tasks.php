<?php
/**
 * Tasks.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin
 */

declare(strict_types=1);
/**
 * File: admin/Tasks.php
 *
 * Handles the Tasks page for viewing and managing generation tasks.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\ServiceContainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tasks {
	/**
	 * Task constants
	 */
	private const TASK_TRANSIENT_EXPIRY = DAY_IN_SECONDS;
	private const MAX_TASKS_DISPLAY     = 50;
	private const TASKS_PER_PAGE        = 20;
	private const CACHE_EXPIRY          = 300; // 5 minutes

	/** Settings repository instance. */
	private $settings_repo;

	/** Service container. */
	private $container;

	public function __construct( SettingsRepository $settings_repo, ServiceContainer $container ) {
		$this->settings_repo = $settings_repo;
		$this->container     = $container;
	}

	/**
	 * Handle early redirects before headers are sent.
	 * This method should be called from admin_init hook.
	 */
	public function handle_early_redirects(): void {
		// Only process on our tasks page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'nuclear-engagement-tasks' ) {
			return;
		}

		// Check if refresh requested
		if ( isset( $_GET['refresh'] ) && $_GET['refresh'] === '1' ) {
			// Clear cache before redirect
			self::clear_tasks_cache();
			
			// Also rebuild task index on refresh to ensure consistency
			if ( $this->container->has( 'task_index_service' ) ) {
				$index_service = $this->container->get( 'task_index_service' );
				$index_service->rebuild_index();
			}

			// Remove refresh parameter and redirect to avoid re-refresh on page reload
			$redirect_url = remove_query_arg( 'refresh' );

			// Ensure we have a valid redirect URL
			if ( empty( $redirect_url ) ) {
				$redirect_url = admin_url( 'admin.php?page=nuclear-engagement-tasks' );
			}

			// Log redirect attempt for debugging
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[Tasks::handle_early_redirects] Redirecting to: %s', $redirect_url )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Render the tasks page.
	 */
	public function render(): void {
		// Handle manual task actions
		$this->handle_task_actions();

		// Gather task data
		$data = $this->gather_tasks_data();

		// Render the view
		$this->render_tasks_view( $data );
	}


	/**
	 * Handle manual task actions (run now, cancel, recover)
	 */
	private function handle_task_actions(): void {
		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'nuclen_task_action' ) ) {
			wp_die( __( 'Security check failed.', 'nuclear-engagement' ) );
		}

		$action = sanitize_text_field( $_GET['action'] );

		if ( isset( $_GET['task_id'] ) ) {
			// Handle individual task actions
			$task_id = sanitize_text_field( $_GET['task_id'] );

			switch ( $action ) {
				case 'run_now':
					$this->run_task_now( $task_id );
					break;
				case 'cancel':
					$this->cancel_task( $task_id );
					break;
				case 'retry':
					$this->retry_task( $task_id );
					break;
			}
		} elseif ( $action === 'reset_circuit_breaker' ) {
			// Handle circuit breaker reset
			$this->reset_circuit_breaker();
		}

		// Clear cache after any action
		self::clear_tasks_cache();

		// Redirect to remove action parameters but preserve paged
		$redirect_url = remove_query_arg( array( 'action', 'task_id', '_wpnonce' ) );
		if ( isset( $_GET['paged'] ) ) {
			$redirect_url = add_query_arg( 'paged', intval( $_GET['paged'] ), $redirect_url );
		}
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get circuit breaker status
	 */
	private function get_circuit_breaker_status(): array {
		$remote_api_breaker = new \NuclearEngagement\Services\CircuitBreaker( 'remote_api' );
		$remote_status = $remote_api_breaker->get_status();
		
		$api_breaker = new \NuclearEngagement\Services\CircuitBreaker( 'api' );
		$api_status = $api_breaker->get_status();
		
		// Return the most relevant status
		if ( $remote_status['is_open'] || $api_status['is_open'] ) {
			return array(
				'is_open' => true,
				'status' => $remote_status['is_open'] ? $remote_status['status'] : $api_status['status'],
				'time_until_retry' => max( $remote_status['time_until_retry'], $api_status['time_until_retry'] ),
				'failures' => max( $remote_status['failures'], $api_status['failures'] ),
			);
		}
		
		return array(
			'is_open' => false,
			'status' => 'closed',
			'time_until_retry' => 0,
			'failures' => 0,
		);
	}

	/**
	 * Reset the circuit breaker
	 */
	private function reset_circuit_breaker(): void {
		// Reset the circuit breaker
		$circuit_breaker = new \NuclearEngagement\Services\CircuitBreaker( 'remote_api' );
		$circuit_breaker->force_reset();
		
		// Also reset the legacy 'api' circuit breaker if it exists
		$api_circuit_breaker = new \NuclearEngagement\Services\CircuitBreaker( 'api' );
		$api_circuit_breaker->force_reset();
		
		// Clear any circuit breaker service cache
		if ( class_exists( '\NuclearEngagement\Services\CircuitBreakerService' ) ) {
			\NuclearEngagement\Services\CircuitBreakerService::reset_all();
		}
		
		// Log the reset
		\NuclearEngagement\Services\LoggingService::log(
			'[Tasks::reset_circuit_breaker] Circuit breaker reset manually by admin',
			'info'
		);
		
		// Add admin notice
		$this->add_admin_notice(
			__( 'Circuit breaker has been reset. API calls can now proceed.', 'nuclear-engagement' ),
			'success'
		);
	}

	/**
	 * Run a task immediately
	 */
	private function run_task_now( string $task_id ): void {
		// Check if it's a batch task
		if ( strpos( $task_id, '_batch_' ) !== false ) {
			// Trigger batch processing
			do_action( 'nuclen_process_batch', $task_id );

			// Add admin notice
			$this->add_admin_notice(
				sprintf( __( 'Batch %s has been triggered for immediate processing.', 'nuclear-engagement' ), esc_html( $task_id ) ),
				'success'
			);
		} else {
			// It's a regular generation - trigger polling
			$batch_data = get_transient( 'nuclen_bulk_job_' . $task_id );
			if ( $batch_data && isset( $batch_data['workflow_type'] ) ) {
				// Extract post IDs from batches
				$all_post_ids = array();
				foreach ( $batch_data['batch_jobs'] as $batch ) {
					$batch_info = get_transient( 'nuclen_batch_' . $batch['batch_id'] );
					if ( $batch_info && isset( $batch_info['posts'] ) ) {
						foreach ( $batch_info['posts'] as $post ) {
							if ( isset( $post['post_id'] ) ) {
								$all_post_ids[] = $post['post_id'];
							} elseif ( isset( $post['id'] ) ) {
								$all_post_ids[] = $post['id'];
							}
						}
					}
				}

				// Add to centralized polling queue if available
				if ( $this->container->has( 'centralized_polling_queue' ) ) {
					$queue = $this->container->get( 'centralized_polling_queue' );
					$queue->add_to_queue( $task_id, $batch_data['workflow_type'], $all_post_ids, 1 ); // High priority
				}
			}
		}
	}

	/**
	 * Retry a failed task
	 */
	private function retry_task( string $task_id ): void {
		// Get task data
		$task_data = \NuclearEngagement\Services\TaskTransientManager::get_task_transient( $task_id );
		if ( ! $task_data || ! in_array( $task_data['status'] ?? '', array( 'failed', 'cancelled' ), true ) ) {
			$this->add_admin_notice(
				__( 'Task cannot be retried in its current state.', 'nuclear-engagement' ),
				'error'
			);
			return;
		}

		// Reset task status to pending
		$task_data['status']     = 'pending';
		$task_data['error']      = null;
		$task_data['retry_at']   = time();
		$task_data['retried_by'] = get_current_user_id();

		\NuclearEngagement\Services\TaskTransientManager::set_task_transient( $task_id, $task_data, DAY_IN_SECONDS );

		// Update task index if available
		if ( $this->container->has( 'task_index_service' ) ) {
			$index_service = $this->container->get( 'task_index_service' );
			$index_service->update_task_status( $task_id, 'pending' );
		}

		// Trigger immediate processing
		if ( $this->container->has( 'bulk_generation_batch_processor' ) ) {
			$processor = $this->container->get( 'bulk_generation_batch_processor' );
			$processor->schedule_next_batch( $task_id );
		}

		$this->add_admin_notice(
			sprintf( __( 'Task %s has been queued for retry.', 'nuclear-engagement' ), esc_html( $task_id ) ),
			'success'
		);
	}




	/**
	 * Cancel a task
	 */
	private function cancel_task( string $task_id ): void {
		// Check if it's a batch task
		if ( strpos( $task_id, '_batch_' ) !== false ) {
			// Update batch status
			$batch_data = get_transient( 'nuclen_batch_' . $task_id );
			if ( $batch_data ) {
				$batch_data['status'] = 'cancelled';
				set_transient( 'nuclen_batch_' . $task_id, $batch_data, DAY_IN_SECONDS );

				// Clear any scheduled events
				wp_clear_scheduled_hook( 'nuclen_process_batch', array( $task_id ) );

				$this->add_admin_notice(
					sprintf( __( 'Batch %s has been cancelled.', 'nuclear-engagement' ), esc_html( $task_id ) ),
					'info'
				);
			}
		} else {
			// Cancel generation
			$batch_data = get_transient( 'nuclen_bulk_job_' . $task_id );
			if ( $batch_data ) {
				$batch_data['status'] = 'cancelled';
				set_transient( 'nuclen_bulk_job_' . $task_id, $batch_data, DAY_IN_SECONDS );

				// Cancel all batches
				foreach ( $batch_data['batch_jobs'] as $batch ) {
					$this->cancel_task( $batch['batch_id'] );
				}

				// Remove from polling queue
				if ( $this->container->has( 'centralized_polling_queue' ) ) {
					$queue = $this->container->get( 'centralized_polling_queue' );
					$queue->mark_generation_complete( $task_id );
				}

				$this->add_admin_notice(
					sprintf( __( 'Generation %s has been cancelled.', 'nuclear-engagement' ), $task_id ),
					'info'
				);
			}
		}
	}

	/**
	 * Add admin notice
	 */
	private function add_admin_notice( string $message, string $type = 'info' ): void {
		if ( $this->container->has( 'admin_notice_service' ) ) {
			$notice_service = $this->container->get( 'admin_notice_service' );
			$notice_service->add( $message );
		}
	}

	/**
	 * Clear tasks cache for all users
	 */
	public static function clear_tasks_cache(): void {
		global $wpdb;

		// Clear cache for all users and pages
		$wpdb->query(
			"DELETE FROM $wpdb->options 
			WHERE option_name LIKE '_transient_nuclen_tasks_%' 
			OR option_name LIKE '_transient_timeout_nuclen_tasks_%'"
		);

		// Also clear from object cache
		$cache_group = '';
		for ( $page = 1; $page <= 10; $page++ ) {
			// Clear for different user IDs (we'll clear for common user IDs)
			for ( $user_id = 1; $user_id <= 10; $user_id++ ) {
				wp_cache_delete( 'nuclen_tasks_' . $user_id . '_page_' . $page, $cache_group );
			}
		}

		\NuclearEngagement\Services\LoggingService::log( '[Tasks::clear_tasks_cache] Cleared all tasks cache entries' );
	}

	/**
	 * Gather all task data
	 */
	private function gather_tasks_data(): array {

		// Get current page
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

		// Initialize data array
		$data = array(
			'generation_tasks'       => array(),
			'credits'                => $this->get_credits_data(), // Get credits separately (not cached per page)
			'cron_status'            => $this->check_cron_status(),
			'circuit_breaker_status' => $this->get_circuit_breaker_status(),
			'pagination'             => array(),
			'has_task_index_service' => $this->container->has( 'task_index_service' ),
		);

		// Use TaskIndexService for efficient task retrieval
		if ( $this->container->has( 'task_index_service' ) ) {
			$index_service = $this->container->get( 'task_index_service' );

			// Clean up orphaned tasks periodically (once per user session)
			$cleanup_key = 'nuclen_task_cleanup_' . get_current_user_id();
			if ( false === get_transient( $cleanup_key ) ) {
				$index_service->cleanup_orphaned_tasks();
				$index_service->cleanup_batch_tasks();
				set_transient( $cleanup_key, true, HOUR_IN_SECONDS );
			}

			// Get filters if any
			$filters = array();
			if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
				$filters['status'] = sanitize_text_field( $_GET['status'] );
			}

			// Get paginated tasks
			$result = $index_service->get_paginated_tasks( $current_page, self::TASKS_PER_PAGE, $filters );

			// Process tasks for display
			$tasks = array();
			foreach ( $result['tasks'] as $task_data ) {
				$tasks[] = $this->format_task_for_display( $task_data );
			}

			$data['generation_tasks'] = $tasks;
			$data['pagination']       = array(
				'total_items'  => $result['total'],
				'total'        => $result['total'],
				'current_page' => $result['page'],
				'total_pages'  => $result['total_pages'],
				'per_page'     => $result['per_page'],
			);

			return $data;
		}

		// Fallback to old method if TaskIndexService not available
		// Check cache for tasks only
		$cache_key    = 'nuclen_tasks_' . get_current_user_id() . '_page_' . $current_page;
		$cached_tasks = wp_cache_get( $cache_key );
		if ( false !== $cached_tasks && isset( $cached_tasks['tasks'] ) && isset( $cached_tasks['pagination'] ) ) {
			$data['generation_tasks'] = $cached_tasks['tasks'];
			$data['pagination']       = $cached_tasks['pagination'];
			return $data;
		}

		global $wpdb;

		// Get generation tasks from transients
		$tasks = array();

		// First, get total count for pagination
		$total_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s",
				'_transient_nuclen_bulk_job_%',
				'_transient_timeout_nuclen_bulk_job_%'
			)
		);

		// Calculate offset
		$offset = ( $current_page - 1 ) * self::TASKS_PER_PAGE;

		// Find all bulk job transients with pagination
		$bulk_jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s
				ORDER BY option_id DESC
				LIMIT %d OFFSET %d",
				'_transient_nuclen_bulk_job_%',
				'_transient_timeout_nuclen_bulk_job_%',
				self::TASKS_PER_PAGE,
				$offset
			)
		);

		// Debug: Log query results
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Tasks query found %d bulk job transients (page %d)', count( $bulk_jobs ), $current_page )
			);
		}

		foreach ( $bulk_jobs as $job ) {
			$generation_id = str_replace( '_transient_nuclen_bulk_job_', '', $job->option_name );

			// Safely unserialize job data
			try {
				$job_data = maybe_unserialize( $job->option_value );
				if ( ! is_array( $job_data ) ) {
					continue;
				}
			} catch ( \Exception $e ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( 'Failed to unserialize job data for %s: %s', $job->option_name, $e->getMessage() )
				);
				continue;
			}

			// Calculate progress based on batches with actual result counts
			$progress            = 0;
			$processed           = 0;
			$failed              = 0;
			$batches_with_counts = 0;
			$total_batches       = $job_data['total_batches'] ?? 0;

			if ( isset( $job_data['batch_jobs'] ) ) {
				foreach ( $job_data['batch_jobs'] as $batch_job ) {
					$batch_data = get_transient( 'nuclen_batch_' . $batch_job['batch_id'] );
					if ( $batch_data ) {
						// Check if batch has actual result counts
						$has_counts = false;

						// Use actual success/fail counts if available
						if ( isset( $batch_data['success_count'] ) ) {
							$processed += $batch_data['success_count'];
							$has_counts = true;
						} elseif ( isset( $batch_data['results']['success_count'] ) ) {
							$processed += $batch_data['results']['success_count'];
							$has_counts = true;
						}

						if ( isset( $batch_data['fail_count'] ) ) {
							$failed    += $batch_data['fail_count'];
							$has_counts = true;
						} elseif ( isset( $batch_data['results']['fail_count'] ) ) {
							$failed    += $batch_data['results']['fail_count'];
							$has_counts = true;
						}

						// Only count this batch as complete if it has actual counts
						if ( $has_counts && in_array( $batch_data['status'] ?? '', array( 'completed', 'failed' ), true ) ) {
							++$batches_with_counts;
						}
					}
				}
			}

			$total_posts = $job_data['total_posts'] ?? 0;

			// Calculate progress based on batches with counts if status is not yet completed
			if ( in_array( $job_data['status'] ?? '', array( 'processing', 'scheduled', 'pending' ), true ) && $total_batches > 0 ) {
				// For active tasks, use batch completion ratio to show more accurate progress
				$progress = round( ( $batches_with_counts / $total_batches ) * 100 );
			} elseif ( $total_posts > 0 ) {
				// For completed tasks or as fallback, use post-based progress
				$progress = round( ( $processed / $total_posts ) * 100 );
			}

			$tasks[] = array(
				'id'            => $generation_id,
				'created_at'    => $job_data['created_at'] ?? 0,
				'scheduled_at'  => $job_data['scheduled_at'] ?? null,
				'workflow_type' => $job_data['workflow_type'] ?? 'unknown',
				'status'        => $job_data['status'] ?? 'unknown',
				'total_posts'   => $total_posts,
				'processed'     => $processed,
				'failed'        => $failed,
				'progress'      => $progress,
				'action'        => $job_data['action'] ?? 'bulk',
				'details'       => sprintf(
					__( '%1$d of %2$d posts successfully processed', 'nuclear-engagement' ),
					$processed,
					$total_posts
				),
			);
		}

		// Sort by created_at descending
		usort(
			$tasks,
			function ( $a, $b ) {
				return $b['created_at'] - $a['created_at'];
			}
		);

		$data['generation_tasks'] = $tasks;

		// Add pagination info
		$data['pagination'] = array(
			'total_items'  => intval( $total_count ),
			'total_pages'  => ceil( intval( $total_count ) / self::TASKS_PER_PAGE ),
			'current_page' => $current_page,
			'per_page'     => self::TASKS_PER_PAGE,
		);

		// Cache only tasks and pagination
		$cache_data = array(
			'tasks'      => $tasks,
			'pagination' => $data['pagination'],
		);
		wp_cache_set( $cache_key, $cache_data, '', self::CACHE_EXPIRY );

		return $data;
	}

	/**
	 * Get credits data separately (not cached per page)
	 */
	private function get_credits_data(): array {
		// For the Tasks page, we'll let the component handle fetching via AJAX
		// This avoids the issue with the API response format
		return array( 'use_component' => true );
	}

	/**
	 * Format a task for display
	 */
	private function format_task_for_display( array $task_data ): array {
		// Calculate progress based on batches with actual result counts
		$progress            = 0;
		$processed           = 0;
		$failed              = 0;
		$batches_with_counts = 0;
		$total_batches       = $task_data['total_batches'] ?? 0;

		if ( isset( $task_data['batch_jobs'] ) && is_array( $task_data['batch_jobs'] ) ) {
			foreach ( $task_data['batch_jobs'] as $batch_job ) {
				try {
					if ( ! isset( $batch_job['batch_id'] ) ) {
						continue;
					}

					$batch_data = \NuclearEngagement\Services\TaskTransientManager::get_batch_transient( $batch_job['batch_id'] );
					if ( $batch_data && is_array( $batch_data ) ) {
						// Check if batch has actual result counts
						$has_counts = false;

						// Use actual success/fail counts if available
						if ( isset( $batch_data['success_count'] ) ) {
							$processed += $batch_data['success_count'];
							$has_counts = true;
						} elseif ( isset( $batch_data['results']['success_count'] ) ) {
							$processed += $batch_data['results']['success_count'];
							$has_counts = true;
						}

						if ( isset( $batch_data['fail_count'] ) ) {
							$failed    += $batch_data['fail_count'];
							$has_counts = true;
						} elseif ( isset( $batch_data['results']['fail_count'] ) ) {
							$failed    += $batch_data['results']['fail_count'];
							$has_counts = true;
						}

						// Only count this batch as complete if it has actual counts
						if ( $has_counts && in_array( $batch_data['status'] ?? '', array( 'completed', 'failed' ), true ) ) {
							++$batches_with_counts;
						}
					}
				} catch ( \Throwable $e ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Error formatting batch job %s: %s',
							$batch_job['batch_id'] ?? 'unknown',
							$e->getMessage()
						)
					);
				}
			}
		}

		$total_posts = $task_data['total_posts'] ?? 0;

		// Calculate progress based on batches with counts if status is not yet completed
		if ( in_array( $task_data['status'] ?? '', array( 'processing', 'scheduled', 'pending' ), true ) && $total_batches > 0 ) {
			// For active tasks, use batch completion ratio to show more accurate progress
			$progress = round( ( $batches_with_counts / $total_batches ) * 100 );
		} elseif ( $total_posts > 0 ) {
			// For completed tasks or as fallback, use post-based progress
			$progress = round( ( $processed / $total_posts ) * 100 );
		}

		return array(
			'id'                => $task_data['id'],
			'status'            => $task_data['status'] ?? 'pending',
			'workflow_type'     => $task_data['workflow_type'] ?? 'unknown',
			'priority'          => $task_data['priority'] ?? 'normal',
			'action'            => $task_data['action'] ?? 'bulk',
			'total_posts'       => $total_posts,
			'processed'         => $processed,
			'failed'            => $failed,
			'progress'          => $progress,
			'created_at'        => $task_data['created_at'] ?? null,
			'scheduled_at'      => $task_data['scheduled_at'] ?? null,
			'completed_at'      => $task_data['completed_at'] ?? null,
			'completed_batches' => $task_data['completed_batches'] ?? 0,
			'failed_batches'    => $task_data['failed_batches'] ?? 0,
			'total_batches'     => $task_data['total_batches'] ?? 0,
			'details'           => sprintf(
				__( '%1$d of %2$d posts successfully processed', 'nuclear-engagement' ),
				$processed,
				$total_posts
			),
		);
	}

	/**
	 * Check cron status
	 */
	private function check_cron_status(): array {
		$status = array(
			'enabled'  => true,
			'next_run' => null,
		);

		// Check if WP-Cron is disabled
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$status['enabled'] = false;
		}

		// Check next scheduled cron run
		$crons = _get_cron_array();
		if ( ! empty( $crons ) ) {
			$next_run           = min( array_keys( $crons ) );
			$status['next_run'] = $next_run;
		}

		return $status;
	}



	/**
	 * Render the tasks view
	 */
	private function render_tasks_view( array $data ): void {
		include NUCLEN_PLUGIN_DIR . 'templates/admin/nuclen-tasks-page.php';
	}
}
