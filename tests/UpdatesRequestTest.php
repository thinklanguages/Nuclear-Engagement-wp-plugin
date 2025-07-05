<?php
namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Requests\UpdatesRequest;

	// Just ensure the function exists - it should trim in the bootstrap
	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $t ) { return is_string( $t ) ? trim( $t ) : ''; }
	}
	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $v ) { return $v; }
	}

	require_once __DIR__ . '/../nuclear-engagement/inc/Requests/UpdatesRequest.php';

	class UpdatesRequestTest extends TestCase {
		public function test_from_post_returns_generation_id(): void {
			$req = UpdatesRequest::fromPost( [ 'generation_id' => ' gid ' ] );
			$this->assertSame( 'gid', $req->generationId );
		}

		public function test_from_post_missing_generation_id_returns_empty_string(): void {
			$req = UpdatesRequest::fromPost( [] );
			$this->assertSame( '', $req->generationId );
		}
	}
}
