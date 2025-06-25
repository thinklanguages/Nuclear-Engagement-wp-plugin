<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\LoggingService;

namespace NuclearEngagement\Services {
    function add_action(...$args) {
        $GLOBALS['ls_actions'][] = $args;
    }
    function file_put_contents($file, $data, $flags = 0) {
        $GLOBALS['ls_puts'][] = $file;
        return \file_put_contents($file, $data, $flags);
    }
    function register_shutdown_function($cb) {
        $GLOBALS['ls_shutdown'][] = $cb;
    }
    function error_log($msg) {
        $GLOBALS['ls_errors'][] = $msg;
    }
    function rename($from, $to) {
        if (!empty($GLOBALS['ls_rename_fail'])) {
            return false;
        }
        return \rename($from, $to);
    }
    if (!function_exists('apply_filters')) {
        function apply_filters($hook, $value) {
            if ($hook === 'nuclen_enable_log_buffer' && isset($GLOBALS['ls_filter_buffer'])) {
                return $GLOBALS['ls_filter_buffer'];
            }
            return $value;
        }
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
            $GLOBALS['ls_puts'] = [];
            $GLOBALS['ls_shutdown'] = [];
            $GLOBALS['test_upload_basedir'] = sys_get_temp_dir() . '/ls_' . uniqid();
            mkdir($GLOBALS['test_upload_basedir']);
            $GLOBALS['ls_filter_buffer'] = false;
            $GLOBALS['ls_rename_fail'] = false;
        }

        protected function tearDown(): void {
            LoggingService::flush();
            foreach ($GLOBALS['ls_shutdown'] as $cb) {
                $cb();
            }
            unset($GLOBALS['ls_filter_buffer']);
            unset($GLOBALS['ls_rename_fail']);
            $base = $GLOBALS['test_upload_basedir'];
            foreach (glob("$base/*") as $file) {
                @unlink($file);
            }
            @rmdir($base);
        }

        public function test_unwritable_directory_triggers_fallback(): void {
            chmod($GLOBALS['test_upload_basedir'], 0555);
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
            $this->assertStringContainsString('[DEBUG] debug message', $contents);
        }

        public function test_logs_strip_html_and_truncate_long_message(): void {
            $long = '<p>' . str_repeat('a', 1005) . '</p>';
            LoggingService::log($long);
            $info = LoggingService::get_log_file_info();
            $this->assertFileExists($info['path']);
            $contents = file_get_contents($info['path']);
            $expected = str_repeat('a', 1000) . '...';
            $this->assertStringContainsString($expected, $contents);
            $this->assertStringNotContainsString('<p>', $contents);
        }

        public function test_buffered_logging_single_write(): void {
            $GLOBALS['ls_filter_buffer'] = true;
            for ($i = 0; $i < 5; $i++) {
                LoggingService::log("msg $i");
            }
            $info = LoggingService::get_log_file_info();
            $this->assertFileDoesNotExist($info['path']);

            LoggingService::flush();

            $this->assertFileExists($info['path']);
            $this->assertCount(1, $GLOBALS['ls_puts']);
            $contents = file_get_contents($info['path']);
            $this->assertStringContainsString('msg 4', $contents);
        }

        public function test_rotation_failure_triggers_fallback(): void {
            if (!defined('NUCLEN_LOG_FILE_MAX_SIZE')) {
                define('NUCLEN_LOG_FILE_MAX_SIZE', 1);
            }
            $info = LoggingService::get_log_file_info();
            if (!file_exists($info['dir'])) {
                mkdir($info['dir'], 0777, true);
            }
            file_put_contents($info['path'], 'aa');
            $GLOBALS['ls_rename_fail'] = true;

            LoggingService::log('rotate');

            $this->assertNotEmpty($GLOBALS['ls_errors']);
            $this->assertStringContainsString('Failed to rotate log file', $GLOBALS['ls_errors'][0]);
        }
    }
}
