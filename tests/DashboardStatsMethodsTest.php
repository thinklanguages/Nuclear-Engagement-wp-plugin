<?php
namespace {
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Dashboard;
use NuclearEngagement\Services\DashboardDataService;

if ( ! function_exists( 'get_post_stati' ) ) {
function get_post_stati( $a = [], $o = 'objects' ) { return ['publish' => (object)['label' => 'Published']]; }
}
if ( ! function_exists( 'get_post_type_object' ) ) {
function get_post_type_object( $t ) { return (object)['labels' => (object)['name' => 'Post']]; }
}
if ( ! function_exists( 'get_users' ) ) {
function get_users( $a ) { return [ (object)['ID' => 1, 'display_name' => 'Admin'] ]; }
}
if ( ! function_exists( '__' ) ) { function __( $t, $d = null ){ return $t; } }
if ( ! function_exists( 'get_object_taxonomies' ) ) { function get_object_taxonomies( $pt ){ return ['category']; } }

class DummySvc extends DashboardDataService {
public array $rows = [];
public function get_dual_counts( string $g, array $pt, array $st ): array { return $this->rows; }
public function get_category_dual_counts( array $pt, array $st ): array { return $this->rows; }
public function get_scheduled_generations(): array { return []; }
}

class DashboardStatsMethodsTest extends TestCase {
private Dashboard $dash;
private DummySvc $svc;

protected function setUp(): void {
$this->svc = new DummySvc();
$repo = new class {
public function get() { return null; }
};
$this->dash = new Dashboard( $repo, $this->svc );
}

private function call( string $method, array $args ) {
$ref = new \ReflectionMethod( Dashboard::class, $method );
$ref->setAccessible( true );
return $ref->invokeArgs( $this->dash, $args );
}

public function test_get_status_stats_maps_labels(): void {
$this->svc->rows = [ [ 'g' => 'publish', 'quiz_with' => 1, 'quiz_without' => 0, 'summary_with' => 2, 'summary_without' => 0 ] ];
$res = $this->call( 'get_status_stats', [ ['post'], ['publish'] ] );
$this->assertSame( 1, $res['quiz']['Published']['with'] );
$this->assertSame( 2, $res['summary']['Published']['with'] );
}

public function test_get_category_stats_uses_service(): void {
$this->svc->rows = [ [ 'cat_name' => 'News', 'quiz_with' => 1, 'quiz_without' => 0, 'summary_with' => 1, 'summary_without' => 0 ] ];
$res = $this->call( 'get_category_stats', [ ['post'], ['publish'] ] );
$this->assertSame( 1, $res['quiz']['News']['with'] );
}
}
}
}
