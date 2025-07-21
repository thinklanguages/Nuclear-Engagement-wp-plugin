<?php
/**
 * ContentStorageService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/ContentStorageService.php
 *
 * Content Storage Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Utils\Utils;
use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Core\BaseService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for storing generated content
 */
class ContentStorageService extends BaseService {
	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * @var Utils
	 */
	private Utils $utils;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository $settings
	 */
	public function __construct( SettingsRepository $settings ) {
		parent::__construct();

		$this->settings = $settings;
		$this->utils    = new Utils();

		// Set service-specific cache TTL
		$this->cache_ttl = 3600; // 1 hour for content data
	}

	/**
	 * Store generation results
	 *
	 * @param array  $results
	 * @param string $workflowType
	 *
	 * @return array<int, mixed> Status for each post ID
	 */
	public function storeResults( array $results, string $workflowType ): array {
			$updateLastModified = $this->settings->get_bool( 'update_last_modified', false );
			$dateNow            = current_time( 'mysql' );

			$statuses = array();

			\NuclearEngagement\Services\LoggingService::log(
				'storeResults called with ' . count( $results ) . ' results'
			);

		foreach ( $results as $post_idString => $data ) {
			$post_id = (int) $post_idString;

			// Ensure date is set.
			if ( empty( $data['date'] ) ) {
				$data['date'] = $dateNow;
			}

			try {
				if ( $workflowType === 'quiz' ) {
								$this->storeQuizData( $post_id, $data );
				} else {
									$this->storeSummaryData( $post_id, $data );
				}

										// Update post modified time if enabled.
				if ( $updateLastModified ) {
						$this->updatePostModifiedTime( $post_id );
				} else {
						clean_post_cache( $post_id );
				}

							
										$statuses[ $post_id ] = true;

			} catch ( \Throwable $e ) {
					\NuclearEngagement\Services\LoggingService::log_exception( $e );
					$statuses[ $post_id ] = $e->getMessage();
			}
		}

			return $statuses;
	}

	/**
	 * Store quiz data
	 *
	 * @param int   $post_id
	 * @param array $data
	 * @throws \InvalidArgumentException On invalid data
	 */
	public function storeQuizData( int $post_id, array $data ): void {
		$this->validate_quiz_data( $post_id, $data );
		$questions = $this->process_quiz_questions( $post_id, $data['questions'] );
		$formatted = $this->format_quiz_data( $data, $questions );
		$this->save_quiz_data_transaction( $post_id, $formatted );
	}

	/**
	 * Validate quiz data structure.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data Quiz data to validate.
	 * @throws \InvalidArgumentException On invalid data.
	 */
	private function validate_quiz_data( int $post_id, array $data ): void {
		if ( empty( $data['questions'] ) || ! is_array( $data['questions'] ) ) {
			$error_details = 'questions field is ' . ( ! isset( $data['questions'] ) ? 'missing' : ( is_array( $data['questions'] ) ? 'empty array' : 'not an array' ) );
			\NuclearEngagement\Services\LoggingService::log( "Quiz data validation failed for post {$post_id}: {$error_details}. Full data: " . wp_json_encode( $data ) );
			throw new \InvalidArgumentException( "Invalid quiz data for post {$post_id}: {$error_details}" );
		}
	}

	/**
	 * Process and validate quiz questions.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $raw_questions Raw questions data.
	 * @return array Processed questions.
	 * @throws \InvalidArgumentException On no valid questions.
	 */
	private function process_quiz_questions( int $post_id, array $raw_questions ): array {
		$max_answers = $this->settings->get_int( 'answers_per_question', 4 );
		$questions   = array();

		foreach ( $raw_questions as $question ) {
			if ( ! is_array( $question ) ) {
				continue;
			}

			$processed = $this->process_single_question( $question, $max_answers );
			if ( $processed !== null ) {
				$questions[] = $processed;
			}
		}

		if ( empty( $questions ) ) {
			\NuclearEngagement\Services\LoggingService::log( "No valid questions found after processing quiz data for post {$post_id}. Original questions count: " . count( $raw_questions ) );
			throw new \InvalidArgumentException( "No valid quiz questions found for post {$post_id}" );
		}

		return $questions;
	}

