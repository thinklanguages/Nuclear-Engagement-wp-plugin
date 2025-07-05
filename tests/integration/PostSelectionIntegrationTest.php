<?php
declare(strict_types=1);

namespace NuclearEngagement\Tests\Integration;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Controller\Ajax\PostsCountController;
use NuclearEngagement\Services\PostsQueryService;
use NuclearEngagement\Requests\PostsCountRequest;

// WordPress function stubs
if (!function_exists('check_ajax_referer')) { 
	function check_ajax_referer($a, $f, $d = false) { 
		return $GLOBALS['ajax_nonce_valid'] ?? true; 
	} 
}
if (!function_exists('current_user_can')) { 
	function current_user_can($c) { 
		return $GLOBALS['user_can'] ?? true; 
	} 
}
if (!function_exists('wp_send_json_success')) { 
	function wp_send_json_success($data) { 
		$GLOBALS['json_response'] = ['success' => true, 'data' => $data]; 
	} 
}
if (!function_exists('wp_send_json_error')) { 
	function wp_send_json_error($data, $code = 0) { 
		$GLOBALS['json_response'] = ['success' => false, 'data' => $data, 'code' => $code]; 
	} 
}
if (!function_exists('status_header')) { 
	function status_header($code) { 
		$GLOBALS['status_header'] = $code; 
	} 
}
if (!function_exists('sanitize_text_field')) { 
	function sanitize_text_field($t) { 
		return trim(strip_tags($t)); 
	} 
}
if (!function_exists('wp_unslash')) { 
	function wp_unslash($d) { 
		return is_string($d) ? stripslashes($d) : 
			(is_array($d) ? array_map('stripslashes', $d) : $d); 
	} 
}
if (!function_exists('absint')) { 
	function absint($v) { 
		return abs(intval($v)); 
	} 
}
if (!function_exists('__')) { 
	function __($t, $d = null) { 
		return $t; 
	} 
}
if (!function_exists('get_option')) { 
	function get_option($option, $default = false) { 
		return $GLOBALS['wp_options'][$option] ?? $default;
	} 
}
if (!function_exists('wp_cache_get')) {
	function wp_cache_get($key, $group = '', $force = false, &$found = null) {
		$found = false;
		return false;
	}
}
if (!function_exists('wp_cache_set')) {
	function wp_cache_set($key, $value, $group = '', $ttl = 0) {
		return true;
	}
}
if (!function_exists('get_transient')) {
	function get_transient($key) { 
		return false; 
	}
}
if (!function_exists('set_transient')) {
	function set_transient($key, $value, $ttl = 0) { 
		return true; 
	}
}
if (!function_exists('get_current_blog_id')) { 
	function get_current_blog_id() { 
		return 1; 
	} 
}
if (!function_exists('wp_json_encode')) { 
	function wp_json_encode($data) { 
		return json_encode($data); 
	} 
}

// Mock WPDB for integration tests
class MockWPDB {
		public $posts = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public $term_relationships = 'wp_term_relationships';
		public $term_taxonomy = 'wp_term_taxonomy';
		public $last_error = '';
		public $queries = [];
		
		// Mock posts data
		private $mock_posts = [
			// Posts
			['ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1],
			['ID' => 2, 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => 1],
			['ID' => 3, 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 2],
			// Pages
			['ID' => 4, 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => 1],
			['ID' => 5, 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => 1],
			// Custom post type (not allowed)
			['ID' => 6, 'post_type' => 'product', 'post_status' => 'publish', 'post_author' => 1],
			['ID' => 7, 'post_type' => 'product', 'post_status' => 'publish', 'post_author' => 1],
		];
		
		public function prepare($sql, ...$args) {
			return vsprintf($sql, $args);
		}
		
		public function get_col($query) {
			$this->queries[] = $query;
			
			$results = [];
			
			// Parse the query to determine what to return
			if (strpos($query, "p.post_type = 'post'") !== false) {
				if (strpos($query, "p.post_status = 'publish'") !== false) {
					$results = [1, 3]; // Published posts
				} else if (strpos($query, "p.post_status = 'draft'") !== false) {
					$results = [2]; // Draft posts
				} else {
					$results = [1, 2, 3]; // All posts
				}
			} else if (strpos($query, "p.post_type = 'page'") !== false) {
				$results = [4, 5]; // All pages
			} else if (strpos($query, "p.post_type = 'product'") !== false) {
				$results = [6, 7]; // Products (should be filtered out)
			}
			
			// Handle LIMIT/OFFSET for pagination
			if (preg_match('/LIMIT (\d+) OFFSET (\d+)/', $query, $matches)) {
				$limit = (int)$matches[1];
				$offset = (int)$matches[2];
				$results = array_slice($results, $offset, $limit);
			}
			
			return array_map('strval', $results);
		}
	}

