<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Installer;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\Defaults;
use NuclearEngagement\Core\Activator;
use NuclearEngagement\Core\Deactivator;
use NuclearEngagement\Security\ApiUserManager;
use NuclearEngagement\Services\LoggingService;
use NuclearEngagement\Modules\Summary\Summary_Service;

class InstallerTest extends TestCase {

	private $installer;

	protected function setUp(): void {
		$this->installer = new Installer();
		
		// Reset global state
		$GLOBALS['wp_options'] = [];
		$GLOBALS['wp_postmeta'] = [];
		$GLOBALS['wpdb_queries'] = [];
		$GLOBALS['wpdb_last_error'] = null;
		
		// Mock LoggingService
		if (!class_exists('NuclearEngagement\Services\LoggingService')) {
			eval('
				namespace NuclearEngagement\Services {
					class LoggingService {
						public static $logs = [];
						public static function log($message) {
							self::$logs[] = $message;
						}
					}
				}
			');
		}
		
		// Mock ApiUserManager
		if (!class_exists('NuclearEngagement\Security\ApiUserManager')) {
			eval('
				namespace NuclearEngagement\Security {
					class ApiUserManager {
						public static $init_called = false;
						public static $cleanup_called = false;
						public static function init() {
							self::$init_called = true;
						}
						public static function cleanup() {
							self::$cleanup_called = true;
						}
					}
				}
			');
		}
		
		// Mock Activator
		if (!class_exists('NuclearEngagement\Core\Activator')) {
			eval('
				namespace NuclearEngagement\Core {
					class Activator {
						public static $activate_called = false;
						public static $settings_received = null;
						public static function nuclen_activate($settings) {
							self::$activate_called = true;
							self::$settings_received = $settings;
						}
					}
				}
			');
		}
		
		// Mock Deactivator
		if (!class_exists('NuclearEngagement\Core\Deactivator')) {
			eval('
				namespace NuclearEngagement\Core {
					class Deactivator {
						public static $deactivate_called = false;
						public static $settings_received = null;
						public static function nuclen_deactivate($settings) {
							self::$deactivate_called = true;
							self::$settings_received = $settings;
						}
					}
				}
			');
		}
		
		// Mock Summary_Service
		if (!class_exists('NuclearEngagement\Modules\Summary\Summary_Service')) {
			eval('
				namespace NuclearEngagement\Modules\Summary {
					class Summary_Service {
						public const META_KEY = "nuclen-summary-data";
					}
				}
			');
		}
		
		// Mock global $wpdb
		global $wpdb;
		$wpdb = new class {
			public $postmeta = 'wp_postmeta';
			public $last_error = '';
			public $queries = [];
			
			public function prepare($query, ...$args) {
				return vsprintf(str_replace('%s', "'%s'", $query), $args);
			}
			
			public function query($sql) {
				$this->queries[] = $sql;
				
				// Simulate success unless we specifically set an error
				if (strpos($sql, 'ERROR_TEST') !== false) {
					$this->last_error = 'Simulated database error';
					return false;
				}
				
				return true;
			}
		};
	}

	protected function tearDown(): void {
		// Reset static states
		if (class_exists('NuclearEngagement\Security\ApiUserManager')) {
			ApiUserManager::$init_called = false;
			ApiUserManager::$cleanup_called = false;
		}
		
		if (class_exists('NuclearEngagement\Core\Activator')) {
			Activator::$activate_called = false;
			Activator::$settings_received = null;
		}
		
		if (class_exists('NuclearEngagement\Core\Deactivator')) {
			Deactivator::$deactivate_called = false;
			Deactivator::$settings_received = null;
		}
		
		if (class_exists('NuclearEngagement\Services\LoggingService')) {
			LoggingService::$logs = [];
		}
		
		SettingsRepository::reset_for_tests();
	}

	public function test_activate_initializes_components(): void {
		$this->installer->activate();
		
		$this->assertTrue(ApiUserManager::$init_called, 'ApiUserManager::init should be called');
		$this->assertTrue(Activator::$activate_called, 'Activator::nuclen_activate should be called');
		$this->assertInstanceOf(SettingsRepository::class, Activator::$settings_received);
	}

	public function test_activate_uses_default_settings(): void {
		$this->installer->activate();
		
		$expected_defaults = Defaults::nuclen_get_default_settings();
		$received_settings = Activator::$settings_received;
		
		$this->assertEquals($expected_defaults, $received_settings->get_defaults());
	}

	public function test_deactivate_cleans_up_components(): void {
		$this->installer->deactivate();
		
		$this->assertTrue(ApiUserManager::$cleanup_called, 'ApiUserManager::cleanup should be called');
		$this->assertTrue(Deactivator::$deactivate_called, 'Deactivator::nuclen_deactivate should be called');
		$this->assertInstanceOf(SettingsRepository::class, Deactivator::$settings_received);
	}

	public function test_migrate_post_meta_skips_if_already_done(): void {
		// Set migration as already done
		update_option('nuclen_meta_migration_done', true);
		
		global $wpdb;
		$wpdb->queries = [];
		
		$this->installer->migrate_post_meta();
		
		$this->assertEmpty($wpdb->queries, 'No database queries should be executed if migration is already done');
	}

	public function test_migrate_post_meta_updates_summary_meta_key(): void {
		// Ensure migration is not marked as done
		delete_option('nuclen_meta_migration_done');
		
		global $wpdb;
		$wpdb->queries = [];
		
		$this->installer->migrate_post_meta();
		
		$this->assertCount(2, $wpdb->queries, 'Should execute 2 update queries');
		
		// Check summary meta key migration
		$expected_summary_query = sprintf(
			"UPDATE wp_postmeta SET meta_key = '%s' WHERE meta_key = '%s'",
			Summary_Service::META_KEY,
			'ne-summary-data'
		);
		$this->assertContains($expected_summary_query, $wpdb->queries);
	}

	public function test_migrate_post_meta_updates_quiz_meta_key(): void {
		// Ensure migration is not marked as done
		delete_option('nuclen_meta_migration_done');
		
		global $wpdb;
		$wpdb->queries = [];
		
		$this->installer->migrate_post_meta();
		
		// Check quiz meta key migration
		$expected_quiz_query = sprintf(
			"UPDATE wp_postmeta SET meta_key = '%s' WHERE meta_key = '%s'",
			'nuclen-quiz-data',
			'ne-quiz-data'
		);
		$this->assertContains($expected_quiz_query, $wpdb->queries);
	}

	public function test_migrate_post_meta_marks_migration_as_done(): void {
		// Ensure migration is not marked as done
		delete_option('nuclen_meta_migration_done');
		
		$this->installer->migrate_post_meta();
		
		$this->assertTrue(get_option('nuclen_meta_migration_done'), 'Migration should be marked as done');
	}

	public function test_migrate_post_meta_handles_database_errors(): void {
		// Ensure migration is not marked as done
		delete_option('nuclen_meta_migration_done');
		
		global $wpdb;
		$wpdb->queries = [];
		
		// Simulate a database error on the first query
		$wpdb->prepare = function($query, ...$args) {
			if (strpos($query, 'ne-summary-data') !== false) {
				return 'UPDATE wp_postmeta SET meta_key = \'ERROR_TEST\' WHERE meta_key = \'ne-summary-data\'';
			}
			return vsprintf(str_replace('%s', "'%s'", $query), $args);
		};
		
		$this->installer->migrate_post_meta();
		
		$this->assertNotEmpty(LoggingService::$logs, 'Error should be logged');
		$this->assertNotEmpty(get_option('nuclen_meta_migration_error'), 'Error should be stored in options');
		$this->assertFalse(get_option('nuclen_meta_migration_done'), 'Migration should not be marked as done on error');
	}

	public function test_migrate_post_meta_cleans_up_error_option_on_success(): void {
		// Set up existing error option
		update_option('nuclen_meta_migration_error', 'Previous error');
		delete_option('nuclen_meta_migration_done');
		
		$this->installer->migrate_post_meta();
		
		$this->assertFalse(get_option('nuclen_meta_migration_error'), 'Error option should be cleaned up on success');
		$this->assertTrue(get_option('nuclen_meta_migration_done'), 'Migration should be marked as done');
	}

	public function test_migrate_post_meta_stops_on_first_error(): void {
		// Ensure migration is not marked as done
		delete_option('nuclen_meta_migration_done');
		
		global $wpdb;
		$wpdb->queries = [];
		
		// Simulate a database error on the first query
		$wpdb->prepare = function($query, ...$args) {
			if (strpos($query, 'ne-summary-data') !== false) {
				return 'UPDATE wp_postmeta SET meta_key = \'ERROR_TEST\' WHERE meta_key = \'ne-summary-data\'';
			}
			return vsprintf(str_replace('%s', "'%s'", $query), $args);
		};
		
		$this->installer->migrate_post_meta();
		
		// Should only execute one query due to error
		$this->assertCount(1, $wpdb->queries, 'Should stop after first error');
		$this->assertFalse(get_option('nuclen_meta_migration_done'), 'Migration should not be marked as done on error');
	}
}