<?php
/**
 * QuizView.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Front
 */

declare(strict_types=1);
namespace NuclearEngagement\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QuizView {
	public function container( array $settings ): string {
		$data = array(
			'start_message'          => '',
			'title'                  => $this->title( $settings['quiz_title'] ),
			'progress_bar'           => $this->progressBar(),
			'question_container'     => $this->questionContainer(),
			'answers_container'      => $this->answersContainer(),
			'result_container'       => $this->resultContainer(),
			'explanation_container'  => $this->explanationContainer(),
			'next_button'            => $this->nextButton(),
			'final_result_container' => $this->finalResultContainer(),
		);

		if ( trim( $settings['html_before'] ) !== '' ) {
			$data['start_message'] = $this->startMessage( $settings['html_before'] );
		}

		return $this->render( 'container', $data );
	}

	public function attribution( bool $show ): string {
		return $this->render( 'attribution', array( 'show' => $show ) );
	}

	private function startMessage( string $html_before ): string {
		return $this->render( 'start-message', array( 'html_before' => $html_before ) );
	}

	private function title( string $title ): string {
		return $this->render( 'title', array( 'title' => $title ) );
	}

	private function progressBar(): string {
		return $this->render( 'progress-bar' );
	}

	private function questionContainer(): string {
		return $this->render( 'question-container' );
	}

	private function answersContainer(): string {
		return $this->render( 'answers-container' );
	}

	private function resultContainer(): string {
		return $this->render( 'result-container' );
	}

	private function explanationContainer(): string {
		return $this->render( 'explanation-container' );
	}

	private function nextButton(): string {
		return $this->render( 'next-button' );
	}

	private function finalResultContainer(): string {
		return $this->render( 'final-result-container' );
	}

	private function render( string $slug, array $data = array() ): string {
		$template = locate_template( array( 'nuclear-engagement/quiz/' . $slug . '.php' ), false, false );
		if ( '' === $template ) {
			$template = NUCLEN_PLUGIN_DIR . 'templates/front/quiz/' . $slug . '.php';
		}
		ob_start();
		extract( $data, EXTR_SKIP );
		include $template;
		return ob_get_clean();
	}
}
