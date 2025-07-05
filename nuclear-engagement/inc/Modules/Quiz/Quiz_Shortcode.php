<?php
/**
 * Quiz_Shortcode.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Modules_Quiz
 */

declare(strict_types=1);
/**
 * Quiz shortcode handler and renderer.
 *
 * This class handles the [nuclear_engagement_quiz] shortcode functionality,
 * including registration, rendering, validation, and asset management for
 * displaying quizzes on the frontend.
 *
 * @package NuclearEngagement\Modules\Quiz
 * @since   1.0.0
 */

namespace NuclearEngagement\Modules\Quiz;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Front\QuizView;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quiz Shortcode class for handling quiz display via WordPress shortcodes.
 *
 * This class manages the entire lifecycle of quiz shortcode processing:
 * - Shortcode registration with WordPress
 * - Quiz data validation and retrieval
 * - Frontend asset enqueuing
 * - HTML rendering with proper sanitization
 * - Theme and styling application
 *
 * @since 1.0.0
 */
class Quiz_Shortcode {

	/**
	 * Settings repository instance.
	 *
	 * @since 1.0.0
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Quiz view renderer instance.
	 *
	 * @since 1.0.0
	 * @var QuizView
	 */
	private QuizView $view;

	/**
	 * Frontend class instance for asset management.
	 *
	 * @since 1.0.0
	 * @var FrontClass
	 */
	private FrontClass $front;

	/**
	 * Quiz service instance for data operations.
	 *
	 * @since 1.0.0
	 * @var Quiz_Service
	 */
	private Quiz_Service $service;

	/**
	 * Constructor for Quiz_Shortcode.
	 *
	 * Initializes the shortcode handler with required dependencies for
	 * settings management, frontend operations, and quiz data handling.
	 *
	 * @since 1.0.0
	 *
	 * @param SettingsRepository $settings Settings repository instance.
	 * @param FrontClass         $front    Frontend class instance.
	 * @param Quiz_Service       $service  Quiz service instance.
	 */
	public function __construct( SettingsRepository $settings, FrontClass $front, Quiz_Service $service ) {
		$this->settings = $settings;
		$this->front    = $front;
		$this->view     = new QuizView();
		$this->service  = $service;
	}

	/**
	 * Register the quiz shortcode with WordPress.
	 *
	 * This method registers the [nuclear_engagement_quiz] shortcode with
	 * WordPress, making it available for use in posts and pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'nuclear_engagement_quiz', array( $this, 'render' ) );
	}

	/**
	 * Render the quiz shortcode output.
	 *
	 * This method handles the complete quiz rendering process:
	 * - Validates quiz data exists and is properly formatted
	 * - Enqueues necessary frontend assets
	 * - Generates HTML output with proper theme styling
	 * - Applies security escaping to all output
	 *
	 * @since 1.0.0
	 * @return string The rendered quiz HTML or empty string if invalid.
	 */
	public function render(): string {
		// Force enqueue frontend assets (CSS/JS) for quiz functionality.
		$this->front->nuclen_force_enqueue_assets();

		// Get current post ID.
		$post_id = get_the_ID();

		// Validate post ID before proceeding.
		if ( ! $post_id || ! is_int( $post_id ) ) {
			return '';
		}

		// Retrieve quiz data for the current post.
		$quiz_data = $this->service->get_quiz_data( $post_id );

		// Validate quiz data before rendering.
		if ( ! $this->isValidQuizData( $quiz_data ) ) {
			return '';
		}

		// Get quiz-specific settings and theme configuration.
		$settings = $this->getQuizSettings();
		$theme    = $this->settings->get_string( 'theme', 'bright' );

		// Build HTML output with proper escaping.
		$html  = '<div class="nuclen-root" data-theme="' . esc_attr( $theme ) . '">';
		$html .= $this->view->container( $settings );
		$html .= $this->view->attribution( $settings['show_attribution'] );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Validate quiz data structure and content.
	 *
	 * This method performs comprehensive validation to ensure quiz data
	 * is properly structured and contains valid questions before rendering.
	 *
	 * Validation checks:
	 * - Data is an array
	 * - Questions array exists and is not empty
	 * - At least one question has valid content
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $quiz_data The quiz data to validate.
	 * @return bool True if quiz data is valid, false otherwise.
	 */
	private function isValidQuizData( $quiz_data ): bool {
		// Basic structure validation.
		if ( ! is_array( $quiz_data ) || empty( $quiz_data['questions'] ) ) {
			return false;
		}

		// Filter out questions with empty or invalid content.
		$valid_questions = array_filter(
			$quiz_data['questions'],
			static function ( $q ) {
				return isset( $q['question'] ) && trim( $q['question'] ) !== '';
			}
		);

		// Require at least one valid question.
		return ! empty( $valid_questions );
	}

	/**
	 * Get quiz-specific settings for rendering.
	 *
	 * This method retrieves and formats settings specific to quiz display,
	 * including titles, custom HTML, and attribution preferences. All
	 * settings include fallback defaults for reliability.
	 *
	 * @since 1.0.0
	 * @return array {
	 *     Quiz settings array.
	 *
	 *     @type string $quiz_title       Quiz title text.
	 *     @type string $html_before      Custom HTML to display before quiz.
	 *     @type bool   $show_attribution Whether to show plugin attribution.
	 * }
	 */
	private function getQuizSettings(): array {
		return array(
			'quiz_title'       => $this->settings->get_string( 'quiz_title', __( 'Test your knowledge', 'nuclear-engagement' ) ),
			'html_before'      => $this->settings->get_string( 'custom_quiz_html_before', '' ),
			'show_attribution' => $this->settings->get_bool( 'show_attribution', false ),
		);
	}
}
