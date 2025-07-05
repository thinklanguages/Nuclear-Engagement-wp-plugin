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

										\NuclearEngagement\Services\LoggingService::log( "Stored {$workflowType} data for post {$post_id}" );

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
		if ( empty( $data['questions'] ) || ! is_array( $data['questions'] ) ) {
				throw new \InvalidArgumentException( "Invalid quiz data for post {$post_id}" );
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
				throw new \InvalidArgumentException( "Invalid quiz data for post {$post_id}" );
		}

				$formatted = array(
					'date'      => $data['date'] ?? current_time( 'mysql' ),
					'questions' => $questions,
				);

				// Use WordPress database transactions for race condition prevention.
				global $wpdb;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query( 'START TRANSACTION' );

				try {
					$current = get_post_meta( $post_id, 'nuclen-quiz-data', true );
					if ( $current === $formatted ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$wpdb->query( 'COMMIT' );
						return;
					}

					$updated = update_post_meta( $post_id, 'nuclen-quiz-data', $formatted );
					if ( $updated === false ) {
						// Check if the update actually worked (WordPress quirk).
						$check = get_post_meta( $post_id, 'nuclen-quiz-data', true );
						if ( $check !== $formatted ) {
							throw new \RuntimeException( "Failed to update quiz data for post {$post_id}" );
						}
					}

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query( 'COMMIT' );
				} catch ( \Throwable $e ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query( 'ROLLBACK' );
					throw $e;
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

				// Use WordPress database transactions for race condition prevention.
				global $wpdb;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query( 'START TRANSACTION' );

				try {
					$current = get_post_meta( $post_id, Summary_Service::META_KEY, true );
					if ( $current === $formatted ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$wpdb->query( 'COMMIT' );
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

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query( 'COMMIT' );
				} catch ( \Throwable $e ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query( 'ROLLBACK' );
					throw $e;
				}
	}

	/**
	 * Update post modified time
	 *
	 * @param int $post_id
	 */
	private function updatePostModifiedTime( int $post_id ): void {
		$time   = current_time( 'mysql' );
		$result = wp_update_post(
			array(
				'ID'                => $post_id,
				'post_modified'     => $time,
				'post_modified_gmt' => get_gmt_from_date( $time ),
			)
		);

		if ( is_wp_error( $result ) ) {
			\NuclearEngagement\Services\LoggingService::log( "Failed to update modified time for post {$post_id}: " . $result->get_error_message() );
		}

		clean_post_cache( $post_id );
	}
}
