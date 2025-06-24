<?php
declare(strict_types=1);
namespace NuclearEngagement\Front;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Front\FrontClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QuizShortcode {
	private SettingsRepository $settings;
	private QuizView $view;
	private FrontClass $front;

	public function __construct( SettingsRepository $settings, FrontClass $front ) {
		$this->settings = $settings;
		$this->front    = $front;
		$this->view     = new QuizView();
	}

	public function register(): void {
		add_shortcode( 'nuclear_engagement_quiz', array( $this, 'render' ) );
	}

	public function render(): string {
		$this->front->nuclen_force_enqueue_assets();
		$quiz_data = $this->getQuizData();
		if ( ! $this->isValidQuizData( $quiz_data ) ) {
			return '';
		}

		$settings = $this->getQuizSettings();
		$html     = '<div class="nuclen-root">';
		$html    .= $this->view->container( $settings );
		$html    .= $this->view->attribution( $settings['show_attribution'] );
		$html    .= '</div>';
		return $html;
	}

	private function getQuizData() {
		$post_id   = get_the_ID();
		$quiz_meta = get_post_meta( $post_id, 'nuclen-quiz-data', true );
		return maybe_unserialize( $quiz_meta );
	}

	private function isValidQuizData( $quiz_data ): bool {
		if ( ! is_array( $quiz_data ) || empty( $quiz_data['questions'] ) ) {
			return false;
		}

		$valid_questions = array_filter(
			$quiz_data['questions'],
			static function ( $q ) {
				return isset( $q['question'] ) && trim( $q['question'] ) !== '';
			}
		);

		return ! empty( $valid_questions );
	}

	private function getQuizSettings(): array {
		return array(
			'quiz_title'       => $this->settings->get_string( 'quiz_title', __( 'Test your knowledge', 'nuclear-engagement' ) ),
			'html_before'      => $this->settings->get_string( 'custom_quiz_html_before', '' ),
			'show_attribution' => $this->settings->get_bool( 'show_attribution', false ),
		);
	}
}
