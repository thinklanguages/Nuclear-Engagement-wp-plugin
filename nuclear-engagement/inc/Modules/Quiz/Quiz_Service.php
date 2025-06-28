<?php
declare(strict_types=1);
/**
 * Quiz data storage and management handler.
 *
 * This service class handles all quiz-related data operations including:
 * - Storing and retrieving quiz data from post meta
 * - Sanitizing and validating quiz content
 * - Managing quiz protection status
 * - Caching and performance optimizations
 *
 * @package NuclearEngagement\Modules\Quiz
 * @since   1.0.0
 */

namespace NuclearEngagement\Modules\Quiz;

use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quiz Service for data management and storage operations.
 *
 * This service provides a centralized interface for all quiz-related
 * data operations, ensuring consistent data handling and validation
 * across the plugin.
 *
 * @since 1.0.0
 */
final class Quiz_Service {
	
	/**
	 * Meta key for storing quiz data in WordPress post meta.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const META_KEY = 'nuclen-quiz-data';
	
	/**
	 * Meta key for storing quiz protection status.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const PROTECTED_KEY = 'nuclen_quiz_protected';

	/**
	 * Retrieve quiz data for a specific post.
	 *
	 * This method fetches quiz data from WordPress post meta and ensures
	 * it returns a consistently formatted array structure. If no quiz data
	 * exists or the data is corrupted, it returns a default structure.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to retrieve quiz data for.
	 * @return array {
	 *     Quiz data array.
	 *
	 *     @type array  $questions Array of quiz questions.
	 *     @type string $date      Date when quiz was created/updated.
	 * }
	 */
	public function get_quiz_data( int $post_id ): array {
		// Retrieve raw quiz data from post meta
		$quiz_data = get_post_meta( $post_id, self::META_KEY, true );
		
		// Unserialize data if it exists and is not empty
		if ( ! empty( $quiz_data ) ) {
			$quiz_data = maybe_unserialize( $quiz_data );
		}
		
		// Ensure we always return a valid array structure
		if ( ! is_array( $quiz_data ) ) {
			$quiz_data = array(
				'questions' => array(),
				'date'      => '',
			);
		}
		
		return $quiz_data;
	}

	/**
	 * Save quiz data for a specific post.
	 *
	 * This method sanitizes, validates, and stores quiz data in WordPress
	 * post meta. All content is properly sanitized using WordPress functions
	 * to prevent XSS and other security issues.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id The post ID to save quiz data for.
	 * @param array $raw     Raw quiz data to be sanitized and saved. {
	 *     @type string $date      Optional. Quiz creation/update date.
	 *     @type array  $questions Optional. Array of quiz questions.
	 * }
	 * @return void
	 */
	public function save_quiz_data( int $post_id, array $raw ): void {
		// Initialize formatted data structure with sanitized date
		$formatted = array(
			'date'      => sanitize_text_field( $raw['date'] ?? gmdate( 'Y-m-d H:i:s' ) ),
			'questions' => array(),
		);

		// Process and sanitize questions if they exist
		if ( isset( $raw['questions'] ) && is_array( $raw['questions'] ) ) {
			foreach ( $raw['questions'] as $q_index => $q_raw ) {
				// Sanitize question text allowing basic HTML formatting
				$question = isset( $q_raw['question'] ) ? wp_kses_post( $q_raw['question'] ) : '';
				
				// Process answer options (ensure we have exactly 4 answers)
				$answers_raw = isset( $q_raw['answers'] ) && is_array( $q_raw['answers'] ) ? $q_raw['answers'] : array();
				$answers_raw = array_pad( $answers_raw, 4, '' ); // Pad to ensure 4 answers
				$answers     = array_map( 'wp_kses_post', $answers_raw ); // Sanitize each answer
				
				// Sanitize explanation text
				$explan = isset( $q_raw['explanation'] ) ? wp_kses_post( $q_raw['explanation'] ) : '';

				// Build formatted question structure
				$formatted['questions'][ $q_index ] = array(
					'question'    => $question,
					'answers'     => $answers,
					'explanation' => $explan,
				);
			}
		}

		// Attempt to save the formatted data to post meta
		$updated = update_post_meta( $post_id, self::META_KEY, $formatted );
		
		// Handle save failure with logging and admin notification
		if ( false === $updated ) {
			LoggingService::log( 'Failed to update quiz data for post ' . $post_id );
			LoggingService::notify_admin( 'Failed to update quiz data for post ' . $post_id );
		}
		
		// Clear post cache to ensure fresh data on next request
		clean_post_cache( $post_id );
	}

	/**
	 * Check if a quiz is marked as protected.
	 *
	 * Protected quizzes may have restricted access or special handling
	 * requirements. This method retrieves the protection status from
	 * post meta and ensures a boolean return value.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check protection status for.
	 * @return bool True if the quiz is protected, false otherwise.
	 */
	public function is_protected( int $post_id ): bool {
		// Retrieve protection flag and cast to boolean
		return (bool) get_post_meta( $post_id, self::PROTECTED_KEY, true );
	}

	/**
	 * Set the protection status for a quiz.
	 *
	 * This method updates the protection flag for a quiz post. When set to
	 * protected, the quiz may require special authentication or have access
	 * restrictions applied. When unprotected, the meta is completely removed.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $post_id   The post ID to update protection status for.
	 * @param bool $protected Whether the quiz should be protected.
	 * @return void
	 */
	public function set_protected( int $post_id, bool $protected ): void {
		if ( $protected ) {
			// Set protection flag to true (stored as 1)
			$updated = update_post_meta( $post_id, self::PROTECTED_KEY, 1 );
			
			// Log failure if meta update didn't succeed
			if ( false === $updated ) {
				LoggingService::log( 'Failed to update quiz protected flag for post ' . $post_id );
				LoggingService::notify_admin( 'Failed to update quiz protected flag for post ' . $post_id );
			}
		} else {
			// Remove protection flag entirely when not protected
			delete_post_meta( $post_id, self::PROTECTED_KEY );
		}
	}
}