	require_once dirname(dirname(__DIR__)) . '/nuclear-engagement/admin/Controller/Ajax/BaseController.php';
	require_once dirname(dirname(__DIR__)) . '/nuclear-engagement/admin/Controller/Ajax/PostsCountController.php';
	require_once dirname(dirname(__DIR__)) . '/nuclear-engagement/inc/Services/PostsQueryService.php';
	require_once dirname(dirname(__DIR__)) . '/nuclear-engagement/inc/Requests/PostsCountRequest.php';
	require_once dirname(dirname(__DIR__)) . '/nuclear-engagement/inc/Modules/Summary/Summary_Service.php';

	class PostSelectionIntegrationTest extends TestCase {
		
		protected function setUp(): void {
			global $wpdb;
			$wpdb = new MockWPDB();
			
			$_POST = [];
			$GLOBALS['json_response'] = null;
			$GLOBALS['wp_options'] = [
				'nuclear_engagement_settings' => [
					'generation_post_types' => ['post', 'page']
				]
			];
			$GLOBALS['ajax_nonce_valid'] = true;
			$GLOBALS['user_can'] = true;
			
			\NuclearEngagement\Services\LoggingService::$exceptions = [];
			\NuclearEngagement\Services\LoggingService::$logs = [];
		}
		
		public function test_complete_flow_with_valid_post_type(): void {
			// Simulate user selecting 'post' type and 'publish' status
			$_POST = [
				'action' => 'nuclen_get_posts_count',
				'security' => 'valid_nonce',
				'nuclen_post_type' => 'post',
				'nuclen_post_status' => 'publish',
				'nuclen_category' => '0',
				'nuclen_author' => '0',
				'nuclen_generate_workflow' => 'quiz',
				'nuclen_allow_regenerate_data' => '0',
				'nuclen_regenerate_protected_data' => '0'
			];
			
			// Create the service and controller
			$service = new PostsQueryService();
			$controller = new PostsCountController($service);
			
			// Execute the request
			$controller->handle();
			
			// Verify response
			$this->assertNotNull($GLOBALS['json_response']);
			$this->assertTrue($GLOBALS['json_response']['success']);
			$this->assertArrayHasKey('count', $GLOBALS['json_response']['data']);
			$this->assertArrayHasKey('post_ids', $GLOBALS['json_response']['data']);
			
			// Should return 2 published posts
			$this->assertEquals(2, $GLOBALS['json_response']['data']['count']);
			$this->assertEquals(['1', '3'], $GLOBALS['json_response']['data']['post_ids']);
		}
		
		public function test_complete_flow_with_page_post_type(): void {
			$_POST = [
				'action' => 'nuclen_get_posts_count',
				'security' => 'valid_nonce',
				'nuclen_post_type' => 'page',
				'nuclen_post_status' => 'publish',
				'nuclen_category' => '0',
				'nuclen_author' => '0',
				'nuclen_generate_workflow' => 'summary',
				'nuclen_allow_regenerate_data' => '1',
				'nuclen_regenerate_protected_data' => '0'
			];
			
			$service = new PostsQueryService();
			$controller = new PostsCountController($service);
			
			$controller->handle();
			
			$this->assertTrue($GLOBALS['json_response']['success']);
			$this->assertEquals(2, $GLOBALS['json_response']['data']['count']);
			$this->assertEquals(['4', '5'], $GLOBALS['json_response']['data']['post_ids']);
		}
		
