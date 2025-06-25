<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Utils;

namespace NuclearEngagement {
    function get_option($name, $default = '') {
        return $GLOBALS['ut_options'][$name] ?? $default;
    }
}

namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static function log(string $msg): void { self::$logs[] = $msg; }
    }
}

namespace {
    class UtilsTest extends TestCase {
        protected function setUp(): void {
            $GLOBALS['test_upload_basedir'] = sys_get_temp_dir() . '/ut_' . uniqid();
            $GLOBALS['ut_options'] = [];
            if (file_exists($GLOBALS['test_upload_basedir'])) {
                // clean leftover
                @unlink($GLOBALS['test_upload_basedir']);
            }
        }

        protected function tearDown(): void {
            $base = $GLOBALS['test_upload_basedir'];
            if (is_dir($base)) {
                array_map('unlink', glob("$base/*"));
                rmdir($base);
            }
        }

        public function test_directory_created_if_missing(): void {
            $info = Utils::nuclen_get_custom_css_info();
            $this->assertDirectoryExists($info['dir']);
            $this->assertSame($info['path'], $info['dir'] . '/nuclen-theme-custom.css');
        }

        public function test_version_generated_when_option_empty(): void {
            $dir = $GLOBALS['test_upload_basedir'] . '/nuclear-engagement';
            mkdir($dir, 0777, true);
            $file = $dir . '/nuclen-theme-custom.css';
            file_put_contents($file, 'body{}');
            $info = Utils::nuclen_get_custom_css_info();
            $file_mtime = filemtime($file);
            $hash = md5_file($file);
            $version = $file_mtime . '-' . substr($hash, 0, 8);
            $this->assertStringContainsString('?v=' . $version, $info['url']);
        }

        public function test_version_from_option_used_when_set(): void {
            $dir = $GLOBALS['test_upload_basedir'] . '/nuclear-engagement';
            mkdir($dir, 0777, true);
            $file = $dir . '/nuclen-theme-custom.css';
            file_put_contents($file, 'body{}');
            $GLOBALS['ut_options']['nuclen_custom_css_version'] = 'abc123';
            $info = Utils::nuclen_get_custom_css_info();
            $this->assertStringContainsString('?v=abc123', $info['url']);
        }

        public function test_returns_empty_array_on_directory_failure(): void {
            $GLOBALS['test_wp_mkdir_p_failure'] = true;
            $info = Utils::nuclen_get_custom_css_info();
            $this->assertSame([], $info);
            $this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$logs);
            unset($GLOBALS['test_wp_mkdir_p_failure']);
        }

        public function test_returns_empty_array_on_upload_dir_error(): void {
            $GLOBALS['test_upload_error'] = 'fail';
            $info = Utils::nuclen_get_custom_css_info();
            $this->assertSame([], $info);
            $this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$logs);
            unset($GLOBALS['test_upload_error']);
        }
    }
}
