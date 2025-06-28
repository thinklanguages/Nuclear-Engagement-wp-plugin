<?php
namespace NuclearEngagement\Admin {
function get_post_stati( $a = [], $o = 'objects' ) { return ['draft' => (object) ['label' => 'Draft']]; }
function get_post_type_object( $t ) { return (object) ['labels' => (object) ['name' => 'Post']]; }
function get_users( $args ) { return [ (object) ['ID' => 1, 'display_name' => 'Admin'] ]; }
function get_object_taxonomies( $pt ) { return ['category']; }
function __( $t, $d = null ) { return $t; }
}
namespace {
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Dashboard;
use NuclearEngagement\Core\SettingsRepository;

require_once __DIR__ . '/../nuclear-engagement/admin/Dashboard.php';
require_once __DIR__ . '/../nuclear-engagement/inc/Core/SettingsRepository.php';

class DashboardTest extends TestCase {
protected function setUp(): void {
SettingsRepository::reset_for_tests();
}

private function makeDashboard( $svc ): Dashboard {
$repo = SettingsRepository::get_instance();
return new Dashboard( $repo, $svc );
}

public function test_get_status_stats_returns_counts(): void {
$svc = new class {
public function get_dual_counts( $g, $pt, $st ) { return [ [ 'g' => 'draft', 'quiz_with' => 1, 'quiz_without' => 0, 'summary_with' => 2, 'summary_without' => 0 ] ]; }
};
$dash = $this->makeDashboard( $svc );
$ref = new \ReflectionMethod( Dashboard::class, 'get_status_stats' );
$ref->setAccessible( true );
list( $quiz, $sum ) = $ref->invoke( $dash, [ 'post' ], [ 'draft' ] );
$this->assertSame( 1, $quiz['Draft']['with'] );
$this->assertSame( 2, $sum['Draft']['with'] );
}

public function test_get_author_stats_maps_names(): void {
$svc = new class {
public function get_dual_counts( $g, $pt, $st ) { return [ [ 'g' => 1, 'quiz_with' => 1, 'quiz_without' => 0, 'summary_with' => 1, 'summary_without' => 0 ] ]; }
};
$dash = $this->makeDashboard( $svc );
$ref = new \ReflectionMethod( Dashboard::class, 'get_author_stats' );
$ref->setAccessible( true );
list( $quiz, $sum ) = $ref->invoke( $dash, [ 'post' ], [ 'draft' ] );
$this->assertSame( 1, $quiz['Admin']['with'] );
$this->assertSame( 1, $sum['Admin']['with'] );
}
}
}
