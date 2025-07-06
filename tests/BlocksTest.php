<?php
namespace NuclearEngagement\Services {
	class LoggingService {
		public static array $logs = [];
		public static function log(string $message): void {
			self::$logs[] = $message;
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Core\Blocks;
	function register_block_type(string $name, array $args): void {
		$GLOBALS['block_regs'][$name] = $args;
	}
	function wp_script_is(string $handle, string $list = ''): bool {
		return $GLOBALS['script_is'] ?? false;
	}
	if (!function_exists('__')) {
		function __($t, $d = null) { return $t; }
	}
	if (!function_exists('esc_html__')) {
		function esc_html__($t, $d = null) { return $t; }
	}
	function do_shortcode(string $code) {
		$GLOBALS['shortcode_calls'][] = $code;
		// Return custom responses if set, otherwise default
		if (isset($GLOBALS['shortcode_responses'][$code])) {
			$response = $GLOBALS['shortcode_responses'][$code];
			// Convert null to empty string to match real WordPress behavior
			return $response === null ? '' : $response;
		}
		return 'OUT:' . $code;
	}

	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Blocks.php';

	class BlocksTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['block_regs'] = [];
			$GLOBALS['script_is'] = true;
			$GLOBALS['shortcode_calls'] = [];
			$GLOBALS['shortcode_responses'] = [];
			\NuclearEngagement\Services\LoggingService::$logs = [];
		}

		public function test_missing_script_triggers_logging(): void {
			$GLOBALS['script_is'] = false;
			Blocks::register();
			$this->assertSame(
				['Nuclear Engagement: nuclen-admin script missing.'],
				\NuclearEngagement\Services\LoggingService::$logs
			);
			$this->assertSame([], $GLOBALS['block_regs']);
		}

               public function test_registers_three_blocks_and_callbacks_use_shortcodes(): void {
                       Blocks::register();
                       $this->assertCount(3, $GLOBALS['block_regs']);
                       $this->assertArrayHasKey('nuclear-engagement/quiz', $GLOBALS['block_regs']);
                       $this->assertArrayHasKey('nuclear-engagement/summary', $GLOBALS['block_regs']);
                       $this->assertArrayHasKey('nuclear-engagement/toc', $GLOBALS['block_regs']);

                       $quiz_cb = $GLOBALS['block_regs']['nuclear-engagement/quiz']['render_callback'];
                       $summary_cb = $GLOBALS['block_regs']['nuclear-engagement/summary']['render_callback'];
                       $toc_cb = $GLOBALS['block_regs']['nuclear-engagement/toc']['render_callback'];

                       $this->assertIsCallable($quiz_cb);
                       $this->assertIsCallable($summary_cb);
                       $this->assertIsCallable($toc_cb);

                       $quiz_html = $quiz_cb();
                       $summary_html = $summary_cb();
                       $toc_html = $toc_cb();

                       $this->assertSame('OUT:[nuclear_engagement_quiz]', $quiz_html);
                       $this->assertSame('OUT:[nuclear_engagement_summary]', $summary_html);
                       $this->assertSame('OUT:[nuclear_engagement_toc]', $toc_html);

                       $this->assertSame([
                                '[nuclear_engagement_quiz]',
                                '[nuclear_engagement_summary]',
                                '[nuclear_engagement_toc]'
                        ], $GLOBALS['shortcode_calls']);
                       $this->assertSame([], \NuclearEngagement\Services\LoggingService::$logs);
               }