	/**
	 * Process a single question.
	 *
	 * @param array $question Question data.
	 * @param int   $max_answers Maximum answers per question.
	 * @return array|null Processed question or null if invalid.
	 */
	private function process_single_question( array $question, int $max_answers ): ?array {
		$q_text  = trim( (string) ( $question['question'] ?? '' ) );
		$answers = $this->process_question_answers( $question );

		if ( $q_text === '' || empty( $answers ) ) {
			return null;
		}

		return array(
			'question'    => sanitize_text_field( $q_text ),
			'answers'     => array_map( 'sanitize_text_field', array_slice( $answers, 0, $max_answers ) ),
			'explanation' => sanitize_text_field( (string) ( $question['explanation'] ?? '' ) ),
		);
	}

	/**
	 * Process question answers.
	 *
	 * @param array $question Question data.
	 * @return array Processed answers.
	 */
	private function process_question_answers( array $question ): array {
		if ( ! isset( $question['answers'] ) || ! is_array( $question['answers'] ) ) {
			return array();
		}

		$answers = array_map( 'trim', $question['answers'] );
		return array_filter(
			$answers,
			static function ( $a ) {
				return $a !== '';
			}
		);
	}

	/**
	 * Format quiz data for storage.
	 *
	 * @param array $data Original data.
	 * @param array $questions Processed questions.
	 * @return array Formatted data.
	 */
	private function format_quiz_data( array $data, array $questions ): array {
		return array(
			'date'      => $data['date'] ?? current_time( 'mysql' ),
			'questions' => $questions,
		);
	}

	/**
	 * Save quiz data using database transaction.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $formatted Formatted data.
	 * @throws \RuntimeException On database errors.
	 */
	private function save_quiz_data_transaction( int $post_id, array $formatted ): void {
		// Use atomic locking instead of transactions for metadata
		$lock_key      = "nuclen_content_lock_quiz_{$post_id}";
		$lock_acquired = false;
		$max_attempts  = 10;
		$attempt       = 0;

		// Try to acquire lock with exponential backoff
		while ( ! $lock_acquired && $attempt < $max_attempts ) {
			$lock_value = wp_generate_password( 12, false );

			// Use add_option for atomic lock acquisition
			if ( add_option(
				$lock_key,
				array(
					'value' => $lock_value,
					'time'  => time(),
				),
				'',
				'no'
			) ) {
				$lock_acquired = true;
			} else {
				// Check if existing lock is expired (older than 30 seconds)
				$existing = get_option( $lock_key );
				if ( is_array( $existing ) && isset( $existing['time'] ) ) {
					if ( time() - $existing['time'] > 30 ) {
						// Try to take over expired lock
						if ( update_option(
							$lock_key,
							array(
								'value' => $lock_value,
								'time'  => time(),
							)
						) ) {
							$lock_acquired = true;
							continue;
						}
					}
				}

				++$attempt;
				// Exponential backoff: 10ms, 20ms, 40ms, etc.
				usleep( 10000 * pow( 2, $attempt - 1 ) );
			}
		}

		if ( ! $lock_acquired ) {
			throw new \RuntimeException( "Failed to acquire lock for post {$post_id} after {$max_attempts} attempts" );
		}

		try {
			// Double-check data hasn't changed while waiting for lock
			if ( $this->is_data_unchanged( $post_id, $formatted ) ) {
				return;
			}

			$this->update_quiz_meta( $post_id, $formatted );
		} finally {
			// Always release lock
			delete_option( $lock_key );
		}
	}

	/**
	 * Check if data is unchanged.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $formatted New data.
	 * @return bool True if unchanged.
	 */
	private function is_data_unchanged( int $post_id, array $formatted ): bool {
		$current = get_post_meta( $post_id, 'nuclen-quiz-data', true );
		return $current === $formatted;
	}

