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

	/** Task data gatherer */
	private TaskDataGatherer $dataGatherer;

	/** Task action handler */
	private TaskActionHandler $actionHandler;

	public function __construct( SettingsRepository $settings_repo, ServiceContainer $container ) {
		$this->settings_repo = $settings_repo;
		$this->container     = $container;

		// Initialize handlers
		$this->dataGatherer  = new TaskDataGatherer( $container );
		$this->actionHandler = new TaskActionHandler( $container );
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
	 * Handle manual task actions (run now, cancel)
	 */
	private function handle_task_actions(): void {
		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['task_id'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'nuclen_task_action' ) ) {
			wp_die( __( 'Security check failed.', 'nuclear-engagement' ) );
		}

		$action  = sanitize_text_field( $_GET['action'] );
		$task_id = sanitize_text_field( $_GET['task_id'] );

		$this->actionHandler->handleAction( $action, $task_id );

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
		return $this->dataGatherer->gatherData();
	}

	/**
	 * Render the tasks view
	 */
	private function render_tasks_view( array $data ): void {
		include NUCLEN_PLUGIN_DIR . 'templates/admin/nuclen-tasks-page.php';
	}
}

/**
 * Handles task action operations
 */
class TaskActionHandler {
	/** Service container. */
	private ServiceContainer $container;

	public function __construct( ServiceContainer $container ) {
		$this->container = $container;
	}

	/**
	 * Handle task action
	 *
	 * @param string $action Action to perform
	 * @param string $task_id Task ID
	 */
	public function handleAction( string $action, string $task_id ): void {
		switch ( $action ) {
			case 'run_now':
				$this->run_task_now( $task_id );
				break;
			case 'cancel':
				$this->cancel_task( $task_id );
				break;
		}
	}

	/**
	 * Run a task immediately
	 */
	private function run_task_now( string $task_id ): void {
		// Check if it's a batch task
		if ( strpos( $task_id, '_batch_' ) !== false ) {
			$this->runBatchNow( $task_id );
		} else {
			$this->runGenerationNow( $task_id );
		}
	}

	/**
	 * Run batch immediately
	 */
	private function runBatchNow( string $task_id ): void {
		// Trigger batch processing
		do_action( 'nuclen_process_batch', $task_id );

		// Add admin notice
		$this->add_admin_notice(
			sprintf( __( 'Batch %s has been triggered for immediate processing.', 'nuclear-engagement' ), esc_html( $task_id ) ),
			'success'
		);
	}

	/**
	 * Run generation immediately
	 */
	private function runGenerationNow( string $task_id ): void {
		$batch_data = get_transient( 'nuclen_bulk_job_' . $task_id );
		if ( ! $batch_data || ! isset( $batch_data['workflow_type'] ) ) {
			return;
		}

		// Extract post IDs from batches
		$all_post_ids = $this->extractPostIdsFromBatches( $batch_data['batch_jobs'] );

		// Add to centralized polling queue if available
		if ( $this->container->has( 'centralized_polling_queue' ) ) {
			$queue = $this->container->get( 'centralized_polling_queue' );
			$queue->add_to_queue( $task_id, $batch_data['workflow_type'], $all_post_ids, 1 ); // High priority
		}
	}

