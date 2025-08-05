<?php
/**
 * GenerationService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/GenerationService.php

 * Generation Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Requests\GenerateRequest as GenerateRequestData;
use NuclearEngagement\Responses\GenerationResponse;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Utils\Utils;
use NuclearEngagement\Utils\ContentExtractor;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Services\PostDataFetcher;
use NuclearEngagement\Services\BulkGenerationBatchProcessor;
use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Exceptions\ValidationException;
use NuclearEngagement\Exceptions\ApiException as CustomApiException;
use NuclearEngagement\Exceptions\ResourceException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for handling content generation
 */
class GenerationService {
	/** Seconds to wait between polling events. */
	private const DEFAULT_POLL_DELAY = 30;

	private int $pollDelay = self::DEFAULT_POLL_DELAY;
	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * @var RemoteApiService
	 */
	private RemoteApiService $api;

	/**
	 * @var ContentStorageService
	 */
	private ContentStorageService $storage;

		/**
		 * @var PostDataFetcher
		 */
	private PostDataFetcher $fetcher;

	/**
	 * @var BulkGenerationBatchProcessor
	 */
	private BulkGenerationBatchProcessor $batchProcessor;

	/**
	 * @var Utils
	 */
	private Utils $utils;

	/** Resource limit constants */
	private const MAX_MEMORY_PERCENT      = 80;
	private const MAX_EXECUTION_PERCENT   = 70;
	private const MAX_CONCURRENT_REQUESTS = 5;

	/** Track concurrent requests */
	private static int $currentRequests = 0;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository    $settings
	 * @param RemoteApiService      $api
	 * @param ContentStorageService $storage
	 */
	public function __construct(
		SettingsRepository $settings,
		RemoteApiService $api,
		ContentStorageService $storage,
		?PostDataFetcher $fetcher = null,
		?BulkGenerationBatchProcessor $batchProcessor = null
	) {
			$this->settings       = $settings;
			$this->api            = $api;
			$this->storage        = $storage;
			$this->fetcher        = $fetcher ?: new PostDataFetcher();
			$this->batchProcessor = $batchProcessor ?: new BulkGenerationBatchProcessor( $settings );
			$this->utils          = new Utils();
		if ( defined( 'NUCLEN_GENERATION_POLL_DELAY' ) ) {
				$this->pollDelay = (int) constant( 'NUCLEN_GENERATION_POLL_DELAY' );
		}
	}

	/**
	 * Generate content for multiple posts
	 *
	 * @param GenerateRequestData $request
	 * @return GenerationResponse
	 * @throws ValidationException On invalid input
	 * @throws ApiException On API errors
	 */
	public function generateContent( GenerateRequestData $request ): GenerationResponse {
		// Starting content generation

		// Check resource limits before processing
		try {
			$this->checkResourceLimits();
		} catch ( ResourceException $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[ERROR] Resource limit exceeded | GenID: %s | Error: %s',
					$request->generationId,
					$e->getMessage()
				),
				'error'
			);

			// Return error response instead of throwing
			$response               = new GenerationResponse();
			$response->success      = false;
			$response->error        = $e->getMessage();
			$response->generationId = $request->generationId;

			// Store partial generation for recovery
			$this->storePartialGeneration( $request );

