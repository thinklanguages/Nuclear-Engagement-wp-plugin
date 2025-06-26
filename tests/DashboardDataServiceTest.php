<?php
namespace NuclearEngagement\Services {
    class LoggingService {
        public static array $logs = [];
        public static function log(string $msg): void { self::$logs[] = $msg; }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Services\DashboardDataService;

if ( ! function_exists( 'date_i18n' ) ) {
    function date_i18n( $format, $timestamp ) { return date( $format, $timestamp ); }
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}

// ------------------------------------------------------
// Simple object cache stubs
// ------------------------------------------------------
if ( ! isset( $GLOBALS['wp_cache'] ) ) {
    $GLOBALS['wp_cache'] = [];
}
if ( ! isset( $GLOBALS['transients'] ) ) {
    $GLOBALS['transients'] = [];
}
if ( ! function_exists( 'wp_cache_get' ) ) {
    function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
        $found = isset( $GLOBALS['wp_cache'][ $group ][ $key ] );
        return $GLOBALS['wp_cache'][ $group ][ $key ] ?? false;
    }
}
if ( ! function_exists( 'wp_cache_set' ) ) {
    function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
        $GLOBALS['wp_cache'][ $group ][ $key ] = $value;
    }
}
if ( ! function_exists( 'wp_cache_flush_group' ) ) {
    function wp_cache_flush_group( $group ) {
        unset( $GLOBALS['wp_cache'][ $group ] );
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        return $GLOBALS['transients'][ $key ] ?? false;
    }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $ttl = 0 ) {
        $GLOBALS['transients'][ $key ] = $value;
    }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['transients'][ $key ] );
    }
}

class DashboardDataServiceTest extends TestCase {
    protected function setUp(): void {
        global $wp_options, $wp_posts, $wpdb;
        $wp_options = $wp_posts = [];
        $wpdb = null;
    }

    public function test_get_group_counts_returns_results(): void {
        global $wpdb;
        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public array $args = [];
            public function prepare( $query, ...$args ) { $this->args = $args; return 'SQL'; }
            public function get_results( $sql, $output ) { return [ [ 'g' => 'draft', 'w' => 'with', 'c' => 2 ] ]; }
        };

        $svc = new DashboardDataService();
        $res = $svc->get_group_counts( 'p.post_status', 'key', ['post'], ['draft'] );

        $this->assertSame( [ [ 'g' => 'draft', 'w' => 'with', 'c' => 2 ] ], $res );
        $this->assertSame( ['key','post','draft'], $wpdb->args );
    }

    public function test_get_dual_counts_returns_results(): void {
        global $wpdb;
        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public array $args = [];
            public function prepare( $query, ...$args ) { $this->args = $args; return 'SQL'; }
            public function get_results( $sql, $output ) { return [ [ 'g' => 1, 'quiz_with' => 1, 'quiz_without' => 0, 'summary_with' => 1, 'summary_without' => 0 ] ]; }
        };

        $svc = new DashboardDataService();
        $res = $svc->get_dual_counts( 'p.post_author', ['post'], ['publish'] );

        $this->assertSame( 1, $res[0]['quiz_with'] );
        $this->assertSame( ['post','publish'], $wpdb->args );
    }

    public function test_get_scheduled_generations_formats_results(): void {
        global $wp_options, $wp_posts;
        $wp_posts[5] = (object) [ 'post_title' => 'Post' ];
        $wp_options['nuclen_active_generations'] = [
            'gen' => [
                'post_ids' => [5],
                'workflow_type' => 'quiz',
                'attempt' => 3,
                'next_poll' => 1000,
            ],
        ];
        $wp_options['date_format'] = 'Y-m-d';
        $wp_options['time_format'] = 'H:i';

        $svc = new DashboardDataService();
        $tasks = $svc->get_scheduled_generations();

        $this->assertCount( 1, $tasks );
        $this->assertSame( 'Post', $tasks[0]['post_title'] );
        $this->assertSame( 'quiz', $tasks[0]['workflow_type'] );
        $this->assertSame( 3, $tasks[0]['attempt'] );
        $this->assertSame( date( 'Y-m-d H:i', 1000 ), $tasks[0]['next_poll'] );
    }

    public function test_group_counts_are_cached(): void {
        global $wpdb;
        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public array $args = [];
            public int $calls = 0;
            public function prepare( $query, ...$args ) { $this->args = $args; return 'SQL'; }
            public function get_results( $sql, $output ) { $this->calls++; return [ [ 'g' => 'draft', 'w' => 'with', 'c' => 2 ] ]; }
        };

        $svc = new DashboardDataService();
        $svc->get_group_counts( 'p.post_status', 'key', ['post'], ['draft'] );
        $this->assertSame( 1, $wpdb->calls );

        $svc->get_group_counts( 'p.post_status', 'key', ['post'], ['draft'] );
        $this->assertSame( 1, $wpdb->calls );

        \NuclearEngagement\Core\InventoryCache::clear();

        $svc->get_group_counts( 'p.post_status', 'key', ['post'], ['draft'] );
        $this->assertSame( 2, $wpdb->calls );
    }

    public function test_dual_counts_are_cached(): void {
        global $wpdb;
        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public array $args = [];
            public int $calls = 0;
            public function prepare( $query, ...$args ) { $this->args = $args; return 'SQL'; }
            public function get_results( $sql, $output ) { $this->calls++; return [ [ 'g' => 1, 'quiz_with' => 1, 'quiz_without' => 0, 'summary_with' => 1, 'summary_without' => 0 ] ]; }
        };

        $svc = new DashboardDataService();
        $svc->get_dual_counts( 'p.post_author', ['post'], ['publish'] );
        $this->assertSame( 1, $wpdb->calls );

        $svc->get_dual_counts( 'p.post_author', ['post'], ['publish'] );
        $this->assertSame( 1, $wpdb->calls );

        \NuclearEngagement\Core\InventoryCache::clear();

        $svc->get_dual_counts( 'p.post_author', ['post'], ['publish'] );
        $this->assertSame( 2, $wpdb->calls );
    }

    public function test_group_counts_returns_empty_on_error(): void {
        global $wpdb, $wp_cache, $transients;
        $wp_cache = $transients = [];
        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public string $last_error = '';
            public function prepare( $q, ...$a ) { return 'SQL'; }
            public function get_results( $sql, $output ) { $this->last_error = 'fail'; return []; }
        };

        \NuclearEngagement\Services\LoggingService::$logs = [];

        $svc = new DashboardDataService();
        $res = $svc->get_group_counts( 'p.post_status', 'key', ['post'], ['draft'] );
        $this->assertSame( [], $res );
        $this->assertSame( ['Dashboard query error: fail'], \NuclearEngagement\Services\LoggingService::$logs );
        $this->assertEmpty( $wp_cache );
        $this->assertEmpty( $transients );
    }

    public function test_dual_counts_returns_empty_on_error(): void {
        global $wpdb, $wp_cache, $transients;
        $wp_cache = $transients = [];
        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public string $last_error = '';
            public function prepare( $q, ...$a ) { return 'SQL'; }
            public function get_results( $sql, $output ) { $this->last_error = 'fail'; return []; }
        };

        \NuclearEngagement\Services\LoggingService::$logs = [];

        $svc = new DashboardDataService();
        $res = $svc->get_dual_counts( 'p.post_author', ['post'], ['publish'] );
        $this->assertSame( [], $res );
        $this->assertSame( ['Dashboard query error: fail'], \NuclearEngagement\Services\LoggingService::$logs );
        $this->assertEmpty( $wp_cache );
        $this->assertEmpty( $transients );
    }
}
}
