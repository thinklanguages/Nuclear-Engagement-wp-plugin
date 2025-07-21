<?php
/**
 * TaskIndexService.php - Part of the Nuclear Engagement plugin.
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
 * Service for maintaining an index of tasks for efficient querying
 */
class TaskIndexService extends BaseService {

	/**
	 * Option name for task index
	 */
	private const INDEX_OPTION = 'nuclen_task_index';

	/**
	 * Maximum tasks in index
	 */
	private const MAX_INDEX_SIZE = 1000;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->cache_ttl = 300; // 5 minutes
	}

	/**
	 * Get service name for logging and caching.
	 *
	 * @return string Service name.
	 */
	protected function get_service_name(): string {
		return 'task_index';
	}

	/**
	 * Add a task to the index
	 *
	 * @param string $task_id Task ID
	 * @param array  $task_meta Task metadata
	 */
	public function add_task( string $task_id, array $task_meta ): void {
		$index = $this->get_index();

		// Add or update task in index
		$index[ $task_id ] = array(
			'created_at'    => $task_meta['created_at'] ?? time(),
			'status'        => $task_meta['status'] ?? 'pending',
			'type'          => $task_meta['type'] ?? 'generation',
			'workflow_type' => $task_meta['workflow_type'] ?? '',
			'total_posts'   => $task_meta['total_posts'] ?? 0,
			'priority'      => $task_meta['priority'] ?? 'normal',
			'parent_id'     => $task_meta['parent_id'] ?? null,
		);

		// Sort by created_at descending
		uasort(
			$index,
			function ( $a, $b ) {
				return $b['created_at'] - $a['created_at'];
			}
		);

		// Trim to max size
		if ( count( $index ) > self::MAX_INDEX_SIZE ) {
			$index = array_slice( $index, 0, self::MAX_INDEX_SIZE, true );
		}

		$this->save_index( $index );
		$this->delete_cache( 'task_index' );
		
		// Clear all paginated task caches to ensure immediate visibility
		$this->clear_all_task_caches();
	}

	/**
	 * Remove a task from the index
	 *
	 * @param string $task_id Task ID
	 */
	public function remove_task( string $task_id ): void {
		$index = $this->get_index();

		if ( isset( $index[ $task_id ] ) ) {
			unset( $index[ $task_id ] );
			$this->save_index( $index );
			$this->delete_cache( 'task_index' );
		}
	}

	/**
	 * Update task status in index
	 *
	 * @param string $task_id Task ID
	 * @param string $status New status
	 * @param array  $additional_data Additional data to update
	 */
	public function update_task_status( string $task_id, string $status, array $additional_data = array() ): void {
		$index = $this->get_index();

		if ( isset( $index[ $task_id ] ) ) {
			$index[ $task_id ]['status']     = $status;
			$index[ $task_id ]['updated_at'] = time();

			// Update additional fields if provided
			foreach ( $additional_data as $key => $value ) {
				$index[ $task_id ][ $key ] = $value;
			}

			$this->save_index( $index );
			$this->delete_cache( 'task_index' );
		}
	}

	/**
	 * Update task data in index
	 *
	 * @param string $task_id Task ID
	 * @param array  $task_data Full task data to update
	 */
	public function update_task( string $task_id, array $task_data ): void {
		$index = $this->get_index();

		if ( isset( $index[ $task_id ] ) ) {
			// Update all relevant fields from task data
			if ( isset( $task_data['status'] ) ) {
				$index[ $task_id ]['status'] = $task_data['status'];
			}
			if ( isset( $task_data['scheduled_at'] ) ) {
				$index[ $task_id ]['scheduled_at'] = $task_data['scheduled_at'];
			}
			if ( isset( $task_data['total_posts'] ) ) {
				$index[ $task_id ]['total_posts'] = $task_data['total_posts'];
			}
			if ( isset( $task_data['workflow_type'] ) ) {
				$index[ $task_id ]['workflow_type'] = $task_data['workflow_type'];
			}
			if ( isset( $task_data['priority'] ) ) {
				$index[ $task_id ]['priority'] = $task_data['priority'];
			}
			
			$index[ $task_id ]['updated_at'] = time();

			$this->save_index( $index );
			$this->delete_cache( 'task_index' );
			
			// Clear all paginated task caches to ensure immediate visibility
			$this->clear_all_task_caches();
		}
	}

	/**
	 * Get paginated tasks from index
	 *
	 * @param int   $page Page number
	 * @param int   $per_page Items per page
	 * @param array $filters Optional filters
	 * @return array Tasks data with pagination info
	 */
	public function get_paginated_tasks( int $page = 1, int $per_page = 20, array $filters = array() ): array {
		$cache_key = sprintf( 'tasks_page_%d_%d_%s', $page, $per_page, md5( serialize( $filters ) ) );

		$cached = $this->get_cache( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		$result = ( function () use ( $page, $per_page, $filters ) {
			$index = $this->get_index();

			// Filter out batch tasks - only show parent generation tasks
			$index = array_filter(
				$index,
				function ( $task ) {
					return ( $task['type'] ?? 'generation' ) !== 'batch';
				}
			);

			// Apply filters
			if ( ! empty( $filters ) ) {
				$index = $this->apply_filters( $index, $filters );
			}

			// Calculate pagination
			$total       = count( $index );
			$offset      = ( $page - 1 ) * $per_page;
			$paged_tasks = array_slice( $index, $offset, $per_page, true );

			// Get full task data for visible tasks
			$tasks = array();
			foreach ( $paged_tasks as $task_id => $meta ) {
				// Check if it's a bulk job or batch
				if ( strpos( $task_id, '_batch_' ) !== false ) {
					$task_data = TaskTransientManager::get_batch_transient( $task_id );
				} else {
					$task_data = TaskTransientManager::get_task_transient( $task_id );
				}

				if ( $task_data ) {
					$tasks[] = array_merge(
						array( 'id' => $task_id ),
						$meta,
						$task_data
					);
				}
			}

			return array(
				'tasks'       => $tasks,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			);
		} )();

		$this->set_cache( $cache_key, $result );

		return $result;
	}

	/**
	 * Get task statistics
	 *
	 * @return array Statistics
	 */
	public function get_statistics(): array {
		$cached = $this->get_cache( 'task_statistics' );

		if ( $cached !== false ) {
			return $cached;
		}

		$result = ( function () {
			$index = $this->get_index();

			$stats = array(
				'total'       => count( $index ),
				'by_status'   => array(),
				'by_type'     => array(),
				'by_priority' => array(),
			);

			foreach ( $index as $task ) {
				// Count by status
				$status                        = $task['status'] ?? 'unknown';
				$stats['by_status'][ $status ] = ( $stats['by_status'][ $status ] ?? 0 ) + 1;

				// Count by type
				$type                      = $task['type'] ?? 'unknown';
				$stats['by_type'][ $type ] = ( $stats['by_type'][ $type ] ?? 0 ) + 1;

				// Count by priority
				$priority                          = $task['priority'] ?? 'normal';
				$stats['by_priority'][ $priority ] = ( $stats['by_priority'][ $priority ] ?? 0 ) + 1;
			}

			return $stats;
		} )();

		$this->set_cache( 'task_statistics', $result );

		return $result;
	}

	/**
	 * Clean up batch tasks from index (they shouldn't be there)
	 *
	 * @return int Number of batch tasks removed
	 */
	public function cleanup_batch_tasks(): int {
		$index   = $this->get_index();
		$cleaned = 0;

		foreach ( $index as $task_id => $meta ) {
			// Remove batch tasks from index
			if ( isset( $meta['type'] ) && $meta['type'] === 'batch' ) {
				unset( $index[ $task_id ] );
				++$cleaned;
			}
		}

		if ( $cleaned > 0 ) {
			$this->save_index( $index );
			$this->delete_cache( 'task_index' );

			LoggingService::log( sprintf( 'Cleaned %d batch tasks from index', $cleaned ) );
		}

		return $cleaned;
	}

	/**
	 * Clean up orphaned tasks from index
	 *
	 * @return int Number of tasks cleaned
	 */
	public function cleanup_orphaned_tasks(): int {
		$index   = $this->get_index();
		$cleaned = 0;

		foreach ( $index as $task_id => $meta ) {
			// Check if task still exists
			if ( strpos( $task_id, '_batch_' ) !== false ) {
				$exists = TaskTransientManager::get_batch_transient( $task_id ) !== false;
			} else {
				$exists = TaskTransientManager::get_task_transient( $task_id ) !== false;
			}

			if ( ! $exists ) {
				unset( $index[ $task_id ] );
				++$cleaned;
			}
		}

		if ( $cleaned > 0 ) {
			$this->save_index( $index );
			$this->delete_cache( 'task_index' );

			LoggingService::log( sprintf( 'Cleaned %d orphaned tasks from index', $cleaned ) );
		}

		return $cleaned;
	}

	/**
	 * Rebuild the entire index from existing transients
	 *
	 * @return int Number of tasks indexed
	 */
	public function rebuild_index(): int {
		global $wpdb;

		$index = array();
		$count = 0;

		// Get all bulk job transients
		$bulk_jobs = $wpdb->get_results(
			"SELECT option_name, option_value FROM $wpdb->options 
			WHERE option_name LIKE '_transient_nuclen_bulk_job_%' 
			AND option_name NOT LIKE '_transient_timeout_%'
			ORDER BY option_id DESC
			LIMIT 1000"
		);

		foreach ( $bulk_jobs as $job ) {
			$task_id   = str_replace( '_transient_nuclen_bulk_job_', '', $job->option_name );
			$task_data = maybe_unserialize( $job->option_value );

			if ( is_array( $task_data ) ) {
				$index[ $task_id ] = array(
					'created_at'    => $task_data['created_at'] ?? time(),
					'status'        => $task_data['status'] ?? 'pending',
					'type'          => 'generation',
					'workflow_type' => $task_data['workflow_type'] ?? '',
					'total_posts'   => $task_data['total_posts'] ?? 0,
					'priority'      => $task_data['priority'] ?? 'normal',
				);
				++$count;
			}
		}

		// Don't include batch transients in the index anymore
		// Batches are internal implementation details - only parent tasks should be shown

		$this->save_index( $index );
		$this->delete_cache( 'task_index' );

		LoggingService::log( sprintf( 'Rebuilt task index with %d tasks', $count ) );

		return $count;
	}

	/**
	 * Get the task index
	 *
	 * @return array
	 */
	private function get_index(): array {
		$index = get_option( self::INDEX_OPTION, array() );
		return is_array( $index ) ? $index : array();
	}

	/**
	 * Save the task index
	 *
	 * @param array $index
	 */
	private function save_index( array $index ): void {
		// Check size before saving
		$serialized = serialize( $index );
		$size_kb    = strlen( $serialized ) / 1024;

		if ( $size_kb > 1000 ) { // 1MB limit
			LoggingService::log(
				sprintf( 'Task index too large (%d KB), trimming to 500 most recent tasks', $size_kb ),
				'warning'
			);

			// Sort by created_at and keep most recent
			uasort(
				$index,
				function ( $a, $b ) {
					return $b['created_at'] - $a['created_at'];
				}
			);
			$index = array_slice( $index, 0, 500, true );
		}

		// Get current value to check if update is needed
		$current = get_option( self::INDEX_OPTION, array() );
		
		// Only update if the values are different
		if ( $current !== $index ) {
			$result = update_option( self::INDEX_OPTION, $index, false );
			
			// update_option returns false on failure OR when the value hasn't changed
			// We already checked that values are different, so false means actual failure
			if ( ! $result ) {
				// Double-check that the update actually failed by re-reading the value
				$new_value = get_option( self::INDEX_OPTION, array() );
				if ( $new_value !== $index ) {
					LoggingService::log( 'Failed to save task index', 'error' );
				}
			}
		}
		// If values are the same, no update needed - this is not an error
	}

	/**
	 * Apply filters to task index
	 *
	 * @param array $index Task index
	 * @param array $filters Filters to apply
	 * @return array Filtered index
	 */
	private function apply_filters( array $index, array $filters ): array {
		foreach ( $filters as $key => $value ) {
			$index = array_filter(
				$index,
				function ( $task ) use ( $key, $value ) {
					return isset( $task[ $key ] ) && $task[ $key ] === $value;
				}
			);
		}

		return $index;
	}

	/**
	 * Clear all task-related caches
	 */
	private function clear_all_task_caches(): void {
		global $wpdb;
		
		// Clear all task-related caches from the database
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				'_transient_' . $this->get_service_name() . '_tasks_page_%',
				'_transient_timeout_' . $this->get_service_name() . '_tasks_page_%'
			)
		);
		
		// Clear from object cache as well
		// Use the service name as the cache group, which is how BaseService works
		$cache_group = $this->get_service_name();
		
		// Clear paginated task caches for common page/filter combinations
		for ( $page = 1; $page <= 10; $page++ ) {
			for ( $per_page = 10; $per_page <= 50; $per_page += 10 ) {
				// Clear with no filters
				$cache_key = sprintf( 'tasks_page_%d_%d_%s', $page, $per_page, md5( serialize( array() ) ) );
				$this->delete_cache( $cache_key );
				
				// Clear common filter combinations
				$common_filters = array(
					array( 'status' => 'pending' ),
					array( 'status' => 'scheduled' ),
					array( 'status' => 'running' ),
					array( 'status' => 'processing' ),
					array( 'status' => 'completed' ),
					array( 'status' => 'failed' ),
				);
				
				foreach ( $common_filters as $filter ) {
					$cache_key = sprintf( 'tasks_page_%d_%d_%s', $page, $per_page, md5( serialize( $filter ) ) );
					$this->delete_cache( $cache_key );
				}
			}
		}
		
		// Task caches cleared
	}
}
