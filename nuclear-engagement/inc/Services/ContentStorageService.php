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
use NuclearEngagement\Utils;

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
     */
    public function storeResults( array $results, string $workflowType ): void {
        $updateLastModified = $this->settings->get_bool( 'update_last_modified', false );
        $dateNow            = current_time( 'mysql' );

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

            } catch ( \Throwable $e ) {
                \NuclearEngagement\Services\LoggingService::log_exception( $e );
            }
        }
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

                        $qText = trim( (string) ( $question['question'] ?? '' ) );
                        $answers = isset( $question['answers'] ) && is_array( $question['answers'] )
                                ? array_map( 'trim', $question['answers'] )
                                : array();
                        $answers = array_filter( $answers, static function ( $a ) {
                                return $a !== '';
                        } );

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
                        'questions' => $questions,
                        'date'      => $data['date'] ?? current_time( 'mysql' ),
                );

                if ( ! update_post_meta( $postId, 'nuclen-quiz-data', $formatted ) ) {
                        throw new \RuntimeException( "Failed to update quiz data for post {$postId}" );
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
            'summary' => wp_kses( $data['summary'], $allowedHtml ),
            'date'    => $data['date'] ?? current_time( 'mysql' ),
        );

        if ( ! update_post_meta( $postId, 'nuclen-summary-data', $formatted ) ) {
            throw new \RuntimeException( "Failed to update summary data for post {$postId}" );
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
