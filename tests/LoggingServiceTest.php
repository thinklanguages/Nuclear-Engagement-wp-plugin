<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\LoggingService;

namespace NuclearEngagement\Services {
    function wp_upload_dir() {
        return [
            'basedir' => $GLOBALS['ls_base'],
            'baseurl'  => 'http://example.com/uploads',
        ];
    }
    function wp_mkdir_p($dir) {
        return mkdir($dir, 0777, true);
    }
    function add_action(...$args) {
        $GLOBALS['ls_actions'][] = $args;
    }
    function error_log($msg) {
        $GLOBALS['ls_errors'][] = $msg;
    }
    if (!function_exists('esc_html')) {
        function esc_html($text) { return $text; }
    }
}

namespace {
    class LoggingServiceTest extends TestCase {
        protected function setUp(): void {
            $GLOBALS['ls_actions'] = [];
            $GLOBALS['ls_errors'] = [];
            $GLOBALS['ls_base'] = sys_get_temp_dir() . '/ls_' . uniqid();
            mkdir($GLOBALS['ls_base']);
        }

        protected function tearDown(): void {
            $base = $GLOBALS['ls_base'];
            foreach (glob("$base/*") as $file) {
                @unlink($file);
            }
            @rmdir($base);
        }

        public function test_unwritable_directory_triggers_fallback(): void {
            chmod($GLOBALS['ls_base'], 0555);
            LoggingService::log('test message');
            $this->assertSame(['test message'], $GLOBALS['ls_errors']);
            $this->assertSame('admin_notices', $GLOBALS['ls_actions'][0][0]);
        }

        public function test_logs_message_to_file_when_writable(): void {
            LoggingService::log('hello world');
            $info = LoggingService::get_log_file_info();
            $this->assertFileExists($info['path']);
            $contents = file_get_contents($info['path']);
            $this->assertStringContainsString('hello world', $contents);
        }

        public function test_debug_logs_only_when_constant_defined(): void {
            LoggingService::debug('no constant');
            $info = LoggingService::get_log_file_info();
            $this->assertFileDoesNotExist($info['path']);

            if (!defined('WP_DEBUG')) {
                define('WP_DEBUG', true);
            }
            LoggingService::debug('debug message');
            $this->assertFileExists($info['path']);
            $contents = file_get_contents($info['path']);
            $this->assertStringContainsString('debug message', $contents);
        }
    }
}
