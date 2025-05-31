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

use NuclearEngagement\SettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait ShortcodesTrait {

	/* ---------- Auto-insert into content ---------- */
	public function nuclen_auto_insert_shortcodes( $content ) {
		$settings_repo = $this->get_settings_repository();
		$summary_position = $settings_repo->get( 'display_summary', 'none' );
		$quiz_position    = $settings_repo->get( 'display_quiz', 'none' );
		$toc_position     = $settings_repo->get( 'display_toc', 'manual' );

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

	/* ---------- Quiz shortcode methods ---------- */
	
	/**
	 * Main quiz shortcode handler
	 *
	 * @return string
	 */
	public function nuclen_render_quiz_shortcode() {
		$quiz_data = $this->getQuizData();
		
		if (!$this->isValidQuizData($quiz_data)) {
			return '';
		}

		$settings = $this->getQuizSettings();
		
		$html = $this->renderQuizContainer($settings);
		$html .= $this->renderQuizAttribution($settings['show_attribution']);
		
		return $html;
	}

	/**
	 * Get quiz data for current post
	 *
	 * @return array|null
	 */
	private function getQuizData() {
		$post_id = get_the_ID();
		$quiz_meta = get_post_meta($post_id, 'nuclen-quiz-data', true);
		return maybe_unserialize($quiz_meta);
	}

	/**
	 * Validate quiz data
	 *
	 * @param mixed $quiz_data
	 * @return bool
	 */
	private function isValidQuizData($quiz_data) {
		if (!is_array($quiz_data) || empty($quiz_data['questions'])) {
			return false;
		}

		$valid_questions = array_filter(
			$quiz_data['questions'],
			static function ($q) {
				return isset($q['question']) && trim($q['question']) !== '';
			}
		);

		return !empty($valid_questions);
	}

	/**
	 * Get quiz settings from repository
	 *
	 * @return array
	 */
	private function getQuizSettings() {
		$settings = SettingsRepository::get_instance();
		
		return [
			'quiz_title' => $settings->get_string('quiz_title', __('Test your knowledge', 'nuclear-engagement')),
			'html_before' => $settings->get_string('custom_quiz_html_before', ''),
			'show_attribution' => $settings->get_bool('show_attribution', false),
		];
	}

	/**
	 * Render the main quiz container HTML
	 *
	 * @param array $settings
	 * @return string
	 */
	private function renderQuizContainer($settings) {
		$html = '<section id="nuclen-quiz-container" class="nuclen-quiz">';
		
		// Custom HTML before quiz
		if (trim($settings['html_before']) !== '') {
			$html .= $this->renderQuizStartMessage($settings['html_before']);
		}

		// Quiz structure
		$html .= $this->renderQuizTitle($settings['quiz_title']);
		$html .= $this->renderQuizProgressBar();
		$html .= $this->renderQuizQuestionContainer();
		$html .= $this->renderQuizAnswersContainer();
		$html .= $this->renderQuizResultContainer();
		$html .= $this->renderQuizExplanationContainer();
		$html .= $this->renderQuizNextButton();
		$html .= $this->renderQuizFinalResultContainer();
		
		$html .= '</section>';
		
		return $html;
	}

	/**
	 * Render quiz start message
	 *
	 * @param string $html_before
	 * @return string
	 */
	private function renderQuizStartMessage($html_before) {
		return sprintf(
			'<div id="nuclen-quiz-start-message" class="nuclen-fg">%s</div>',
			shortcode_unautop($html_before)
		);
	}

	/**
	 * Render quiz title
	 *
	 * @param string $title
	 * @return string
	 */
	private function renderQuizTitle($title) {
		return sprintf(
			'<h2 id="nuclen-quiz-title" class="nuclen-fg">%s</h2>',
			esc_html($title)
		);
	}

	/**
	 * Render quiz progress bar
	 *
	 * @return string
	 */
	private function renderQuizProgressBar() {
		return '<div id="nuclen-quiz-progress-bar-container"><div id="nuclen-quiz-progress-bar"></div></div>';
	}

	/**
	 * Render quiz question container
	 *
	 * @return string
	 */
	private function renderQuizQuestionContainer() {
		return '<div id="nuclen-quiz-question-container" class="nuclen-fg"></div>';
	}

	/**
	 * Render quiz answers container
	 *
	 * @return string
	 */
	private function renderQuizAnswersContainer() {
		return '<div id="nuclen-quiz-answers-container" class="nuclen-quiz-answers-grid"></div>';
	}

	/**
	 * Render quiz result container
	 *
	 * @return string
	 */
	private function renderQuizResultContainer() {
		return '<div id="nuclen-quiz-result-container"></div>';
	}

	/**
	 * Render quiz explanation container
	 *
	 * @return string
	 */
	private function renderQuizExplanationContainer() {
		return '<div id="nuclen-quiz-explanation-container" class="nuclen-fg nuclen-quiz-hidden"></div>';
	}

	/**
	 * Render quiz next button
	 *
	 * @return string
	 */
	private function renderQuizNextButton() {
		return sprintf(
			'<button id="nuclen-quiz-next-button" class="nuclen-quiz-hidden">%s</button>',
			esc_html__('Next', 'nuclear-engagement')
		);
	}

	/**
	 * Render quiz final result container
	 *
	 * @return string
	 */
	private function renderQuizFinalResultContainer() {
		return '<div id="nuclen-quiz-final-result-container"></div>';
	}

	/**
	 * Render quiz attribution if enabled
	 *
	 * @param bool $show_attribution
	 * @return string
	 */
	private function renderQuizAttribution($show_attribution) {
		if (!$show_attribution) {
			return '';
		}

		return sprintf(
			'<div class="nuclen-attribution">%s <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank">%s</a></div>',
			esc_html__('Quiz by', 'nuclear-engagement'),
			esc_html__('Nuclear Engagement', 'nuclear-engagement')
		);
	}

	/* ---------- Summary shortcode methods ---------- */
	
	/**
	 * Main summary shortcode handler
	 *
	 * @return string
	 */
	public function nuclen_render_summary_shortcode() {
		$summary_data = $this->getSummaryData();
		
		if (!$this->isValidSummaryData($summary_data)) {
			return '';
		}

		$settings = $this->getSummarySettings();
		
		$html = $this->renderSummaryContainer($summary_data, $settings);
		$html .= $this->renderSummaryAttribution($settings['show_attribution']);
		
		return $html;
	}

	/**
	 * Get summary data for current post
	 *
	 * @return array|null
	 */
	private function getSummaryData() {
		$post_id = get_the_ID();
		return get_post_meta($post_id, 'nuclen-summary-data', true);
	}

	/**
	 * Validate summary data
	 *
	 * @param mixed $summary_data
	 * @return bool
	 */
	private function isValidSummaryData($summary_data) {
		return !empty($summary_data) && !empty(trim($summary_data['summary'] ?? ''));
	}

	/**
	 * Get summary settings from repository
	 *
	 * @return array
	 */
	private function getSummarySettings() {
		$settings = SettingsRepository::get_instance();
		
		return [
			'summary_title' => $settings->get_string('summary_title', __('Key Facts', 'nuclear-engagement')),
			'show_attribution' => $settings->get_bool('show_attribution', false),
		];
	}

	/**
	 * Render the main summary container HTML
	 *
	 * @param array $summary_data
	 * @param array $settings
	 * @return string
	 */
	private function renderSummaryContainer($summary_data, $settings) {
		$summary_content = wp_kses_post($summary_data['summary']);

		return sprintf(
			'<section id="nuclen-summary-container" class="nuclen-summary">
				<h2 id="nuclen-summary-title" class="nuclen-fg">%s</h2>
				<div class="nuclen-fg" id="nuclen-summary-body">%s</div>
			</section>',
			esc_html($settings['summary_title']),
			$summary_content
		);
	}

	/**
	 * Render summary attribution if enabled
	 *
	 * @param bool $show_attribution
	 * @return string
	 */
	private function renderSummaryAttribution($show_attribution) {
		if (!$show_attribution) {
			return '';
		}

		return sprintf(
			'<div class="nuclen-attribution">%s <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank">%s</a></div>',
			esc_html__('Summary by', 'nuclear-engagement'),
			esc_html__('Nuclear Engagement', 'nuclear-engagement')
		);
	}
}