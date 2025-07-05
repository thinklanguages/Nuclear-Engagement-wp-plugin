<?php
namespace NuclearEngagement\Core {
	if (!function_exists(__NAMESPACE__ . '\set_transient')) {
		function set_transient($name, $value, $expiration) {
			$GLOBALS['wp_transients'][$name] = ['value' => $value, 'expiration' => $expiration];
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\get_option')) {
		function get_option($name, $default = false) {
			return $GLOBALS['wp_options'][$name] ?? $default;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\update_option')) {
		function update_option($name, $value) {
			$GLOBALS['wp_options'][$name] = $value;
			return true;
		}
	}
}

namespace {
	// Define required constants
	if (!defined('NUCLEN_ACTIVATION_REDIRECT_TTL')) {
		define('NUCLEN_ACTIVATION_REDIRECT_TTL', 30);
	}
	if (!defined('NUCLEN_PLUGIN_DIR')) {
		define('NUCLEN_PLUGIN_DIR', dirname(__DIR__) . '/nuclear-engagement');
	}
	if (!defined('NUCLEN_PLUGIN_VERSION')) {
		define('NUCLEN_PLUGIN_VERSION', '1.0.0');
	}
	
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Core\Activator;
	use NuclearEngagement\Core\SettingsRepository;
	use NuclearEngagement\Core\Defaults;
	use NuclearEngagement\Core\AssetVersions;
	use NuclearEngagement\OptinData;

	class ActivatorTest extends TestCase {
		protected function setUp(): void {
			global $wp_options, $wp_transients, $wpdb;
			$wp_options = $wp_transients = [];
			
			// Mock wpdb for index creation
			$wpdb = new class {
				public $postmeta = 'wp_postmeta';
				public $prefix = 'wp_';
				public $last_error = '';
				private $get_var_return = null;
				private $query_return = true;
				private $query_calls = [];
				
				public function get_var($query) {
					return $this->get_var_return;
				}
				
				public function prepare($query, ...$args) {
					return $query;
				}
				
				public function query($sql) {
					$this->query_calls[] = $sql;
					return $this->query_return;
				}
				
				public function set_get_var_return($value) {
					$this->get_var_return = $value;
				}
				
				public function set_query_return($value) {
					$this->query_return = $value;
				}
				
				public function get_query_calls() {
					return $this->query_calls;
				}
				
				public function get_charset_collate() {
					return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
				}
			};
			
			// Reset static states
			SettingsRepository::reset_for_tests();
			
			// Reset OptinData if it's our mock
			if (property_exists(OptinData::class, 'table_exists_calls')) {
				OptinData::$table_exists_calls = 0;
				OptinData::$create_table_calls = 0;
				OptinData::$table_exists = false;
				OptinData::$create_result = true;
			}
			
			// Reset AssetVersions if it's our mock
			if (property_exists(AssetVersions::class, 'update_calls')) {
				AssetVersions::$update_calls = 0;
			}
		}

		public function test_nuclen_activate_sets_activation_redirect_transient(): void {
			Activator::nuclen_activate();
			
			$this->assertArrayHasKey('nuclen_plugin_activation_redirect', $GLOBALS['wp_transients']);
			$this->assertTrue($GLOBALS['wp_transients']['nuclen_plugin_activation_redirect']['value']);
			$this->assertEquals(NUCLEN_ACTIVATION_REDIRECT_TTL, $GLOBALS['wp_transients']['nuclen_plugin_activation_redirect']['expiration']);
		}

		public function test_nuclen_activate_sets_default_settings_when_not_exists(): void {
			$default_settings = Defaults::nuclen_get_default_settings();
			
			Activator::nuclen_activate();
			
			$this->assertArrayHasKey('nuclear_engagement_setup', $GLOBALS['wp_options']);
			$this->assertEquals($default_settings, $GLOBALS['wp_options']['nuclear_engagement_setup']);
		}

		public function test_nuclen_activate_does_not_override_existing_setup_option(): void {
			$existing_settings = ['custom' => 'value'];
			$GLOBALS['wp_options']['nuclear_engagement_setup'] = $existing_settings;
			
			Activator::nuclen_activate();
			
			$this->assertEquals($existing_settings, $GLOBALS['wp_options']['nuclear_engagement_setup']);
		}

		public function test_nuclen_activate_creates_optin_table(): void {
			// Skip this test if we're using the real OptinData class
			if (!property_exists(OptinData::class, 'create_table_calls')) {
				$this->markTestSkipped('This test requires the OptinData mock');
			}
			
			Activator::nuclen_activate();
			
			$this->assertGreaterThan(0, OptinData::$create_table_calls);
		}

		public function test_nuclen_activate_creates_postmeta_indexes(): void {
			global $wpdb;
			
			Activator::nuclen_activate();
			
			$query_calls = $wpdb->get_query_calls();
			
			// Verify all expected indexes were created
			$this->assertCount(4, $query_calls);
			$this->assertStringContainsString('CREATE INDEX nuclen_quiz_data_idx', $query_calls[0]);
			$this->assertStringContainsString('CREATE INDEX nuclen_summary_data_idx', $query_calls[1]);
			$this->assertStringContainsString('CREATE INDEX nuclen_quiz_protected_idx', $query_calls[2]);
			$this->assertStringContainsString('CREATE INDEX nuclen_summary_protected_idx', $query_calls[3]);
		}

		public function test_nuclen_activate_skips_existing_indexes(): void {
			global $wpdb;
			
			// Create a custom mock that returns different values for each call
			$wpdb = new class extends stdClass {
				public $postmeta = 'wp_postmeta';
				public $prefix = 'wp_';
				public $last_error = '';
				private $get_var_calls = 0;
				private $query_calls = 0;
				
				public function get_var($query) {
					$this->get_var_calls++;
					// First and third indexes exist
					return in_array($this->get_var_calls, [1, 3]) ? 'existing_index' : null;
				}
				
				public function prepare($query, ...$args) {
					return $query;
				}
				
				public function query($sql) {
					$this->query_calls++;
					return true;
				}
				
				public function get_query_count() {
					return $this->query_calls;
				}
				
				public function get_charset_collate() {
					return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
				}
			};
			
			Activator::nuclen_activate();
			
			// Only 2 indexes should be created (the ones that don't exist)
			$this->assertEquals(2, $wpdb->get_query_count());
		}

		public function test_nuclen_activate_updates_asset_versions(): void {
			// Skip this test if we're using the real AssetVersions class
			if (!property_exists(AssetVersions::class, 'update_calls')) {
				$this->markTestSkipped('This test requires the AssetVersions mock');
			}
			
			Activator::nuclen_activate();
			
			$this->assertGreaterThan(0, AssetVersions::$update_calls);
		}

		public function test_nuclen_activate_with_custom_settings_repository(): void {
			// Since SettingsRepository might be final, create a concrete instance instead
			$custom_settings = SettingsRepository::get_instance();
			
			Activator::nuclen_activate($custom_settings);
			
			// Verify the method completes without errors
			$this->assertArrayHasKey('nuclen_plugin_activation_redirect', $GLOBALS['wp_transients']);
		}
	}
}

// Mock classes that don't exist in test environment
namespace NuclearEngagement {
	if (!class_exists(__NAMESPACE__ . '\OptinData')) {
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

namespace NuclearEngagement\Core {
	if (!class_exists(__NAMESPACE__ . '\AssetVersions')) {
		class AssetVersions {
			public static int $update_calls = 0;
			public static function update_versions(): void {
				self::$update_calls++;
			}
		}
	}
	
	if (!class_exists(__NAMESPACE__ . '\Defaults')) {
		class Defaults {
			public static function nuclen_get_default_settings(): array {
				return [
					'api_key' => '',
					'enable_quiz' => true,
					'enable_summary' => true,
					'quiz_position' => 'after',
					'summary_position' => 'before'
				];
			}
		}
	}
	
	if (!class_exists(__NAMESPACE__ . '\SettingsRepository')) {
		class SettingsRepository {
			private static $instance = null;
			private $cache = [];
			
			public static function get_instance($defaults = []): self {
				if (self::$instance === null) {
					self::$instance = new self();
				}
				return self::$instance;
			}
			
			public static function reset_for_tests(): void {
				self::$instance = null;
			}
			
			public function clear_cache(): void {
				$this->cache = [];
			}
		}
	}
}

namespace NuclearEngagement\Modules\Summary {
	if (!class_exists(__NAMESPACE__ . '\Summary_Service')) {
		class Summary_Service {
			const META_KEY = 'nuclen-summary-data';
			const PROTECTED_KEY = 'nuclen_summary_protected';
		}
	}
}