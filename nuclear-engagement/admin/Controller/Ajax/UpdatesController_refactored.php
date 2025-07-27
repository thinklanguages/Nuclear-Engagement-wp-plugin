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
	 * @var UpdatesCreditsHandler
	 */
	private UpdatesCreditsHandler $credits_handler;

	/**
	 * @var UpdatesBatchHandler
	 */
	private UpdatesBatchHandler $batch_handler;

	/**
	 * @var UpdatesResultsHandler
	 */
	private UpdatesResultsHandler $results_handler;

	/**
	 * Constructor.
	 *
	 * @param RemoteApiService      $api
	 * @param ContentStorageService $storage
	 */
	public function __construct( RemoteApiService $api, ContentStorageService $storage ) {
		$this->api     = $api;
		$this->storage = $storage;
		
		// Initialize handlers
		$this->credits_handler = new UpdatesCreditsHandler( $api );
		$this->batch_handler = new UpdatesBatchHandler( $api );
		$this->results_handler = new UpdatesResultsHandler( $storage );
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

			// Handle credit check requests
			if ( empty( $request->generationId ) ) {
				$this->handleCreditCheck( $request );
				return;
			}

			// Send keepalive signal
			BulkGenerationTimeoutHandler::send_keepalive();

			// Process the request
			$data = $this->processRequest( $request );

			// Build and send response
			$response = $this->buildResponse( $data, $request );
			wp_send_json_success( $response->toArray() );

		} catch ( ApiException $e ) {
			$this->handleApiException( $e, $request );
		} catch ( \Throwable $e ) {
			$this->handleGeneralException( $e, $request );
		}
	}

	/**
	 * Handle credit check requests
	 *
	 * @param UpdatesRequest $request
	 */
	private function handleCreditCheck( UpdatesRequest $request ): void {
		$response = $this->credits_handler->handleCreditCheck( $request );
		wp_send_json_success( $response->toArray() );
	}

	/**
	 * Process the main request
	 *
	 * @param UpdatesRequest $request
	 * @return array
	 */
	private function processRequest( UpdatesRequest $request ): array {
		// Check if this is a batch job
		$batch_status = $this->batch_handler->getBatchStatus( $request->generationId );
		
		if ( $batch_status && is_array( $batch_status ) ) {
			// Check if cancelled
			if ( isset( $batch_status['status'] ) && $batch_status['status'] === 'cancelled' ) {
				\NuclearEngagement\Services\LoggingService::log( 
					sprintf( 'Task %s has been cancelled, stopping polling', $request->generationId )
				);
				$this->send_error( __( 'Task has been cancelled', 'nuclear-engagement' ), 410 );
				exit; // Stop execution
			}
			
			// Process batch request
			return $this->batch_handler->processBatchRequest( $request->generationId, $batch_status );
		}
		
		// Process normal single generation
		return $this->processSingleGeneration( $request );
	}

	/**
	 * Process single generation request
	 *
	 * @param UpdatesRequest $request
	 * @return array
	 */
	private function processSingleGeneration( UpdatesRequest $request ): array {
		$data = $this->api->fetch_updates( $request->generationId );

		// Validate response
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

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\NuclearEngagement\Services\LoggingService::log( 
				sprintf(
					'DEBUG: GENERATION_UPDATE - Generation: %s | Status: %s | Results: %s | Credits: %d',
					$request->generationId ?? 'unknown',
					$data['status'] ?? 'unknown',
					! empty( $data['results'] ) ? 'present' : 'none',
					$data['credits'] ?? -1
				)
			);
		}

		return $data;
	}

	/**
	 * Build response from data
	 *
	 * @param array $data
	 * @param UpdatesRequest $request
	 * @return UpdatesResponse
	 */
	private function buildResponse( array $data, UpdatesRequest $request ): UpdatesResponse {
		$response = new UpdatesResponse();
		$response->success = true;

		// Add basic data
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
		
		// Add workflow if present
		if ( isset( $data['workflow'] ) ) {
			$response->workflow = $data['workflow'];
		}

		// Process results if present
		if ( ! empty( $data['results'] ) && is_array( $data['results'] ) ) {
			$processed_response = $this->results_handler->processResults( 
				$data['results'], 
				$request->generationId 
			);
			
			// Merge processed response data
			foreach ( $processed_response as $key => $value ) {
				$response->$key = $value;
			}
		}

		return $response;
	}

	/**
	 * Handle API exceptions
	 *
	 * @param ApiException $e
	 * @param UpdatesRequest $request
	 */
	private function handleApiException( ApiException $e, UpdatesRequest $request ): void {
		\NuclearEngagement\Services\LoggingService::log( 
			sprintf(
				'API error fetching updates - Generation: %s | Error: %s | Code: %d',
				$request->generationId ?? 'unknown',
				$e->getMessage(),
				$e->getCode()
			)
		);
		$message = __( 'Failed to fetch updates. Please try again later.', 'nuclear-engagement' );
		$this->send_error( $message, $e->getCode() ?: 500 );
	}

	/**
	 * Handle general exceptions
	 *
	 * @param \Throwable $e
	 * @param UpdatesRequest $request
	 */
	private function handleGeneralException( \Throwable $e, UpdatesRequest $request ): void {
		\NuclearEngagement\Services\LoggingService::log( 
			sprintf(
				'Unexpected error fetching updates - Generation: %s | Error: %s | Type: %s',
				$request->generationId ?? 'unknown',
				$e->getMessage(),
				get_class($e)
			)
		);
		$this->send_error( __( 'An unexpected error occurred.', 'nuclear-engagement' ) );
	}
}

