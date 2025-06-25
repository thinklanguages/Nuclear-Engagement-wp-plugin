<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\PostsQueryService;
use NuclearEngagement\Requests\PostsCountRequest;

namespace NuclearEngagement\Services {
    // Simplified LoggingService stub
    class LoggingService {
        public static array $logs = [];
        public static function log(string $msg): void { self::$logs[] = $msg; }
    }
}

namespace {
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
                [ 'key' => 'nuclen-summary-data', 'compare' => 'NOT EXISTS' ],
                [
                    'relation' => 'OR',
                    [ 'key' => 'nuclen_summary_protected', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => 'nuclen_summary_protected', 'value' => '1', 'compare' => '!=' ],
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
            $this->assertSame(1, $wpdb->get_var_calls);

            $res2 = $svc->getPostsCount($req);
            $this->assertSame($res1, $res2);
            $this->assertSame(1, $wpdb->get_var_calls, 'uses cached result');

            PostsQueryService::clear_cache();

            $res3 = $svc->getPostsCount($req);
            $this->assertSame($res1, $res3);
            $this->assertSame(2, $wpdb->get_var_calls, 'cache cleared');
        }
    }
}
