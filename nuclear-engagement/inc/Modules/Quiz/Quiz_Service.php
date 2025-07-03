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
		
		// Secure unserialize with validation
		if ( ! empty( $quiz_data ) ) {
			$quiz_data = $this->safe_unserialize( $quiz_data );
		}
		
		// Ensure we always return a valid array structure
		if ( ! is_array( $quiz_data ) ) {
			$quiz_data = array(
				'questions' => array(),
				'date'      => '',
			);
		}
		
		// Validate and sanitize the structure
		return $this->validate_quiz_structure( $quiz_data );
	}

	/**
	 * Safely unserialize data with validation.
	 *
	 * @param mixed $data Data to unserialize.
	 * @return mixed Unserialized data or false on failure.
	 */
	private function safe_unserialize( $data ) {
		// First try maybe_unserialize for WordPress compatibility
		$unserialized = maybe_unserialize( $data );
		
		// If it's a string and looks like serialized data, validate it
		if ( is_string( $data ) && preg_match( '/^[aOs]:[0-9]+:/', $data ) ) {
			// Check for potentially dangerous classes
			if ( preg_match( '/[CO]:[0-9]+:"/', $data ) ) {
				LoggingService::log( 'Attempted to unserialize data containing objects - blocked for security' );
				return false;
			}
		}
		
		// Additional validation - ensure result is expected type
		if ( $unserialized && ! is_array( $unserialized ) ) {
			LoggingService::log( 'Unserialized quiz data is not an array - potential security issue' );
			return false;
		}
		
		return $unserialized;
	}

	/**
	 * Validate and sanitize quiz data structure.
	 *
	 * @param array $quiz_data Quiz data to validate.
	 * @return array Validated quiz data.
	 */
	private function validate_quiz_structure( array $quiz_data ): array {
		$validated = array(
			'questions' => array(),
			'date'      => '',
		);
		
		// Validate date
		if ( isset( $quiz_data['date'] ) && is_string( $quiz_data['date'] ) ) {
			$validated['date'] = sanitize_text_field( $quiz_data['date'] );
		}
		
		// Validate questions array
		if ( isset( $quiz_data['questions'] ) && is_array( $quiz_data['questions'] ) ) {
			foreach ( $quiz_data['questions'] as $index => $question ) {
				if ( is_array( $question ) ) {
					$validated_question = array(
						'question'    => '',
						'answers'     => array(),
						'explanation' => '',
					);
					
					// Validate question text
					if ( isset( $question['question'] ) && is_string( $question['question'] ) ) {
						$validated_question['question'] = wp_kses_post( $question['question'] );
					}
					
					// Validate answers
					if ( isset( $question['answers'] ) && is_array( $question['answers'] ) ) {
						$validated_question['answers'] = array_map( function( $answer ) {
							return is_string( $answer ) ? wp_kses_post( $answer ) : '';
						}, array_slice( $question['answers'], 0, 4 ) ); // Limit to 4 answers
					}
					
					// Validate explanation
					if ( isset( $question['explanation'] ) && is_string( $question['explanation'] ) ) {
						$validated_question['explanation'] = wp_kses_post( $question['explanation'] );
					}
					
					$validated['questions'][$index] = $validated_question;
				}
			}
		}
		
		return $validated;
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
