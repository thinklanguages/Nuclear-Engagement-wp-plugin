<?php
namespace NuclearEngagement\Modules\Quiz {
	function update_post_meta($postId, $key, $value) { return false; }
	function delete_post_meta($postId, $key) {}
	function clean_post_cache($id) {}
	function sanitize_text_field($val) { return $val; }
	function wp_kses_post($val) { return $val; }
}

namespace NuclearEngagement\Services {
	class LoggingService {
		public static array $logs = [];
		public static array $notices = [];
		public static function log(string $msg): void { self::$logs[] = $msg; }
		public static function notify_admin(string $msg): void { self::$notices[] = $msg; }
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Modules\Quiz\Quiz_Service;
	class QuizServiceFailureTest extends TestCase {
		protected function setUp(): void {
			\NuclearEngagement\Services\LoggingService::$logs = [];
			\NuclearEngagement\Services\LoggingService::$notices = [];
		}

		public function test_save_quiz_data_logs_on_failure(): void {
			$service = new Quiz_Service();
			$service->save_quiz_data(1, [ 'questions' => [ [ 'question' => 'Q', 'answers' => ['A'] ] ] ]);
			$expected = ['Failed to update quiz data for post 1'];
			$this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$logs);
			$this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$notices);
		}

		public function test_set_protected_logs_on_failure(): void {
			$service = new Quiz_Service();
			$service->set_protected(2, true);
			$expected = ['Failed to update quiz protected flag for post 2'];
			$this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$logs);
			$this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$notices);
		}
	}
}
