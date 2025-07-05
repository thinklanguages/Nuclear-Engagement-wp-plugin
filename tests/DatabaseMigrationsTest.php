<?php
namespace NuclearEngagement\Core {
	if (!function_exists(__NAMESPACE__ . '\get_option')) {
		function get_option($name, $default = false) {
			return $GLOBALS['wp_options'][$name] ?? $default;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\update_option')) {
		function update_option($name, $value, $autoload = null) {
			$GLOBALS['wp_options'][$name] = $value;
			$GLOBALS['wp_updated_options'][] = ['name' => $name, 'value' => $value, 'autoload' => $autoload];
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\version_compare')) {
		function version_compare($version1, $version2, $operator = null) {
			return \version_compare($version1, $version2, $operator);
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Core\DatabaseMigrations;
	use NuclearEngagement\Services\LoggingService;

	class DatabaseMigrationsTest extends TestCase {
		protected function setUp(): void {
			global $wp_options, $wp_updated_options, $wpdb;
			$wp_options = $wp_updated_options = [];
			
			// Mock wpdb
			$wpdb = new class {
				public $postmeta = 'wp_postmeta';
				public $prefix = 'wp_';
				public $last_error = '';
				private $get_var_return = 0;
				private $query_return = true;
				private $prepare_callback = null;
				
				public function get_var($query) {
					return is_callable($this->get_var_return) ? call_user_func($this->get_var_return) : $this->get_var_return;
				}
				
				public function prepare($query, ...$args) {
					if ($this->prepare_callback) {
						return call_user_func($this->prepare_callback, $query, ...$args);
					}
					return $query;
				}
				
				public function query($sql) {
					return is_callable($this->query_return) ? call_user_func($this->query_return, $sql) : $this->query_return;
				}
				
				public function set_get_var_return($value) {
					$this->get_var_return = $value;
				}
				
				public function set_query_return($value) {
					$this->query_return = $value;
				}
				
				public function set_prepare_callback($callback) {
					$this->prepare_callback = $callback;
				}
			};
			
			// Reset LoggingService logs if it's our mock
			if (property_exists(LoggingService::class, 'test_logs')) {
				LoggingService::$test_logs = [];
			}
		}

		public function test_migrate_updates_database_version_when_outdated(): void {
			global $wpdb;
			
			// Setup
			$GLOBALS['wp_options']['nuclen_db_version'] = '1.0.0';
			
			// Mock database queries
			$wpdb->set_get_var_return(0); // No indexes exist
			$wpdb->set_query_return(true); // Success
			
			// Act
			DatabaseMigrations::migrate();
			
			// Assert
			$this->assertEquals('1.1.0', $GLOBALS['wp_options']['nuclen_db_version']);
			$this->assertContains(
				['name' => 'nuclen_db_version', 'value' => '1.1.0', 'autoload' => false],
				$GLOBALS['wp_updated_options']
			);
		}

		public function test_migrate_skips_when_already_up_to_date(): void {
			global $wpdb;
			
			// Setup - already at current version
			$GLOBALS['wp_options']['nuclen_db_version'] = '1.1.0';
			
			// Track if get_var was called
			$get_var_called = false;
			$wpdb->set_get_var_return(function() use (&$get_var_called) {
				$get_var_called = true;
				return 0;
			});
			
			// Act
			DatabaseMigrations::migrate();
			
			// Assert - no updates should occur
			$this->assertEmpty($GLOBALS['wp_updated_options']);
			$this->assertFalse($get_var_called);
		}

		public function test_migrate_creates_all_required_indexes(): void {
			global $wpdb;
			
			$expectedIndexes = [
				'idx_nuclen_nuclen_quiz_data',
				'idx_nuclen_nuclen_quiz_protected',
				'idx_nuclen_nuclen_summary_data',
				'idx_nuclen_nuclen_summary_protected'
			];
			
			$createdIndexes = [];
			
			// Mock no existing indexes
			$wpdb->set_get_var_return(0);
			
			// Track created indexes
			$wpdb->set_prepare_callback(function($sql, ...$args) use (&$createdIndexes) {
				// Replace %i placeholder with actual value
				if (strpos($sql, 'CREATE INDEX %i') === 0 && isset($args[0])) {
					$sql = str_replace('%i', $args[0], $sql);
					$createdIndexes[] = $args[0];
				}
				// Replace other placeholders
				foreach ($args as $i => $arg) {
					$sql = str_replace('%s', "'$arg'", $sql);
				}
				return $sql;
			});
			
			$wpdb->set_query_return(true);
			
			// Act
			DatabaseMigrations::migrate();
			
			// Assert
			foreach ($expectedIndexes as $index) {
				$this->assertContains($index, $createdIndexes);
			}
		}

		public function test_migrate_skips_existing_indexes(): void {
			global $wpdb;
			
			// Mock that first two indexes exist, last two don't
			$get_var_calls = 0;
			$wpdb->set_get_var_return(function() use (&$get_var_calls) {
				$get_var_calls++;
				return in_array($get_var_calls, [1, 2]) ? 1 : 0;
			});
			
			// Track query calls
			$query_calls = 0;
			$wpdb->set_query_return(function() use (&$query_calls) {
				$query_calls++;
				return true;
			});
			
			// Act
			DatabaseMigrations::migrate();
			
			// Assert - should only have created 2 indexes
			$this->assertEquals(2, $query_calls);
			$this->assertEquals('1.1.0', $GLOBALS['wp_options']['nuclen_db_version']);
		}

		public function test_migrate_handles_index_creation_failure(): void {
			global $wpdb;
			
			// Mock no existing indexes
			$wpdb->set_get_var_return(0);
			
			// First query fails, fallback succeeds
			$query_calls = 0;
			$wpdb->set_query_return(function() use (&$query_calls) {
				$query_calls++;
				// Odd calls fail, even calls succeed
				return ($query_calls % 2) === 0;
			});
			
			// Set error for first attempt
			$wpdb->last_error = 'Syntax error';
			
			// Act
			DatabaseMigrations::migrate();
			
			// Assert - should still update version
			$this->assertEquals('1.1.0', $GLOBALS['wp_options']['nuclen_db_version']);
			
			// Check logs contain fallback attempts
			if (property_exists(LoggingService::class, 'test_logs')) {
				$logs = LoggingService::$test_logs;
				$this->assertNotEmpty($logs);
			} else {
				$this->assertTrue(true); // Skip log check if using real LoggingService
			}
		}

		public function test_migrate_handles_complete_failure(): void {
			global $wpdb;
			
			// Mock exception during migration
			$wpdb->set_get_var_return(function() {
				throw new \Exception('Database connection failed');
			});
			
			// Act
			DatabaseMigrations::migrate();
			
			// Assert - version should not be updated
			$this->assertArrayNotHasKey('nuclen_db_version', $GLOBALS['wp_options']);
			
			// Check error was logged
			if (property_exists(LoggingService::class, 'test_logs')) {
				$logs = LoggingService::$test_logs;
				$this->assertNotEmpty($logs);
				$this->assertStringContainsString('migration failed', $logs[0]);
			} else {
				$this->assertTrue(true); // Skip log check if using real LoggingService
			}
		}

		public function test_needs_migration_returns_true_when_outdated(): void {
			$testCases = [
				'1.0.0' => true,
				'1.0.5' => true,
				'1.0.9' => true,
				'1.1.0' => false,
				'1.2.0' => false,
				'2.0.0' => false
			];
			
			foreach ($testCases as $version => $expected) {
				$GLOBALS['wp_options']['nuclen_db_version'] = $version;
				$result = DatabaseMigrations::needs_migration();
				$this->assertEquals($expected, $result, "Version $version should " . ($expected ? 'need' : 'not need') . " migration");
			}
		}

		public function test_needs_migration_returns_true_when_option_missing(): void {
			// No option set
			unset($GLOBALS['wp_options']['nuclen_db_version']);
			
			$result = DatabaseMigrations::needs_migration();
			
			$this->assertTrue($result);
		}

		public function test_add_meta_indexes_creates_correct_sql(): void {
			global $wpdb;
			
			$capturedQueries = [];
			
			$wpdb->set_get_var_return(0);
			
			$wpdb->set_prepare_callback(function($sql, ...$args) use (&$capturedQueries) {
				// Replace placeholders
				$processed = $sql;
				if (isset($args[0]) && strpos($sql, '%i') !== false) {
					$processed = str_replace('%i', $args[0], $processed);
				}
				if (isset($args[1]) && strpos($processed, '%s') !== false) {
					$processed = str_replace('%s', "'$args[1]'", $processed);
				}
				$capturedQueries[] = $processed;
				return $processed;
			});
			
			$wpdb->set_query_return(true);
			
			// Act
			DatabaseMigrations::migrate();
			
			// Assert SQL structure
			foreach ($capturedQueries as $query) {
				if (strpos($query, 'CREATE INDEX') === 0) {
					// Check primary index format
					if (strpos($query, 'WHERE meta_key') !== false) {
						$this->assertMatchesRegularExpression('/CREATE INDEX \w+ ON wp_postmeta \(meta_key, post_id\) WHERE meta_key = /', $query);
					} else {
						// Check fallback index format
						$this->assertMatchesRegularExpression('/CREATE INDEX \w+ ON wp_postmeta \(meta_key\(20\), post_id\)/', $query);
					}
				}
			}
		}

		public function test_migrate_logs_success_message(): void {
			global $wpdb;
			
			$wpdb->set_get_var_return(0);
			$wpdb->set_query_return(true);
			
			// Act
			DatabaseMigrations::migrate();
			
			// Assert
			if (property_exists(LoggingService::class, 'test_logs')) {
				$logs = LoggingService::$test_logs;
				$this->assertNotEmpty($logs);
				$successLog = array_filter($logs, function($log) {
					return strpos($log, 'Database migrated to version') !== false;
				});
				$this->assertNotEmpty($successLog);
			} else {
				$this->assertTrue(true); // Skip log check if using real LoggingService
			}
		}
	}
}

// Mock dependencies
namespace NuclearEngagement\Services {
	if (!class_exists(__NAMESPACE__ . '\LoggingService')) {
		class LoggingService {
			public static $test_logs = [];
			
			public static function log($message) {
				self::$test_logs[] = $message;
			}
		}
	}
}

namespace {
	if (!defined('DB_NAME')) {
		define('DB_NAME', 'test_database');
	}
}