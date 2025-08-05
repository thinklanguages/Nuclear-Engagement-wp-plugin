<?php
/**
 * UpdatesController.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Controller_Ajax
 */

declare(strict_types=1);
/**
 * File: admin/Controller/Ajax/UpdatesController.php
 *
 * Updates Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Requests\UpdatesRequest;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Responses\UpdatesResponse;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Services\BulkGenerationTimeoutHandler;
use NuclearEngagement\Services\TaskTransientManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for polling updates
 */
class UpdatesController extends BaseController {

	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $api;

	/**
	 * @var ContentStorageService
	 */
	private ContentStorageService $storage;


	/**
	 * Constructor.
	 *
	 * @param RemoteApiService      $api
	 * @param ContentStorageService $storage
	 */
	public function __construct( RemoteApiService $api, ContentStorageService $storage ) {
		$this->api     = $api;
		$this->storage = $storage;
	}

	/**
	 * Handle updates request.
	 */
	public function handle(): void {
		try {
			if ( ! $this->verify_request( 'nuclen_admin_ajax_nonce' ) ) {
				return;
			}

				$request = UpdatesRequest::fromPost( $_POST );

				// Credits-check ping uses a dummy generation_id.
			$is_credits_check = false;
			if ( empty( $request->generationId ) ) {
				// For credit checks without generation ID, return early with just credits
				try {
					// Fetch credits only
					$credits_data      = $this->api->fetch_credits_only();
					$response          = new UpdatesResponse();
					$response->success = true;
					if ( isset( $credits_data['remaining_credits'] ) ) {
						$response->remainingCredits = (int) $credits_data['remaining_credits'];
					}
					wp_send_json_success( $response->toArray() );
					return;
				} catch ( \Exception $e ) {
					// If credits-only fetch fails, continue with the normal flow
					$request->generationId = 'gen_' . uniqid( 'auto_', true );
					$is_credits_check      = true;
					\NuclearEngagement\Services\LoggingService::debug( 'Credit-only fetch failed, falling back to updates API with dummy ID: ' . $request->generationId );
				}
			}

								// Send keepalive signal before API call
				BulkGenerationTimeoutHandler::send_keepalive();

				// Check if this is a batch job (skip for credits check)
				$batch_status = ! $is_credits_check ? $this->getBatchStatus( $request->generationId ) : null;
			if ( $batch_status && is_array( $batch_status ) ) {
				// Check if the task has been cancelled
				if ( isset( $batch_status['status'] ) && $batch_status['status'] === 'cancelled' ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( 'Task %s has been cancelled, stopping polling', $request->generationId )
					);
					$this->send_error( __( 'Task has been cancelled', 'nuclear-engagement' ), 410 );
					return;
				}
				// For batch jobs, we need to poll individual batches
				$batch_results = $this->pollBatchResults( $request->generationId );

				// Return aggregated batch progress
				$data = array(
					'processed'      => isset( $batch_status['processed_posts'] ) ? $batch_status['processed_posts'] : 0,
					'total'          => isset( $batch_status['total_posts'] ) ? $batch_status['total_posts'] : 0,
					'success_count'  => isset( $batch_status['processed_posts'], $batch_status['failed_posts'] ) ? $batch_status['processed_posts'] - $batch_status['failed_posts'] : 0,
					'fail_count'     => isset( $batch_status['failed_posts'] ) ? $batch_status['failed_posts'] : 0,
					'message'        => sprintf(
						'Processing %d of %d posts',
						isset( $batch_status['processed_posts'] ) ? $batch_status['processed_posts'] : 0,
						isset( $batch_status['total_posts'] ) ? $batch_status['total_posts'] : 0
					),
					'is_batch_mode'  => true,
					'batch_progress' => isset( $batch_status['progress_percentage'] ) ? $batch_status['progress_percentage'] : 0,
				);

				// Add any new results from polling
				if ( ! empty( $batch_results ) && is_array( $batch_results ) ) {
					$data['results']  = $batch_results;
					$data['workflow'] = $this->detectWorkflowType( $batch_results, reset( $batch_results ), $request->generationId );
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[UpdatesController] New batch results found: %d items', count( $batch_results ) )
					);
				}

				// Add any new results from polling
				if ( ! empty( $batch_results ) && is_array( $batch_results ) ) {
					$data['results']  = $batch_results;
					$data['workflow'] = $this->detectWorkflowType( $batch_results, reset( $batch_results ), $request->generationId );
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[UpdatesController] New batch results found: %d items', count( $batch_results ) )
					);
				}

