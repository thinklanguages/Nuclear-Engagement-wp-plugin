<?php
declare(strict_types=1);
/**
 * Quiz shortcode handler.
 *
 * @package NuclearEngagement\Modules\Quiz
 */

namespace NuclearEngagement\Modules\Quiz;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Front\QuizView;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Quiz_Shortcode {
	private SettingsRepository $settings;
	private QuizView $view;
	private FrontClass $front;
	private Quiz_Service $service;

	public function __construct( SettingsRepository $settings, FrontClass $front, Quiz_Service $service ) {
		$this->settings = $settings;
		$this->front    = $front;
		$this->view     = new QuizView();
		$this->service  = $service;
	}

	public function register(): void {
		add_shortcode( 'nuclear_engagement_quiz', array( $this, 'render' ) );
	}

	public function render(): string {
		$this->front->nuclen_force_enqueue_assets();
		$quiz_data = $this->service->get_quiz_data( get_the_ID() );
		if ( ! $this->isValidQuizData( $quiz_data ) ) {
			return '';
		}

		$settings = $this->getQuizSettings();
		$theme    = $this->settings->get_string( 'theme', 'bright' );
		$html     = '<div class="nuclen-root" data-theme="' . esc_attr( $theme ) . '">';
		$html    .= $this->view->container( $settings );
		$html    .= $this->view->attribution( $settings['show_attribution'] );
		$html    .= '</div>';
		return $html;
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
