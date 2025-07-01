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
	if (!function_exists('get_option')) { 
		function get_option($option, $default = false) { 
			// Mock settings for allowed post types
			if ($option === 'nuclear_engagement_settings') {
				return $GLOBALS['mock_settings'] ?? ['generation_post_types' => ['post', 'page']];
			}
			return $default;
		} 
	}

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
			$GLOBALS['mock_settings'] = null;
			\NuclearEngagement\Services\LoggingService::$exceptions = [];
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

		public function test_handle_rejects_invalid_post_type(): void {
			$service = new DummyService();
			$controller = new PostsCountController($service);
			
			// Set allowed post types
			$GLOBALS['mock_settings'] = ['generation_post_types' => ['post', 'page']];

			$_POST = [
				'nuclen_post_type' => 'custom_post_type', // Not in allowed list
				'nuclen_post_status' => 'publish',
				'nuclen_generate_workflow' => 'quiz',
				'security' => 'valid',
			];

			$controller->handle();

			// Should return error
			$this->assertSame('error', $GLOBALS['json_response'][0]);
			$this->assertStringContainsString('not allowed', $GLOBALS['json_response'][1]);
			// Service should not be called
			$this->assertCount(0, $service->requests);
		}

		public function test_handle_allows_page_post_type(): void {
			$service = new DummyService();
			$controller = new PostsCountController($service);
			
			// Set allowed post types
			$GLOBALS['mock_settings'] = ['generation_post_types' => ['post', 'page']];

			$_POST = [
				'nuclen_post_type' => 'page', // In allowed list
				'nuclen_post_status' => 'publish',
				'nuclen_generate_workflow' => 'summary',
				'security' => 'valid',
			];

			$controller->handle();

			$this->assertSame(['success',$service->result], $GLOBALS['json_response']);
			$this->assertInstanceOf(PostsCountRequest::class, $service->requests[0]);
			$this->assertSame('page', $service->requests[0]->postType);
		}

		public function test_handle_with_empty_post_type(): void {
			$service = new DummyService();
			$controller = new PostsCountController($service);

			$_POST = [
				'nuclen_post_type' => '', // Empty post type
				'nuclen_post_status' => 'publish',
				'nuclen_generate_workflow' => 'quiz',
				'security' => 'valid',
			];

			$controller->handle();

			// Should succeed (empty post type is allowed to pass validation)
			$this->assertSame(['success',$service->result], $GLOBALS['json_response']);
			$this->assertInstanceOf(PostsCountRequest::class, $service->requests[0]);
			// PostsCountRequest should default to 'post'
			$this->assertSame('post', $service->requests[0]->postType);
		}

		public function test_handle_with_no_settings(): void {
			$service = new DummyService();
			$controller = new PostsCountController($service);
			
			// No settings (defaults to ['post'])
			$GLOBALS['mock_settings'] = [];

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

		public function test_handle_with_exception(): void {
			$service = new DummyService();
			// Make service throw exception
			$service->result = null;
			
			$controller = new PostsCountController($service);

			$_POST = [
				'nuclen_post_type' => 'post',
				'nuclen_post_status' => 'publish',
				'nuclen_generate_workflow' => 'quiz',
				'security' => 'valid',
			];

			// Override getPostsCount to throw exception
			$reflector = new \ReflectionClass($service);
			$method = $reflector->getMethod('getPostsCount');
			$service = $this->getMockBuilder(DummyService::class)
				->onlyMethods(['getPostsCount'])
				->getMock();
			$service->expects($this->once())
				->method('getPostsCount')
				->willThrowException(new \Exception('Database error'));

			$controller = new PostsCountController($service);
			$controller->handle();

			// Should return error
			$this->assertSame('error', $GLOBALS['json_response'][0]);
			$this->assertStringContainsString('Database error', $GLOBALS['json_response'][1]);
			// Exception should be logged
			$this->assertContains('Database error', \NuclearEngagement\Services\LoggingService::$exceptions);
		}
	}
}
