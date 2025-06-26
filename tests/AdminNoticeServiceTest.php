<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\AdminNoticeService;

namespace NuclearEngagement\Services {
    function add_action(...$args) {
        $GLOBALS['ans_actions'][] = $args;
    }
    if (!function_exists('esc_html')) {
        function esc_html($text) { return $text; }
    }
}

namespace {
    class AdminNoticeServiceTest extends TestCase {
        protected function setUp(): void {
            $GLOBALS['ans_actions'] = [];
        }

        public function test_add_hooks_into_admin_notices_once(): void {
            $service = new AdminNoticeService();
            $service->add('First');
            $service->add('Second');
            $this->assertCount(1, $GLOBALS['ans_actions']);
            $this->assertSame('admin_notices', $GLOBALS['ans_actions'][0][0]);
            $this->assertSame([$service, 'render'], $GLOBALS['ans_actions'][0][1]);
        }

        public function test_render_outputs_notices_and_clears(): void {
            $service = new AdminNoticeService();
            $service->add('Hello');
            ob_start();
            $service->render();
            $output = ob_get_clean();
            $this->assertSame('<div class="notice notice-error"><p>Hello</p></div>', trim($output));

            $service->add('Again');
            $this->assertCount(2, $GLOBALS['ans_actions']);
            ob_start();
            $service->render();
            $second = ob_get_clean();
            $this->assertSame('<div class="notice notice-error"><p>Again</p></div>', trim($second));
        }
    }
}