			return $response;
		}

		// Get posts data.
		$posts = $this->getPostsData( $request->postIds, $request->postType, $request->postStatus, $request->workflowType );

		if ( empty( $posts ) ) {
			// Check if posts exist but have empty content
			$empty_content_posts = array();
			foreach ( $request->postIds as $post_id ) {
				$post = get_post( $post_id );
				if ( $post && ( empty( trim( $post->post_title ) ) || empty( ContentExtractor::extract_content( $post ) ) ) ) {
					$empty_content_posts[] = $post_id;
				}
			}

			if ( ! empty( $empty_content_posts ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[ERROR] Posts have empty content | GenID: %s | Empty posts: %s',
						$request->generationId,
						implode( ',', $empty_content_posts )
					),
					'error'
				);
				throw new ValidationException(
					array(
						'empty_content' => true,
						'post_ids'      => $empty_content_posts,
					),
					'This post appears to be empty. No content can be generated.'
				);
			}

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[ERROR] No matching posts found | GenID: %s | Requested: %s',
					$request->generationId,
					implode( ',', $request->postIds )
				),
				'error'
			);
			throw new ValidationException(
				array(
					'post_ids'    => $request->postIds,
					'post_type'   => $request->postType,
					'post_status' => $request->postStatus,
				),
				'No matching posts found'
			);
		}

		// Build workflow data.
		$workflow = array(
			'type'                    => $request->workflowType,
			'summary_format'          => $request->summaryFormat,
			'summary_length'          => $request->summaryLength,
			'summary_number_of_items' => $request->summaryItems,
		);

		// Create response.
		$response               = new GenerationResponse();
		$response->generationId = $request->generationId;

		// Always use batch processing for consistency and reliability
		// Create batches based on priority
		$batches = $this->batchProcessor->create_batches( $posts, $request->priority );

		// Include priority and retry info in workflow
		$workflow['priority']    = $request->priority;
		$workflow['source']      = $request->source;
		$workflow['max_retries'] = $request->maxRetries > 0 ? $request->maxRetries : 0;

		$batch_jobs = $this->batchProcessor->create_batch_jobs( $request->generationId, $batches, $workflow );

		// Schedule batch processing
		$scheduled = $this->batchProcessor->schedule_batch_processing( $batch_jobs );

		if ( ! $scheduled ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[ERROR] Failed to schedule batch processing | GenID: %s',
					$request->generationId
				),
				'error'
			);
			$response->success = false;
			$response->error   = 'Failed to schedule batch processing';
			return $response;
		}

		// Return success response - progress will be tracked via polling
		$response->success      = true;
		$response->generationId = $request->generationId;
		$response->message      = sprintf(
			'Started processing %d posts',
			count( $posts )
		);
		$response->totalPosts   = count( $posts );
		$response->totalBatches = count( $batches );

		return $response;
	}


	/**
	 * Get posts data for generation
	 *
	 * @param array  $post_ids
	 * @param string $post_type
	 * @param string $postStatus
	 * @param string $workflowType
	 * @return array
	 */
	private function getPostsData( array $post_ids, string $post_type, string $postStatus, string $workflowType = '' ): array {
			$data      = array();
			$postsById = array();

			$chunkSize = defined( 'NUCLEN_POST_FETCH_CHUNK' ) ? (int) constant( 'NUCLEN_POST_FETCH_CHUNK' ) : 200;
			$chunks    = count( $post_ids ) <= $chunkSize ? array( $post_ids ) : array_chunk( $post_ids, $chunkSize );

		foreach ( $chunks as $chunkIndex => $chunk ) {
				$posts = $this->fetcher->fetch( $chunk, $workflowType );

			foreach ( $posts as $post ) {
				$title   = trim( $post->post_title );
				$content = ContentExtractor::extract_content( $post );

				// Skip posts with empty title or content
				if ( empty( $title ) || empty( $content ) ) {
					\NuclearEngagement\Services\LoggingService::log(
						sprintf( '[GenerationService::getPostsData] Skipping post %d: %s', $post->ID, empty( $title ) ? 'empty title' : 'empty content' )
					);
					continue;
				}

				$postsById[ (int) $post->ID ] = array(
					'id'      => (int) $post->ID,
					'title'   => $title,
					'content' => $content,
				);
			}
		}

		$missingPosts = array();
		foreach ( $post_ids as $id ) {
			if ( isset( $postsById[ $id ] ) ) {
				$data[] = $postsById[ $id ];
			} else {
				$missingPosts[] = $id;
			}
		}

		if ( ! empty( $missingPosts ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[WARNING] Posts not found | Missing: %d | IDs: %s',
					count( $missingPosts ),
					implode( ', ', $missingPosts )
				),
				'warning'
			);
		}

			// Posts data retrieved successfully

			return $data;
	}

	/**
	 * Check if content is protected from regeneration
	 *
	 * @param int    $post_id
	 * @param string $workflowType
	 * @return bool
	 */
	private function isProtected( int $post_id, string $workflowType ): bool {
		$meta_key = $workflowType === 'quiz' ? 'nuclen_quiz_protected' : Summary_Service::PROTECTED_KEY;
		return (bool) get_post_meta( $post_id, $meta_key, true );
	}

	/**
	 * Queue posts for auto-generation
	 *
	 * @param array  $post_ids Post IDs to queue
	 * @param string $workflow_type Workflow type
	 * @return string Generation ID
	 * @throws ValidationException On invalid input
	 * @throws ApiException On queue errors
	 */
	public function queueAutoGeneration( array $post_ids, string $workflow_type ): string {
		// Queuing auto-generation

		// Filter out protected posts
		$filtered_ids = array_filter(
			$post_ids,
			function ( $id ) use ( $workflow_type ) {
				return ! $this->isProtected( $id, $workflow_type );
			}
		);

		$protected_count = count( $post_ids ) - count( $filtered_ids );
		if ( $protected_count > 0 ) {
			// Filtered protected posts
		}

		if ( empty( $filtered_ids ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[ERROR] All posts protected | Count: %d',
					count( $post_ids )
				),
				'error'
			);
			throw new ValidationException(
				array(
					'posts'         => 'No posts available for auto-generation',
					'original_ids'  => $post_ids,
					'workflow_type' => $workflow_type,
					'reason'        => 'All posts are protected from regeneration',
				),
				'No posts available for auto-generation'
			);
		}

		// Get autogeneration settings if this is for summary workflow
		$workflow_settings = array();
		if ( $workflow_type === 'summary' ) {
			$workflow_settings = array(
				'summary_format'          => $this->settings->get( 'auto_summary_format', 'paragraph' ),
				'summary_length'          => (int) $this->settings->get( 'auto_summary_length', 30 ),
				'summary_number_of_items' => (int) $this->settings->get( 'auto_summary_number_of_items', 5 ),
			);
		}

		// Use batch processor's queue method
		$generation_id = $this->batchProcessor->queue_generation( $filtered_ids, $workflow_type, 'low', 'auto', $workflow_settings );

		return $generation_id;
	}

	/**
	 * Get retry status from batch processor
	 *
	 * @return array Retry status information
	 */
	public function get_retry_status(): array {
		return $this->batchProcessor->get_retry_status();
	}

	/**
	 * Check resource limits before processing
	 *
	 * @throws ResourceException If resource limits exceeded
	 */
	private function checkResourceLimits(): void {
		// Check memory usage
		$memory_percent = ( memory_get_usage( true ) / $this->getMemoryLimit() ) * 100;
		if ( $memory_percent > self::MAX_MEMORY_PERCENT ) {
			throw ResourceException::memoryLimitExceeded(
				$memory_percent,
				self::MAX_MEMORY_PERCENT
			);
		}

		// Check execution time
		$max_execution = (int) ini_get( 'max_execution_time' );
		if ( $max_execution > 0 ) {
			$elapsed      = microtime( true ) - ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
			$time_percent = ( $elapsed / $max_execution ) * 100;

			if ( $time_percent > self::MAX_EXECUTION_PERCENT ) {
				throw ResourceException::executionTimeExceeded(
					$time_percent,
					self::MAX_EXECUTION_PERCENT
				);
			}
		}

		// Check concurrent requests
		if ( self::$currentRequests >= self::MAX_CONCURRENT_REQUESTS ) {
			throw ResourceException::concurrentRequestLimitExceeded(
				self::$currentRequests,
				self::MAX_CONCURRENT_REQUESTS
			);
		}
	}

	/**
	 * Calculate optimal chunk size based on resources
	 *
	 * @param int $default_size Default chunk size
	 * @param int $total_items Total items to process
	 * @return int Optimal chunk size
	 */
	private function calculateOptimalChunkSize( int $default_size, int $total_items ): int {
		$memory_percent = ( memory_get_usage( true ) / $this->getMemoryLimit() ) * 100;

		// Reduce chunk size based on memory usage
		if ( $memory_percent > 60 ) {
			$default_size = (int) ( $default_size * 0.5 );
		} elseif ( $memory_percent > 40 ) {
			$default_size = (int) ( $default_size * 0.75 );
		}

		// Ensure minimum chunk size
		$min_size     = 10;
		$default_size = max( $min_size, $default_size );

		// Don't create tiny last chunk
		if ( $total_items > $default_size && $total_items % $default_size < $min_size ) {
			$default_size = (int) ( $total_items / ceil( $total_items / $default_size ) );
		}

		return $default_size;
	}

	/**
	 * Get memory limit in bytes
	 *
	 * @return int Memory limit
	 */
	private function getMemoryLimit(): int {
		$limit = ini_get( 'memory_limit' );

		if ( $limit == -1 ) {
			return PHP_INT_MAX;
		}

		$value = (int) $limit;
		$unit  = strtolower( substr( $limit, -1 ) );

		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
	}

	/**
	 * Get service name for logging and caching.
	 *
	 * @return string Service name.
	 */
	protected function get_service_name(): string {
		return 'generation_service';
	}

	/**
	 * Store partial generation for recovery
	 *
	 * @param GenerateRequestData $request Generation request
	 */
	private function storePartialGeneration( GenerateRequestData $request ): void {
		$recovery_key  = 'nuclen_partial_generation_' . $request->generationId;
		$recovery_data = array(
			'request'   => array(
				'post_ids'       => $request->postIds,
				'workflow_type'  => $request->workflowType,
				'post_type'      => $request->postType,
				'post_status'    => $request->postStatus,
				'summary_format' => $request->summaryFormat,
				'summary_length' => $request->summaryLength,
				'summary_items'  => $request->summaryItems,
				'priority'       => $request->priority,
				'source'         => $request->source,
				'retry_count'    => $request->retryCount,
				'max_retries'    => $request->maxRetries,
			),
			'stored_at' => time(),
			'reason'    => 'resource_limit_exceeded',
		);

		set_transient( $recovery_key, $recovery_data, DAY_IN_SECONDS );

		// Schedule recovery attempt
		$scheduled_time = time() + 300; // 5 minutes
		wp_schedule_single_event(
			$scheduled_time,
			'nuclen_recover_generation',
			array( $request->generationId )
		);
	}

	/**
	 * Recover a partial generation
	 *
	 * @param string $generation_id Generation ID to recover
	 * @return bool Success status
	 */
	public function recoverGeneration( string $generation_id ): bool {

		$recovery_key  = 'nuclen_partial_generation_' . $generation_id;
		$recovery_data = get_transient( $recovery_key );

		if ( ! is_array( $recovery_data ) || ! isset( $recovery_data['request'] ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[WARNING] No recovery data found | GenID: %s', $generation_id ),
				'warning'
			);
			return false;
		}

		// Check if too old (more than 24 hours)
		$age_hours = ( time() - $recovery_data['stored_at'] ) / 3600;
		if ( time() - $recovery_data['stored_at'] > DAY_IN_SECONDS ) {
			delete_transient( $recovery_key );
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[WARNING] Recovery data expired | GenID: %s | Age: %.1f hours',
					$generation_id,
					$age_hours
				),
				'warning'
			);
			return false;
		}

		try {
			// Recreate request
			$request                = new GenerateRequestData();
			$request->postIds       = $recovery_data['request']['post_ids'];
			$request->workflowType  = $recovery_data['request']['workflow_type'];
			$request->postType      = $recovery_data['request']['post_type'];
			$request->postStatus    = $recovery_data['request']['post_status'];
			$request->summaryFormat = $recovery_data['request']['summary_format'];
			$request->summaryLength = $recovery_data['request']['summary_length'];
			$request->summaryItems  = $recovery_data['request']['summary_items'];
			$request->generationId  = $generation_id;
			$request->priority      = $recovery_data['request']['priority'] ?? 'low'; // Lower priority for recovery
			$request->source        = 'recovery';
			$request->retryCount    = ( $recovery_data['request']['retry_count'] ?? 0 ) + 1;
			$request->maxRetries    = $recovery_data['request']['max_retries'] ?? 3;

			// Try to generate again
			$response = $this->generateContent( $request );

			if ( $response->success ) {
				// Clean up recovery data
				delete_transient( $recovery_key );
				return true;
			} else {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf(
						'[ERROR] Recovery failed | GenID: %s | Error: %s',
						$generation_id,
						$response->error ?? 'Unknown error'
					),
					'error'
				);

				// Schedule another attempt if within retry limits
				if ( $request->retryCount < $request->maxRetries ) {
					$next_attempt = time() + ( 600 * $request->retryCount ); // Exponential backoff
					wp_schedule_single_event(
						$next_attempt,
						'nuclen_recover_generation',
						array( $generation_id )
					);
				} else {
					// Max retries reached, clean up
					delete_transient( $recovery_key );
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'[ERROR] Max recovery attempts reached | Limit: %d | GenID: %s',
							$request->maxRetries,
							$generation_id
						),
						'error'
					);
				}
			}
		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[CRITICAL] Recovery exception | GenID: %s | Error: %s',
					$generation_id,
					$e->getMessage()
				),
				'error'
			);
			return false;
		}

		return false;
	}

	/**
	 * Get all pending recoveries
	 *
	 * @return array Array of generation IDs pending recovery
	 */
	public function getPendingRecoveries(): array {
		global $wpdb;

		$pending = array();

		// Find all recovery transients
		$transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s",
				'_transient_nuclen_partial_generation_%',
				'_transient_timeout_nuclen_partial_generation_%'
			)
		);

		foreach ( $transients as $transient ) {
			$generation_id = str_replace( '_transient_nuclen_partial_generation_', '', $transient->option_name );
			$pending[]     = $generation_id;
		}

		return $pending;
	}
}
