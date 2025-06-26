<?php
namespace BootstrapTestNS {
    $GLOBALS['test_actions'] = [];
    $GLOBALS['test_activation'] = [];
    $GLOBALS['test_deactivation'] = [];

    function add_action(...$args) {
        $GLOBALS['test_actions'][] = $args;
    }
    function register_activation_hook(...$args) {
        $GLOBALS['test_activation'][] = $args;
    }
    function register_deactivation_hook(...$args) {
        $GLOBALS['test_deactivation'][] = $args;
    }
    if (!function_exists('__')) {
        function __($text, $domain = null) { return $text; }
    }
    if (!function_exists('get_file_data')) {
        function get_file_data($file, $keys, $type = null) { return ['Version' => '1.0']; }
    }
    if (!defined('NUCLEN_PLUGIN_FILE')) {
        define('NUCLEN_PLUGIN_FILE', dirname(__DIR__) . '/nuclear-engagement/nuclear-engagement.php');
    }
    require dirname(__DIR__) . '/nuclear-engagement/bootstrap.php';
}

namespace {
    use PHPUnit\Framework\TestCase;

    class BootstrapTest extends TestCase {
        public function test_hooks_registered(): void {
            $found_plugins_loaded = false;
            $found_init = false;
            foreach ($GLOBALS['test_actions'] as $args) {
                if ($args[0] === 'plugins_loaded' && $args[1] === 'nuclear_engagement_init') {
                    $found_plugins_loaded = true;
                }
                if ($args[0] === 'init' && $args[1] === 'nuclear_engagement_load_textdomain') {
                    $found_init = true;
                }
            }
            $this->assertTrue($found_plugins_loaded, 'plugins_loaded hook not registered');
            $this->assertTrue($found_init, 'init hook not registered');
            $this->assertNotEmpty($GLOBALS['test_activation']);
            $this->assertNotEmpty($GLOBALS['test_deactivation']);
            $this->assertSame('nuclear_engagement_activate_plugin', $GLOBALS['test_activation'][0][1]);
            $this->assertSame('nuclear_engagement_deactivate_plugin', $GLOBALS['test_deactivation'][0][1]);
            $this->assertSame(NUCLEN_PLUGIN_FILE, $GLOBALS['test_activation'][0][0]);
            $this->assertSame(NUCLEN_PLUGIN_FILE, $GLOBALS['test_deactivation'][0][0]);
        }
    }
}