/**
 * Handles credit check operations
 */
class UpdatesCreditsHandler {
	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $api;

	/**
	 * Constructor
	 *
	 * @param RemoteApiService $api
	 */
	public function __construct( RemoteApiService $api ) {
		$this->api = $api;
	}

	/**
	 * Handle credit check request
	 *
	 * @param UpdatesRequest $request
	 * @return UpdatesResponse
	 */
	public function handleCreditCheck( UpdatesRequest $request ): UpdatesResponse {
		try {
			// Fetch credits only
			$credits_data = $this->api->fetch_credits_only();
			$response = new UpdatesResponse();
			$response->success = true;
			
			if ( isset( $credits_data['remaining_credits'] ) ) {
				$response->remainingCredits = (int) $credits_data['remaining_credits'];
			}
			
			return $response;
		} catch ( \Exception $e ) {
			// If credits-only fetch fails, fallback to dummy generation ID
			$request->generationId = 'gen_' . uniqid( 'auto_', true );
			
			\NuclearEngagement\Services\LoggingService::debug( 
				'Credit-only fetch failed, falling back to updates API with dummy ID: ' . $request->generationId 
			);
			
			// Rethrow to be handled by main flow
			throw $e;
		}
	}
}

/**
 * Handles batch processing operations
 */
class UpdatesBatchHandler {
	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $api;

	/**
	 * Constructor
	 *
	 * @param RemoteApiService $api
	 */
	public function __construct( RemoteApiService $api ) {
		$this->api = $api;
	}

	/**
	 * Get batch processing status
	 *
	 * @param string $generationId Generation ID
	 * @return array|null Batch status or null if not a batch job
	 */
	public function getBatchStatus( string $generationId ): ?array {
		$processor = new \NuclearEngagement\Services\BulkGenerationBatchProcessor(
			\NuclearEngagement\Core\SettingsRepository::get_instance()
		);
		return $processor->get_batch_status( $generationId );
	}

	/**
	 * Process batch request
	 *
	 * @param string $generationId
	 * @param array $batch_status
	 * @return array
	 */
	public function processBatchRequest( string $generationId, array $batch_status ): array {
		// Poll individual batches
		$batch_results = $this->pollBatchResults( $generationId );

		// Build response data
		$data = array(
			'processed'      => isset( $batch_status['processed_posts'] ) ? $batch_status['processed_posts'] : 0,
			'total'          => isset( $batch_status['total_posts'] ) ? $batch_status['total_posts'] : 0,
			'success_count'  => isset( $batch_status['processed_posts'], $batch_status['failed_posts'] ) 
				? $batch_status['processed_posts'] - $batch_status['failed_posts'] : 0,
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
			$data['results'] = $batch_results;
			$data['workflow'] = $this->detectWorkflowType( $batch_results, reset( $batch_results ), $generationId );
			\NuclearEngagement\Services\LoggingService::log( 
				sprintf( '[UpdatesController] New batch results found: %d items', count( $batch_results ) )
			);
		}
		
		// If all batches are complete or no results yet, gather all results
		if ( $this->shouldGatherAllResults( $batch_status, $data ) ) {
			$all_available_results = $this->gatherBatchResults( $generationId );
			if ( ! empty( $all_available_results ) && is_array( $all_available_results ) ) {
				$data['results'] = $all_available_results;
				$data['workflow'] = $this->detectWorkflowType( $data['results'], reset( $data['results'] ), $generationId );
				\NuclearEngagement\Services\LoggingService::log( 
					sprintf( '[UpdatesController] Gathered all results for completed generation %s: %d items', 
						$generationId, count( $all_available_results ) )
				);
			}
		}

		return $data;
	}

