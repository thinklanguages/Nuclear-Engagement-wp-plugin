<?php
namespace NuclearEngagement\Admin {
	function current_user_can($cap, $id) { return true; }
	function wp_unslash($val) { return $val; }
	function sanitize_text_field($val) { return $val; }
	function wp_kses_post($val) { return $val; }
	function update_post_meta($id, $key, $value) {}
	function delete_post_meta($id, $key) {}
	function clean_post_cache($id) {}
	function remove_action(...$args) {}
	function add_action(...$args) {}
	function get_gmt_from_date($time) { return $time; }
	function wp_update_post(array $data, $error = false) { return $GLOBALS['mb_result']; }
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
	require_once dirname(__DIR__) . '/nuclear-engagement/admin/Traits/AdminQuizMetabox.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/admin/Traits/AdminSummaryMetabox.php';

	class DummyRepo {
		public function get($key, $default = 0) { return 1; }
	}

	class QuizBox {
		use \NuclearEngagement\Admin\Traits\AdminQuizMetabox;
		public function nuclen_get_settings_repository() { return new DummyRepo(); }
	}

	class SummaryBox {
		use \NuclearEngagement\Admin\Traits\AdminSummaryMetabox;
		public function nuclen_get_settings_repository() { return new DummyRepo(); }
	}

	class MetaboxUpdateErrorTest extends TestCase {
		protected function setUp(): void {
			$_POST = [
				'nuclen_quiz_data_nonce' => 'n',
				'nuclen_quiz_data' => [],
				'nuclen_summary_data_nonce' => 'n',
				'nuclen_summary_data' => [],
			];
			$GLOBALS['mb_result'] = new \WP_Error();
			\NuclearEngagement\Services\LoggingService::$logs = [];
			\NuclearEngagement\Services\LoggingService::$notices = [];
			$GLOBALS['test_verify_nonce'] = true;
		}

		protected function tearDown(): void {
			unset($GLOBALS['test_verify_nonce']);
		}

		public function test_quiz_update_error_logs_and_notifies(): void {
			$box = new QuizBox();
			$box->nuclen_save_quiz_data_meta(1);
			$expected = ['Failed to update modified time for post 1: error'];
			$this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$logs);
			$this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$notices);
		}

		public function test_summary_update_error_logs_and_notifies(): void {
			$box = new SummaryBox();
			$box->nuclen_save_summary_data_meta(2);
			$expected = ['Failed to update modified time for post 2: error'];
			$this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$logs);
			$this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$notices);
		}
	}
}
