<?php
namespace NuclearEngagement\Services {
	// Simplified LoggingService stub
	class LoggingService {
		public static array $logs = [];
		public static function log(string $msg): void { self::$logs[] = $msg; }
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\PostsQueryService;
	use NuclearEngagement\Requests\PostsCountRequest;
	use NuclearEngagement\Modules\Summary\Summary_Service;
	if (!defined('MINUTE_IN_SECONDS')) { define('MINUTE_IN_SECONDS', 60); }

	// ------------------------------------------------------
	// Object cache stubs
	// ------------------------------------------------------
	if (!isset($GLOBALS['wp_cache'])) { $GLOBALS['wp_cache'] = []; }
	if (!isset($GLOBALS['transients'])) { $GLOBALS['transients'] = []; }
	if (!function_exists('wp_cache_get')) {
		function wp_cache_get($key, $group = '', $force = false, &$found = null) {
			$found = isset($GLOBALS['wp_cache'][$group][$key]);
			return $GLOBALS['wp_cache'][$group][$key] ?? false;
		}
	}
	if (!function_exists('wp_cache_set')) {
		function wp_cache_set($key, $value, $group = '', $ttl = 0) {
			$GLOBALS['wp_cache'][$group][$key] = $value;
		}
	}
	if (!function_exists('wp_cache_flush_group')) {
		function wp_cache_flush_group($group) { unset($GLOBALS['wp_cache'][$group]); }
	}
	if (!function_exists('wp_cache_flush')) {
		function wp_cache_flush() { $GLOBALS['wp_cache'] = []; }
	}
	if (!function_exists('get_transient')) {
		function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
	}
	if (!function_exists('set_transient')) {
		function set_transient($key, $value, $ttl = 0) { $GLOBALS['transients'][$key] = $value; }
	}