	/**
	 * Check if we should gather all results
	 *
	 * @param array $batch_status
	 * @param array $data
	 * @return bool
	 */
	private function shouldGatherAllResults( array $batch_status, array $data ): bool {
		$is_completed = isset( $batch_status['status'] ) && 
			in_array( $batch_status['status'], array( 'completed', 'completed_with_errors' ), true );
		
		$no_results_yet = empty( $data['results'] );
		
		return $is_completed || $no_results_yet;
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
			$batch_results = $this->pollSingleBatch( $job, $generationId );
			
			// Add to results - preserve keys!
			foreach ( $batch_results as $post_id => $result ) {
				// Only add numeric post IDs
				if ( is_numeric( $post_id ) ) {
					$all_results[ $post_id ] = $result;
				} else {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[pollBatchResults] Skipping non-numeric key: %s', $post_id )
					);
				}
			}
		}

		return $all_results;
	}

	/**
	 * Poll a single batch for results
	 *
	 * @param array $job
	 * @param string $generationId
	 * @return array
	 */
	private function pollSingleBatch( array $job, string $generationId ): array {
		$results = array();
		$batch_data = TaskTransientManager::get_batch_transient( $job['batch_id'] );
		
		if ( ! is_array( $batch_data ) || $batch_data['status'] !== 'processing' ) {
			return $results;
		}

		// Get API generation ID
		$api_generation_id = $batch_data['api_generation_id'] ?? $job['batch_id'];

		try {
			$updates = $this->api->fetch_updates( $api_generation_id );

			if ( ! empty( $updates['results'] ) && is_array( $updates['results'] ) ) {
				$this->logBatchResults( $job['batch_id'], $updates['results'] );
				
				// Process and store results
				$this->processBatchUpdates( $batch_data, $updates, $job['batch_id'], $generationId );
				
				// Return the results
				$results = $updates['results'];
			} elseif ( isset( $updates['processed'] ) && isset( $updates['total'] ) ) {
				// Still processing, update progress
				$batch_data['processed'] = $updates['processed'];
				$batch_data['total'] = $updates['total'];
				TaskTransientManager::set_batch_transient( $job['batch_id'], $batch_data, DAY_IN_SECONDS );
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( 'Error polling batch %s: %s', $job['batch_id'], $e->getMessage() )
			);
		}

		return $results;
	}

	/**
	 * Process batch updates
	 *
	 * @param array $batch_data
	 * @param array $updates
	 * @param string $batch_id
	 * @param string $generation_id
	 */
	private function processBatchUpdates( array &$batch_data, array $updates, string $batch_id, string $generation_id ): void {
		// Check if generation is complete
		$is_complete = false;
		if ( isset( $updates['processed'] ) && isset( $updates['total'] ) ) {
			$is_complete = $updates['processed'] >= $updates['total'];
			$this->logBatchProgress( $batch_id, $updates['processed'], $updates['total'], $is_complete );
		}

		// Store partial results
		if ( ! isset( $batch_data['accumulated_results'] ) ) {
			$batch_data['accumulated_results'] = array();
		}
		
		// Merge new results
		foreach ( $updates['results'] as $post_id => $result ) {
			$batch_data['accumulated_results'][ $post_id ] = $result;
		}

		if ( $is_complete ) {
			$this->completeBatch( $batch_data, $batch_id, $generation_id );
		}
		
		TaskTransientManager::set_batch_transient( $batch_id, $batch_data, DAY_IN_SECONDS );
	}

	/**
	 * Complete a batch
	 *
	 * @param array $batch_data
	 * @param string $batch_id
	 * @param string $generation_id
	 */
	private function completeBatch( array &$batch_data, string $batch_id, string $generation_id ): void {
		$batch_data['status'] = 'completed';
		$batch_data['results'] = $batch_data['accumulated_results'];
		
		// Calculate success/fail counts
		$counts = $this->calculateBatchCounts( $batch_data['accumulated_results'] );
		
		$batch_data['success_count'] = $counts['success'];
		$batch_data['fail_count'] = $counts['fail'];
		
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'Batch %s completed with counts - Success: %d, Failed: %d',
				$batch_id,
				$counts['success'],
				$counts['fail']
			)
		);
		
		// Update parent job status
		$this->updateParentJobStatus( $generation_id, $batch_id, 'completed', $counts );
	}

	/**
	 * Calculate success/fail counts from results
	 *
	 * @param array $results
	 * @return array
	 */
	private function calculateBatchCounts( array $results ): array {
		$success_count = 0;
		$fail_count = 0;
		
		foreach ( $results as $post_id => $result ) {
			if ( is_array( $result ) ) {
				// Check if the result indicates failure
				if ( isset( $result['error'] ) || isset( $result['failed'] ) || 
					 ( isset( $result['status'] ) && $result['status'] === 'failed' ) ) {
					$fail_count++;
				} else {
					$success_count++;
				}
			}
		}
		
		return array( 'success' => $success_count, 'fail' => $fail_count );
	}

	/**
	 * Update parent job status when a batch completes
	 *
	 * @param string $parentId Parent generation ID
	 * @param string $batchId Batch ID
	 * @param string $status New status
	 * @param array $counts Success/fail counts
	 */
	private function updateParentJobStatus( string $parentId, string $batchId, string $status, array $counts ): void {
		$processor = new \NuclearEngagement\Services\BulkGenerationBatchProcessor(
			\NuclearEngagement\Core\SettingsRepository::get_instance()
		);
		$processor->update_batch_status( $batchId, $status, array(
			'success_count' => $counts['success'],
			'fail_count' => $counts['fail']
		) );
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
				// Get results from completed batches
				$batch_results = $this->extractBatchResults( $batch_data );
				
				// Merge results
				foreach ( $batch_results as $post_id => $result ) {
					if ( is_numeric( $post_id ) ) {
						$all_results[ $post_id ] = $result;
					}
				}
			}
		}

		return $all_results;
	}

	/**
	 * Extract results from batch data
	 *
	 * @param array $batch_data
	 * @return array
	 */
	private function extractBatchResults( array $batch_data ): array {
		$results = array();
		
		// Check for final results
		if ( ! empty( $batch_data['results'] ) && is_array( $batch_data['results'] ) ) {
			$results = $batch_data['results'];
		}
		// Also check for accumulated results from in-progress batches
		elseif ( ! empty( $batch_data['accumulated_results'] ) && is_array( $batch_data['accumulated_results'] ) ) {
			$results = $batch_data['accumulated_results'];
		}
		
		return $results;
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
	 * Log batch results
	 *
	 * @param string $batch_id
	 * @param array $results
	 */
	private function logBatchResults( string $batch_id, array $results ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'Batch %s: API returned %d results for posts: %s',
				$batch_id,
				count( $results ),
				implode( ', ', array_keys( $results ) )
			)
		);
	}

	/**
	 * Log batch progress
	 *
	 * @param string $batch_id
	 * @param int $processed
	 * @param int $total
	 * @param bool $is_complete
	 */
	private function logBatchProgress( string $batch_id, int $processed, int $total, bool $is_complete ): void {
		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'Batch %s: processed=%d, total=%d, complete=%s',
				$batch_id,
				$processed,
				$total,
				$is_complete ? 'yes' : 'no'
			)
		);
	}
}

