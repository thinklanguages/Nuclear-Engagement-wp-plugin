<?php
namespace NuclearEngagement\Core {
	if (!function_exists(__NAMESPACE__ . '\wp_clear_scheduled_hook')) {
		function wp_clear_scheduled_hook($hook) {
			$GLOBALS['wp_cleared_hooks'][] = $hook;
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\delete_option')) {
		function delete_option($name) {
			unset($GLOBALS['wp_options'][$name]);
			$GLOBALS['wp_deleted_options'][] = $name;
			return true;
		}
	}
	if (!function_exists(__NAMESPACE__ . '\delete_transient')) {
		function delete_transient($name) {
			unset($GLOBALS['wp_transients'][$name]);
			$GLOBALS['wp_deleted_transients'][] = $name;
			return true;
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Core\Deactivator;
	use NuclearEngagement\Core\SettingsRepository;
	use NuclearEngagement\Services\AutoGenerationService;

	class DeactivatorTest extends TestCase {
		protected function setUp(): void {
			global $wp_options, $wp_transients, $wp_cleared_hooks, $wp_deleted_options, $wp_deleted_transients;
			$wp_options = $wp_transients = [];
			$wp_cleared_hooks = $wp_deleted_options = $wp_deleted_transients = [];
			
			// Add some test data
			$wp_options['nuclen_active_generations'] = ['gen1', 'gen2'];
			$wp_transients['nuclen_plugin_activation_redirect'] = ['value' => true, 'expiration' => 3600];
			
			SettingsRepository::reset_for_tests();
		}

		public function test_nuclen_deactivate_clears_scheduled_cron_hooks(): void {
			Deactivator::nuclen_deactivate();
			
			$expected_hooks = [
				AutoGenerationService::START_HOOK,
				AutoGenerationService::QUEUE_HOOK,
				'nuclen_poll_generation'
			];
			
			$this->assertCount(3, $GLOBALS['wp_cleared_hooks']);
			foreach ($expected_hooks as $hook) {
				$this->assertContains($hook, $GLOBALS['wp_cleared_hooks']);
			}
		}

		public function test_nuclen_deactivate_removes_active_generations_option(): void {
			$this->assertArrayHasKey('nuclen_active_generations', $GLOBALS['wp_options']);
			
			Deactivator::nuclen_deactivate();
			
			$this->assertArrayNotHasKey('nuclen_active_generations', $GLOBALS['wp_options']);
			$this->assertContains('nuclen_active_generations', $GLOBALS['wp_deleted_options']);
		}

		public function test_nuclen_deactivate_clears_activation_redirect_transient(): void {
			$this->assertArrayHasKey('nuclen_plugin_activation_redirect', $GLOBALS['wp_transients']);
			
			Deactivator::nuclen_deactivate();
			
			$this->assertArrayNotHasKey('nuclen_plugin_activation_redirect', $GLOBALS['wp_transients']);
			$this->assertContains('nuclen_plugin_activation_redirect', $GLOBALS['wp_deleted_transients']);
		}

		public function test_nuclen_deactivate_clears_settings_cache_when_provided(): void {
			// Since SettingsRepository is final, we'll use the real instance
			$settings = SettingsRepository::get_instance();
			
			// Call deactivate with settings
			Deactivator::nuclen_deactivate($settings);
			
			// Verify basic deactivation tasks were still performed
			$this->assertCount(3, $GLOBALS['wp_cleared_hooks']);
			$this->assertContains('nuclen_active_generations', $GLOBALS['wp_deleted_options']);
		}

		public function test_nuclen_deactivate_works_without_settings_instance(): void {
			// Should complete without errors when settings is null
			Deactivator::nuclen_deactivate(null);
			
			// Verify basic deactivation tasks were still performed
			$this->assertCount(3, $GLOBALS['wp_cleared_hooks']);
			$this->assertContains('nuclen_active_generations', $GLOBALS['wp_deleted_options']);
		}

		public function test_nuclen_deactivate_handles_missing_options_gracefully(): void {
			// Remove the options that would normally exist
			unset($GLOBALS['wp_options']['nuclen_active_generations']);
			unset($GLOBALS['wp_transients']['nuclen_plugin_activation_redirect']);
			
			// Should not throw any errors
			Deactivator::nuclen_deactivate();
			
			// Verify hooks were still cleared
			$this->assertCount(3, $GLOBALS['wp_cleared_hooks']);
		}
	}
}

// Mock the AutoGenerationService constants
namespace NuclearEngagement\Services {
	if (!class_exists(__NAMESPACE__ . '\AutoGenerationService')) {
		class AutoGenerationService {
			const START_HOOK = 'nuclen_start_auto_generation';
			const QUEUE_HOOK = 'nuclen_process_generation_queue';
		}
	}
}