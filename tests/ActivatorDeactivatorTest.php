<?php
namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Core\Activator;
    use NuclearEngagement\Core\Deactivator;
    use NuclearEngagement\Services\AutoGenerationService;
    if (!defined('NUCLEN_PLUGIN_DIR')) {
        define('NUCLEN_PLUGIN_DIR', dirname(__DIR__) . '/nuclear-engagement/');
    }
    if (!defined('NUCLEN_PLUGIN_VERSION')) { define('NUCLEN_PLUGIN_VERSION', '1.0'); }
    if (!defined('NUCLEN_ASSET_VERSION')) { define('NUCLEN_ASSET_VERSION', 'dev'); }
    if (!defined('NUCLEN_ACTIVATION_REDIRECT_TTL')) { define('NUCLEN_ACTIVATION_REDIRECT_TTL', 30); }

    // Globals used by stubs
    $GLOBALS['transients'] = $GLOBALS['wp_options'] = $GLOBALS['wp_autoload'] = [];
    $GLOBALS['update_option_calls'] = [];
    $GLOBALS['cleared_hooks'] = [];

    // WordPress function stubs
    if (!function_exists('set_transient')) {
        function set_transient($key, $value, $ttl = 0) {
            $GLOBALS['transients'][$key] = $value;
        }
    }
    if (!function_exists('get_transient')) {
        function get_transient($key) {
            return $GLOBALS['transients'][$key] ?? false;
        }
    }
    if (!function_exists('delete_transient')) {
        function delete_transient($key) {
            unset($GLOBALS['transients'][$key]);
        }
    }
    if (!function_exists('update_option')) {
        function update_option($name, $value, $autoload = 'yes') {
            $GLOBALS['update_option_calls'][$name] = ($GLOBALS['update_option_calls'][$name] ?? 0) + 1;
            $GLOBALS['wp_options'][$name] = $value;
            $GLOBALS['wp_autoload'][$name] = $autoload;
            return true;
        }
    }
    if (!function_exists('get_option')) {
        function get_option($name, $default = false) {
            return $GLOBALS['wp_options'][$name] ?? $default;
        }
    }
    if (!function_exists('delete_option')) {
        function delete_option($name) {
            unset($GLOBALS['wp_options'][$name]);
            return true;
        }
    }
    if (!function_exists('wp_clear_scheduled_hook')) {
        function wp_clear_scheduled_hook($hook) {
            $GLOBALS['cleared_hooks'][] = $hook;
        }
    }

    // wpdb stub
    class AD_WPDB {
        public string $postmeta = 'wp_postmeta';
        public string $prefix = 'wp_';
        public array $queries = [];
        public function prepare($query, ...$args) {
            foreach ($args as $a) {
                $query = preg_replace('/%s/', $a, $query, 1);
            }
            return $query;
        }
        public function get_var($sql) {
            if (strpos($sql, 'SHOW TABLES') !== false) {
                return $this->prefix . 'nuclen_optins';
            }
            return null;
        }
        public function query($sql) {
            $this->queries[] = $sql;
        }
        public function get_charset_collate() { return ''; }
    }

    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Defaults.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsRepository.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/OptinData.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/AssetVersions.php';
    require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Activator.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Deactivator.php';
}

namespace {
use PHPUnit\Framework\TestCase;

class ActivatorDeactivatorTest extends TestCase {
    protected function setUp(): void {
        global $wpdb, $wp_options, $wp_autoload, $transients, $update_option_calls, $cleared_hooks;
        $wpdb = new AD_WPDB();
        $wp_options = $wp_autoload = $transients = $update_option_calls = [];
        $cleared_hooks = [];
        \NuclearEngagement\Core\SettingsRepository::reset_for_tests();
    }

    public function test_activation_creates_indexes_and_sets_options(): void {
        global $wpdb, $wp_options, $transients, $update_option_calls;
        Activator::nuclen_activate();
        $this->assertTrue($transients['nuclen_plugin_activation_redirect']);
        $this->assertArrayHasKey('nuclear_engagement_setup', $wp_options);
        $this->assertSame(1, $update_option_calls['nuclear_engagement_setup'] ?? 0);
        $this->assertCount(4, $wpdb->queries);
        $this->assertStringContainsString('nuclen_quiz_data_idx', $wpdb->queries[0]);
    }

    public function test_deactivation_clears_hooks_and_options(): void {
        global $wp_options, $transients, $cleared_hooks;
        $wp_options['nuclen_active_generations'] = ['x'];
        $transients['nuclen_plugin_activation_redirect'] = true;
        Deactivator::nuclen_deactivate();
        $this->assertArrayNotHasKey('nuclen_active_generations', $wp_options);
        $this->assertArrayNotHasKey('nuclen_plugin_activation_redirect', $transients);
        $expected = [
            AutoGenerationService::START_HOOK,
            AutoGenerationService::QUEUE_HOOK,
            'nuclen_poll_generation',
        ];
        $this->assertSame($expected, $cleared_hooks);
    }
}
}
