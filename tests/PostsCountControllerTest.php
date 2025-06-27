<?php
namespace NuclearEngagement\Services {
	class LoggingService {
		public static array $exceptions = [];
		public static function log_exception(\Throwable $e): void { self::$exceptions[] = $e->getMessage(); }
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Admin\Controller\Ajax\PostsCountController;
	use NuclearEngagement\Requests\PostsCountRequest;
	// ------------------------------------------------------
	// Basic stubs for WordPress AJAX functions
	// ------------------------------------------------------
	if (!function_exists('check_ajax_referer')) { function check_ajax_referer($a,$f,$d=false){ return true; } }
	if (!function_exists('current_user_can')) { function current_user_can($c){ return true; } }
	if (!function_exists('wp_send_json_success')) { function wp_send_json_success($data){ $GLOBALS['json_response'] = ['success',$data]; } }
	if (!function_exists('wp_send_json_error')) { function wp_send_json_error($data,$code=0){ $GLOBALS['json_response'] = ['error',$data,$code]; } }
	if (!function_exists('status_header')) { function status_header($code){ $GLOBALS['status_header'] = $code; } }
	if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return $t; } }
	if (!function_exists('wp_unslash')) { function wp_unslash($d){ return $d; } }
	if (!function_exists('absint')) { function absint($v){ return abs(intval($v)); } }
	if (!function_exists('__')) { function __($t,$d=null){ return $t; } }

	require_once dirname(__DIR__) . '/nuclear-engagement/admin/Controller/Ajax/BaseController.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/admin/Controller/Ajax/PostsCountController.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Requests/PostsCountRequest.php';

	class DummyService {
		public array $requests = [];
		public array $result = ['count'=>1,'post_ids'=>[7]];
		public function getPostsCount(PostsCountRequest $req): array {
			$this->requests[] = $req;
			return $this->result;
		}
	}

	class PostsCountControllerTest extends TestCase {
		protected function setUp(): void {
			$_POST = [];
			$GLOBALS['json_response'] = null;
		}

		public function test_handle_outputs_json_success(): void {
			$service = new DummyService();
			$controller = new PostsCountController($service);

			$_POST = [
				'nuclen_post_type' => 'post',
				'nuclen_post_status' => 'publish',
				'nuclen_generate_workflow' => 'quiz',
				'security' => 'valid',
			];

			$controller->handle();

			$this->assertSame(['success',$service->result], $GLOBALS['json_response']);
			$this->assertInstanceOf(PostsCountRequest::class, $service->requests[0]);
			$this->assertSame('post', $service->requests[0]->postType);
		}
	}
}
