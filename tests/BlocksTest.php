<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Blocks;

namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static function log(string $msg): void {
            self::$logs[] = $msg;
        }
    }
}

namespace {
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
        return 'OUT:' . $code;
    }

    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Blocks.php';

    class BlocksTest extends TestCase {
        protected function setUp(): void {
            $GLOBALS['block_regs'] = [];
            $GLOBALS['script_is'] = true;
            $GLOBALS['shortcode_calls'] = [];
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

        public function test_registers_two_blocks_and_callbacks_use_shortcodes(): void {
            Blocks::register();
            $this->assertCount(2, $GLOBALS['block_regs']);
            $this->assertArrayHasKey('nuclear-engagement/quiz', $GLOBALS['block_regs']);
            $this->assertArrayHasKey('nuclear-engagement/summary', $GLOBALS['block_regs']);

            $quiz_cb = $GLOBALS['block_regs']['nuclear-engagement/quiz']['render_callback'];
            $summary_cb = $GLOBALS['block_regs']['nuclear-engagement/summary']['render_callback'];

            $this->assertIsCallable($quiz_cb);
            $this->assertIsCallable($summary_cb);

            $quiz_html = $quiz_cb();
            $summary_html = $summary_cb();

            $this->assertSame('OUT:[nuclear_engagement_quiz]', $quiz_html);
            $this->assertSame('OUT:[nuclear_engagement_summary]', $summary_html);

            $this->assertSame([
                '[nuclear_engagement_quiz]',
                '[nuclear_engagement_summary]'
            ], $GLOBALS['shortcode_calls']);
            $this->assertSame([], \NuclearEngagement\Services\LoggingService::$logs);
        }
    }
}
