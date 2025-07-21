<?php
namespace NuclearEngagement\Services {
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\PublishGenerationHandler;
	use NuclearEngagement\Core\SettingsRepository;
	if (!function_exists('current_user_can')) {
		function current_user_can($cap, $id = 0) { return $GLOBALS['can_publish'] ?? true; }
	}
	if (!function_exists('wp_doing_cron')) {
		function wp_doing_cron() { return $GLOBALS['doing_cron'] ?? false; }
	}
	if (!function_exists('wp_next_scheduled')) {
		function wp_next_scheduled($hook, $args = null) { return $GLOBALS['next_scheduled'] ?? false; }
	}
	if (!function_exists('wp_schedule_single_event')) {
		function wp_schedule_single_event($timestamp, $hook, $args) {
			$GLOBALS['scheduled'][] = compact('timestamp','hook','args');
			return $GLOBALS['schedule_result'] ?? true;
		}
	}
	if (!function_exists('get_post_meta')) {
		function get_post_meta($post_id, $key, $single) {
			return $GLOBALS['meta'][$post_id][$key] ?? '';
		}
	}

	require_once __DIR__ . '/../nuclear-engagement/inc/Services/PublishGenerationHandler.php';
	require_once __DIR__ . '/../nuclear-engagement/inc/Core/SettingsRepository.php';

	class PublishGenerationHandlerTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['scheduled'] = [];
			$GLOBALS['meta'] = [];
			$GLOBALS['schedule_result'] = true;
			$GLOBALS['can_publish'] = true;
			$GLOBALS['next_scheduled'] = false;
			$GLOBALS['doing_cron'] = false;
			\NuclearEngagement\Services\LoggingService::$logs = [];
			SettingsRepository::reset_for_tests();
		}

		private function makeHandler(): PublishGenerationHandler {
			$repo = SettingsRepository::get_instance([
				'generation_post_types' => ['post'],
				'auto_generate_quiz_on_publish' => true,
				'auto_generate_summary_on_publish' => true,
			]);
			return new PublishGenerationHandler($repo);
		}

		public function test_schedules_generation_when_allowed(): void {
			$handler = $this->makeHandler();
			$post = (object)[ 'ID' => 1, 'post_type' => 'post' ];
			$handler->handle_post_publish('publish', 'draft', $post);
			$this->assertCount(2, $GLOBALS['scheduled']);
			$this->assertSame([1,'quiz'], $GLOBALS['scheduled'][0]['args']);
			$this->assertSame([1,'summary'], $GLOBALS['scheduled'][1]['args']);
		}

		public function test_skips_generation_when_content_exists(): void {
			$handler = $this->makeHandler();
			// Set existing quiz and summary data
			$GLOBALS['meta'][7]['nuclen-quiz-data'] = 'existing quiz data';
			$GLOBALS['meta'][7]['nuclen-summary-data'] = 'existing summary data';
			$post = (object)[ 'ID' => 7, 'post_type' => 'post' ];
			$handler->handle_post_publish('publish', 'draft', $post);
			// Should not schedule any generation since content already exists
			$this->assertEmpty($GLOBALS['scheduled']);
		}

		public function test_generates_only_missing_content(): void {
			$handler = $this->makeHandler();
			// Set only existing quiz data, but no summary
			$GLOBALS['meta'][8]['nuclen-quiz-data'] = 'existing quiz data';
			$post = (object)[ 'ID' => 8, 'post_type' => 'post' ];
			$handler->handle_post_publish('publish', 'draft', $post);
			// Should only schedule summary generation
			$this->assertCount(1, $GLOBALS['scheduled']);
			$this->assertSame([8,'summary'], $GLOBALS['scheduled'][0]['args']);
		}

		public function test_skips_for_protected_or_unauthorized(): void {
			$handler = $this->makeHandler();
			$GLOBALS['can_publish'] = false;
			$GLOBALS['meta'][5]['nuclen_quiz_protected'] = 1;
			$post = (object)[ 'ID' => 5, 'post_type' => 'post' ];
			$handler->handle_post_publish('publish', 'draft', $post);
			$this->assertEmpty($GLOBALS['scheduled']);
		}

		public function test_logs_when_schedule_fails(): void {
			$handler = $this->makeHandler();
			$GLOBALS['schedule_result'] = false;
			$post = (object)[ 'ID' => 3, 'post_type' => 'post' ];
			$handler->handle_post_publish('publish', 'draft', $post);
			$this->assertNotEmpty(\NuclearEngagement\Services\LoggingService::$logs);
		}
	}
}