	/**
	 * Extract post IDs from batch jobs
	 */
	private function extractPostIdsFromBatches( array $batch_jobs ): array {
		$all_post_ids = array();

		foreach ( $batch_jobs as $batch ) {
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

		return $all_post_ids;
	}

	/**
	 * Cancel a task
	 */
	private function cancel_task( string $task_id ): void {
		// Check if it's a batch task
		if ( strpos( $task_id, '_batch_' ) !== false ) {
			$this->cancelBatch( $task_id );
		} else {
			$this->cancelGeneration( $task_id );
		}
	}

	/**
	 * Cancel batch
	 */
	private function cancelBatch( string $task_id ): void {
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
	}

	/**
	 * Cancel generation
	 */
	private function cancelGeneration( string $task_id ): void {
		$batch_data = get_transient( 'nuclen_bulk_job_' . $task_id );
		if ( ! $batch_data ) {
			return;
		}

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

	/**
	 * Add admin notice
	 */
	private function add_admin_notice( string $message, string $type = 'info' ): void {
		if ( $this->container->has( 'admin_notice_service' ) ) {
			$notice_service = $this->container->get( 'admin_notice_service' );
			$notice_service->add( $message );
		}
	}
}

/**
 * Handles task data gathering
 */
class TaskDataGatherer {
	/** Service container. */
	private ServiceContainer $container;

	/** Task formatter */
	private TaskFormatter $formatter;

	/** Cache handler */
	private TaskCacheHandler $cacheHandler;

	public function __construct( ServiceContainer $container ) {
		$this->container    = $container;
		$this->formatter    = new TaskFormatter();
		$this->cacheHandler = new TaskCacheHandler();
	}

	/**
	 * Gather all task data
	 */
	public function gatherData(): array {
		// Check and recover stuck tasks first
		$this->recoverStuckTasks();

		// Get current page
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

		// Initialize data array
		$data = array(
			'generation_tasks'       => array(),
			'credits'                => $this->get_credits_data(),
			'cron_status'            => $this->check_cron_status(),
			'pagination'             => array(),
			'has_task_index_service' => $this->container->has( 'task_index_service' ),
		);

		// Use TaskIndexService if available
		if ( $this->container->has( 'task_index_service' ) ) {
			return $this->gatherDataFromIndex( $data, $current_page );
		}

		// Fallback to transient-based method
		return $this->gatherDataFromTransients( $data, $current_page );
	}

	/**
	 * Recover stuck tasks
	 */
	private function recoverStuckTasks(): void {
		if ( $this->container->has( 'bulk_generation_batch_processor' ) ) {
			$processor = $this->container->get( 'bulk_generation_batch_processor' );
			$processor->check_and_recover_stuck_tasks();
		}
	}

	/**
	 * Gather data from task index service
	 */
	private function gatherDataFromIndex( array $data, int $current_page ): array {
		$index_service = $this->container->get( 'task_index_service' );

		// Get filters if any
		$filters = array();
		if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
			$filters['status'] = sanitize_text_field( $_GET['status'] );
		}

		// Get paginated tasks
		$result = $index_service->get_paginated_tasks( $current_page, Tasks::TASKS_PER_PAGE, $filters );

		// Process tasks for display
		$tasks = array();
		foreach ( $result['tasks'] as $task_data ) {
			$tasks[] = $this->formatter->formatTaskForDisplay( $task_data );
		}

		$data['generation_tasks'] = $tasks;
		$data['pagination']       = array(
			'total'        => $result['total'],
			'current_page' => $result['page'],
			'total_pages'  => $result['total_pages'],
			'per_page'     => $result['per_page'],
		);

		return $data;
	}

	/**
	 * Gather data from transients
	 */
	private function gatherDataFromTransients( array $data, int $current_page ): array {
		// Check cache first
		$cached_data = $this->cacheHandler->getCachedData( $current_page );
		if ( $cached_data ) {
			$data['generation_tasks'] = $cached_data['tasks'];
			$data['pagination']       = $cached_data['pagination'];
			return $data;
		}

		// Get tasks from database
		$db_results = $this->queryTasksFromDatabase( $current_page );

		// Process tasks
		$tasks = $this->processTaskResults( $db_results['jobs'] );

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
			'total_items'  => intval( $db_results['total'] ),
			'total_pages'  => ceil( intval( $db_results['total'] ) / Tasks::TASKS_PER_PAGE ),
			'current_page' => $current_page,
			'per_page'     => Tasks::TASKS_PER_PAGE,
		);

		// Cache the data
		$this->cacheHandler->cacheData( $current_page, $tasks, $data['pagination'] );

		return $data;
	}

