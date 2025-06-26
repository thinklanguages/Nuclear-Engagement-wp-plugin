<?php
declare(strict_types=1);
/**
 * Quiz data storage handler.
 *
 * @package NuclearEngagement\Modules\Quiz
 */

namespace NuclearEngagement\Modules\Quiz;

use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Quiz_Service {
    public const META_KEY      = 'nuclen-quiz-data';
    public const PROTECTED_KEY = 'nuclen_quiz_protected';

    /**
     * Retrieve quiz meta for a post.
     */
    public function get_quiz_data( int $post_id ): array {
        $quiz_data = get_post_meta( $post_id, self::META_KEY, true );
        if ( ! empty( $quiz_data ) ) {
            $quiz_data = maybe_unserialize( $quiz_data );
        }
        if ( ! is_array( $quiz_data ) ) {
            $quiz_data = array(
                'questions' => array(),
                'date'      => '',
            );
        }
        return $quiz_data;
    }

    /**
     * Persist quiz data for a post.
     */
    public function save_quiz_data( int $post_id, array $raw ): void {
        $formatted = array(
            'date'      => sanitize_text_field( $raw['date'] ?? gmdate( 'Y-m-d H:i:s' ) ),
            'questions' => array(),
        );

        if ( isset( $raw['questions'] ) && is_array( $raw['questions'] ) ) {
            foreach ( $raw['questions'] as $q_index => $q_raw ) {
                $question    = isset( $q_raw['question'] ) ? wp_kses_post( $q_raw['question'] ) : '';
                $answers_raw = isset( $q_raw['answers'] ) && is_array( $q_raw['answers'] ) ? $q_raw['answers'] : array();
                $answers_raw = array_pad( $answers_raw, 4, '' );
                $answers     = array_map( 'wp_kses_post', $answers_raw );
                $explan      = isset( $q_raw['explanation'] ) ? wp_kses_post( $q_raw['explanation'] ) : '';

                $formatted['questions'][ $q_index ] = array(
                    'question'    => $question,
                    'answers'     => $answers,
                    'explanation' => $explan,
                );
            }
        }

        $updated = update_post_meta( $post_id, self::META_KEY, $formatted );
        if ( false === $updated ) {
            LoggingService::log( 'Failed to update quiz data for post ' . $post_id );
            LoggingService::notify_admin( 'Failed to update quiz data for post ' . $post_id );
        }
        clean_post_cache( $post_id );
    }

    /**
     * Get protected flag for a post.
     */
    public function is_protected( int $post_id ): bool {
        return (bool) get_post_meta( $post_id, self::PROTECTED_KEY, true );
    }

    /**
     * Store protected flag for a post.
     */
    public function set_protected( int $post_id, bool $protected ): void {
        if ( $protected ) {
            $updated = update_post_meta( $post_id, self::PROTECTED_KEY, 1 );
            if ( false === $updated ) {
                LoggingService::log( 'Failed to update quiz protected flag for post ' . $post_id );
                LoggingService::notify_admin( 'Failed to update quiz protected flag for post ' . $post_id );
            }
        } else {
            delete_post_meta( $post_id, self::PROTECTED_KEY );
        }
    }
}
