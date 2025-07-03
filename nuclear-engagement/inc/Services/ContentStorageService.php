<?php
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for storing generated content
 */
class ContentStorageService {
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
		$this->settings = $settings;
		$this->utils    = new Utils();
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

		foreach ( $results as $postIdString => $data ) {
			$postId = (int) $postIdString;

			// Ensure date is set
			if ( empty( $data['date'] ) ) {
				$data['date'] = $dateNow;
			}

						try {
								if ( $workflowType === 'quiz' ) {
										$this->storeQuizData( $postId, $data );
								} else {
										$this->storeSummaryData( $postId, $data );
								}

								// Update post modified time if enabled
								if ( $updateLastModified ) {
										$this->updatePostModifiedTime( $postId );
								} else {
										clean_post_cache( $postId );
								}

								\NuclearEngagement\Services\LoggingService::log( "Stored {$workflowType} data for post {$postId}" );

								$statuses[ $postId ] = true;

						} catch ( \Throwable $e ) {
								\NuclearEngagement\Services\LoggingService::log_exception( $e );
								$statuses[ $postId ] = $e->getMessage();
						}
				}

				return $statuses;
		}

	/**
	 * Store quiz data
	 *
	 * @param int   $postId
	 * @param array $data
	 * @throws \InvalidArgumentException On invalid data
	 */
	public function storeQuizData( int $postId, array $data ): void {
		if ( empty( $data['questions'] ) || ! is_array( $data['questions'] ) ) {
				throw new \InvalidArgumentException( "Invalid quiz data for post {$postId}" );
		}

			$maxAnswers = $this->settings->get_int( 'answers_per_question', 4 );
			$questions  = array();

		foreach ( $data['questions'] as $question ) {
			if ( ! is_array( $question ) ) {
					continue;
			}

				$qText   = trim( (string) ( $question['question'] ?? '' ) );
				$answers = isset( $question['answers'] ) && is_array( $question['answers'] )
						? array_map( 'trim', $question['answers'] )
						: array();
				$answers = array_filter(
					$answers,
					static function ( $a ) {
							return $a !== '';
					}
				);

			if ( $qText === '' || empty( $answers ) ) {
				continue;
			}

				$questions[] = array(
					'question'    => sanitize_text_field( $qText ),
					'answers'     => array_map( 'sanitize_text_field', array_slice( $answers, 0, $maxAnswers ) ),
					'explanation' => sanitize_text_field( (string) ( $question['explanation'] ?? '' ) ),
				);
		}

		if ( empty( $questions ) ) {
				throw new \InvalidArgumentException( "Invalid quiz data for post {$postId}" );
		}

				$formatted = array(
					'date'      => $data['date'] ?? current_time( 'mysql' ),
					'questions' => $questions,
);

			// Use WordPress database transactions for race condition prevention
			global $wpdb;
			
			$wpdb->query( 'START TRANSACTION' );
			
			try {
				$current = get_post_meta( $postId, 'nuclen-quiz-data', true );
				if ( $current === $formatted ) {
					$wpdb->query( 'COMMIT' );
					return;
				}

				$updated = update_post_meta( $postId, 'nuclen-quiz-data', $formatted );
				if ( $updated === false ) {
					// Check if the update actually worked (WordPress quirk)
					$check = get_post_meta( $postId, 'nuclen-quiz-data', true );
					if ( $check !== $formatted ) {
						throw new \RuntimeException( "Failed to update quiz data for post {$postId}" );
					}
				}
				
				$wpdb->query( 'COMMIT' );
			} catch ( \Throwable $e ) {
				$wpdb->query( 'ROLLBACK' );
				throw $e;
			}
	}

	/**
	 * Store summary data
	 *
	 * @param int   $postId
	 * @param array $data
	 * @throws \InvalidArgumentException On invalid data
	 */
	public function storeSummaryData( int $postId, array $data ): void {
		if ( ! isset( $data['summary'] ) || ! is_string( $data['summary'] ) ) {
			// Legacy support for 'content' key
			if ( isset( $data['content'] ) && is_string( $data['content'] ) ) {
				$data['summary'] = $data['content'];
			} else {
				throw new \InvalidArgumentException( "Invalid summary data for post {$postId}" );
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

			// Use WordPress database transactions for race condition prevention
			global $wpdb;
			
			$wpdb->query( 'START TRANSACTION' );
			
			try {
				$current = get_post_meta( $postId, Summary_Service::META_KEY, true );
				if ( $current === $formatted ) {
					$wpdb->query( 'COMMIT' );
					return;
				}

				$updated = update_post_meta( $postId, Summary_Service::META_KEY, $formatted );
				if ( $updated === false ) {
					// Check if the update actually worked (WordPress quirk)
					$check = get_post_meta( $postId, Summary_Service::META_KEY, true );
					if ( $check !== $formatted ) {
						throw new \RuntimeException( "Failed to update summary data for post {$postId}" );
					}
				}
				
				$wpdb->query( 'COMMIT' );
			} catch ( \Throwable $e ) {
				$wpdb->query( 'ROLLBACK' );
				throw $e;
			}
	}

	/**
	 * Update post modified time
	 *
	 * @param int $postId
	 */
	private function updatePostModifiedTime( int $postId ): void {
		$time   = current_time( 'mysql' );
		$result = wp_update_post(
			array(
				'ID'                => $postId,
				'post_modified'     => $time,
				'post_modified_gmt' => get_gmt_from_date( $time ),
			)
		);

		if ( is_wp_error( $result ) ) {
			\NuclearEngagement\Services\LoggingService::log( "Failed to update modified time for post {$postId}: " . $result->get_error_message() );
		}

		clean_post_cache( $postId );
	}
}
