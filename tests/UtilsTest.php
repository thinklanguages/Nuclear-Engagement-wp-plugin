<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Utils;

namespace NuclearEngagement {
    function wp_upload_dir() {
        return [
            'basedir' => $GLOBALS['ut_base'],
            'baseurl'  => 'http://example.com/uploads',
        ];
    }
    function wp_mkdir_p($dir) {
        return mkdir($dir, 0777, true);
    }
    function get_option($name, $default = '') {
        return $GLOBALS['ut_options'][$name] ?? $default;
    }
}

namespace {
    class UtilsTest extends TestCase {
        protected function setUp(): void {
            $GLOBALS['ut_base'] = sys_get_temp_dir() . '/ut_' . uniqid();
            $GLOBALS['ut_options'] = [];
            if (file_exists($GLOBALS['ut_base'])) {
                // clean leftover
                @unlink($GLOBALS['ut_base']);
            }
        }

        protected function tearDown(): void {
            $base = $GLOBALS['ut_base'];
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
            $dir = $GLOBALS['ut_base'] . '/nuclear-engagement';
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
            $dir = $GLOBALS['ut_base'] . '/nuclear-engagement';
            mkdir($dir, 0777, true);
            $file = $dir . '/nuclen-theme-custom.css';
            file_put_contents($file, 'body{}');
            $GLOBALS['ut_options']['nuclen_custom_css_version'] = 'abc123';
            $info = Utils::nuclen_get_custom_css_info();
            $this->assertStringContainsString('?v=abc123', $info['url']);
        }
    }
}