	/**
	 * Update quiz meta with validation.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $formatted Data to save.
	 * @throws \RuntimeException On update failure.
	 */
	private function update_quiz_meta( int $post_id, array $formatted ): void {
		$updated = update_post_meta( $post_id, 'nuclen-quiz-data', $formatted );
		if ( $updated === false ) {
			// Check if the update actually worked (WordPress quirk)
			$check = get_post_meta( $post_id, 'nuclen-quiz-data', true );
			if ( $check !== $formatted ) {
				throw new \RuntimeException( "Failed to update quiz data for post {$post_id}" );
			}
		}
	}

	/**
	 * Store summary data
	 *
	 * @param int   $post_id
	 * @param array $data
	 * @throws \InvalidArgumentException On invalid data
	 */
	public function storeSummaryData( int $post_id, array $data ): void {
		if ( ! isset( $data['summary'] ) || ! is_string( $data['summary'] ) ) {
			// Legacy support for 'content' key.
			if ( isset( $data['content'] ) && is_string( $data['content'] ) ) {
				$data['summary'] = $data['content'];
			} else {
				throw new \InvalidArgumentException( "Invalid summary data for post {$post_id}" );
			}
		}

		$allowedHtml = array(
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'p'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'h1'     => array(),
			'h2'     => array(),
			'h3'     => array(),
			'h4'     => array(),
			'div'    => array( 'class' => array() ),
			'span'   => array( 'class' => array() ),
		);

				$formatted = array(
					'date'    => $data['date'] ?? current_time( 'mysql' ),
					'summary' => wp_kses( $data['summary'], $allowedHtml ),
				);

				// Use atomic locking for race condition prevention
				$lock_key      = "nuclen_content_lock_summary_{$post_id}";
				$lock_acquired = false;
				$max_attempts  = 10;
				$attempt       = 0;

				// Try to acquire lock with exponential backoff
				while ( ! $lock_acquired && $attempt < $max_attempts ) {
					$lock_value = wp_generate_password( 12, false );

					// Use add_option for atomic lock acquisition
					if ( add_option(
						$lock_key,
						array(
							'value' => $lock_value,
							'time'  => time(),
						),
						'',
						'no'
					) ) {
						$lock_acquired = true;
					} else {
						// Check if existing lock is expired (older than 30 seconds)
						$existing = get_option( $lock_key );
						if ( is_array( $existing ) && isset( $existing['time'] ) ) {
							if ( time() - $existing['time'] > 30 ) {
								// Try to take over expired lock
								if ( update_option(
									$lock_key,
									array(
										'value' => $lock_value,
										'time'  => time(),
									)
								) ) {
									$lock_acquired = true;
									continue;
								}
							}
						}

						++$attempt;
						// Exponential backoff: 10ms, 20ms, 40ms, etc.
						usleep( 10000 * pow( 2, $attempt - 1 ) );
					}
				}

				if ( ! $lock_acquired ) {
					throw new \RuntimeException( "Failed to acquire lock for post {$post_id} after {$max_attempts} attempts" );
				}

				try {
					// Double-check data hasn't changed while waiting for lock
					$current = get_post_meta( $post_id, Summary_Service::META_KEY, true );
					if ( $current === $formatted ) {
						return;
					}

					$updated = update_post_meta( $post_id, Summary_Service::META_KEY, $formatted );
					if ( $updated === false ) {
						// Check if the update actually worked (WordPress quirk).
						$check = get_post_meta( $post_id, Summary_Service::META_KEY, true );
						if ( $check !== $formatted ) {
							throw new \RuntimeException( "Failed to update summary data for post {$post_id}" );
						}
					}
				} finally {
					// Always release lock
					delete_option( $lock_key );
				}
	}

