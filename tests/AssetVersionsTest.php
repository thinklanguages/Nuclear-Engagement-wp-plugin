<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\AssetVersions;

namespace NuclearEngagement {
    function time() {
        return $GLOBALS['av_now'] ?? \time();
    }
}

namespace {
    class AssetVersionsTest extends TestCase {
        private static string $dir;

        public static function setUpBeforeClass(): void {
            self::$dir = sys_get_temp_dir() . '/av_' . uniqid();
            if (!defined('NUCLEN_PLUGIN_DIR')) {
                define('NUCLEN_PLUGIN_DIR', self::$dir . '/');
            }
            if (!defined('NUCLEN_PLUGIN_VERSION')) {
                define('NUCLEN_PLUGIN_VERSION', '1.0');
            }
            if (!defined('NUCLEN_ASSET_VERSION')) {
                define('NUCLEN_ASSET_VERSION', 'default');
            }
            require_once dirname(__DIR__) . '/nuclear-engagement/includes/AssetVersions.php';
        }

        protected function setUp(): void {
            global $wp_options, $wp_autoload;
            $wp_options = $wp_autoload = [];
            $this->cleanDir(self::$dir);
            $dirs = [
                'admin/css',
                'admin/js',
                'front/css',
                'front/js',
                'modules/toc/assets/css',
                'modules/toc/assets/js',
            ];
            foreach ($dirs as $d) {
                @mkdir(self::$dir . '/' . $d, 0777, true);
            }
        }

        protected function tearDown(): void {
            $this->cleanDir(self::$dir);
            unset($GLOBALS['av_now']);
        }

        private function cleanDir(string $dir): void {
            if (!is_dir($dir)) {
                return;
            }
            $items = scandir($dir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . '/' . $item;
                if (is_dir($path)) {
                    $this->cleanDir($path);
                } else {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        }

        public function test_get_returns_default_version_when_option_missing(): void {
            $GLOBALS['av_now'] = 42;
            $this->assertSame('default', AssetVersions::get('admin_css'));
        }

        public function test_update_versions_computes_versions_based_on_files(): void {
            $file1 = self::$dir . '/admin/css/nuclen-admin.css';
            $file2 = self::$dir . '/front/js/nuclen-front.js';
            file_put_contents($file1, '');
            file_put_contents($file2, '');
            touch($file1, 123);
            touch($file2, 456);
            $GLOBALS['av_now'] = 999;
            AssetVersions::update_versions();
            $this->assertSame('123', AssetVersions::get('admin_css'));
            $this->assertSame('456', AssetVersions::get('front_js'));
            $this->assertSame('999', AssetVersions::get('theme_bright_css'));
        }

        public function test_init_updates_versions_when_version_changes(): void {
            global $wp_options;
            $wp_options['nuclen_asset_versions_build'] = 'old';
            $GLOBALS['av_now'] = 5;
            AssetVersions::init();
            $this->assertSame('1.0', $wp_options['nuclen_asset_versions_build']);
            $this->assertNotEmpty($wp_options['nuclen_asset_versions']);
        }
    }
}
