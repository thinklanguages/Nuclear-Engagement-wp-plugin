<?php
/**
 * File: front/traits/ShortcodesTrait.php
 *
 * Trait: ShortcodesTrait
 *
 * Quiz / summary shortcodes and auto-insertion helpers.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ShortcodesTrait {

	/* ---------- Auto-insert into content ---------- */
	public function nuclen_auto_insert_shortcodes( $content ) {
		$options          = get_option( 'nuclear_engagement_settings', array() );
		$summary_position = $options['display_summary'] ?? 'none';
		$quiz_position    = $options['display_quiz']   ?? 'none';
		$toc_position     = $options['display_toc']    ?? 'manual';

		// Collect all elements with their positions
		$elements = [
			'summary' => [
				'position' => $summary_position,
				'shortcode' => '[nuclear_engagement_summary]',
			],
			'quiz' => [
				'position' => $quiz_position,
				'shortcode' => '[nuclear_engagement_quiz]',
			],
			'toc' => [
				'position' => $toc_position,
				'shortcode' => '[nuclear_engagement_toc]',
			],
		];

		// Process elements that should appear before content
		$before_content = '';
		
		// First add summary if set to before
		if ( $elements['summary']['position'] === 'before' ) {
			$before_content .= do_shortcode( $elements['summary']['shortcode'] );
		}
		
		// Then add TOC if set to before (right after summary)
		if ( $elements['toc']['position'] === 'before' ) {
			$before_content .= do_shortcode( $elements['toc']['shortcode'] );
		}
		
		// Then add quiz if set to before (after summary and TOC)
		if ( $elements['quiz']['position'] === 'before' ) {
			$before_content .= do_shortcode( $elements['quiz']['shortcode'] );
		}

		// Process elements that should appear after content
		$after_content = '';
		
		// First add summary if set to after
		if ( $elements['summary']['position'] === 'after' ) {
			$after_content .= do_shortcode( $elements['summary']['shortcode'] );
		}
		
		// Then add TOC if set to after (right after summary)
		if ( $elements['toc']['position'] === 'after' ) {
			$after_content .= do_shortcode( $elements['toc']['shortcode'] );
		}
		
		// Then add quiz if set to after (after summary and TOC)
		if ( $elements['quiz']['position'] === 'after' ) {
			$after_content .= do_shortcode( $elements['quiz']['shortcode'] );
		}

		return $before_content . $content . $after_content;
	}

	/* ---------- Shortcode registrations ---------- */
	public function nuclen_register_quiz_shortcode() {
		add_shortcode( 'nuclear_engagement_quiz', array( $this, 'nuclen_render_quiz_shortcode' ) );
	}

	public function nuclen_register_summary_shortcode() {
		add_shortcode( 'nuclear_engagement_summary', array( $this, 'nuclen_render_summary_shortcode' ) );
	}

	/* ---------- Render quiz shortcode ---------- */
	public function nuclen_render_quiz_shortcode() {
		$post_id    = get_the_ID();
		$quiz_meta  = get_post_meta( $post_id, 'nuclen-quiz-data', true );
		$quiz_data  = maybe_unserialize( $quiz_meta );

		if ( ! is_array( $quiz_data ) || empty( $quiz_data['questions'] ) ) {
			return '';
		}
		$valid = array_filter(
			$quiz_data['questions'],
			static function ( $q ) {
				return isset( $q['question'] ) && trim( $q['question'] ) !== '';
			}
		);
		if ( empty( $valid ) ) {
			return '';
		}

		$options     = get_option( 'nuclear_engagement_settings', array() );
		$quiz_title  = $options['quiz_title']            ?? __( 'Test your knowledge', 'nuclear-engagement' );
		$html_before = $options['custom_quiz_html_before'] ?? '';

		$html  = '<section id="nuclen-quiz-container" class="nuclen-quiz">';
		if ( trim( $html_before ) !== '' ) {
			$html .= '<div id="nuclen-quiz-start-message" class="nuclen-fg">' . shortcode_unautop( $html_before ) . '</div>';
		}

		$html .= '
				<h2 id="nuclen-quiz-title" class="nuclen-fg">' . esc_html( $quiz_title ) . '</h2>
				<div id="nuclen-quiz-progress-bar-container"><div id="nuclen-quiz-progress-bar"></div></div>
				<div id="nuclen-quiz-question-container" class="nuclen-fg"></div>
				<div id="nuclen-quiz-answers-container" class="nuclen-quiz-answers-grid"></div>
				<div id="nuclen-quiz-result-container"></div>
				<div id="nuclen-quiz-explanation-container" class="nuclen-fg nuclen-quiz-hidden"></div>
				<button id="nuclen-quiz-next-button" class="nuclen-quiz-hidden">' . esc_html__( 'Next', 'nuclear-engagement' ) . '</button>
				<div id="nuclen-quiz-final-result-container"></div>
			</section>';

		if ( ! empty( $options['show_attribution'] ) ) {
			$html .= '<div class="nuclen-attribution">' .
				esc_html__( 'Quiz by', 'nuclear-engagement' ) .
				' <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank">' .
				esc_html__( 'Nuclear Engagement', 'nuclear-engagement' ) .
				'</a></div>';
		}
		return $html;
	}

	/* ---------- Render summary shortcode ---------- */
	public function nuclen_render_summary_shortcode() {
		$post_id      = get_the_ID();
		$summary_data = get_post_meta( $post_id, 'nuclen-summary-data', true );

		if ( empty( $summary_data ) || empty( trim( $summary_data['summary'] ?? '' ) ) ) {
			return '';
		}

		$options         = get_option( 'nuclear_engagement_settings', array() );
		$summary_title   = $options['summary_title'] ?? __( 'Key Facts', 'nuclear-engagement' );
		$summary_content = wp_kses_post( $summary_data['summary'] );

		$html = '<section id="nuclen-summary-container" class="nuclen-summary">
				<h2 id="nuclen-summary-title" class="nuclen-fg">' . esc_html( $summary_title ) . '</h2>
				<div class="nuclen-fg" id="nuclen-summary-body">' . $summary_content . '</div>
			</section>';

		if ( ! empty( $options['show_attribution'] ) ) {
			$html .= '<div class="nuclen-attribution">' .
				esc_html__( 'Summary by', 'nuclear-engagement' ) .
				' <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank">' .
				esc_html__( 'Nuclear Engagement', 'nuclear-engagement' ) .
				'</a></div>';
		}
		return $html;
	}
}
