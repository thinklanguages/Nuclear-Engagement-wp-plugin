<?php
use PHPUnit\Framework\TestCase;

if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', true);
}

if (!function_exists('delete_post_meta_by_key')) {
    function delete_post_meta_by_key($key): void {
        $GLOBALS['deleted_meta_keys'][] = $key;
        foreach ($GLOBALS['wp_meta'] as &$post_meta) {
            unset($post_meta[$key]);
        }
    }
}

if (!function_exists('wp_delete_file')) {
    function wp_delete_file(string $path): void {
        $GLOBALS['deleted_files'][] = $path;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql($sql) { return $sql; }
}

class DummyWPDB {
    public string $prefix = 'wp_';
    public array $queries = [];
    public function query($sql) { $this->queries[] = $sql; }
}

class UninstallTest extends TestCase {
    private string $baseDir;
    private string $pluginDir;

    protected function setUp(): void {
        global $wp_options, $wp_meta, $wpdb;
        $this->baseDir = sys_get_temp_dir() . '/uninstall_' . uniqid();
        mkdir($this->baseDir, 0777, true);
        $GLOBALS['test_upload_basedir'] = $this->baseDir;
        $this->pluginDir = sys_get_temp_dir() . '/un_plugin_' . uniqid();
        if (!defined('NUCLEN_PLUGIN_DIR')) {
            define('NUCLEN_PLUGIN_DIR', $this->pluginDir . '/');
        }
        mkdir($this->pluginDir, 0777, true);

        $wp_options = [
            'nuclear_engagement_settings' => [
                'delete_settings_on_uninstall' => true,
                'delete_generated_content_on_uninstall' => true,
                'delete_optin_data_on_uninstall' => true,
                'delete_log_file_on_uninstall' => true,
                'delete_custom_css_on_uninstall' => true,
            ],
            'nuclear_engagement_setup' => ['installed' => true],
            'nuclen_custom_css_version' => 'v1',
        ];

        $wp_meta = [
            1 => [
                'nuclen-quiz-data' => 'q',
                'nuclen_quiz_protected' => '1',
            ],
            2 => [
                'nuclen-summary-data' => 's',
            ],
        ];

        $logDir = $this->baseDir . '/nuclear-engagement';
        mkdir($logDir, 0777, true);
        file_put_contents($logDir . '/log.txt', 'log');
        file_put_contents($logDir . '/nuclen-theme-custom.css', 'css');

        $GLOBALS['deleted_files'] = [];
        $GLOBALS['deleted_meta_keys'] = [];

        $wpdb = new DummyWPDB();
    }

    protected function tearDown(): void {
        $this->deleteDir($this->baseDir);
        $this->deleteDir($this->pluginDir);
        unset($GLOBALS['test_upload_basedir']);
        unset($GLOBALS['deleted_files'], $GLOBALS['deleted_meta_keys']);
    }

    private function deleteDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') as $file) {
            if (is_dir($file)) {
                $this->deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dir);
    }

    public function test_uninstall_removes_all_data(): void {
        require __DIR__ . '/../nuclear-engagement/uninstall.php';

        global $wp_options, $wp_meta;

        $this->assertArrayNotHasKey('nuclear_engagement_settings', $wp_options);
        $this->assertArrayNotHasKey('nuclear_engagement_setup', $wp_options);
        $this->assertArrayNotHasKey('nuclen_custom_css_version', $wp_options);

        $this->assertEmpty($wp_meta[1]);
        $this->assertEmpty($wp_meta[2]);

        $this->assertFileDoesNotExist($this->baseDir . '/nuclear-engagement/log.txt');
        $this->assertFileDoesNotExist($this->baseDir . '/nuclear-engagement/nuclen-theme-custom.css');

        $this->assertSame(['nuclen-quiz-data', 'nuclen-summary-data', 'nuclen_quiz_protected', 'nuclen_summary_protected'], $GLOBALS['deleted_meta_keys']);
        $expected_files = [
            $this->baseDir . '/nuclear-engagement/log.txt',
            $this->baseDir . '/nuclear-engagement/nuclen-theme-custom.css',
        ];
        $this->assertSame($expected_files, $GLOBALS['deleted_files']);
    }
}
