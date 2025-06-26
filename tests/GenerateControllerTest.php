<?php
namespace NuclearEngagement\Services {
    class GenerationService {
        public array $received = [];
        public function generateContent(GenerateRequest $r) { $this->received[] = $r; return new \NuclearEngagement\Responses\GenerateResponse(); }
    }
    class LoggingService {
        public static array $errors = [];
        public static function log_exception(\Throwable $e): void { self::$errors[] = $e->getMessage(); }
        public static function log(string $m): void { self::$errors[] = $m; }
    }
}

namespace NuclearEngagement\Responses {
    class GenerateResponse {
        public function toArray(): array { return ['ok'=>1]; }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Admin\Controller\Ajax\GenerateController;
    use NuclearEngagement\Requests\GenerateRequest;
    if (!function_exists('check_ajax_referer')) { function check_ajax_referer($a,$f,$d=false){ return true; } }
    if (!function_exists('current_user_can')) { function current_user_can($c){ return true; } }
    if (!function_exists('wp_send_json_success')) { function wp_send_json_success($d){ $GLOBALS['json_response']=['success',$d]; } }
    if (!function_exists('wp_send_json_error')) { function wp_send_json_error($d,$c=0){ $GLOBALS['json_response']=['error',$d,$c]; } }
    if (!function_exists('status_header')) { function status_header($c){ $GLOBALS['status_header']=$c; } }
    if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return $t; } }
    if (!function_exists('wp_unslash')) { function wp_unslash($d){ return $d; } }
    if (!function_exists('__')) { function __($t,$d=null){ return $t; } }

    require_once __DIR__ . '/../nuclear-engagement/admin/Controller/Ajax/BaseController.php';
    require_once __DIR__ . '/../nuclear-engagement/admin/Controller/Ajax/GenerateController.php';
    require_once __DIR__ . '/../nuclear-engagement/inc/Requests/GenerateRequest.php';
    
    class GenerateControllerTest extends TestCase {
        protected function setUp(): void {
            $_POST = [];
            $GLOBALS['json_response'] = null;
            $GLOBALS['status_header'] = null;
        }

        public function test_valid_request_calls_service(): void {
            $service = new \NuclearEngagement\Services\GenerationService();
            $controller = new GenerateController($service);
            $_POST['payload'] = json_encode([
                'nuclen_selected_post_ids' => json_encode([1]),
                'nuclen_selected_generate_workflow' => 'quiz'
            ]);
            $_POST['security'] = 'valid';
            $controller->handle();
            $this->assertSame(['success',['ok'=>1]], $GLOBALS['json_response']);
            $this->assertInstanceOf(GenerateRequest::class, $service->received[0]);
        }

        public function test_missing_payload_returns_400(): void {
            $service = new \NuclearEngagement\Services\GenerationService();
            $controller = new GenerateController($service);
            $_POST['security'] = 'valid';
            $controller->handle();
            $this->assertSame(400, $GLOBALS['status_header']);
            $this->assertSame('Missing payload in request', $GLOBALS['json_response'][1]['message']);
        }
    }
}
