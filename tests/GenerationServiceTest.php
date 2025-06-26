<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\GenerationService;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Requests\GenerateRequest;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Modules\Summary\Summary_Service;

class GS_WPDB {
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public array $prepared_sqls = [];
    public array $args = [];
    public array $results_sqls = [];

    public function prepare($sql, ...$args) {
        $this->prepared_sqls[] = $sql;
        $this->args = $args;
        return $sql;
    }

    public function get_results($sql) {
        $this->results_sqls[] = $sql;
        $ids = array_slice($this->args, 2);
        $rows = [];
        foreach ($ids as $id) {
            if (!isset($GLOBALS['wp_posts'][$id])) { continue; }
            $p = $GLOBALS['wp_posts'][$id];
            if ($p->post_status !== 'publish') { continue; }
            if (!empty($GLOBALS['wp_meta'][$id]['nuclen_quiz_protected']) || !empty($GLOBALS['wp_meta'][$id][Summary_Service::PROTECTED_KEY])) { continue; }
            $rows[] = (object) [
                'ID' => $p->ID,
                'post_title' => $p->post_title,
                'post_content' => $p->post_content,
            ];
        }
        return $rows;
    }
}

class GSRemoteApi {
    public function send_posts_to_generate(array $data): array {
        throw new ApiException('api fail', 401);
    }
    public function fetch_updates(string $id): array { return []; }
}

class GSStorage {
    public array $stored = [];
    public function storeResults(array $r, string $t): array { $this->stored[] = [$r,$t]; return array_fill_keys(array_keys($r), true); }
}

class GenerationServiceTest extends TestCase {
    protected function setUp(): void {
        global $wp_posts, $wp_options, $wp_autoload, $wp_meta, $wpdb;
        $wp_posts = $wp_options = $wp_autoload = $wp_meta = [];
        $wpdb = new GS_WPDB();
        SettingsRepository::reset_for_tests();
    }

    private function makeService(): GenerationService {
        $settings = SettingsRepository::get_instance();
        $api = new GSRemoteApi();
        $storage = new GSStorage();
        return new GenerationService($settings, $api, $storage);
    }

    public function test_generate_content_returns_error_response_on_exception(): void {
        global $wp_posts;
        $wp_posts[1] = (object)[
            'ID' => 1,
            'post_title' => 'T',
            'post_content' => 'C',
            'post_type' => 'post',
            'post_status' => 'publish',
        ];
        $req = new GenerateRequest();
        $req->postIds = [1];
        $req->workflowType = 'quiz';
        $req->generationId = 'gid';
        $req->postType = 'post';
        $req->postStatus = 'publish';

        $service = $this->makeService();
        $res = $service->generateContent($req);
        $this->assertFalse($res->success);
        $this->assertSame('api fail', $res->error);
        $this->assertSame(401, $res->statusCode);

        global $wpdb;
        $this->assertNotEmpty($wpdb->prepared_sqls);
        $this->assertStringContainsString('SELECT ID, post_title, post_content', $wpdb->prepared_sqls[0]);
    }

    public function test_generate_content_sends_posts_from_wpdb(): void {
        global $wp_posts, $wpdb;

        $wp_posts[1] = (object) [
            'ID' => 1,
            'post_title' => 'A',
            'post_content' => '<b>C1</b>',
            'post_type' => 'post',
            'post_status' => 'publish',
        ];
        $wp_posts[2] = (object) [
            'ID' => 2,
            'post_title' => 'B',
            'post_content' => '<i>C2</i>',
            'post_type' => 'post',
            'post_status' => 'publish',
        ];

        $api = new class {
            public array $data = [];
            public function send_posts_to_generate(array $d): array { $this->data = $d['posts']; return []; }
            public function fetch_updates(string $id): array { return []; }
        };
        $storage = new GSStorage();
        $service = new GenerationService(SettingsRepository::get_instance(), $api, $storage);

        $req = new GenerateRequest();
        $req->postIds = [1, 2];
        $req->workflowType = 'quiz';
        $req->generationId = 'gid';
        $req->postType = 'post';
        $req->postStatus = 'publish';

        $service->generateContent($req);

        $this->assertCount(1, $wpdb->results_sqls);
        $this->assertSame([
            ['id' => 1, 'title' => 'A', 'content' => 'C1'],
            ['id' => 2, 'title' => 'B', 'content' => 'C2'],
        ], $api->data);
    }

    public function test_generate_content_chunks_large_id_lists(): void {
        if ( ! defined( 'NUCLEN_POST_FETCH_CHUNK' ) ) {
            define( 'NUCLEN_POST_FETCH_CHUNK', 2 );
        }

        global $wp_posts, $wpdb;
        for ( $i = 1; $i <= 5; $i++ ) {
            $wp_posts[ $i ] = (object) [
                'ID' => $i,
                'post_title' => 'T' . $i,
                'post_content' => 'C' . $i,
                'post_type' => 'post',
                'post_status' => 'publish',
            ];
        }

        $api = new class {
            public array $posts = [];
            public function send_posts_to_generate( array $d ): array {
                $this->posts = $d['posts'];
                return [];
            }
            public function fetch_updates( string $id ): array { return []; }
        };
        $storage = new GSStorage();
        $service = new GenerationService( SettingsRepository::get_instance(), $api, $storage );

        $req = new GenerateRequest();
        $req->postIds = [1, 2, 3, 4, 5];
        $req->workflowType = 'quiz';
        $req->generationId = 'gid';
        $req->postType = 'post';
        $req->postStatus = 'publish';

        $service->generateContent( $req );

        $this->assertCount( 3, $wpdb->results_sqls );
        $this->assertCount( 5, $api->posts );
    }
}
