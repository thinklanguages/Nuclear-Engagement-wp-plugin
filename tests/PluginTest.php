<?php
namespace NuclearEngagement\Core {
	if (!function_exists('NuclearEngagement\\Core\\register_activation_hook')) {
		function register_activation_hook($file, $callback) {
			$GLOBALS['ph_activation'][] = [$file, $callback];
		}
	}
	if (!function_exists('NuclearEngagement\\Core\\add_action')) {
		function add_action(...$args) { $GLOBALS['ph_actions'][] = $args; }
	}
	if (!function_exists('NuclearEngagement\\Core\\add_filter')) {
		function add_filter(...$args) { $GLOBALS['ph_filters'][] = $args; }
	}
	if (!function_exists('NuclearEngagement\\Core\\remove_action')) {
		function remove_action(...$args) { $GLOBALS['ph_removed'][] = $args; }
	}

	if (!class_exists('NuclearEngagement\\Core\\ModuleLoader')) {
		class ModuleLoader {
			public static int $calls = 0;
			public function __construct(string $base_dir = NUCLEN_PLUGIN_DIR) {}
			public function load_all(): void { self::$calls++; }
		}
	}
}

namespace NuclearEngagement\Services {
	if (!function_exists('NuclearEngagement\\Services\\add_action')) {
		function add_action(...$args) { $GLOBALS['ph_actions'][] = $args; }
	}
}

namespace NuclearEngagement {
	if (!class_exists('NuclearEngagement\\OptinData')) {
		class OptinData {
			public static int $table_exists_calls = 0;
			public static int $create_table_calls = 0;
			public static bool $table_exists = false;
			public static bool $create_result = true;
			public static function init(): void {}
			public static function table_exists(): bool {
				self::$table_exists_calls++;
				return self::$table_exists;
			}
			public static function maybe_create_table(): bool {
				self::$create_table_calls++;
				return self::$create_result;
			}
		}
	}
}

namespace NuclearEngagement\Admin {
	if (!class_exists('NuclearEngagement\\Admin\\Admin')) {
		class Admin { public function __construct(...$args) {} }
	}
	if (!class_exists('NuclearEngagement\\Admin\\Onboarding')) {
		class Onboarding { public function nuclen_register_hooks() {} }
	}
	if (!class_exists('NuclearEngagement\\Admin\\Setup')) {
		class Setup {
			public function __construct(...$args) {}
			public function nuclen_add_setup_page() {}
			public function nuclen_handle_connect_app() {}
			public function nuclen_handle_generate_app_password() {}
			public function nuclen_handle_reset_api_key() {}
			public function nuclen_handle_reset_wp_app_connection() {}
		}
	}
}

namespace NuclearEngagement\Front {
	if (!class_exists('NuclearEngagement\\Front\\FrontClass')) {
		class FrontClass {
			public function __construct(...$args) {}
			public function wp_enqueue_styles() {}
			public function wp_enqueue_scripts() {}
			public function nuclen_register_quiz_shortcode() {}
			public function nuclen_register_summary_shortcode() {}
			public function nuclen_auto_insert_shortcodes() {}
		}
	}
}

