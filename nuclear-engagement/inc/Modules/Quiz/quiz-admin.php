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

        require NUCLEN_PLUGIN_DIR . 'templates/admin/quiz-metabox.php';
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
