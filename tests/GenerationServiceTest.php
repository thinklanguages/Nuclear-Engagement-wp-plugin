<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\GenerationService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Requests\GenerateRequest;

class FailingRemoteApiService {
    public function sendPostsToGenerate(array $data): array { throw new \RuntimeException('api boom', 500); }
    public function fetchUpdates(string $id): array { return []; }
}
class DummyStorageService extends ContentStorageService {
    public array $stored = [];
    public function storeResults(array $results, string $workflowType): void {
        $this->stored[] = [$results, $workflowType];
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args) {
        global $wp_posts;
        $posts = [];
        foreach ($args['post__in'] as $id) {
            if (isset($wp_posts[$id])) {
                $posts[] = $wp_posts[$id];
            }
        }
        return $posts;
    }
}

class GenerationServiceTest extends TestCase {
    protected function setUp(): void {
        global $wp_options, $wp_autoload, $wp_posts, $wp_meta, $wp_events;
        $wp_options = $wp_autoload = $wp_posts = $wp_meta = $wp_events = [];
        SettingsRepository::_reset_for_tests();
    }

    private function makeService(): GenerationService {
        $settings = SettingsRepository::get_instance();
        $api = new FailingRemoteApiService();
        $storage = new DummyStorageService($settings);
        return new GenerationService($settings, $api, $storage);
    }

    public function test_generate_content_handles_api_error(): void {
        global $wp_posts;
        $wp_posts[1] = (object)[
            'ID' => 1,
            'post_title' => 'T',
            'post_content' => 'C',
            'post_type' => 'post',
            'post_status' => 'publish'
        ];
        $service = $this->makeService();
        $req = new GenerateRequest();
        $req->postIds = [1];
        $req->workflowType = 'quiz';
        $req->summaryFormat = 'paragraph';
        $req->summaryLength = 10;
        $req->summaryItems = 1;
        $req->generationId = 'gen1';
        $req->postType = 'post';
        $req->postStatus = 'publish';

        $res = $service->generateContent($req);
        $this->assertFalse($res->success);
        $this->assertSame('api boom', $res->error);
        $this->assertSame(500, $res->statusCode);
    }
}
