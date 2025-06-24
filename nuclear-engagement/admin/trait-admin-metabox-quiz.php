<?php
declare(strict_types=1);
/**
 * File: admin/trait-admin-metabox-quiz.php
 *
 * Handles Quiz meta-box registration, rendering, and saving.
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_Quiz_Metabox {

	/*
	-------------------------------------------------------------------------
	 *  Meta-box registration
	 * ---------------------------------------------------------------------- */

	public function nuclen_add_quiz_data_meta_box() {
		$settings_repo = $this->nuclen_get_settings_repository();
		$post_types    = $settings_repo->get( 'generation_post_types', array( 'post' ) );
		$post_types    = is_array( $post_types ) ? $post_types : array( 'post' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'nuclen-quiz-data-meta-box',
				'Quiz',
				array( $this, 'nuclen_render_quiz_data_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/*
	-------------------------------------------------------------------------
	 *  Quiz meta-box – render
	 * ---------------------------------------------------------------------- */

	public function nuclen_render_quiz_data_meta_box( $post ) {
		$quiz_data = get_post_meta( $post->ID, 'nuclen-quiz-data', true );
		if ( ! empty( $quiz_data ) ) {
			$quiz_data = maybe_unserialize( $quiz_data );
		} else {
			$quiz_data = array(
				'questions' => array(),
				'date'      => '',
			);
		}

		$questions = isset( $quiz_data['questions'] ) && is_array( $quiz_data['questions'] )
			? $quiz_data['questions']
			: array();
		$date      = isset( $quiz_data['date'] ) ? $quiz_data['date'] : '';

		/* Ensure ten question slots */
		for ( $i = 0; $i < 10; $i++ ) {
			if ( ! isset( $questions[ $i ] ) ) {
				$questions[ $i ] = array(
					'question'    => '',
					'answers'     => array( '', '', '', '' ),
					'explanation' => '',
				);
			}
		}

		$quiz_protected = get_post_meta( $post->ID, 'nuclen_quiz_protected', true );

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

		/* Render the 10 question blocks */
		for ( $q_index = 0; $q_index < 10; $q_index++ ) {
			$q_data  = $questions[ $q_index ];
			$q_text  = $q_data['question'] ?? '';
			$answers = ( isset( $q_data['answers'] ) && is_array( $q_data['answers'] ) )
				? $q_data['answers']
				: array( '', '', '', '' );
			$explan  = $q_data['explanation'] ?? '';

			/* Ensure exactly 4 answers */
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

	/*
	-------------------------------------------------------------------------
	 *  Quiz meta-box – save
	 * ---------------------------------------------------------------------- */

	public function nuclen_save_quiz_data_meta( $post_id ) {

		/* ---- Capability / nonce / autosave checks ------------------------ */
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

		/* ---- Raw input ---------------------------------------------------- */
		$raw_quiz_data = $_POST['nuclen_quiz_data'] ?? array();
		$raw_quiz_data = is_array( $raw_quiz_data ) ? wp_unslash( $raw_quiz_data ) : array();

		/* ---- Sanitise & format ------------------------------------------- */
		$formatted = array(
			'date'      => sanitize_text_field( $raw_quiz_data['date'] ?? gmdate( 'Y-m-d H:i:s' ) ),
			'questions' => array(),
		);

		if ( isset( $raw_quiz_data['questions'] ) && is_array( $raw_quiz_data['questions'] ) ) {
			foreach ( $raw_quiz_data['questions'] as $q_index => $q_raw ) {

				$question = isset( $q_raw['question'] ) ? wp_kses_post( $q_raw['question'] ) : '';

				$answers_raw = $q_raw['answers'] ?? array();
				$answers_raw = is_array( $answers_raw ) ? $answers_raw : array();
				$answers_raw = array_pad( $answers_raw, 4, '' );

				$answers = array_map( 'wp_kses_post', $answers_raw );

				$explan = isset( $q_raw['explanation'] ) ? wp_kses_post( $q_raw['explanation'] ) : '';

				$formatted['questions'][ $q_index ] = array(
					'question'    => $question,
					'answers'     => $answers,
					'explanation' => $explan,
				);
			}
		}

		/* ---- Save to DB --------------------------------------------------- */
		update_post_meta( $post_id, 'nuclen-quiz-data', $formatted );
		clean_post_cache( $post_id );

		/* ---- Update post_modified if enabled ----------------------------- */
		$settings_repo        = $this->nuclen_get_settings_repository();
		$update_last_modified = $settings_repo->get( 'update_last_modified', 0 );
		if ( ! empty( $update_last_modified ) && (int) $update_last_modified === 1 ) {
			remove_action( 'save_post', array( $this, 'nuclen_save_quiz_data_meta' ), 10 );
			remove_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ), 10 );

			$time = current_time( 'mysql' );
			wp_update_post(
				array(
					'ID'                => $post_id,
					'post_modified'     => $time,
					'post_modified_gmt' => get_gmt_from_date( $time ),
				)
			);

			add_action( 'save_post', array( $this, 'nuclen_save_quiz_data_meta' ), 10, 1 );
			add_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ), 10, 1 );
		}

		/* ---- Protected flag ---------------------------------------------- */
		if ( isset( $_POST['nuclen_quiz_protected'] ) && $_POST['nuclen_quiz_protected'] === '1' ) {
			update_post_meta( $post_id, 'nuclen_quiz_protected', 1 );
		} else {
			delete_post_meta( $post_id, 'nuclen_quiz_protected' );
		}
	}
}
