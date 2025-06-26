<?php
namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static function log(string $msg): void { self::$logs[] = $msg; }
        public static function log_exception(\Throwable $e): void { self::$logs[] = $e->getMessage(); }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Admin\Controller\Ajax\UpdatesController;
    use NuclearEngagement\Services\ApiException;
    if (!function_exists('check_ajax_referer')) { function check_ajax_referer($a,$f,$d=false){ return true; } }
    if (!function_exists('current_user_can')) { function current_user_can($c){ return true; } }
    if (!function_exists('wp_send_json_success')) { function wp_send_json_success($d){ $GLOBALS['json_response']=['success',$d]; } }
    if (!function_exists('wp_send_json_error')) { function wp_send_json_error($d,$c=0){ $GLOBALS['json_response']=['error',$d,$c]; } }
    if (!function_exists('status_header')) { function status_header($c){ $GLOBALS['status_header']=$c; } }
    if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return $t; } }
    if (!function_exists('wp_unslash')) { function wp_unslash($d){ return $d; } }
    if (!function_exists('__')) { function __($t,$d=null){ return $t; } }

    require_once __DIR__ . '/../nuclear-engagement/admin/Controller/Ajax/BaseController.php';
    require_once __DIR__ . '/../nuclear-engagement/admin/Controller/Ajax/UpdatesController.php';
    require_once __DIR__ . '/../nuclear-engagement/inc/Requests/UpdatesRequest.php';
    require_once __DIR__ . '/../nuclear-engagement/inc/Responses/UpdatesResponse.php';

    class DummyApi {
        public $result = [];
        public $exception = null;
        public function fetch_updates(string $id): array {
            if ($this->exception) {
                throw $this->exception;
            }
            return $this->result;
        }
    }

    class DummyStorage {
        public array $stored = [];
        public function storeResults(array $results, string $workflow): void {
            $this->stored[] = [$results, $workflow];
        }
    }

    class UpdatesControllerTest extends TestCase {
        protected function setUp(): void {
            $_POST = [];
            $GLOBALS['json_response'] = null;
            $GLOBALS['status_header'] = null;
        }

        public function test_valid_data_stores_results_and_outputs_json(): void {
            $_POST = ['generation_id' => 'gid', 'security' => 'valid'];
            $api = new DummyApi();
            $api->result = [
                'processed' => 1,
                'total' => 1,
                'remaining_credits' => 5,
                'results' => [
                    '7' => [
                        'questions' => [ ['question' => 'Q1', 'answers' => ['A1']] ],
                        'date' => '2024-01-01'
                    ]
                ]
            ];
            $storage = new DummyStorage();
            $c = new UpdatesController($api, $storage);
            $c->handle();
            $this->assertSame([['7'=>['questions'=>[['question'=>'Q1','answers'=>['A1']]],'date'=>'2024-01-01']], 'quiz'], $storage->stored[0]);
            $this->assertSame('success', $GLOBALS['json_response'][0]);
            $this->assertSame('quiz', $GLOBALS['json_response'][1]['workflow']);
            $this->assertSame(1, $GLOBALS['json_response'][1]['processed']);
        }

        public function test_api_exception_returns_error_response(): void {
            $_POST = ['generation_id' => 'gid', 'security' => 'valid'];
            $api = new DummyApi();
            $api->exception = new ApiException('fail', 418);
            $storage = new DummyStorage();
            $c = new UpdatesController($api, $storage);
            $c->handle();
            $this->assertSame('error', $GLOBALS['json_response'][0]);
            $this->assertSame('Failed to fetch updates. Please try again later.', $GLOBALS['json_response'][1]['message']);
            $this->assertSame(418, $GLOBALS['status_header']);
        }

        public function test_general_exception_returns_error_response(): void {
            $_POST = ['generation_id' => 'gid', 'security' => 'valid'];
            $api = new DummyApi();
            $api->exception = new \RuntimeException('boom');
            $storage = new DummyStorage();
            $c = new UpdatesController($api, $storage);
            $c->handle();
            $this->assertSame('error', $GLOBALS['json_response'][0]);
            $this->assertSame('An unexpected error occurred.', $GLOBALS['json_response'][1]['message']);
            $this->assertSame(500, $GLOBALS['status_header']);
        }
    }
}