namespace NuclearEngagement {
	if (!class_exists('NuclearEngagement\\Blocks')) {
		class Blocks { public static function register() {} }
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Core\Plugin;
	use NuclearEngagement\Core\Container;
	use NuclearEngagement\Core\SettingsRepository;
	use NuclearEngagement\Core\Defaults;
	if (!defined('NUCLEN_PLUGIN_DIR')) {
		define('NUCLEN_PLUGIN_DIR', dirname(__DIR__) . '/nuclear-engagement/');
	}

	spl_autoload_register(static function ($class) {
		$prefix = 'NuclearEngagement\\';
		if (strpos($class, $prefix) !== 0) {
			return;
		}
		$relative = str_replace('\\', '/', substr($class, strlen($prefix)));
		$paths = [];
		$paths[] = NUCLEN_PLUGIN_DIR . $relative . '.php';
		$segments = explode('/', $relative);
		if (in_array($segments[0], ['Admin','Front'], true)) {
			$segments[0] = strtolower($segments[0]);
			$paths[] = NUCLEN_PLUGIN_DIR . implode('/', $segments) . '.php';
			if (isset($segments[1])) {
				$paths[] = NUCLEN_PLUGIN_DIR . $segments[0] . '/traits/' . $segments[1] . '.php';
			}
		}
		$paths[] = NUCLEN_PLUGIN_DIR . 'inc/' . $relative . '.php';
		$paths[] = NUCLEN_PLUGIN_DIR . 'inc/Core/' . $relative . '.php';
		foreach ($paths as $file) {
			if (file_exists($file)) {
				require_once $file;
				return;
			}
		}
	});

	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/ServiceContainer.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsRepository.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Defaults.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/ContainerRegistrar.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Loader.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Plugin.php';

	class PluginTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['ph_actions'] = [];
			$GLOBALS['ph_filters'] = [];
			$GLOBALS['ph_activation'] = [];
			$GLOBALS['ph_removed'] = [];
			\NuclearEngagement\Services\LoggingService::$notices = [];
			\NuclearEngagement\OptinData::$table_exists_calls = 0;
			\NuclearEngagement\OptinData::$create_table_calls = 0;
			\NuclearEngagement\OptinData::$table_exists = false;
			\NuclearEngagement\OptinData::$create_result = true;
			SettingsRepository::reset_for_tests();
			Container::getInstance()->reset();
		}

		public function test_plugin_initializes_and_registers_hooks(): void {
			$plugin = new Plugin();

			$repo = $plugin->nuclen_get_settings_repository();
			$this->assertSame(Defaults::nuclen_get_default_settings(), $repo->get_defaults());

			$container = $plugin->get_container();
			$this->assertTrue($container->has('settings'));
			$this->assertSame($repo, $container->get('settings'));

			$loader = $plugin->nuclen_get_loader();
			$ref = new \ReflectionProperty($loader, 'actions');
			$ref->setAccessible(true);
			$actions = $ref->getValue($loader);
			$found = false;
			foreach ($actions as $a) {
				if ($a['hook'] === 'admin_menu' && $a['callback'] === 'nuclen_add_setup_page') {
					$found = true;
				}
			}
			$this->assertTrue($found, 'admin_menu action not registered');

			$ref = new \ReflectionProperty($loader, 'filters');
			$ref->setAccessible(true);
			$filters = $ref->getValue($loader);
			$found = false;
			foreach ($filters as $f) {
				if ($f['hook'] === 'the_content' && $f['callback'] === 'nuclen_auto_insert_shortcodes' && $f['priority'] === 50) {
					$found = true;
				}
			}
			$this->assertTrue($found, 'the_content filter not registered');

			$registered_hooks = array_column($GLOBALS['ph_actions'], 0);
			$this->assertContains(\NuclearEngagement\Services\AutoGenerationService::START_HOOK, $registered_hooks);
			$this->assertContains(\NuclearEngagement\Services\AutoGenerationService::QUEUE_HOOK, $registered_hooks);
			$this->assertContains('nuclen_poll_generation', $registered_hooks);
			$this->assertContains('transition_post_status', $registered_hooks);
		}

		public function test_activation_hook_callback_executes(): void {
			$plugin = new Plugin();
			$this->assertNotEmpty($GLOBALS['ph_activation']);
			[$file, $cb] = $GLOBALS['ph_activation'][0];
			$expected = dirname(__DIR__) . '/nuclear-engagement/nuclear-engagement.php';
			$this->assertSame($expected, $file);
			$this->assertIsCallable($cb);

			\NuclearEngagement\OptinData::$table_exists = false;
			\NuclearEngagement\OptinData::$create_result = true;
			call_user_func($cb);
			$this->assertSame(1, \NuclearEngagement\OptinData::$create_table_calls);
			$this->assertEmpty(\NuclearEngagement\Services\LoggingService::$notices);
		}
	}
}
