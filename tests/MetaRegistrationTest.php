<?php
namespace NuclearEngagement\Core {
	if (!function_exists(__NAMESPACE__ . '\sanitize_text_field')) {
		function sanitize_text_field($text) { return trim($text); }
	}
	if (!function_exists(__NAMESPACE__ . '\wp_kses_post')) {
		function wp_kses_post($text) { return $text; }
	}
	if (!function_exists(__NAMESPACE__ . '\wp_kses')) {
		function wp_kses($text, $allowed_html) {
			if (is_array($allowed_html) && !empty($allowed_html)) {
				$allowed = '<' . implode('><', array_keys($allowed_html)) . '>';
				return strip_tags($text, $allowed);
			}
			return strip_tags($text);
		}
	}
}

namespace {
	// Global function stubs for array_map callbacks
	if (!function_exists('wp_kses_post')) {
		function wp_kses_post($text) { return $text; }
	}
	
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Core\MetaRegistration;
	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/MetaRegistration.php';

	class MetaRegistrationTest extends TestCase {
		public function test_invalid_input_returns_empty_string(): void {
			$this->assertSame('', MetaRegistration::sanitize_quiz_data('bad'));
			$this->assertSame('', MetaRegistration::sanitize_summary_data(false));
		}

		public function test_quiz_data_sanitizes_structure(): void {
			$input = [
				'date' => ' 2024-05-01 ',
				'questions' => [
					[
						'question' => '<i>Q1</i>',
						'answers' => ['A1', '<b>A2</b>'],
						'explanation' => '<p>E1</p>'
					],
					'ignore',
					[
						'question' => 'Q2'
					]
				]
			];

			$expected = [
				'date' => '2024-05-01',
				'questions' => [
					[
						'question' => '<i>Q1</i>',
						'answers' => ['A1', '<b>A2</b>'],
						'explanation' => '<p>E1</p>'
					],
					[
						'question' => 'Q2',
						'answers' => [],
						'explanation' => ''
					]
				]
			];

			$this->assertSame($expected, MetaRegistration::sanitize_quiz_data($input));
		}

		public function test_summary_data_sanitizes_structure(): void {
			$input = [
				'date' => ' 2024 ',
				'summary' => '<p>hello <script>alert("x")</script> <a href="/">Link</a></p>'
			];
			$expected = [
				'date' => '2024',
				'summary' => '<p>hello alert("x") <a href="/">Link</a></p>'
			];
			$this->assertSame($expected, MetaRegistration::sanitize_summary_data($input));
		}
	}
}
