<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\DashboardDataService;

if ( ! function_exists( 'date_i18n' ) ) {
    function date_i18n( $format, $timestamp ) { return date( $format, $timestamp ); }
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
}
