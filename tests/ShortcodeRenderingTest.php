<?php
/**
 * Test shortcode rendering functionality
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Modules\Quiz\Quiz_Service;
use NuclearEngagement\Modules\Quiz\Quiz_Shortcode;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Front\QuizView;

/**
 * Shortcode rendering test class.
 *
 * Tests that shortcodes render properly and don't output blank content
 * when valid data is present.
 */
class ShortcodeRenderingTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define constants if not already defined
		if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
			define( 'NUCLEN_PLUGIN_DIR', dirname( __DIR__ ) . '/nuclear-engagement/' );
		}
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/wordpress/' );
		}

		// Mock WordPress functions
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test that quiz shortcode renders content when valid data exists.
	 */
	public function test_quiz_shortcode_renders_with_valid_data() {
		// Mock get_the_ID to return a valid post ID
		Functions\when( 'get_the_ID' )->justReturn( 123 );

		// Mock settings repository
		$settings_mock = $this->createMock( SettingsRepository::class );
		$settings_mock->method( 'get_string' )->willReturnMap( [
			[ 'theme', 'bright', 'light' ],
			[ 'quiz_title', 'Test your knowledge', 'My Quiz Title' ],
			[ 'custom_quiz_html_before', '', '' ],
		] );
		$settings_mock->method( 'get_bool' )->willReturn( false );

		// Mock front class
		$front_mock = $this->createMock( FrontClass::class );
		$front_mock->expects( $this->once() )
			->method( 'nuclen_force_enqueue_assets' );

		// Mock quiz service to return valid quiz data
		$quiz_service_mock = $this->createMock( Quiz_Service::class );
		$quiz_service_mock->method( 'get_quiz_data' )->willReturn( [
			'questions' => [
				[
					'question' => 'What is 2 + 2?',
					'answers' => [ '3', '4', '5', '6' ],
					'correct' => 1,
					'explanation' => '2 + 2 equals 4'
				],
				[
					'question' => 'What is the capital of France?',
					'answers' => [ 'London', 'Berlin', 'Paris', 'Madrid' ],
					'correct' => 2,
					'explanation' => 'Paris is the capital of France'
				]
			]
		] );

		// Mock locate_template to return empty (use plugin templates)
		Functions\when( 'locate_template' )->justReturn( '' );

		// Create quiz shortcode instance
		$quiz_shortcode = new Quiz_Shortcode( $settings_mock, $front_mock, $quiz_service_mock );

		// Render the shortcode
		$output = $quiz_shortcode->render();

		// Assert output is not empty
		$this->assertNotEmpty( $output, 'Quiz shortcode should not render empty content when valid data exists' );

		// Assert output contains expected structure
		$this->assertStringContainsString( 'nuclen-root', $output );
		$this->assertStringContainsString( 'data-theme="light"', $output );
		$this->assertStringContainsString( 'nuclen-quiz-container', $output );
		$this->assertStringContainsString( 'My Quiz Title', $output );
	}

	/**
	 * Test that quiz shortcode returns empty string when no data exists.
	 */
	public function test_quiz_shortcode_returns_empty_without_data() {
		// Mock get_the_ID to return a valid post ID
		Functions\when( 'get_the_ID' )->justReturn( 456 );

		// Mock settings repository
		$settings_mock = $this->createMock( SettingsRepository::class );

		// Mock front class
		$front_mock = $this->createMock( FrontClass::class );
		$front_mock->expects( $this->once() )
			->method( 'nuclen_force_enqueue_assets' );

		// Mock quiz service to return empty data
		$quiz_service_mock = $this->createMock( Quiz_Service::class );
		$quiz_service_mock->method( 'get_quiz_data' )->willReturn( [] );

		// Create quiz shortcode instance
		$quiz_shortcode = new Quiz_Shortcode( $settings_mock, $front_mock, $quiz_service_mock );

		// Render the shortcode
		$output = $quiz_shortcode->render();

		// Assert output is empty
		$this->assertEmpty( $output, 'Quiz shortcode should return empty string when no quiz data exists' );
	}

	/**
	 * Test that quiz shortcode handles invalid post ID gracefully.
	 */
	public function test_quiz_shortcode_handles_invalid_post_id() {
		// Mock get_the_ID to return false
		Functions\when( 'get_the_ID' )->justReturn( false );

		// Mock settings repository
		$settings_mock = $this->createMock( SettingsRepository::class );

		// Mock front class
		$front_mock = $this->createMock( FrontClass::class );
		$front_mock->expects( $this->once() )
			->method( 'nuclen_force_enqueue_assets' );

		// Mock quiz service
		$quiz_service_mock = $this->createMock( Quiz_Service::class );

		// Create quiz shortcode instance
		$quiz_shortcode = new Quiz_Shortcode( $settings_mock, $front_mock, $quiz_service_mock );

		// Render the shortcode
		$output = $quiz_shortcode->render();

		// Assert output is empty
		$this->assertEmpty( $output, 'Quiz shortcode should return empty string when post ID is invalid' );
	}

	/**
	 * Test that JavaScript data is properly localized for frontend.
	 */
	public function test_quiz_data_localization() {
		// This test verifies that the AssetsTrait properly localizes quiz data
		$settings_mock = $this->createMock( SettingsRepository::class );
		$settings_mock->method( 'get' )->willReturn( false );
		$settings_mock->method( 'get_int' )->willReturn( 10 );

		// Create a test implementation of AssetsTrait
		$assets_handler = new class( $settings_mock ) {
			use \NuclearEngagement\Front\AssetsTrait;
			
			private $plugin_name = 'nuclear-engagement';
			private $settings;
			
			public function __construct( $settings ) {
				$this->settings = $settings;
			}
			
			public function nuclen_get_settings_repository() {
				return $this->settings;
			}
			
			public function test_get_post_quiz_data() {
				return $this->get_post_quiz_data();
			}
		};

		// Mock get_the_ID
		Functions\when( 'get_the_ID' )->justReturn( 789 );

		// Mock get_post_meta to return quiz data
		Functions\when( 'get_post_meta' )->justReturn( [
			'questions' => [
				[ 'question' => 'Test question?', 'answers' => [ 'A', 'B', 'C', 'D' ] ]
			]
		] );
		Functions\when( 'maybe_unserialize' )->returnArg();

		// Get quiz data
		$quiz_data = $assets_handler->test_get_post_quiz_data();

		// Assert data is properly retrieved
		$this->assertNotEmpty( $quiz_data );
		$this->assertArrayHasKey( 'questions', $quiz_data );
		$this->assertCount( 1, $quiz_data['questions'] );
	}
}