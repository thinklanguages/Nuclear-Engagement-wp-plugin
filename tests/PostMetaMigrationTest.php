<?php
namespace NuclearEngagement\Services {
	// Stub LoggingService to capture log messages
	class LoggingService {
		public static array $logs = [];
		public static function log(string $msg): void {
			self::$logs[] = $msg;
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\LoggingService;

	class PostMetaMigrationTest extends TestCase {
		protected function setUp(): void {
			global $wpdb, $wp_options, $wp_autoload;
			$wp_options = $wp_autoload = [];
			$wpdb = new class {
				public string $last_error = '';
				public string $postmeta = 'wp_postmeta';
				public int $queries = 0;
				public function prepare($q, ...$args) { return $q; }
				public function query($sql) {
					$this->queries++;
					$this->last_error = 'fail';
					return false;
				}
			};
			LoggingService::$logs = [];
		}

		private function load_bootstrap(): void {
			if (!defined('NUCLEN_PLUGIN_FILE')) {
				define('NUCLEN_PLUGIN_FILE', dirname(__DIR__) . '/nuclear-engagement/nuclear-engagement.php');
			}
			// Stub classes used when bootstrap runs
			if (!class_exists('NuclearEngagement\\MetaRegistration')) {
				class_alias(\stdClass::class, 'NuclearEngagement\\MetaRegistration');
			}
			if (!class_exists('NuclearEngagement\\Plugin')) {
				class_alias(\stdClass::class, 'NuclearEngagement\\Plugin');
			}
			require_once dirname(__DIR__) . '/nuclear-engagement/bootstrap.php';
		}

		public function test_logs_error_and_sets_option_on_failure(): void {
			$this->load_bootstrap();
			$installer = new \NuclearEngagement\Core\Installer();
			$installer->migrate_post_meta();
			$this->assertNotEmpty(LoggingService::$logs);
			$this->assertSame('fail', get_option('nuclen_meta_migration_error'));
			$this->assertFalse(get_option('nuclen_meta_migration_done'));
		}
	}
}