/**
 * Handles result processing operations
 */
class UpdatesResultsHandler {
	/**
	 * @var ContentStorageService
	 */
	private ContentStorageService $storage;

	/**
	 * Constructor
	 *
	 * @param ContentStorageService $storage
	 */
	public function __construct( ContentStorageService $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Process and store results
	 *
	 * @param array $results
	 * @param string $generationId
	 * @return array Response data
	 */
	public function processResults( array $results, string $generationId ): array {
		$response_data = array();
		
		// Filter out summary statistics from results
		$post_results = $this->filterPostResults( $results );

		if ( ! empty( $post_results ) ) {
			$first = reset( $post_results );

			// Detect workflow type
			$workflow_type = $this->detectWorkflowType( $post_results, $first, $generationId );

			// Store results
			$statuses = $this->storage->storeResults( $post_results, $workflow_type );

			if ( array_filter( $statuses, static fn( $s ) => $s !== true ) ) {
				throw new \Exception( __( 'Failed to store content.', 'nuclear-engagement' ) );
			}

			$response_data['results'] = $post_results;
			$response_data['workflow'] = $workflow_type;
			
			// Add summary statistics if they exist
			$this->addSummaryStatistics( $response_data, $results );
		} else {
			// No actual post results
			$response_data['results'] = array();
		}

		return $response_data;
	}

	/**
	 * Filter out summary statistics from results
	 *
	 * @param array $results
	 * @return array
	 */
	private function filterPostResults( array $results ): array {
		return array_filter( 
			$results, 
			function( $key ) {
				// Only keep numeric post IDs, filter out summary keys
				return is_numeric( $key ) && ! in_array( $key, ['success_count', 'fail_count', 'processed_count'], true );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Add summary statistics to response
	 *
	 * @param array $response_data
	 * @param array $results
	 */
	private function addSummaryStatistics( array &$response_data, array $results ): void {
		if ( isset( $results['success_count'] ) ) {
			$response_data['success_count'] = $results['success_count'];
		}
		if ( isset( $results['fail_count'] ) ) {
			$response_data['fail_count'] = $results['fail_count'];
		}
		if ( isset( $results['processed_count'] ) ) {
			$response_data['processed_count'] = $results['processed_count'];
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
}