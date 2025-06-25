<?php
declare(strict_types=1);
/**
 * Quiz admin UI and meta box handling.
 *
 * @package NuclearEngagement\Modules\Quiz
 */

namespace NuclearEngagement\Modules\Quiz;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Quiz_Admin {
    private SettingsRepository $settings;
    private Quiz_Service $service;

    public function __construct( SettingsRepository $settings, Quiz_Service $service ) {
        $this->settings = $settings;
        $this->service  = $service;
    }

    /** Register meta box actions. */
    public function register_hooks(): void {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_meta' ) );
    }

    /** Add the Quiz meta box. */
    public function add_meta_box(): void {
        $post_types = $this->settings->get( 'generation_post_types', array( 'post' ) );
        $post_types = is_array( $post_types ) ? $post_types : array( 'post' );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'nuclen-quiz-data-meta-box',
                'Quiz',
                array( $this, 'render_meta_box' ),
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /** Render the Quiz meta box. */
    public function render_meta_box( $post ): void {
        $quiz_data = $this->service->get_quiz_data( $post->ID );

        $questions = isset( $quiz_data['questions'] ) && is_array( $quiz_data['questions'] )
            ? $quiz_data['questions']
            : array();
        $date = $quiz_data['date'] ?? '';

        for ( $i = 0; $i < 10; $i++ ) {
            if ( ! isset( $questions[ $i ] ) ) {
                $questions[ $i ] = array(
                    'question'    => '',
                    'answers'     => array( '', '', '', '' ),
                    'explanation' => '',
                );
            }
        }

        $quiz_protected = $this->service->is_protected( $post->ID );

        wp_nonce_field( 'nuclen_quiz_data_nonce', 'nuclen_quiz_data_nonce' );

        echo '<div><label>';
        echo '<input type="checkbox" name="nuclen_quiz_protected" value="1"';
        checked( $quiz_protected, 1 );
        echo ' /> Protected? <span nuclen-tooltip="Tick this box and save post to prevent overwriting during bulk generation.">🛈</span>';
        echo '</label></div>';

        echo '<div>
            <button type="button"
                    id="nuclen-generate-quiz-single"
                    class="button nuclen-generate-single"
                    data-post-id="' . esc_attr( $post->ID ) . '"
                    data-workflow="quiz">
                Generate Quiz with AI
            </button>
            <span nuclen-tooltip="(re)Generate. Data will be stored automatically (no need to save post).">🛈</span>
        </div>';

        echo '<p><strong>Date</strong><br>';
        echo '<input type="text" name="nuclen_quiz_data[date]" value="' . esc_attr( $date ) . '" readonly class="nuclen-meta-date-input" />';
        echo '</p>';

        for ( $q_index = 0; $q_index < 10; $q_index++ ) {
            $q_data  = $questions[ $q_index ];
            $q_text  = $q_data['question'] ?? '';
            $answers = isset( $q_data['answers'] ) && is_array( $q_data['answers'] )
                ? $q_data['answers']
                : array( '', '', '', '' );
            $explan  = $q_data['explanation'] ?? '';

            $answers = array_pad( $answers, 4, '' );

            echo '<div class="nuclen-quiz-metabox-question">';
            echo '<h4>Question ' . ( $q_index + 1 ) . '</h4>';

            echo '<input type="text" name="nuclen_quiz_data[questions][' . $q_index . '][question]" value="' . esc_attr( $q_text ) . '" class="nuclen-width-full" />';

            echo '<p><strong>Answers</strong></p>';
            foreach ( $answers as $a_index => $answer ) {
                $class = $a_index === 0 ? 'nuclen-answer-correct' : '';
                echo '<p class="nuclen-answer-label ' . esc_attr( $class ) . '">Answer ' . ( $a_index + 1 ) . '<br>';
                echo '<input type="text" name="nuclen_quiz_data[questions][' . $q_index . '][answers][' . $a_index . ']" value="' . esc_attr( $answer ) . '" class="nuclen-width-full" /></p>';
            }

            echo '<p><strong>Explanation</strong><br>';
            echo '<textarea name="nuclen_quiz_data[questions][' . $q_index . '][explanation]" rows="3" class="nuclen-width-full">' . esc_textarea( $explan ) . '</textarea></p>';
            echo '</div>';
        }
    }

    /** Save quiz meta on post save. */
    public function save_meta( int $post_id ): void {
        $nonce = $_POST['nuclen_quiz_data_nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'nuclen_quiz_data_nonce' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $raw_quiz_data = $_POST['nuclen_quiz_data'] ?? array();
        $raw_quiz_data = is_array( $raw_quiz_data ) ? wp_unslash( $raw_quiz_data ) : array();

        $this->service->save_quiz_data( $post_id, $raw_quiz_data );

        $update_last_modified = $this->settings->get( 'update_last_modified', 0 );
        if ( ! empty( $update_last_modified ) && (int) $update_last_modified === 1 ) {
            remove_action( 'save_post', array( $this, 'save_meta' ), 10 );

            $time   = current_time( 'mysql' );
            $result = wp_update_post(
                array(
                    'ID'                => $post_id,
                    'post_modified'     => $time,
                    'post_modified_gmt' => get_gmt_from_date( $time ),
                ),
                true
            );

            if ( is_wp_error( $result ) ) {
                LoggingService::log( 'Failed to update modified time for post ' . $post_id . ': ' . $result->get_error_message() );
                LoggingService::notify_admin( 'Failed to update modified time for post ' . $post_id . ': ' . $result->get_error_message() );
            }

            add_action( 'save_post', array( $this, 'save_meta' ), 10 );
        }

        if ( isset( $_POST['nuclen_quiz_protected'] ) && $_POST['nuclen_quiz_protected'] === '1' ) {
            $this->service->set_protected( $post_id, true );
        } else {
            $this->service->set_protected( $post_id, false );
        }
    }
}