	/**
	 * Queue content save as background job.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $data Data to save.
	 * @param string $type Content type (quiz or summary).
	 */
	private function queue_async_save( int $post_id, array $data, string $type ): void {
		// Check if BackgroundProcessor is available
		if ( ! class_exists( '\NuclearEngagement\Core\BackgroundProcessor' ) ) {
			// Fallback to synchronous save
			if ( $type === 'quiz' ) {
				$this->update_quiz_meta( $post_id, $data );
			} else {
				update_post_meta( $post_id, Summary_Service::META_KEY, $data );
			}
			return;
		}

		// Check if there's already a pending save for this post
		$pending_key = "nuclen_pending_save_{$type}_{$post_id}";
		if ( get_transient( $pending_key ) ) {
			// Already queued, skip to prevent duplicate jobs
			return;
		}

		// Queue background job
		$job_id = \NuclearEngagement\Core\BackgroundProcessor::queue_job(
			'content_storage_save',
			array(
				'post_id' => $post_id,
				'data'    => $data,
				'type'    => $type,
			),
			5, // High priority
			0  // No delay
		);

		// Store temporary flag with job ID for tracking
		set_transient( $pending_key, $job_id, 300 );

		// Schedule cleanup in case job fails
		wp_schedule_single_event( time() + 310, 'nuclen_cleanup_pending_save', array( $type, $post_id ) );
	}

	/**
	 * Update post modified time
	 *
	 * @param int $post_id
	 */
	private function updatePostModifiedTime( int $post_id ): void {
		$time   = current_time( 'mysql' );
		$result = $this->execute_db_operation(
			function () use ( $post_id, $time ) {
				return wp_update_post(
					array(
						'ID'                => $post_id,
						'post_modified'     => $time,
						'post_modified_gmt' => get_gmt_from_date( $time ),
					)
				);
			},
			'update_post_modified_time'
		);

		if ( is_wp_error( $result ) ) {
			throw DatabaseException::fromWpError( $result, array( 'post_id' => $post_id ) );
		}

		clean_post_cache( $post_id );
	}

	/**
	 * Register background job handler.
	 */
	public static function register_background_handler(): void {
		if ( ! class_exists( '\NuclearEngagement\Core\BackgroundProcessor' ) ) {
			return;
		}

		\NuclearEngagement\Core\BackgroundProcessor::register_handler(
			'content_storage_save',
			array( __CLASS__, 'handle_background_save' )
		);

		// Register cleanup action
		add_action( 'nuclen_cleanup_pending_save', array( __CLASS__, 'cleanup_pending_save' ), 10, 2 );
	}

	/**
	 * Handle background save job.
	 *
	 * @param \NuclearEngagement\Core\BackgroundJobContext $context Job context.
	 */
	public static function handle_background_save( $context ): void {
		$data         = $context->get_data();
		$post_id      = $data['post_id'] ?? 0;
		$type         = $data['type'] ?? '';
		$content_data = $data['data'] ?? array();

		if ( ! $post_id || ! $type ) {
			return;
		}

		try {
			$context->update_progress( 50, 'Saving content...' );

			if ( $type === 'quiz' ) {
				update_post_meta( $post_id, 'nuclen-quiz-data', $content_data );
			} else {
				update_post_meta( $post_id, Summary_Service::META_KEY, $content_data );
			}

			$context->update_progress( 100, 'Content saved successfully' );
		} finally {
			// Always clear pending flag to prevent memory leak
			delete_transient( "nuclen_pending_save_{$type}_{$post_id}" );
			wp_clear_scheduled_hook( 'nuclen_cleanup_pending_save', array( $type, $post_id ) );
		}
	}

	/**
	 * Clean up pending save transients.
	 *
	 * @param string $type Content type.
	 * @param int    $post_id Post ID.
	 */
	public static function cleanup_pending_save( string $type, int $post_id ): void {
		delete_transient( "nuclen_pending_save_{$type}_{$post_id}" );
	}

	/**
	 * Get service name for logging and caching.
	 *
	 * @return string Service name.
	 */
	protected function get_service_name(): string {
		return 'content_storage_service';
	}
}
