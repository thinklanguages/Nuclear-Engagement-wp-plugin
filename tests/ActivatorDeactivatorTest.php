<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Activator;
use NuclearEngagement\Core\Deactivator;

if (!defined('NUCLEN_PLUGIN_DIR')) {
	define('NUCLEN_PLUGIN_DIR', dirname(__DIR__) . '/nuclear-engagement/');
}
if (!defined('NUCLEN_PLUGIN_VERSION')) { define('NUCLEN_PLUGIN_VERSION', '1.0'); }
// NUCLEN_ASSET_VERSION is loaded from constants.php
if (!defined('NUCLEN_ACTIVATION_REDIRECT_TTL')) { define('NUCLEN_ACTIVATION_REDIRECT_TTL', 30); }

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
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Services/AutoGenerationService.php';

class ActivatorDeactivatorTest extends TestCase {
	protected function setUp(): void {
		global $wpdb, $wp_options, $wp_autoload, $transients, $update_option_calls, $cleared_hooks;
		$wpdb = new AD_WPDB();
		$wp_options = $wp_autoload = $transients = $update_option_calls = [];
		$cleared_hooks = [];
		// Make sure global transients is also reset
		$GLOBALS['wp_transients'] = [];
		$GLOBALS['wp_options'] = [];
		$GLOBALS['wp_autoload'] = [];
		$GLOBALS['update_option_calls'] = [];
		$GLOBALS['cleared_hooks'] = [];
		\NuclearEngagement\Core\SettingsRepository::reset_for_tests();
	}

	public function test_activation_creates_indexes_and_sets_options(): void {
		global $wpdb, $wp_options, $transients, $update_option_calls;
		\NuclearEngagement\Core\Activator::nuclen_activate();
		// Check the global transients array
		$this->assertArrayHasKey('nuclen_plugin_activation_redirect', $GLOBALS['wp_transients']);
		// Check if it's an array with 'value' key or just a boolean
		$transient_value = $GLOBALS['wp_transients']['nuclen_plugin_activation_redirect'];
		if (is_array($transient_value)) {
			$this->assertTrue($transient_value['value']);
		} else {
			$this->assertTrue($transient_value);
		}
		$this->assertArrayHasKey('nuclear_engagement_setup', $GLOBALS['wp_options']);
		$this->assertSame(1, $GLOBALS['update_option_calls']['nuclear_engagement_setup'] ?? 0);
		$this->assertCount(4, $wpdb->queries);
		$this->assertStringContainsString('nuclen_quiz_data_idx', $wpdb->queries[0]);
	}

	public function test_deactivation_clears_hooks_and_options(): void {
		global $wp_options, $transients, $cleared_hooks;
		$GLOBALS['wp_options']['nuclen_active_generations'] = ['x'];
		$GLOBALS['wp_transients']['nuclen_plugin_activation_redirect'] = true;
		$GLOBALS['cleared_hooks'] = []; // Ensure it's initialized
		
		\NuclearEngagement\Core\Deactivator::nuclen_deactivate();
		$this->assertArrayNotHasKey('nuclen_active_generations', $GLOBALS['wp_options']);
		$this->assertArrayNotHasKey('nuclen_plugin_activation_redirect', $GLOBALS['wp_transients']);
		$expected = [
			'nuclen_start_generation',
			'nuclen_poll_generation',
		];
		$this->assertSame($expected, $GLOBALS['cleared_hooks']);
	}
}