	/**
	 * Query tasks from database
	 */
	private function queryTasksFromDatabase( int $current_page ): array {
		global $wpdb;

		// Get total count
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
		$offset = ( $current_page - 1 ) * Tasks::TASKS_PER_PAGE;

		// Get paginated results
		$bulk_jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s
				ORDER BY option_id DESC
				LIMIT %d OFFSET %d",
				'_transient_nuclen_bulk_job_%',
				'_transient_timeout_nuclen_bulk_job_%',
				Tasks::TASKS_PER_PAGE,
				$offset
			)
		);

		return array(
			'total' => $total_count,
			'jobs'  => $bulk_jobs,
		);
	}

	/**
	 * Process task results from database
	 */
	private function processTaskResults( array $bulk_jobs ): array {
		$tasks = array();

		foreach ( $bulk_jobs as $job ) {
			$task = $this->processSingleTask( $job );
			if ( $task ) {
				$tasks[] = $task;
			}
		}

		return $tasks;
	}

	/**
	 * Process single task
	 */
	private function processSingleTask( $job ): ?array {
		$generation_id = str_replace( '_transient_nuclen_bulk_job_', '', $job->option_name );

		// Safely unserialize job data
		try {
			$job_data = maybe_unserialize( $job->option_value );
			if ( ! is_array( $job_data ) ) {
				return null;
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Failed to unserialize job data for %s: %s', $job->option_name, $e->getMessage() )
			);
			return null;
		}

		// Calculate progress
		$progress_data = $this->calculateTaskProgress( $job_data );

		return array(
			'id'            => $generation_id,
			'created_at'    => $job_data['created_at'] ?? 0,
			'scheduled_at'  => $job_data['scheduled_at'] ?? null,
			'workflow_type' => $job_data['workflow_type'] ?? 'unknown',
			'status'        => $job_data['status'] ?? 'unknown',
			'total_posts'   => $job_data['total_posts'] ?? 0,
			'processed'     => $progress_data['processed'],
			'failed'        => $progress_data['failed'],
			'progress'      => $progress_data['progress'],
			'action'        => $job_data['action'] ?? 'bulk',
			'details'       => sprintf(
				__( '%1$d of %2$d posts successfully processed', 'nuclear-engagement' ),
				$progress_data['processed'],
				$job_data['total_posts'] ?? 0
			),
		);
	}

	/**
	 * Calculate task progress
	 */
	private function calculateTaskProgress( array $job_data ): array {
		$processed           = 0;
		$failed              = 0;
		$batches_with_counts = 0;
		$total_batches       = $job_data['total_batches'] ?? 0;
		$total_posts         = $job_data['total_posts'] ?? 0;

		if ( isset( $job_data['batch_jobs'] ) ) {
			foreach ( $job_data['batch_jobs'] as $batch_job ) {
				$batch_progress = $this->calculateBatchProgress( $batch_job );
				$processed     += $batch_progress['processed'];
				$failed        += $batch_progress['failed'];
				if ( $batch_progress['has_counts'] ) {
					++$batches_with_counts;
				}
			}
		}

		// Calculate overall progress
		$progress = 0;
		if ( in_array( $job_data['status'] ?? '', array( 'processing', 'scheduled', 'pending' ), true ) && $total_batches > 0 ) {
			$progress = round( ( $batches_with_counts / $total_batches ) * 100 );
		} elseif ( $total_posts > 0 ) {
			$progress = round( ( $processed / $total_posts ) * 100 );
		}

		return array(
			'processed' => $processed,
			'failed'    => $failed,
			'progress'  => $progress,
		);
	}

	/**
	 * Calculate batch progress
	 */
	private function calculateBatchProgress( array $batch_job ): array {
		$batch_data = get_transient( 'nuclen_batch_' . $batch_job['batch_id'] );
		if ( ! $batch_data ) {
			return array(
				'processed'  => 0,
				'failed'     => 0,
				'has_counts' => false,
			);
		}

		$processed  = 0;
		$failed     = 0;
		$has_counts = false;

		// Check for success counts
		if ( isset( $batch_data['success_count'] ) ) {
			$processed  = $batch_data['success_count'];
			$has_counts = true;
		} elseif ( isset( $batch_data['results']['success_count'] ) ) {
			$processed  = $batch_data['results']['success_count'];
			$has_counts = true;
		}

		// Check for failure counts
		if ( isset( $batch_data['fail_count'] ) ) {
			$failed     = $batch_data['fail_count'];
			$has_counts = true;
		} elseif ( isset( $batch_data['results']['fail_count'] ) ) {
			$failed     = $batch_data['results']['fail_count'];
			$has_counts = true;
		}

		// Only count if batch is complete and has counts
		if ( ! $has_counts || ! in_array( $batch_data['status'] ?? '', array( 'completed', 'failed' ), true ) ) {
			$has_counts = false;
		}

		return array(
			'processed'  => $processed,
			'failed'     => $failed,
			'has_counts' => $has_counts,
		);
	}

	/**
	 * Get credits data
	 */
	private function get_credits_data(): array {
		// For the Tasks page, we'll let the component handle fetching via AJAX
		return array( 'use_component' => true );
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
}

/**
 * Formats tasks for display
 */
class TaskFormatter {
	/**
	 * Format a task for display
	 */
	public function formatTaskForDisplay( array $task_data ): array {
		// Calculate progress
		$progress_data = $this->calculateProgress( $task_data );

		return array(
			'id'                => $task_data['id'],
			'status'            => $task_data['status'] ?? 'pending',
			'workflow_type'     => $task_data['workflow_type'] ?? 'unknown',
			'priority'          => $task_data['priority'] ?? 'normal',
			'action'            => $task_data['action'] ?? 'bulk',
			'total_posts'       => $task_data['total_posts'] ?? 0,
			'processed'         => $progress_data['processed'],
			'failed'            => $progress_data['failed'],
			'progress'          => $progress_data['progress'],
			'created_at'        => $task_data['created_at'] ?? null,
			'scheduled_at'      => $task_data['scheduled_at'] ?? null,
			'completed_at'      => $task_data['completed_at'] ?? null,
			'completed_batches' => $task_data['completed_batches'] ?? 0,
			'failed_batches'    => $task_data['failed_batches'] ?? 0,
			'total_batches'     => $task_data['total_batches'] ?? 0,
			'details'           => sprintf(
				__( '%1$d of %2$d posts successfully processed', 'nuclear-engagement' ),
				$progress_data['processed'],
				$task_data['total_posts'] ?? 0
			),
		);
	}

