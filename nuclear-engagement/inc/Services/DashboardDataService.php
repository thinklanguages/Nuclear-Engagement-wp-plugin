<?php
/**
 * DashboardDataService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/DashboardDataService.php
 *
 * Provides data retrieval helpers for the admin dashboard.
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Services\TaskTransientManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for fetching dashboard data.
 */
class DashboardDataService {
	/** Cache group for dashboard queries. */
	private const CACHE_GROUP = 'nuclen_dashboard';

	/** Cache lifetime in seconds. */
	private const CACHE_TTL = 30 * MINUTE_IN_SECONDS; // 30 minutes.

	/** Index prefix for transient lookups */
	private const TRANSIENT_INDEX_PREFIX = 'nuclen_transient_index_';

	/** Option name storing cache version. */
	private const VERSION_OPTION = 'nuclen_dashboard_version';

	/**
	 * Get current cache version.
	 */
	private function get_cache_version(): int {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * Clear cached dashboard query results.
	 */
	public static function clear_cache(): void {
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		update_option( self::VERSION_OPTION, $version + 1, false );

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_flush();
		}
	}
	/**
	 * Run a grouped count query (status, post type, author, etc.).
	 *
	 * @param string $group_by   Column to group by (prefixed, e.g. "p.post_status").
	 * @param string $meta_key   Meta key to test for existence (quiz/summary).
	 * @param array  $post_types Allowed post types.
	 * @param array  $statuses   Allowed post statuses.
	 * @return array             Rows of counts.
	 */
	public function get_group_counts( string $group_by, string $meta_key, array $post_types, array $statuses ): array {
		global $wpdb;

		$post_types = array_map( 'sanitize_key', $post_types );
		$statuses   = array_map( 'sanitize_key', $statuses );

		$cache_key = md5( wp_json_encode( array( 'grp', $group_by, $meta_key, $post_types, $statuses, $this->get_cache_version(), get_current_blog_id() ) ) );
		$transient = 'nuclen_dash_' . $cache_key;
		$found     = false;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$cached = get_transient( $transient );
		}

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$placeholders_pt = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$placeholders_st = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = $wpdb->prepare(
			"SELECT $group_by AS g,
				   CASE WHEN pm.meta_id IS NULL THEN 'without' ELSE 'with' END AS w,
				   COUNT(*) AS c
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
			  ON pm.post_id = p.ID
			 AND pm.meta_key = %s
			WHERE p.post_type IN ($placeholders_pt)
			  AND p.post_status IN ($placeholders_st)
			GROUP BY $group_by, w",
			array_merge( array( $meta_key ), $post_types, $statuses )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! empty( $wpdb->last_error ) ) {
			LoggingService::log( 'Dashboard query error: ' . $wpdb->last_error );
			return array();
		}

		wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
		set_transient( $transient, $rows, self::CACHE_TTL );