	// Basic WordPress stubs
	if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return $t; } }
	if (!function_exists('absint')) { function absint($v){ return abs(intval($v)); } }
	if (!function_exists('wp_unslash')) { function wp_unslash($v){ return $v; } }
	if (!function_exists('get_option')) { function get_option($opt, $def = false) { return $GLOBALS['wp_options'][$opt] ?? $def; } }
	if (!function_exists('update_option')) { function update_option($opt, $val, $load = true) { $GLOBALS['wp_options'][$opt] = $val; } }
	if (!function_exists('get_current_blog_id')) { function get_current_blog_id() { return 1; } }
	if (!function_exists('wp_json_encode')) { function wp_json_encode($data) { return json_encode($data); } }

	// ------------------------------------------------------
	// WPDB stub for getPostsCount
	// ------------------------------------------------------
	class PQ_WPDB {
		public $posts = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public $term_relationships = 'wp_term_relationships';
		public $term_taxonomy = 'wp_term_taxonomy';
		public $last_error = '';
		public int $get_var_calls = 0;
		public array $ids = [];
		public int $count = 0;
		public function prepare($sql, ...$args) {
			return vsprintf($sql, $args);
		}
		public function get_var($sql) {
			$this->get_var_calls++;
			return $this->count;
		}
		public function get_col($sql) {
			$limit = 1000; $offset = 0;
			if (preg_match('/LIMIT (\d+) OFFSET (\d+)/', $sql, $m)) {
				$limit = (int)$m[1];
				$offset = (int)$m[2];
			}
			return array_slice($this->ids, $offset, $limit);
		}
	}

	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Services/PostsQueryService.php';
	require_once dirname(__DIR__) . '/nuclear-engagement/inc/Requests/PostsCountRequest.php';

	class PostsQueryServiceTest extends TestCase {
		protected function setUp(): void {
			global $wp_cache, $transients, $wp_options;
			$wp_cache = $transients = $wp_options = [];
		}

		public function test_build_query_args_full_request(): void {
			$req = new PostsCountRequest();
			$req->postType = 'post';
			$req->postStatus = 'draft';
			$req->categoryId = 5;
			$req->authorId = 3;
			$req->workflow = 'summary';
			$req->allowRegenerate = false;
			$req->regenerateProtected = false;

			$svc = new PostsQueryService();
			$args = $svc->buildQueryArgs($req);

			$expected_meta = [
				'relation' => 'AND',
				[ 'key' => Summary_Service::META_KEY, 'compare' => 'NOT EXISTS' ],
				[
					'relation' => 'OR',
					[ 'key' => Summary_Service::PROTECTED_KEY, 'compare' => 'NOT EXISTS' ],
					[ 'key' => Summary_Service::PROTECTED_KEY, 'value' => '1', 'compare' => '!=' ],
				],
			];

			$this->assertSame([
				'post_type' => 'post',
				'posts_per_page' => -1,
				'post_status' => 'draft',
				'fields' => 'ids',
				'cat' => 5,
				'author' => 3,
				'meta_query' => $expected_meta,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'cache_results' => false,
			], $args);
		}

		public function test_build_query_args_no_meta(): void {
			$req = new PostsCountRequest();
			$req->postType = 'page';
			$req->postStatus = 'any';
			$req->workflow = 'quiz';
			$req->allowRegenerate = true;
			$req->regenerateProtected = true;

			$svc = new PostsQueryService();
			$args = $svc->buildQueryArgs($req);

			$this->assertArrayNotHasKey('meta_query', $args);
			$this->assertSame('page', $args['post_type']);
			$this->assertSame('any', $args['post_status']);
		}

		public function test_build_query_args_empty_post_type_defaults_to_post(): void {
			$req = new PostsCountRequest();
			$req->postType = ''; // Empty post type
			$req->postStatus = 'publish';

			$svc = new PostsQueryService();
			$args = $svc->buildQueryArgs($req);

			// Should default to 'post'
			$this->assertSame('post', $args['post_type']);
		}

		public function test_get_posts_count_caches_results(): void {
			global $wpdb, $wp_cache, $transients;
			$wpdb = new PQ_WPDB();
			$wpdb->count = 2;
			$wpdb->ids = [1,2];

			$svc = new PostsQueryService();
			$req = new PostsCountRequest();
			$req->postType = 'post';
			$req->workflow = 'quiz';

			$res1 = $svc->getPostsCount($req);
			$this->assertSame(['count'=>2,'post_ids'=>[1,2]], $res1);

			// Second call should use cache
			$calls_before = count($wpdb->get_col(''));
			$res2 = $svc->getPostsCount($req);
			$this->assertSame($res1, $res2);
			$this->assertSame($calls_before, count($wpdb->get_col('')), 'uses cached result');

			PostsQueryService::clear_cache();

			$res3 = $svc->getPostsCount($req);
			$this->assertSame($res1, $res3);
		}

		public function test_get_posts_count_with_empty_post_type(): void {
			global $wpdb;
			$wpdb = new PQ_WPDB();
			$wpdb->ids = [10, 20, 30];

			$svc = new PostsQueryService();
			$req = new PostsCountRequest();
			$req->postType = ''; // Empty post type
			$req->postStatus = 'publish';

			$result = $svc->getPostsCount($req);

			// Should still work and return results
			$this->assertSame(['count' => 3, 'post_ids' => [10, 20, 30]], $result);
		}

		public function test_get_posts_count_handles_large_result_sets(): void {
			global $wpdb;
			$wpdb = new PQ_WPDB();
			// Simulate 2500 posts (will require 3 batches of 1000)
			$wpdb->ids = range(1, 2500);

			$svc = new PostsQueryService();
			$req = new PostsCountRequest();
			$req->postType = 'post';

			$result = $svc->getPostsCount($req);

			$this->assertSame(2500, $result['count']);
			$this->assertCount(2500, $result['post_ids']);
			$this->assertSame(range(1, 2500), $result['post_ids']);
		}

		public function test_get_posts_count_handles_duplicate_ids(): void {
			global $wpdb;
			$wpdb = new PQ_WPDB();
			// Return duplicates
			$wpdb->ids = [1, 2, 3, 2, 1, 4, 3];

			$svc = new PostsQueryService();
			$req = new PostsCountRequest();
			$req->postType = 'post';

			$result = $svc->getPostsCount($req);

			// Should deduplicate
			$this->assertSame(4, $result['count']);
			$this->assertSame([1, 2, 3, 4], $result['post_ids']);
		}

		public function test_get_posts_count_logs_db_errors(): void {
			global $wpdb;
			$wpdb = new PQ_WPDB();
			$wpdb->last_error = 'Database connection lost';
			$wpdb->ids = [];

			\NuclearEngagement\Services\LoggingService::$logs = [];

			$svc = new PostsQueryService();
			$req = new PostsCountRequest();
			$req->postType = 'post';

			$result = $svc->getPostsCount($req);

			// Should still return empty result
			$this->assertSame(['count' => 0, 'post_ids' => []], $result);
			// Should log the error
			$this->assertContains('Posts query error: Database connection lost', \NuclearEngagement\Services\LoggingService::$logs);
		}
	}
}