		public function test_complete_flow_rejects_disallowed_post_type(): void {
			$_POST = [
				'action' => 'nuclen_get_posts_count',
				'security' => 'valid_nonce',
				'nuclen_post_type' => 'product', // Not in allowed types
				'nuclen_post_status' => 'publish',
				'nuclen_category' => '0',
				'nuclen_author' => '0',
				'nuclen_generate_workflow' => 'quiz',
				'nuclen_allow_regenerate_data' => '0',
				'nuclen_regenerate_protected_data' => '0'
			];
			
			$service = new PostsQueryService();
			$controller = new PostsCountController($service);
			
			$controller->handle();
			
			// Should be rejected
			$this->assertFalse($GLOBALS['json_response']['success']);
			$this->assertStringContainsString('not allowed', $GLOBALS['json_response']['data']);
		}
		
		public function test_complete_flow_with_no_ajax_permission(): void {
			$GLOBALS['ajax_nonce_valid'] = false;
			
			$_POST = [
				'action' => 'nuclen_get_posts_count',
				'security' => 'invalid_nonce',
				'nuclen_post_type' => 'post',
				'nuclen_post_status' => 'publish'
			];
			
			$service = new PostsQueryService();
			$controller = new PostsCountController($service);
			
			$controller->handle();
			
			// Should be rejected due to invalid nonce
			$this->assertFalse($GLOBALS['json_response']['success']);
		}
		
		public function test_complete_flow_with_draft_posts(): void {
			$_POST = [
				'action' => 'nuclen_get_posts_count',
				'security' => 'valid_nonce',
				'nuclen_post_type' => 'post',
				'nuclen_post_status' => 'draft',
				'nuclen_category' => '0',
				'nuclen_author' => '0',
				'nuclen_generate_workflow' => 'quiz',
				'nuclen_allow_regenerate_data' => '0',
				'nuclen_regenerate_protected_data' => '0'
			];
			
			$service = new PostsQueryService();
			$controller = new PostsCountController($service);
			
			$controller->handle();
			
			$this->assertTrue($GLOBALS['json_response']['success']);
			$this->assertEquals(1, $GLOBALS['json_response']['data']['count']);
			$this->assertEquals(['2'], $GLOBALS['json_response']['data']['post_ids']);
		}
		
		public function test_complete_flow_with_empty_settings(): void {
			// No settings means default to ['post'] only
			$GLOBALS['wp_options']['nuclear_engagement_settings'] = [];
			
			$_POST = [
				'action' => 'nuclen_get_posts_count',
				'security' => 'valid_nonce',
				'nuclen_post_type' => 'page', // Should be rejected
				'nuclen_post_status' => 'publish',
				'nuclen_category' => '0',
				'nuclen_author' => '0',
				'nuclen_generate_workflow' => 'quiz',
				'nuclen_allow_regenerate_data' => '0',
				'nuclen_regenerate_protected_data' => '0'
			];
			
			$service = new PostsQueryService();
			$controller = new PostsCountController($service);
			
			$controller->handle();
			
			// Page should be rejected as only 'post' is allowed by default
			$this->assertFalse($GLOBALS['json_response']['success']);
			$this->assertStringContainsString('not allowed', $GLOBALS['json_response']['data']);
		}
		
		public function test_complete_flow_handles_database_errors(): void {
			global $wpdb;
			$wpdb->last_error = 'Connection timeout';
			
			$_POST = [
				'action' => 'nuclen_get_posts_count',
				'security' => 'valid_nonce',
				'nuclen_post_type' => 'post',
				'nuclen_post_status' => 'publish',
				'nuclen_category' => '0',
				'nuclen_author' => '0',
				'nuclen_generate_workflow' => 'quiz',
				'nuclen_allow_regenerate_data' => '0',
				'nuclen_regenerate_protected_data' => '0'
			];
			
			$service = new PostsQueryService();
			$controller = new PostsCountController($service);
			
			$controller->handle();
			
			// Should still succeed but log the error
			$this->assertTrue($GLOBALS['json_response']['success']);
			$this->assertContains('Posts query error: Connection timeout', \NuclearEngagement\Services\LoggingService::$logs);
		}
	}