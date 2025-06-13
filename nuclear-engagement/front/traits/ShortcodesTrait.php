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
use NuclearEngagement\Front\QuizView;
use NuclearEngagement\Front\SummaryView;

if (!defined('ABSPATH')) {
    exit;
}

trait ShortcodesTrait {
        private ?QuizView $quiz_view = null;
        private ?SummaryView $summary_view = null;

        private function get_quiz_view(): QuizView {
                if ($this->quiz_view === null) {
                        $this->quiz_view = new QuizView();
                }
                return $this->quiz_view;
        }

        private function get_summary_view(): SummaryView {
                if ($this->summary_view === null) {
                        $this->summary_view = new SummaryView();
                }
                return $this->summary_view;
        }

	/* ---------- Auto-insert into content ---------- */
	public function nuclen_auto_insert_shortcodes( $content ) {
		$settings_repo = $this->nuclen_get_settings_repository();
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

                $view = $this->get_quiz_view();
                $html  = $view->container($settings);
                $html .= $view->attribution($settings['show_attribution']);

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
		
                $view = $this->get_summary_view();
                $html = $view->container($summary_data, $settings);
                $html .= $view->attribution($settings['show_attribution']);
		
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
}