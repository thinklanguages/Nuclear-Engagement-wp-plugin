<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\PostDataFetcher;

namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static function log(string $msg): void { self::$logs[] = $msg; }
    }
}

namespace {
    class PDF_WPDB {
        public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta';
        public string $last_error = '';
        public array $args = [];
        public function prepare($sql, ...$args) { $this->args = $args; return 'SQL'; }
        public function get_results($sql) { $this->last_error = 'fail'; return []; }
    }

    require_once __DIR__ . '/../nuclear-engagement/inc/Services/PostDataFetcher.php';

    class PostDataFetcherTest extends TestCase {
        protected function setUp(): void {
            global $wpdb;
            $wpdb = new PDF_WPDB();
            \NuclearEngagement\Services\LoggingService::$logs = [];
        }

        public function test_fetch_logs_error_and_returns_empty_array(): void {
            $fetcher = new PostDataFetcher();
            $rows = $fetcher->fetch([1]);
            $this->assertSame([], $rows);
            $this->assertSame(['Post fetch error: fail'], \NuclearEngagement\Services\LoggingService::$logs);
        }
    }
}
