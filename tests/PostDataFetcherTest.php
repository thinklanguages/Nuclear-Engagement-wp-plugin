<?php
namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static function log(string $msg): void { self::$logs[] = $msg; }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Services\PostDataFetcher;
    class PDF_WPDB {
        public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta';
        public string $last_error = '';
        public array $args = [];
        public int $calls = 0;
        public function prepare($sql, ...$args) { $this->args = $args; return 'SQL'; }
        public function get_results($sql) {
            $this->calls++;
            if ($this->last_error) { return []; }
            $ids = array_slice($this->args, 2);
            $rows = [];
            foreach ($ids as $id) {
                $rows[] = (object)[ 'ID'=>$id, 'post_title'=>'T'.$id, 'post_content'=>'C'.$id ];
            }
            return $rows;
        }
    }

    if (!isset($GLOBALS['wp_cache'])) { $GLOBALS['wp_cache'] = []; }
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
    if (!function_exists('get_current_blog_id')) { function get_current_blog_id(){ return 1; } }
    if (!function_exists('add_action')) { function add_action(...$a){} }

    require_once __DIR__ . '/../nuclear-engagement/inc/Services/PostDataFetcher.php';

    class PostDataFetcherTest extends TestCase {
        protected function setUp(): void {
            global $wpdb;
            $wpdb = new PDF_WPDB();
            \NuclearEngagement\Services\LoggingService::$logs = [];
            $GLOBALS['wp_cache'] = [];
        }

        public function test_fetch_logs_error_and_returns_empty_array(): void {
            $fetcher = new PostDataFetcher();
            $rows = $fetcher->fetch([1]);
            $this->assertSame([], $rows);
            $this->assertSame(['Post fetch error: fail'], \NuclearEngagement\Services\LoggingService::$logs);
        }

        public function test_fetch_uses_cache(): void {
            global $wpdb;
            $wpdb->last_error = '';
            $fetcher = new PostDataFetcher();
            $rows1 = $fetcher->fetch([1]);
            $this->assertSame(1, $wpdb->calls);
            $rows2 = $fetcher->fetch([1]);
            $this->assertSame(1, $wpdb->calls, 'second call should use cache');
            $this->assertEquals($rows1, $rows2);
        }

        public function test_clear_cache_forces_refetch(): void {
            global $wpdb;
            $wpdb->last_error = '';
            $fetcher = new PostDataFetcher();
            $fetcher->fetch([2]);
            $this->assertSame(1, $wpdb->calls);
            PostDataFetcher::clear_cache();
            $fetcher->fetch([2]);
            $this->assertSame(2, $wpdb->calls);
        }
    }
}
