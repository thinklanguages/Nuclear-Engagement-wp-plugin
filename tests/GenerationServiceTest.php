<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\GenerationService;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Requests\GenerateRequest;
use NuclearEngagement\Services\ApiException;

if (!function_exists('get_posts')) {
    function get_posts($args) {
        $ids = $args['post__in'] ?? [];
        $posts = [];
        foreach ($ids as $id) {
            if (isset($GLOBALS['wp_posts'][$id])) {
                $posts[] = $GLOBALS['wp_posts'][$id];
            }
        }
        return $posts;
    }
}

class GSRemoteApi {
    public function sendPostsToGenerate(array $data): array {
        throw new ApiException('api fail', 401);
    }
    public function fetchUpdates(string $id): array { return []; }
}

class GSStorage {
    public array $stored = [];
    public function storeResults(array $r, string $t): void { $this->stored[] = [$r,$t]; }
}

class GenerationServiceTest extends TestCase {
    protected function setUp(): void {
        global $wp_posts, $wp_options, $wp_autoload;
        $wp_posts = $wp_options = $wp_autoload = [];
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
    }
}
