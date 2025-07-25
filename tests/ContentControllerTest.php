<?php
namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Front\Controller\Rest\ContentController;
	use NuclearEngagement\Core\SettingsRepository;
	use NuclearEngagement\Services\ContentStorageService;
	use NuclearEngagement\Modules\Summary\Summary_Service;
	if (!function_exists('__')) {
		function __($t, $d = null) { return $t; }
	}
	if (!function_exists('sanitize_text_field')) {
		function sanitize_text_field($t) { return $t; }
	}
	if (!function_exists('current_user_can')) {
		function current_user_can($cap) { return true; }
	}
	if (!class_exists('WP_REST_Response')) {
		class WP_REST_Response {
			public $data;
			public $status;
			public function __construct($data = null, $status = 200) {
				$this->data = $data;
				$this->status = $status;
			}
		}
	}

	// Update DummyRequest to extend WP_REST_Request
	class DummyRequest extends WP_REST_Request {
		public function __construct($json = null, array $headers = []) {
			parent::__construct();
			if ($json !== null) {
				$this->set_body_params($json);
			}
			foreach ($headers as $key => $value) {
				$this->set_header($key, $value);
			}
		}
	}

	class DummyStorageContent extends ContentStorageService {
		public array $stored = [];
		public function storeResults(array $results, string $workflowType): array {
			global $wp_meta;
			$metaKey = $workflowType === 'quiz' ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			foreach ($results as $id => $data) {
				$wp_meta[$id][$metaKey] = $data;
			}
			$this->stored[] = [$results, $workflowType];
			return array_fill_keys(array_keys($results), true);
		}
	}

	class FailingStorage extends ContentStorageService {
		public array $stored = [];
		public function storeResults(array $results, string $workflowType): array {
			global $wp_meta;
			$metaKey = $workflowType === 'quiz' ? 'nuclen-quiz-data' : Summary_Service::META_KEY;
			foreach ($results as $id => $data) {
				$wp_meta[$id][$metaKey] = $data;
			}
			$this->stored[] = [$results, $workflowType];
			return array_fill_keys(array_keys($results), 'fail');
		}
	}

	// Mock logging service logs property
	$GLOBALS['test_logs'] = [];
	
	class ContentControllerTest extends TestCase {
		protected function setUp(): void {
			global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $test_logs;
			$wp_options = $wp_autoload = $wp_posts = $wp_meta = [];
			$test_logs = [];
			SettingsRepository::reset_for_tests();
		}

	public function test_handle_invalid_json_returns_error(): void {
		$settings = SettingsRepository::get_instance();
		$storage = new ContentStorageService($settings);
		$controller = new ContentController($storage, $settings);
		$req = new DummyRequest();

			$res = $controller->handle($req);

		$this->assertInstanceOf(WP_Error::class, $res);
		// Skip checking logs since we're not mocking the LoggingService
	}

	public function test_valid_password_returns_rest_response(): void {
		global $wp_posts;
		$wp_posts[1] = (object)['ID' => 1];

		$settings = SettingsRepository::get_instance();
		$settings->set_string('plugin_password', 'secret')->save();

		$storage = new DummyStorageContent($settings);
		$controller = new ContentController($storage, $settings);

		$data = [
			'workflow' => 'summary',
			'results'  => [1 => ['summary' => 'ok', 'date' => '2025-01-01']],
		];
		$req = new DummyRequest($data, ['X-WP-App-Password' => 'secret']);

		$this->assertTrue($controller->permissions($req));

		$res = $controller->handle($req);

		$this->assertInstanceOf(WP_REST_Response::class, $res);
		$this->assertSame(200, $res->status);
		$this->assertNotEmpty($storage->stored);
	}

	public function test_valid_nonce_returns_rest_response(): void {
		global $wp_posts;
		$wp_posts[2] = (object)['ID' => 2];

		$settings = SettingsRepository::get_instance();
		$storage  = new DummyStorageContent($settings);
		$controller = new ContentController($storage, $settings);

		$data = [
			'workflow' => 'quiz',
			'results'  => [2 => ['questions' => [], 'date' => '2025-01-02']],
		];
		$req = new DummyRequest($data, ['X-WP-Nonce' => 'valid']);

		$this->assertTrue($controller->permissions($req));
		$res = $controller->handle($req);

		$this->assertInstanceOf(WP_REST_Response::class, $res);
		$this->assertSame(200, $res->status);
		$this->assertNotEmpty($storage->stored);
	}

	public function test_invalid_credentials_return_error(): void {
		$settings = SettingsRepository::get_instance();
		$settings->set_string('plugin_password', 'secret')->save();

		$storage  = new DummyStorageContent($settings);
		$controller = new ContentController($storage, $settings);
		$data = ['workflow' => 'quiz', 'results' => [3 => ['questions' => []]]];
		$req = new DummyRequest($data, ['X-WP-App-Password' => 'wrong']);

		$allowed = $controller->permissions($req);
		$res = $allowed ? $controller->handle($req)
						: new WP_Error('rest_forbidden', 'forbidden', ['status' => 401]);

		$this->assertFalse($allowed);
		$this->assertInstanceOf(WP_Error::class, $res);
		$this->assertSame(401, $res->data['status']);
	}

	public function test_storage_failure_returns_error(): void {
		global $wp_posts;
		$wp_posts[4] = (object)['ID' => 4];

		$settings = SettingsRepository::get_instance();
		$storage  = new FailingStorage($settings);
		$controller = new ContentController($storage, $settings);

		$data = [
			'workflow' => 'summary',
			'results'  => [4 => ['summary' => 'bad']],
		];
		$req = new DummyRequest($data, ['X-WP-Nonce' => 'valid']);

		$this->assertTrue($controller->permissions($req));
		$res = $controller->handle($req);

		$this->assertInstanceOf(\WP_Error::class, $res);
	}
	}
}
