<?php
/**
 * File: admin/trait-admin-metaboxes.php
 *
 * Handles Meta Box registration, rendering, and saving
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_Metaboxes {

	/* -------------------------------------------------------------------------
	 *  Meta‑box registration
	 * ---------------------------------------------------------------------- */

	/**
	 * Add the quiz meta box to the post editor screen.
	 */
	public function nuclen_add_quiz_data_meta_box() {
		add_meta_box(
			'nuclen-quiz-data-meta-box',
			'Quiz',
			array( $this, 'nuclen_render_quiz_data_meta_box' ),
			'post',
			'normal',
			'default'
		);
	}

	/**
	 * Add the summary meta box to the post editor screen.
	 */
	public function nuclen_add_summary_data_meta_box() {
		add_meta_box(
			'nuclen-summary-data-meta-box',
			'Summary',
			array( $this, 'nuclen_render_summary_data_meta_box' ),
			'post',
			'normal',
			'default'
		);
	}

	/* -------------------------------------------------------------------------
	 *  Quiz meta‑box – render
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

		// Always ensure we have 10 question sets to display.
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

		echo '<div>';
		echo '<label>';
		echo '<input type="checkbox" name="nuclen_quiz_protected" value="1"';
		checked( $quiz_protected, 1 );
		echo ' /> Protected? <span nuclen-tooltip="Tick this box and save post to prevent overwriting during bulk generation.">🛈</span>';
		echo '</label>';
		echo '</div>';

		// *** Single‑Generate Quiz button ***
		echo '<div><button type="button" 
                id="nuclen-generate-quiz-single" 
                class="button nuclen-generate-single"
                data-post-id="' . esc_attr( $post->ID ) . '" 
                data-workflow="quiz"
              >
                Generate Quiz with AI
              </button>
              <span nuclen-tooltip="(re)Generate. Data will be stored automatically (no need to save post).">🛈</span></div>';

		echo '<p><strong>Date</strong><br>';
		echo '<input type="text" name="nuclen_quiz_data[date]" value="' . esc_attr( $date ) . '" readonly style="width:100%; background:#f9f9f9;" />';
		echo '</p>';

		// Render exactly 10 question blocks
		for ( $q_index = 0; $q_index < 10; $q_index++ ) {
			$q_data        = $questions[ $q_index ];
			$question_text = isset( $q_data['question'] ) ? $q_data['question'] : '';
			$answers       = ( isset( $q_data['answers'] ) && is_array( $q_data['answers'] ) )
				? $q_data['answers']
				: array( '', '', '', '' );
			$explanation   = isset( $q_data['explanation'] ) ? $q_data['explanation'] : '';

			// Ensure at least 4 answers
			$answers_count = count( $answers );
			if ( $answers_count < 4 ) {
				for ( $i = $answers_count; $i < 4; $i++ ) {
					$answers[] = '';
				}
			}

			echo '<div class="nuclen-quiz-metabox-question">';
			echo '<h4>Question ' . absint( $q_index + 1 ) . '</h4>';

			echo '<input type="text" 
                        name="nuclen_quiz_data[questions][' . absint( $q_index ) . '][question]" 
                        value="' . esc_attr( $question_text ) . '" 
                        style="width:100%;" />';

			echo '<p><strong>Answers</strong></p>';
			foreach ( $answers as $a_index => $answer ) {
				// style the first answer
				$style = $a_index === 0 ? 'font-weight:bold; background:#e6ffe6;' : '';
				echo '<p style="' . esc_attr( $style ) . '">';
				echo 'Answer ' . absint( $a_index + 1 ) . '<br>';
				echo '<input type="text" 
                            name="nuclen_quiz_data[questions][' . absint( $q_index ) . '][answers][' . absint( $a_index ) . ']" 
                            value="' . esc_attr( $answer ) . '" 
                            style="width:100%;" />';
				echo '</p>';
			}

			echo '<p><strong>Explanation</strong><br>';
			echo '<textarea 
                    name="nuclen_quiz_data[questions][' . absint( $q_index ) . '][explanation]" 
                    style="width:100%;" rows="3">' .
				esc_textarea( $explanation ) .
				'</textarea>';
			echo '</p>';
			echo '</div>';
		}
	}

	/* -------------------------------------------------------------------------
	 *  Summary meta‑box – render
	 * ---------------------------------------------------------------------- */

	public function nuclen_render_summary_data_meta_box( $post ) {
		// 1) Retrieve existing meta
		$summary_data = get_post_meta( $post->ID, 'nuclen-summary-data', true );
		if ( ! empty( $summary_data ) ) {
			$summary_data = maybe_unserialize( $summary_data );
		} else {
			$summary_data = array(
				'summary'         => '',
				'date'            => '',
				'format'          => 'paragraph',
				'length'          => 30,
				'number_of_items' => 3,
			);
		}

		$summary_protected = get_post_meta( $post->ID, 'nuclen_summary_protected', true );

		// 2) Extract each field or set defaults
		$summary = isset( $summary_data['summary'] ) ? $summary_data['summary'] : '';
		$date    = isset( $summary_data['date'] ) ? $summary_data['date'] : '';

		wp_nonce_field( 'nuclen_summary_data_nonce', 'nuclen_summary_data_nonce' );

		// 3) Protected checkbox + "Generate Summary" button
		echo '<div>';
		echo '<label>';
		echo '<input type="checkbox" name="nuclen_summary_protected" value="1"';
		checked( $summary_protected, 1 );
		echo ' /> Protected? <span nuclen-tooltip="Tick this box and save post to prevent overwriting during bulk generation.">🛈</span>';
		echo '</label>';
		echo '</div>';

		echo '<div><button 
                type="button" 
                id="nuclen-generate-summary-single" 
                class="button nuclen-generate-single"
                data-post-id="' . esc_attr( $post->ID ) . '"
                data-workflow="summary"
              >
                Generate Summary with AI
              </button>
              <span nuclen-tooltip="(re)Generate. Data will be stored automatically (no need to save post).">🛈</span></div>';

		// 4) The date field (read‑only by default)
		echo '<p><strong>Date</strong><br>';
		echo '<input
                type="text"
                name="nuclen_summary_data[date]"
                value="' . esc_attr( $date ) . '"
                readonly
                style="width:100%; background:#f9f9f9;"
              />';
		echo '</p>';

		// 5) The main summary textarea
		echo '<p><strong>Summary</strong><br>';
		wp_editor(
			$summary,
			'nuclen_summary_data_summary',
			array(
				'textarea_name' => 'nuclen_summary_data[summary]',
				'textarea_rows' => 5,
				'media_buttons' => false,
				'teeny'         => true,
				array( '__back_compat_meta_box' => true ),
			)
		);
		echo '</p>';
	}

	/* -------------------------------------------------------------------------
	 *  Quiz meta‑box – save
	 * ---------------------------------------------------------------------- */

	public function nuclen_save_quiz_data_meta( $post_id ) {

		/* ---- Standard capability / nonce / autosave checks ---------------- */

		$nonce = isset( $_POST['nuclen_quiz_data_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nuclen_quiz_data_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'nuclen_quiz_data_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/* ---- Collect / sanitise incoming data ----------------------------- */

		$raw_quiz_data = filter_input(
			INPUT_POST,
			'nuclen_quiz_data',
			FILTER_SANITIZE_STRING,
			FILTER_REQUIRE_ARRAY
		);
		$raw_quiz_data = $raw_quiz_data ? wp_unslash( $raw_quiz_data ) : array();

		if ( is_array( $raw_quiz_data ) ) {
			$date      = isset( $raw_quiz_data['date'] )
				? sanitize_text_field( $raw_quiz_data['date'] )
				: gmdate( 'Y-m-d H:i:s' );
			$questions = array();

			if ( isset( $raw_quiz_data['questions'] ) && is_array( $raw_quiz_data['questions'] ) ) {
				foreach ( $raw_quiz_data['questions'] as $q_item ) {
					$question_text = isset( $q_item['question'] )
						? sanitize_text_field( $q_item['question'] )
						: '';
					$explanation   = isset( $q_item['explanation'] )
						? sanitize_textarea_field( $q_item['explanation'] )
						: '';
					$answers       = array();

					if ( isset( $q_item['answers'] ) && is_array( $q_item['answers'] ) ) {
						foreach ( $q_item['answers'] as $ans ) {
							$answers[] = sanitize_text_field( $ans );
						}
					}

					$questions[] = array(
						'question'    => $question_text,
						'answers'     => $answers,
						'explanation' => $explanation,
					);
				}
			}

			$new_data = array(
				'questions' => $questions,
				'date'      => $date,
			);

			update_post_meta( $post_id, 'nuclen-quiz-data', $new_data );
			clean_post_cache( $post_id );

			/* ---- Update post_modified (if enabled) WITHOUT recursion -------- */

			$nuclen_settings = get_option( 'nuclear_engagement_settings', array() );
			if ( isset( $nuclen_settings['update_last_modified'] ) && (int) $nuclen_settings['update_last_modified'] === 1 ) {

				/* Temporarily un‑hook both save callbacks before updating */
				remove_action( 'save_post', array( $this, 'nuclen_save_quiz_data_meta' ), 10 );
				remove_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ), 10 );

				$time      = current_time( 'mysql' );
				$post_data = array(
					'ID'                => $post_id,
					'post_modified'     => $time,
					'post_modified_gmt' => get_gmt_from_date( $time ),
				);
				wp_update_post( $post_data );

				/* Re‑hook the callbacks */
				add_action( 'save_post', array( $this, 'nuclen_save_quiz_data_meta' ), 10, 1 );
				add_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ), 10, 1 );
			}
		}

		/* ---- Protected checkbox ------------------------------------------ */

		$protected = isset( $_POST['nuclen_quiz_protected'] )
			? sanitize_text_field( wp_unslash( $_POST['nuclen_quiz_protected'] ) )
			: '';

		if ( $protected === '1' ) {
			update_post_meta( $post_id, 'nuclen_quiz_protected', 1 );
		} else {
			delete_post_meta( $post_id, 'nuclen_quiz_protected' );
		}
	}

	/* -------------------------------------------------------------------------
	 *  Summary meta‑box – save
	 * ---------------------------------------------------------------------- */

	public function nuclen_save_summary_data_meta( $post_id ) {

		/* ---- Standard capability / nonce / autosave checks ---------------- */

		$nonce = isset( $_POST['nuclen_summary_data_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nuclen_summary_data_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'nuclen_summary_data_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/* ---- Collect / sanitise incoming data ----------------------------- */

		$raw_summary_data = filter_input(
			INPUT_POST,
			'nuclen_summary_data',
			FILTER_SANITIZE_STRING,
			FILTER_REQUIRE_ARRAY
		);
		$raw_summary_data = $raw_summary_data ? wp_unslash( $raw_summary_data ) : array();

		if ( is_array( $raw_summary_data ) ) {

			$date    = isset( $raw_summary_data['date'] )
				? sanitize_text_field( $raw_summary_data['date'] )
				: gmdate( 'Y-m-d H:i:s' );
			$summary = isset( $raw_summary_data['summary'] )
				? wp_kses_post( $raw_summary_data['summary'] )
				: '';

			$formatted_summary_data = array(
				'summary' => $summary,
				'date'    => $date,
			);

			update_post_meta( $post_id, 'nuclen-summary-data', $formatted_summary_data );
			clean_post_cache( $post_id );

			/* ---- Update post_modified (if enabled) WITHOUT recursion -------- */

			$nuclen_settings = get_option( 'nuclear_engagement_settings', array() );
			if ( isset( $nuclen_settings['update_last_modified'] ) && (int) $nuclen_settings['update_last_modified'] === 1 ) {

				/* Temporarily un‑hook both save callbacks before updating */
				remove_action( 'save_post', array( $this, 'nuclen_save_quiz_data_meta' ), 10 );
				remove_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ), 10 );

				$time      = current_time( 'mysql' );
				$post_data = array(
					'ID'                => $post_id,
					'post_modified'     => $time,
					'post_modified_gmt' => get_gmt_from_date( $time ),
				);
				wp_update_post( $post_data );

				/* Re‑hook the callbacks */
				add_action( 'save_post', array( $this, 'nuclen_save_quiz_data_meta' ), 10, 1 );
				add_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ), 10, 1 );
			}
		}

		/* ---- Protected checkbox ------------------------------------------ */

		$protected = isset( $_POST['nuclen_summary_protected'] )
			? sanitize_text_field( wp_unslash( $_POST['nuclen_summary_protected'] ) )
			: '';

		if ( $protected === '1' ) {
			update_post_meta( $post_id, 'nuclen_summary_protected', 1 );
		} else {
			delete_post_meta( $post_id, 'nuclen_summary_protected' );
		}
	}
}
