<?php
namespace NuclearEngagement\Modules\Summary {
    function current_user_can($cap, $id) { return true; }
    function wp_unslash($val) { return $val; }
    function sanitize_text_field($val) { return $val; }
    function wp_kses_post($val) { return $val; }
    function update_post_meta($id, $key, $value) { return false; }
    function delete_post_meta($id, $key) {}
    function clean_post_cache($id) {}
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
    use NuclearEngagement\Modules\Summary\Nuclen_Summary_Metabox;
    use NuclearEngagement\Modules\Summary\Summary_Service;
    class DummyRepo {
        public function get($key, $default = 0) { return 0; }
    }

    class SummaryMetaboxFailureTest extends TestCase {
        protected function setUp(): void {
            $_POST = [
                'nuclen_summary_data_nonce' => 'n',
                'nuclen_summary_data' => [],
            ];
            \NuclearEngagement\Services\LoggingService::$logs = [];
            \NuclearEngagement\Services\LoggingService::$notices = [];
            $GLOBALS['test_verify_nonce'] = true;
        }

        protected function tearDown(): void {
            unset($GLOBALS['test_verify_nonce']);
        }

        public function test_save_meta_logs_on_failure(): void {
            $box = new Nuclen_Summary_Metabox(new DummyRepo());
            $box->nuclen_save_summary_data_meta(1);
            $expected = ['Failed to update summary data for post 1'];
            $this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$logs);
            $this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$notices);
        }

        public function test_protected_flag_logs_on_failure(): void {
            $_POST[Summary_Service::PROTECTED_KEY] = '1';
            $box = new Nuclen_Summary_Metabox(new DummyRepo());
            $box->nuclen_save_summary_data_meta(2);
            $expected = [
                'Failed to update summary data for post 2',
                'Failed to update summary protected flag for post 2'
            ];
            $this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$logs);
            $this->assertSame($expected, \NuclearEngagement\Services\LoggingService::$notices);
        }
    }
}