		return $rows;
	}

	/**
	 * Run a grouped count query for both quiz and summary meta in one go.
	 *
	 * @param string $group_by   Column to group by.
	 * @param array  $post_types Allowed post types.
	 * @param array  $statuses   Allowed post statuses.
	 * @return array             Rows with counts for quiz and summary.
	 */
	public function get_dual_counts( string $group_by, array $post_types, array $statuses ): array {
		global $wpdb;

		$post_types = array_map( 'sanitize_key', $post_types );
		$statuses   = array_map( 'sanitize_key', $statuses );

		$cache_key = md5( wp_json_encode( array( 'dual', $group_by, $post_types, $statuses, $this->get_cache_version(), get_current_blog_id() ) ) );
		$transient = 'nuclen_dash_' . $cache_key;
		$found     = false;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$cached = get_transient( $transient );
		}

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$placeholders_pt = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$placeholders_st = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = $wpdb->prepare(
			"SELECT $group_by AS g,
				   SUM(CASE WHEN pm_q.meta_id IS NULL THEN 0 ELSE 1 END) AS quiz_with,
				   SUM(CASE WHEN pm_q.meta_id IS NULL THEN 1 ELSE 0 END) AS quiz_without,
				   SUM(CASE WHEN pm_s.meta_id IS NULL THEN 0 ELSE 1 END) AS summary_with,
				   SUM(CASE WHEN pm_s.meta_id IS NULL THEN 1 ELSE 0 END) AS summary_without
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_q ON pm_q.post_id = p.ID AND pm_q.meta_key = 'nuclen-quiz-data'
			LEFT JOIN {$wpdb->postmeta} pm_s ON pm_s.post_id = p.ID AND pm_s.meta_key = '" . Summary_Service::META_KEY . "'
			WHERE p.post_type IN ($placeholders_pt)
			  AND p.post_status IN ($placeholders_st)
			GROUP BY $group_by",
			array_merge( $post_types, $statuses )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! empty( $wpdb->last_error ) ) {
			LoggingService::log( 'Dashboard query error: ' . $wpdb->last_error );
			return array();
		}

		wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
		set_transient( $transient, $rows, self::CACHE_TTL );

		return $rows;
	}

	/**
	 * Retrieve counts grouped by category.
	 *
	 * @param array $post_types Allowed post types.
	 * @param array $statuses   Allowed post statuses.
	 * @return array            Rows of counts.
	 */
	public function get_category_dual_counts( array $post_types, array $statuses ): array {
		global $wpdb;

		$post_types = array_map( 'sanitize_key', $post_types );
		$statuses   = array_map( 'sanitize_key', $statuses );

		// Check cache first
		$cache_key = 'cat_dual_' . md5( serialize( array( $post_types, $statuses ) ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$placeholders_pt = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$placeholders_st = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// Use LIMIT to prevent excessive memory usage
		$limit = apply_filters( 'nuclen_dashboard_category_limit', 100 );

		$sql = $wpdb->prepare(
			"SELECT COALESCE(t.term_id, 0) AS term_id,
		 COALESCE(t.name, 'Uncategorized') AS cat_name,
		 SUM(CASE WHEN pm_q.meta_id IS NULL THEN 0 ELSE 1 END) AS quiz_with,
		 SUM(CASE WHEN pm_q.meta_id IS NULL THEN 1 ELSE 0 END) AS quiz_without,
		 SUM(CASE WHEN pm_s.meta_id IS NULL THEN 0 ELSE 1 END) AS summary_with,
		 SUM(CASE WHEN pm_s.meta_id IS NULL THEN 1 ELSE 0 END) AS summary_without
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		LEFT JOIN {$wpdb->term_taxonomy}  tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
		LEFT JOIN {$wpdb->terms}          t  ON t.term_id = tt.term_id
		LEFT JOIN {$wpdb->postmeta}  pm_q ON pm_q.post_id = p.ID AND pm_q.meta_key = 'nuclen-quiz-data'
		LEFT JOIN {$wpdb->postmeta}  pm_s ON pm_s.post_id = p.ID AND pm_s.meta_key = '" . Summary_Service::META_KEY . "'
		WHERE p.post_type  IN ($placeholders_pt)
		AND p.post_status IN ($placeholders_st)
		GROUP BY COALESCE(t.term_id, 0), COALESCE(t.name, 'Uncategorized')
		ORDER BY SUM(CASE WHEN pm_q.meta_id IS NULL THEN 0 ELSE 1 END) + SUM(CASE WHEN pm_s.meta_id IS NULL THEN 0 ELSE 1 END) DESC
		LIMIT %d",
			array_merge( $post_types, $statuses, array( $limit ) )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! empty( $wpdb->last_error ) ) {
			LoggingService::log( 'Category stats query error: ' . $wpdb->last_error );
			return array();
		}

		// Cache the results
		wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );

		return $rows;
	}



	/**
	 * Get all generation tasks including active, completed and retry status.
	 *
	 * @return array Combined list of generation tasks.
	 */
	public function get_all_generation_tasks(): array {
		$tasks       = array();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		// Get all batch jobs (active and completed)
		$batch_jobs = $this->get_active_batch_jobs();
		foreach ( $batch_jobs as $batch ) {
			$tasks[] = $batch;
		}

		// Get retry status
		$retry_tasks = $this->get_retry_tasks();
		foreach ( $retry_tasks as $task ) {
			$tasks[] = $task;
		}

		// Sort tasks by created_at in descending order (most recent first)
		usort(
			$tasks,
			function ( $a, $b ) {
				$a_time = $a['created_at'] ?? 0;
				$b_time = $b['created_at'] ?? 0;
				return $b_time - $a_time;
			}
		);

		return $tasks;
	}

	/**
	 * Get active batch generation jobs using optimized approach.
	 *
	 * @return array Active batch jobs.
	 */
	private function get_active_batch_jobs(): array {
		global $wpdb;
		$jobs        = array();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		// Use transient index for efficient lookup
		$job_index = get_transient( self::TRANSIENT_INDEX_PREFIX . 'bulk_jobs' );
		if ( false === $job_index ) {
			// Rebuild index if missing
			$job_index = $this->rebuild_transient_index( 'bulk_job' );
		}

		if ( empty( $job_index ) ) {
			// Fallback to direct query with LIMIT
			$sql = $wpdb->prepare(
				"SELECT option_name, option_value 
				FROM {$wpdb->options} 
				WHERE option_name LIKE %s
				ORDER BY option_id DESC
				LIMIT 50",
				'_transient_nuclen_bulk_job_%'
			);

			$results = $wpdb->get_results( $sql );

			// Debug logging
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'DashboardDataService: SQL query: %s', $sql )
			);
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'DashboardDataService: Found %d bulk job transients', count( $results ) )
			);

			$results = $wpdb->get_results( $sql );

			// Check for database errors
			if ( ! empty( $wpdb->last_error ) ) {
				LoggingService::log( 'DashboardDataService query error: ' . $wpdb->last_error );
				return array();
			}
		} else {
			// Use indexed transients for better performance
			$results = array();
			foreach ( $job_index as $transient_name ) {
				$value = get_transient( str_replace( '_transient_', '', $transient_name ) );
				if ( $value !== false ) {
					$results[] = (object) array(
						'option_name'  => $transient_name,
						'option_value' => serialize( $value ),
					);
				}
			}
		}

		foreach ( $results as $row ) {
			$data = maybe_unserialize( $row->option_value );
			if ( ! is_array( $data ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( 'DashboardDataService: Skipping non-array transient: %s', $row->option_name )
				);
				continue;
			}

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'DashboardDataService: Processing job from transient %s, status: %s, posts: %d',
					$row->option_name,
					$data['status'] ?? 'unknown',
					$data['total_posts'] ?? 0
				)
			);

			// Determine the status
			$status = 'active';
			if ( isset( $data['status'] ) ) {
				if ( in_array( $data['status'], array( 'completed', 'completed_with_errors' ), true ) ) {
					$status = 'completed';
				} elseif ( $data['status'] === 'failed' ) {
					$status = 'failed';
				}
			}

			$processed     = 0;
			$total         = count( $data['batch_jobs'] ?? array() );
			$success_count = 0;
			$fail_count    = 0;

			// Batch fetch transients for better performance
			$batch_ids = array();
			foreach ( $data['batch_jobs'] ?? array() as $batch_job ) {
				$batch_ids[] = 'nuclen_batch_' . $batch_job['batch_id'];
			}

			$batch_data_results = $this->get_multiple_transients( $batch_ids );

			// Count processed batches and results
			foreach ( $batch_data_results as $batch_data ) {
				if ( is_array( $batch_data ) ) {
					if ( in_array( $batch_data['status'], array( 'completed', 'failed' ), true ) ) {
						++$processed;
					}
					// Check if results are stored in the results array (new format)
					if ( isset( $batch_data['results'] ) ) {
						$success_count += $batch_data['results']['success_count'] ?? 0;
						$fail_count    += $batch_data['results']['fail_count'] ?? 0;
					} else {
						// Fallback to old format if it exists
						$success_count += $batch_data['success_count'] ?? 0;
						$fail_count    += $batch_data['fail_count'] ?? 0;
					}
				}
			}

			$progress = $total > 0 ? round( ( $processed / $total ) * 100 ) : 0;

			// Determine the title based on whether this is a single post or bulk generation
			$post_title = sprintf( __( 'Bulk Generation (%d posts)', 'nuclear-engagement' ), $data['total_posts'] ?? 0 );

			// If this is a single post generation, try to get the actual post title
			if ( isset( $data['total_posts'] ) && $data['total_posts'] === 1 ) {
				// Get the first batch job to access the post data
				if ( ! empty( $data['batch_jobs'] ) && isset( $data['batch_jobs'][0]['batch_id'] ) ) {
					$first_batch_data = TaskTransientManager::get_batch_transient( $data['batch_jobs'][0]['batch_id'] );
					if ( is_array( $first_batch_data ) && ! empty( $first_batch_data['posts'] ) ) {
						$first_post = reset( $first_batch_data['posts'] );
						if ( isset( $first_post['title'] ) ) {
							$post_title = $first_post['title'];
						} elseif ( isset( $first_post['post_id'] ) ) {
							// Try to get title from post ID
							$post = get_post( $first_post['post_id'] );
							if ( $post ) {
								$post_title = $post->post_title;
							}
						}
					}
				}
			}

			// Build the job array based on status
			$job = array(
				'post_title'    => $post_title,
				'workflow_type' => $data['workflow_type'] ?? 'unknown',
				'status'        => $status,
				'created_at'    => $data['created_at'] ?? 0,
			);

			// Add appropriate fields based on status
			if ( $status === 'completed' ) {
				if ( isset( $data['completed_at'] ) ) {
					$job['completed_at'] = date_i18n( $date_format . ' ' . $time_format, $data['completed_at'] );
				}
				$job['details'] = sprintf( __( '%1$d succeeded, %2$d failed', 'nuclear-engagement' ), $success_count, $fail_count );
			} else {
				$job['progress'] = $progress . '%';
				$job['details']  = sprintf( __( '%1$d of %2$d batches processed', 'nuclear-engagement' ), $processed, $total );
			}

			$jobs[] = $job;
		}

		return $jobs;
	}

	/**
	 * Get recent completed batch jobs.
	 *
	 * @return array Recent completed jobs.
	 */
	private function get_recent_completed_batches(): array {
		global $wpdb;
		$jobs        = array();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		// Find completed bulk generation jobs from the last 24 hours
		$sql = $wpdb->prepare(
			"SELECT option_name, option_value 
			FROM {$wpdb->options} 
			WHERE option_name LIKE %s
			AND (option_value LIKE %s OR option_value LIKE %s)",
			'_transient_nuclen_bulk_job_%',
			'%"status":"completed"%',
			'%"status":"completed_with_errors"%'
		);

		$results     = $wpdb->get_results( $sql );
		$cutoff_time = time() - DAY_IN_SECONDS;

		foreach ( $results as $row ) {
			$data = maybe_unserialize( $row->option_value );
			if ( ! is_array( $data ) || empty( $data['completed_at'] ) ) {
				continue;
			}

			// Only show jobs completed in the last 24 hours
			if ( $data['completed_at'] < $cutoff_time ) {
				continue;
			}

			$success_count = 0;
			$fail_count    = 0;

			// Count successes and failures
			foreach ( $data['batch_jobs'] ?? array() as $batch_job ) {
				$batch_data = TaskTransientManager::get_batch_transient( $batch_job['batch_id'] );
				if ( is_array( $batch_data ) ) {
					// Check if results are stored in the results array (new format)
					if ( isset( $batch_data['results'] ) ) {
						$success_count += $batch_data['results']['success_count'] ?? 0;
						$fail_count    += $batch_data['results']['fail_count'] ?? 0;
					} else {
						// Fallback to old format if it exists
						$success_count += $batch_data['success_count'] ?? 0;
						$fail_count    += $batch_data['fail_count'] ?? 0;
					}
				}
			}

			// Determine the title based on whether this is a single post or bulk generation
			$post_title = sprintf( __( 'Bulk Generation (%d posts)', 'nuclear-engagement' ), $data['total_posts'] ?? 0 );

			// If this is a single post generation, try to get the actual post title
			if ( isset( $data['total_posts'] ) && $data['total_posts'] === 1 ) {
				// Get the first batch job to access the post data
				if ( ! empty( $data['batch_jobs'] ) && isset( $data['batch_jobs'][0]['batch_id'] ) ) {
					$first_batch_data = TaskTransientManager::get_batch_transient( $data['batch_jobs'][0]['batch_id'] );
					if ( is_array( $first_batch_data ) && ! empty( $first_batch_data['posts'] ) ) {
						$first_post = reset( $first_batch_data['posts'] );
						if ( isset( $first_post['title'] ) ) {
							$post_title = $first_post['title'];
						} elseif ( isset( $first_post['post_id'] ) ) {
							// Try to get title from post ID
							$post = get_post( $first_post['post_id'] );
							if ( $post ) {
								$post_title = $post->post_title;
							}
						}
					}
				}
			}

			$status = $data['status'] === 'completed_with_errors' ? 'completed' : 'completed';
			$jobs[] = array(
				'post_title'    => $post_title,
				'workflow_type' => $data['workflow_type'] ?? 'unknown',
				'status'        => $status,
				'completed_at'  => date_i18n( $date_format . ' ' . $time_format, $data['completed_at'] ),
				'details'       => sprintf( __( '%1$d succeeded, %2$d failed', 'nuclear-engagement' ), $success_count, $fail_count ),
			);
		}

		return $jobs;
	}

	/**
	 * Get retry tasks from unified generation system.
	 *
	 * @return array Retry tasks.
	 */
	private function get_retry_tasks(): array {
		$tasks       = array();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		// Get retry status from GenerationService
		$container = \NuclearEngagement\Core\ServiceContainer::getInstance();
		if ( $container->has( 'generation_service' ) ) {
			$generation_service = $container->get( 'generation_service' );
			$retry_status       = $generation_service->get_retry_status();

			foreach ( $retry_status as $retry ) {
				// Check for next scheduled batch processing
				$next_retry_time = wp_next_scheduled( 'nuclen_process_batch', array( $retry['batch_id'] ) );

				$tasks[] = array(
					'post_title'    => $retry['post_title'],
					'workflow_type' => $retry['workflow_type'],
					'status'        => 'retry',
					'attempt'       => sprintf( '%d/%d', $retry['retry_count'], $retry['max_retries'] ),
					'next_poll'     => $next_retry_time ? date_i18n( $date_format . ' ' . $time_format, $next_retry_time ) : __( 'Not scheduled', 'nuclear-engagement' ),
					'details'       => substr( $retry['last_error'], 0, 100 ),
					'created_at'    => $retry['started_at'] ?? 0,
				);
			}
		}

		return $tasks;
	}

	/**
	 * Get multiple transients efficiently.
	 *
	 * @param array $transient_names Array of transient names.
	 * @return array Array of transient values.
	 */
	private function get_multiple_transients( array $transient_names ): array {
		global $wpdb;

		if ( empty( $transient_names ) ) {
			return array();
		}

		// Try object cache first
		$results      = array();
		$cache_misses = array();

		foreach ( $transient_names as $name ) {
			$value = wp_cache_get( $name, 'transient' );
			if ( false !== $value ) {
				$results[] = $value;
			} else {
				$cache_misses[] = '_transient_' . $name;
			}
		}

		// Batch fetch cache misses from database
		if ( ! empty( $cache_misses ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $cache_misses ), '%s' ) );
			$sql          = $wpdb->prepare(
				"SELECT option_name, option_value 
				FROM {$wpdb->options} 
				WHERE option_name IN ($placeholders)",
				$cache_misses
			);

			$db_results = $wpdb->get_results( $sql );

			if ( ! empty( $wpdb->last_error ) ) {
				LoggingService::log( 'Multiple transients fetch error: ' . $wpdb->last_error );
			} elseif ( is_array( $db_results ) ) {
				foreach ( $db_results as $row ) {
					$value     = maybe_unserialize( $row->option_value );
					$results[] = $value;

					// Cache for next time
					$transient_name = str_replace( '_transient_', '', $row->option_name );
					wp_cache_set( $transient_name, $value, 'transient', 300 );
				}
			}
		}

		return $results;
	}

	/**
	 * Rebuild transient index for efficient lookups.
	 *
	 * @param string $type Type of transients to index (bulk_job, batch, etc).
	 * @return array Array of transient names.
	 */
	private function rebuild_transient_index( string $type ): array {
		global $wpdb;

		$pattern = '_transient_nuclen_' . $type . '_%';
		$sql     = $wpdb->prepare(
			"SELECT option_name 
			FROM {$wpdb->options} 
			WHERE option_name LIKE %s
			LIMIT 100",
			$pattern
		);

		$results = $wpdb->get_col( $sql );

		if ( ! empty( $wpdb->last_error ) ) {
			LoggingService::log( 'Transient index rebuild error: ' . $wpdb->last_error );
			return array();
		}

		// Store index for 5 minutes
		set_transient( self::TRANSIENT_INDEX_PREFIX . $type . 's', $results, 300 );

		return $results ?: array();
	}

	/**
	 * Ensure database indexes exist for optimal performance.
	 */
	public static function ensure_indexes(): void {
		global $wpdb;

		// Check if indexes already exist
		$existing_indexes = $wpdb->get_results(
			"SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name IN ('nuclen_quiz_idx', 'nuclen_summary_idx')"
		);

		$has_quiz_idx    = false;
		$has_summary_idx = false;

		foreach ( $existing_indexes as $index ) {
			if ( $index->Key_name === 'nuclen_quiz_idx' ) {
				$has_quiz_idx = true;
			} elseif ( $index->Key_name === 'nuclen_summary_idx' ) {
				$has_summary_idx = true;
			}
		}

		// Create missing indexes with MySQL 5.7 compatibility
		if ( ! $has_quiz_idx ) {
			$result = $wpdb->query(
				"CREATE INDEX nuclen_quiz_idx ON {$wpdb->postmeta} (meta_key(20), post_id)"
			);

			if ( false === $result ) {
				LoggingService::log( 'Failed to create quiz index: ' . $wpdb->last_error );
			}
		}

		if ( ! $has_summary_idx ) {
			$result = $wpdb->query(
				"CREATE INDEX nuclen_summary_idx ON {$wpdb->postmeta} (meta_key(20), post_id)"
			);

			if ( false === $result ) {
				LoggingService::log( 'Failed to create summary index: ' . $wpdb->last_error );
			}
		}
	}
}