				// If all batches are complete or we don't have results yet, gather all results
				if ( ( isset( $batch_status['status'] ) && ( $batch_status['status'] === 'completed' || $batch_status['status'] === 'completed_with_errors' ) ) || empty( $data['results'] ) ) {
					$all_available_results = $this->gatherBatchResults( $request->generationId );
					if ( ! empty( $all_available_results ) && is_array( $all_available_results ) ) {
						$data['results']  = $all_available_results;
						$data['workflow'] = $this->detectWorkflowType( $data['results'], reset( $data['results'] ), $request->generationId );
						\NuclearEngagement\Services\LoggingService::log(
							sprintf( '[UpdatesController] Gathered all results for completed generation %s: %d items', $request->generationId, count( $all_available_results ) )
						);
					}
				}
			} else {
				// Normal single generation flow
				$data = $this->api->fetch_updates( $request->generationId );

				// Ensure $data is an array before proceeding
				if ( ! is_array( $data ) ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Invalid API response format - Expected: array | Actual: %s | Generation: %s | Data sample: %s',
							gettype( $data ),
							$request->generationId ?? 'unknown',
							is_string( $data ) ? substr( $data, 0, 200 ) . '...' : wp_json_encode( $data )
						)
					);
					throw new \Exception( 'Invalid response format from API' );
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$log_prefix = $is_credits_check ? 'CREDIT_CHECK' : 'GENERATION_UPDATE';
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'DEBUG: %s - Generation: %s | Status: %s | Results: %s | Credits: %d',
							$log_prefix,
							$request->generationId ?? 'unknown',
							$data['status'] ?? 'unknown',
							! empty( $data['results'] ) ? 'present' : 'none',
							$data['credits'] ?? -1
						)
					);
				}
			}

				$response          = new UpdatesResponse();
				$response->success = true;

			if ( isset( $data['processed'] ) ) {
				$response->processed = (int) $data['processed'];
			}
			if ( isset( $data['total'] ) ) {
				$response->total = (int) $data['total'];
			}
			if ( isset( $data['remaining_credits'] ) ) {
				$response->remainingCredits = (int) $data['remaining_credits'];
			}
			if ( isset( $data['message'] ) ) {
				$response->message = $data['message'];
			}

			// Add workflow if present in data (for batch mode)
			if ( isset( $data['workflow'] ) ) {
				$response->workflow = $data['workflow'];
			}

				/* ── Persist & return results ───────────────────────────── */
			if ( ! empty( $data['results'] ) && is_array( $data['results'] ) ) {
					// Filter out summary statistics from results
					$post_results = array_filter(
						$data['results'],
						function ( $key ) {
							// Only keep numeric post IDs, filter out summary keys
							return is_numeric( $key ) && ! in_array( $key, array( 'success_count', 'fail_count', 'processed_count' ), true );
						},
						ARRAY_FILTER_USE_KEY
					);

				if ( ! empty( $post_results ) ) {
					$first = reset( $post_results );

					// Improve workflow detection logic
					$workflow_type = $this->detectWorkflowType( $post_results, $first, $request->generationId );

					$statuses = $this->storage->storeResults( $post_results, $workflow_type );

					if ( array_filter( $statuses, static fn( $s ) => $s !== true ) ) {
								$this->send_error( __( 'Failed to store content.', 'nuclear-engagement' ) );
								return;
					}

					$response->results  = $post_results;  // Send only actual post results
					$response->workflow = $workflow_type; // NEW → lets JS forward it to /receive-content.

					// Add summary statistics to response if they exist
					if ( isset( $data['results']['success_count'] ) ) {
						$response->success_count = $data['results']['success_count'];
					}
					if ( isset( $data['results']['fail_count'] ) ) {
						$response->fail_count = $data['results']['fail_count'];
					}
					if ( isset( $data['results']['processed_count'] ) ) {
						$response->processed_count = $data['results']['processed_count'];
					}
				} else {
					// No actual post results, just return the response with statistics
					$response->results = array();
				}
			}

				wp_send_json_success( $response->toArray() );

		} catch ( ApiException $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'API error fetching updates - Generation: %s | Error: %s | Code: %d | Workflow: %s',
					$request->generationId ?? 'unknown',
					$e->getMessage(),
					$e->getCode(),
					$workflow_type ?? 'unknown'
				)
			);
			$message = __( 'Failed to fetch updates. Please try again later.', 'nuclear-engagement' );
			$this->send_error( $message, $e->getCode() ?: 500 );
		} catch ( \Throwable $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'Unexpected error fetching updates - Generation: %s | Error: %s | Type: %s',
					$request->generationId ?? 'unknown',
					$e->getMessage(),
					get_class( $e )
				)
			);
			$this->send_error( __( 'An unexpected error occurred.', 'nuclear-engagement' ) );
		}
	}

	/**
	 * Detect workflow type from results data
	 *
	 * @param array  $results Full results array
	 * @param mixed  $first First result item
	 * @param string $generationId Generation ID for pattern matching
	 * @return string Detected workflow type
	 */
	private function detectWorkflowType( array $results, $first, string $generationId ): string {
		// Method 1: Check for 'questions' field in any result
		foreach ( $results as $result ) {
			if ( is_array( $result ) && isset( $result['questions'] ) && is_array( $result['questions'] ) ) {
				return 'quiz';
			}
		}

		// Method 2: Check for 'summary' or 'content' field indicating summary workflow
		foreach ( $results as $result ) {
			if ( is_array( $result ) && ( isset( $result['summary'] ) || isset( $result['content'] ) ) ) {
				return 'summary';
			}
		}

		// Method 3: Pattern matching on generation ID
		if ( strpos( $generationId, 'quiz' ) !== false ) {
			return 'quiz';
		}
		if ( strpos( $generationId, 'summary' ) !== false ) {
			return 'summary';
		}

		// Method 4: Check first result structure (fallback to original logic)
		if ( is_array( $first ) && isset( $first['questions'] ) ) {
			return 'quiz';
		}

		// Default to summary if unclear
		\NuclearEngagement\Services\LoggingService::log( 'Could not determine workflow type from results, defaulting to summary' );
		return 'summary';
	}

	/**
	 * Get batch processing status
	 *
	 * @param string $generationId Generation ID
	 * @return array|null Batch status or null if not a batch job
	 */
	private function getBatchStatus( string $generationId ): ?array {
		$processor = new \NuclearEngagement\Services\BulkGenerationBatchProcessor(
			\NuclearEngagement\Core\SettingsRepository::get_instance()
		);
		return $processor->get_batch_status( $generationId );
	}

	/**
	 * Poll for results from individual batches
	 *
	 * @param string $generationId Parent generation ID
	 * @return array New results found
	 */
	private function pollBatchResults( string $generationId ): array {
		$all_results = array();
		$parent_data = TaskTransientManager::get_task_transient( $generationId );

		if ( ! is_array( $parent_data ) || empty( $parent_data['batch_jobs'] ) ) {
			return $all_results;
		}

		foreach ( $parent_data['batch_jobs'] as $job ) {
			$batch_data = TaskTransientManager::get_batch_transient( $job['batch_id'] );
			if ( ! is_array( $batch_data ) || $batch_data['status'] !== 'processing' ) {
				continue;
			}

			// Poll for this batch's results
			if ( isset( $batch_data['api_generation_id'] ) ) {
				$api_generation_id = $batch_data['api_generation_id'];
			} else {
				// Use the batch ID as the API generation ID
				$api_generation_id = $job['batch_id'];
			}

			try {
				$updates = $this->api->fetch_updates( $api_generation_id );

				if ( ! empty( $updates['results'] ) && is_array( $updates['results'] ) ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Batch %s: API returned %d results for posts: %s',
							$job['batch_id'],
							count( $updates['results'] ),
							implode( ', ', array_keys( $updates['results'] ) )
						)
					);

					// Check if we have all results or if generation is complete
					$is_complete = false;
					if ( isset( $updates['processed'] ) && isset( $updates['total'] ) ) {
						$is_complete = $updates['processed'] >= $updates['total'];
						\NuclearEngagement\Services\LoggingService::log(
							sprintf(
								'Batch %s: processed=%d, total=%d, complete=%s',
								$job['batch_id'],
								$updates['processed'],
								$updates['total'],
								$is_complete ? 'yes' : 'no'
							)
						);
					}

					// Store partial results in batch transient using accumulated_results
					if ( ! isset( $batch_data['accumulated_results'] ) ) {
						$batch_data['accumulated_results'] = array();
					}
					// Merge new results with existing accumulated ones
					foreach ( $updates['results'] as $post_id => $result ) {
						$batch_data['accumulated_results'][ $post_id ] = $result;
					}

					if ( $is_complete ) {
						$batch_data['status'] = 'completed';
						// Move accumulated results to final results
						$batch_data['results'] = $batch_data['accumulated_results'];

						// Calculate success/fail counts from accumulated results
						$success_count = 0;
						$fail_count    = 0;
						if ( isset( $batch_data['accumulated_results'] ) && is_array( $batch_data['accumulated_results'] ) ) {
							foreach ( $batch_data['accumulated_results'] as $post_id => $result ) {
								if ( is_array( $result ) ) {
									// Check if the result indicates success or failure
									if ( isset( $result['error'] ) || isset( $result['failed'] ) ||
										( isset( $result['status'] ) && $result['status'] === 'failed' ) ) {
										++$fail_count;
									} else {
										++$success_count;
									}
								}
							}
						}

						// Store counts in batch data
						$batch_data['success_count'] = $success_count;
						$batch_data['fail_count']    = $fail_count;

						\NuclearEngagement\Services\LoggingService::log(
							sprintf(
								'Batch %s completed with counts - Success: %d, Failed: %d',
								$job['batch_id'],
								$success_count,
								$fail_count
							)
						);

						// Update parent job status with counts
						$this->updateParentJobStatus(
							$generationId,
							$job['batch_id'],
							'completed',
							array(
								'success_count' => $success_count,
								'fail_count'    => $fail_count,
							)
						);
					}
					TaskTransientManager::set_batch_transient( $job['batch_id'], $batch_data, DAY_IN_SECONDS );

					// Add to results - preserve keys!
					foreach ( $updates['results'] as $post_id => $result ) {
						// Only add numeric post IDs to avoid issues
						if ( is_numeric( $post_id ) ) {
							$all_results[ $post_id ] = $result;
						} else {
							\NuclearEngagement\Services\LoggingService::log(
								sprintf( '[pollBatchResults] Skipping non-numeric key: %s', $post_id )
							);
						}
					}
				} elseif ( isset( $updates['processed'] ) && isset( $updates['total'] ) ) {
					// Still processing, update progress in batch data
					$batch_data['processed'] = $updates['processed'];
					$batch_data['total']     = $updates['total'];
					TaskTransientManager::set_batch_transient( $job['batch_id'], $batch_data, DAY_IN_SECONDS );
				}
			} catch ( \Exception $e ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( 'Error polling batch %s: %s', $job['batch_id'], $e->getMessage() )
				);
			}
		}

		return $all_results;
	}

	/**
	 * Update parent job status when a batch completes
	 *
	 * @param string $parentId Parent generation ID
	 * @param string $batchId Batch ID
	 * @param string $status New status
	 * @param array  $results Optional results array with success/fail counts
	 */
	private function updateParentJobStatus( string $parentId, string $batchId, string $status, array $results = array() ): void {
		$processor = new \NuclearEngagement\Services\BulkGenerationBatchProcessor(
			\NuclearEngagement\Core\SettingsRepository::get_instance()
		);
		$processor->update_batch_status( $batchId, $status, $results );
	}

	/**
	 * Gather all results from completed batches
	 *
	 * @param string $generationId Parent generation ID
	 * @return array Aggregated results
	 */
	private function gatherBatchResults( string $generationId ): array {
		$all_results = array();
		$parent_data = TaskTransientManager::get_task_transient( $generationId );

		if ( ! is_array( $parent_data ) || empty( $parent_data['batch_jobs'] ) ) {
			return $all_results;
		}

		foreach ( $parent_data['batch_jobs'] as $job ) {
			$batch_data = TaskTransientManager::get_batch_transient( $job['batch_id'] );
			if ( is_array( $batch_data ) ) {
				// Check for results from completed batches
				if ( ! empty( $batch_data['results'] ) && is_array( $batch_data['results'] ) ) {
					// Preserve post ID keys
					foreach ( $batch_data['results'] as $post_id => $result ) {
						// Only add numeric post IDs
						if ( is_numeric( $post_id ) ) {
							$all_results[ $post_id ] = $result;
						}
					}
				}
				// Also check for accumulated results from in-progress batches
				if ( ! empty( $batch_data['accumulated_results'] ) && is_array( $batch_data['accumulated_results'] ) ) {
					foreach ( $batch_data['accumulated_results'] as $post_id => $result ) {
						// Only add numeric post IDs
						if ( is_numeric( $post_id ) ) {
							$all_results[ $post_id ] = $result;
						}
					}
				}
			}
		}

		return $all_results;
	}
}