	/**
	 * Calculate progress for a task
	 */
	private function calculateProgress( array $task_data ): array {
		$processed           = 0;
		$failed              = 0;
		$batches_with_counts = 0;
		$total_batches       = $task_data['total_batches'] ?? 0;
		$total_posts         = $task_data['total_posts'] ?? 0;

		if ( isset( $task_data['batch_jobs'] ) && is_array( $task_data['batch_jobs'] ) ) {
			foreach ( $task_data['batch_jobs'] as $batch_job ) {
				$batch_progress = $this->getBatchProgress( $batch_job );
				$processed     += $batch_progress['processed'];
				$failed        += $batch_progress['failed'];
				if ( $batch_progress['has_counts'] ) {
					++$batches_with_counts;
				}
			}
		}

		// Calculate overall progress
		$progress = 0;
		if ( in_array( $task_data['status'] ?? '', array( 'processing', 'scheduled', 'pending' ), true ) && $total_batches > 0 ) {
			$progress = round( ( $batches_with_counts / $total_batches ) * 100 );
		} elseif ( $total_posts > 0 ) {
			$progress = round( ( $processed / $total_posts ) * 100 );
		}

		return array(
			'processed' => $processed,
			'failed'    => $failed,
			'progress'  => $progress,
		);
	}

	/**
	 * Get batch progress
	 */
	private function getBatchProgress( array $batch_job ): array {
		try {
			if ( ! isset( $batch_job['batch_id'] ) ) {
				return array(
					'processed'  => 0,
					'failed'     => 0,
					'has_counts' => false,
				);
			}

			$batch_data = \NuclearEngagement\Services\TaskTransientManager::get_batch_transient( $batch_job['batch_id'] );
			if ( ! $batch_data || ! is_array( $batch_data ) ) {
				return array(
					'processed'  => 0,
					'failed'     => 0,
					'has_counts' => false,
				);
			}

			return $this->extractBatchCounts( $batch_data );
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Error getting batch progress for %s: %s', $batch_job['batch_id'] ?? 'unknown', $e->getMessage() )
			);
			return array(
				'processed'  => 0,
				'failed'     => 0,
				'has_counts' => false,
			);
		}
	}

	/**
	 * Extract batch counts
	 */
	private function extractBatchCounts( array $batch_data ): array {
		$processed  = 0;
		$failed     = 0;
		$has_counts = false;

		// Check for success counts
		if ( isset( $batch_data['success_count'] ) ) {
			$processed  = $batch_data['success_count'];
			$has_counts = true;
		} elseif ( isset( $batch_data['results']['success_count'] ) ) {
			$processed  = $batch_data['results']['success_count'];
			$has_counts = true;
		}

		// Check for failure counts
		if ( isset( $batch_data['fail_count'] ) ) {
			$failed     = $batch_data['fail_count'];
			$has_counts = true;
		} elseif ( isset( $batch_data['results']['fail_count'] ) ) {
			$failed     = $batch_data['results']['fail_count'];
			$has_counts = true;
		}

		// Only count if batch is complete and has counts
		if ( $has_counts && in_array( $batch_data['status'] ?? '', array( 'completed', 'failed' ), true ) ) {
			return array(
				'processed'  => $processed,
				'failed'     => $failed,
				'has_counts' => true,
			);
		}

		return array(
			'processed'  => 0,
			'failed'     => 0,
			'has_counts' => false,
		);
	}
}

/**
 * Handles task caching
 */
class TaskCacheHandler {
	/**
	 * Get cached data
	 */
	public function getCachedData( int $current_page ): ?array {
		$cache_key    = 'nuclen_tasks_' . get_current_user_id() . '_page_' . $current_page;
		$cached_tasks = wp_cache_get( $cache_key );

		if ( false !== $cached_tasks && isset( $cached_tasks['tasks'] ) && isset( $cached_tasks['pagination'] ) ) {
			return $cached_tasks;
		}

		return null;
	}

	/**
	 * Cache data
	 */
	public function cacheData( int $current_page, array $tasks, array $pagination ): void {
		$cache_key  = 'nuclen_tasks_' . get_current_user_id() . '_page_' . $current_page;
		$cache_data = array(
			'tasks'      => $tasks,
			'pagination' => $pagination,
		);
		wp_cache_set( $cache_key, $cache_data, '', Tasks::CACHE_EXPIRY );
	}
}