		public function test_block_configuration_properties(): void {
			Blocks::register();
			
			// Test quiz block configuration
			$quiz_config = $GLOBALS['block_regs']['nuclear-engagement/quiz'];
			$this->assertSame(2, $quiz_config['api_version']);
			$this->assertSame('Quiz', $quiz_config['title']);
			$this->assertSame('widgets', $quiz_config['category']);
			$this->assertSame('editor-help', $quiz_config['icon']);
			$this->assertSame('nuclen-admin', $quiz_config['editor_script']);
			
			// Test summary block configuration
			$summary_config = $GLOBALS['block_regs']['nuclear-engagement/summary'];
			$this->assertSame('Summary', $summary_config['title']);
			$this->assertSame('excerpt-view', $summary_config['icon']);
			
			// Test TOC block configuration
			$toc_config = $GLOBALS['block_regs']['nuclear-engagement/toc'];
			$this->assertSame('TOC', $toc_config['title']);
			$this->assertSame('list-view', $toc_config['icon']);
			
			// Ensure all blocks share common properties
			foreach (['nuclear-engagement/quiz', 'nuclear-engagement/summary', 'nuclear-engagement/toc'] as $block_name) {
				$config = $GLOBALS['block_regs'][$block_name];
				$this->assertSame(2, $config['api_version'], "Block {$block_name} should have API version 2");
				$this->assertSame('widgets', $config['category'], "Block {$block_name} should be in widgets category");
				$this->assertSame('nuclen-admin', $config['editor_script'], "Block {$block_name} should use nuclen-admin script");
				$this->assertIsCallable($config['render_callback'], "Block {$block_name} should have callable render callback");
			}
		}

		public function test_blocks_render_fallback_when_shortcode_empty(): void {
			// Set shortcodes to return empty strings
			$GLOBALS['shortcode_responses'] = [
				'[nuclear_engagement_quiz]' => '',
				'[nuclear_engagement_summary]' => '   ', // whitespace only
				'[nuclear_engagement_toc]' => '',
			];
			
			Blocks::register();
			
			$quiz_cb = $GLOBALS['block_regs']['nuclear-engagement/quiz']['render_callback'];
			$summary_cb = $GLOBALS['block_regs']['nuclear-engagement/summary']['render_callback'];
			$toc_cb = $GLOBALS['block_regs']['nuclear-engagement/toc']['render_callback'];
			
			$this->assertSame('<p>Quiz unavailable.</p>', $quiz_cb());
			$this->assertSame('<p>Summary unavailable.</p>', $summary_cb());
			$this->assertSame('<p>TOC unavailable.</p>', $toc_cb());
		}

		public function test_blocks_render_content_when_shortcode_has_data(): void {
			// Set shortcodes to return actual content
			$GLOBALS['shortcode_responses'] = [
				'[nuclear_engagement_quiz]' => '<div class="nuclen-quiz">Quiz content</div>',
				'[nuclear_engagement_summary]' => '<div class="nuclen-summary">Summary content</div>',
				'[nuclear_engagement_toc]' => '<div class="nuclen-toc">TOC content</div>',
			];
			
			Blocks::register();
			
			$quiz_cb = $GLOBALS['block_regs']['nuclear-engagement/quiz']['render_callback'];
			$summary_cb = $GLOBALS['block_regs']['nuclear-engagement/summary']['render_callback'];
			$toc_cb = $GLOBALS['block_regs']['nuclear-engagement/toc']['render_callback'];
			
			$this->assertSame('<div class="nuclen-quiz">Quiz content</div>', $quiz_cb());
			$this->assertSame('<div class="nuclen-summary">Summary content</div>', $summary_cb());
			$this->assertSame('<div class="nuclen-toc">TOC content</div>', $toc_cb());
		}

		public function test_blocks_handle_non_string_shortcode_output(): void {
			// Test various edge cases
			$GLOBALS['shortcode_responses'] = [
				'[nuclear_engagement_quiz]' => false,
				'[nuclear_engagement_summary]' => 0,
				'[nuclear_engagement_toc]' => array('not', 'a', 'string'),
			];
			
			Blocks::register();
			
			$quiz_cb = $GLOBALS['block_regs']['nuclear-engagement/quiz']['render_callback'];
			$summary_cb = $GLOBALS['block_regs']['nuclear-engagement/summary']['render_callback'];
			$toc_cb = $GLOBALS['block_regs']['nuclear-engagement/toc']['render_callback'];
			
			// Should render fallback when shortcode doesn't return a string
			$this->assertSame('<p>Quiz unavailable.</p>', $quiz_cb());
			$this->assertSame('<p>Summary unavailable.</p>', $summary_cb());
			$this->assertSame('<p>TOC unavailable.</p>', $toc_cb());
		}
	}
}
