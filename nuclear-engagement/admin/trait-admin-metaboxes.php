<?php
/**
 * File: nuclear-engagement/admin/trait-admin-metaboxes.php
 *
 * Handles Meta‑Box registration, rendering, and saving
 * for the free “Nuclear Engagement” plugin.
 *
 * – The three Pro‑only controls (date field, protected
 *   checkbox, generate button) are injected by the Pro
 *   add‑on via the two actions shown below.
 * – Both save‑callbacks temporarily un‑hook themselves
 *   before bumping “last‑modified” to avoid the infinite
 *   save loop.
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_Metaboxes {

	/* -----------------------------------------------------------------
	 *  QUIZ  META‑BOX
	 * -----------------------------------------------------------------*/

	/** Register meta‑box */
	public function nuclen_add_quiz_data_meta_box() {
		add_meta_box(
			'nuclen-quiz-data-meta-box',
			'Quiz',
			[ $this, 'nuclen_render_quiz_data_meta_box' ],
			'post',
			'normal',
			'default'
		);
	}

	/** Render meta‑box */
	public function nuclen_render_quiz_data_meta_box( $post ) {

		/* ---------- get stored meta ---------- */
		$quiz_data = get_post_meta( $post->ID, 'nuclen-quiz-data', true );
		$quiz_data = ! empty( $quiz_data ) ? maybe_unserialize( $quiz_data ) : [
			'questions' => [],
			'date'      => '',
		];
		$questions = isset( $quiz_data['questions'] ) && is_array( $quiz_data['questions'] )
			? $quiz_data['questions'] : [];
		$date      = isset( $quiz_data['date'] ) ? $quiz_data['date'] : '';

		/* ---------- ensure 10 question slots ---------- */
		for ( $i = 0; $i < 10; $i ++ ) {
			if ( ! isset( $questions[ $i ] ) ) {
				$questions[ $i ] = [
					'question'    => '',
					'answers'     => [ '', '', '', '' ],
					'explanation' => '',
				];
			}
		}

		/* ---------- security nonce ---------- */
		wp_nonce_field( 'nuclen_quiz_data_nonce', 'nuclen_quiz_data_nonce' );

		/* ==============================================================
		   PRO‑ONLY FIELDS
		   Injected by the Pro add‑on so the free plugin does not output
		   or maintain them directly.
		================================================================*/
		do_action( 'nuclen_render_quiz_pro_fields', $post, $date );

		/* ==============================================================
		   FREE‑ONLY FIELDS (questions, answers, explanations)
		================================================================*/
		for ( $q_index = 0; $q_index < 10; $q_index ++ ) {

			$q_data        = $questions[ $q_index ];
			$question_text = isset( $q_data['question'] ) ? $q_data['question'] : '';
			$answers       = isset( $q_data['answers'] ) && is_array( $q_data['answers'] )
				? $q_data['answers'] : [ '', '', '', '' ];
			$explanation   = isset( $q_data['explanation'] ) ? $q_data['explanation'] : '';

			/* -- ensure four answers -- */
			$answers_count = count( $answers );
			if ( $answers_count < 4 ) {
				for ( $i = $answers_count; $i < 4; $i ++ ) {
					$answers[] = '';
				}
			}

			echo '<div class="nuclen-quiz-metabox-question">';
			echo '<h4>Question ' . absint( $q_index + 1 ) . '</h4>';

			/* question */
			echo '<input type="text"
			          name="nuclen_quiz_data[questions][' . absint( $q_index ) . '][question]"
			          value="' . esc_attr( $question_text ) . '"
			          style="width:100%;" />';

			/* answers */
			echo '<p><strong>Answers</strong></p>';
			foreach ( $answers as $a_index => $answer ) {
				$style = $a_index === 0 ? 'font-weight:bold; background:#e6ffe6;' : '';
				echo '<p style="' . esc_attr( $style ) . '">';
				echo 'Answer ' . absint( $a_index + 1 ) . '<br>';
				echo '<input type="text"
				              name="nuclen_quiz_data[questions][' . absint( $q_index ) . '][answers][' . absint( $a_index ) . ']"
				              value="' . esc_attr( $answer ) . '"
				              style="width:100%;" />';
				echo '</p>';
			}

			/* explanation */
			echo '<p><strong>Explanation</strong><br>';
			echo '<textarea
			        name="nuclen_quiz_data[questions][' . absint( $q_index ) . '][explanation]"
			        style="width:100%;" rows="3">'
			     . esc_textarea( $explanation ) .
			     '</textarea></p>';

			echo '</div>'; // .nuclen-quiz-metabox-question
		}
	}

	/** Save meta‑box */
	public function nuclen_save_quiz_data_meta( $post_id ) {

		/* ---------- security / permission checks ---------- */
		$nonce = isset( $_POST['nuclen_quiz_data_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nuclen_quiz_data_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'nuclen_quiz_data_nonce' )
		     || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		     || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/* ---------- build sanitized meta array ---------- */
		$raw = filter_input(
			INPUT_POST,
			'nuclen_quiz_data',
			FILTER_SANITIZE_STRING,
			FILTER_REQUIRE_ARRAY
		);
		$raw = $raw ? wp_unslash( $raw ) : [];

		if ( is_array( $raw ) ) {

			$date = isset( $raw['date'] )
				? sanitize_text_field( $raw['date'] )
				: gmdate( 'Y-m-d H:i:s' );

			$questions = [];
			if ( isset( $raw['questions'] ) && is_array( $raw['questions'] ) ) {
				foreach ( $raw['questions'] as $q ) {
					$questions[] = [
						'question'    => isset( $q['question'] )
							? sanitize_text_field( $q['question'] ) : '',
						'answers'     => isset( $q['answers'] ) && is_array( $q['answers'] )
							? array_map( 'sanitize_text_field', $q['answers'] ) : [],
						'explanation' => isset( $q['explanation'] )
							? sanitize_textarea_field( $q['explanation'] ) : '',
					];
				}
			}

			update_post_meta( $post_id, 'nuclen-quiz-data', [
				'questions' => $questions,
				'date'      => $date,
			] );
		} else {
			delete_post_meta( $post_id, 'nuclen-quiz-data' );
		}

		/* ---------- Protected? checkbox ---------- */
		$protected = isset( $_POST['nuclen_quiz_protected'] )
			? sanitize_text_field( wp_unslash( $_POST['nuclen_quiz_protected'] ) )
			: '';
		$protected === '1'
			? update_post_meta( $post_id, 'nuclen_quiz_protected', 1 )
			: delete_post_meta( $post_id, 'nuclen_quiz_protected' );

		/* ---------- last‑modified bump (loop‑safe) ---------- */
		$settings = get_option( 'nuclear_engagement_settings', [] );
		if ( isset( $settings['update_last_modified'] ) && (int) $settings['update_last_modified'] === 1 ) {

			/* ‑‑ TEMP‑UNHOOK to prevent recursion ‑‑ */
			remove_action( 'save_post', [ $this, 'nuclen_save_quiz_data_meta' ] );
			remove_action( 'save_post', [ $this, 'nuclen_save_summary_data_meta' ] );

			$time = current_time( 'mysql' );
			wp_update_post( [
				'ID'                => $post_id,
				'post_modified'     => $time,
				'post_modified_gmt' => get_gmt_from_date( $time ),
			] );

			/* ‑‑ RE‑HOOK afterwards ‑‑ */
			add_action( 'save_post', [ $this, 'nuclen_save_quiz_data_meta' ] );
			add_action( 'save_post', [ $this, 'nuclen_save_summary_data_meta' ] );
		}
	}

	/* -----------------------------------------------------------------
	 *  SUMMARY  META‑BOX
	 * -----------------------------------------------------------------*/

	/** Register meta‑box */
	public function nuclen_add_summary_data_meta_box() {
		add_meta_box(
			'nuclen-summary-data-meta-box',
			'Summary',
			[ $this, 'nuclen_render_summary_data_meta_box' ],
			'post',
			'normal',
			'default'
		);
	}

	/** Render meta‑box */
	public function nuclen_render_summary_data_meta_box( $post ) {

		/* ---------- get stored meta ---------- */
		$summary_data = get_post_meta( $post->ID, 'nuclen-summary-data', true );
		$summary_data = ! empty( $summary_data ) ? maybe_unserialize( $summary_data ) : [
			'summary' => '',
			'date'    => '',
		];
		$summary = isset( $summary_data['summary'] ) ? $summary_data['summary'] : '';
		$date    = isset( $summary_data['date'] ) ? $summary_data['date'] : '';

		/* ---------- security nonce ---------- */
		wp_nonce_field( 'nuclen_summary_data_nonce', 'nuclen_summary_data_nonce' );

		/* ==============================================================
		   PRO‑ONLY FIELDS
		================================================================*/
		do_action( 'nuclen_render_summary_pro_fields', $post, $date );

		/* ==============================================================
		   FREE‑ONLY FIELDS
		================================================================*/
		echo '<p><strong>Summary</strong><br>';
		wp_editor(
			$summary,
			'nuclen_summary_data_summary',
			[
				'textarea_name' => 'nuclen_summary_data[summary]',
				'textarea_rows' => 5,
				'media_buttons' => false,
				'teeny'         => true,
				'__back_compat_meta_box' => true,
			]
		);
		echo '</p>';
	}

	/** Save meta‑box */
	public function nuclen_save_summary_data_meta( $post_id ) {

		/* ---------- security / permission checks ---------- */
		$nonce = isset( $_POST['nuclen_summary_data_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nuclen_summary_data_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'nuclen_summary_data_nonce' )
		     || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		     || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/* ---------- build sanitized meta array ---------- */
		$raw = isset( $_POST['nuclen_summary_data'] )
			? wp_unslash( $_POST['nuclen_summary_data'] )
			: [];

		if ( is_array( $raw ) ) {
			$date    = isset( $raw['date'] ) ? sanitize_text_field( $raw['date'] ) : gmdate( 'Y-m-d H:i:s' );
			$summary = isset( $raw['summary'] ) ? wp_kses_post( $raw['summary'] ) : '';

			update_post_meta( $post_id, 'nuclen-summary-data', [
				'summary' => $summary,
				'date'    => $date,
			] );
		} else {
			delete_post_meta( $post_id, 'nuclen-summary-data' );
		}

		/* ---------- Protected? checkbox ---------- */
		$protected = isset( $_POST['nuclen_summary_protected'] )
			? sanitize_text_field( wp_unslash( $_POST['nuclen_summary_protected'] ) )
			: '';
		$protected === '1'
			? update_post_meta( $post_id, 'nuclen_summary_protected', 1 )
			: delete_post_meta( $post_id, 'nuclen_summary_protected' );

		/* ---------- last‑modified bump (loop‑safe) ---------- */
		$settings = get_option( 'nuclear_engagement_settings', [] );
		if ( isset( $settings['update_last_modified'] ) && (int) $settings['update_last_modified'] === 1 ) {

			/* ‑‑ TEMP‑UNHOOK to prevent recursion ‑‑ */
			remove_action( 'save_post', [ $this, 'nuclen_save_quiz_data_meta' ] );
			remove_action( 'save_post', [ $this, 'nuclen_save_summary_data_meta' ] );

			$time = current_time( 'mysql' );
			wp_update_post( [
				'ID'                => $post_id,
				'post_modified'     => $time,
				'post_modified_gmt' => get_gmt_from_date( $time ),
			] );

			/* ‑‑ RE‑HOOK afterwards ‑‑ */
			add_action( 'save_post', [ $this, 'nuclen_save_quiz_data_meta' ] );
			add_action( 'save_post', [ $this, 'nuclen_save_summary_data_meta' ] );
		}
	}
}
