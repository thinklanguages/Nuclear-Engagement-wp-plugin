<?php
namespace NuclearEngagement\Services {
	function update_post_meta($postId, $key, $value) {
		if ( ! empty( $GLOBALS['test_update_meta_set'] ) ) {
			$GLOBALS['wp_meta'][$postId][$key] = $value;
		}
		return false;
	}
	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $text ) { return trim( $text ); }
	}
	if ( ! function_exists( 'wp_kses' ) ) {
		function wp_kses( $text, $allowed_html ) {
			$allowed = '<' . implode( '><', array_keys( $allowed_html ) ) . '>';
			return strip_tags( $text, $allowed );
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\ContentStorageService;
	use NuclearEngagement\Core\SettingsRepository;
	use NuclearEngagement\Modules\Summary\Summary_Service;

	class ContentStorageServiceFailureTest extends TestCase {
		protected function setUp(): void {
			global $wp_options, $wp_autoload, $wp_meta;
			$wp_options = $wp_autoload = $wp_meta = [];
			SettingsRepository::reset_for_tests();
			unset( $GLOBALS['test_update_meta_set'] );
		}

		public function test_store_results_succeeds_when_meta_matches(): void {
			$settings = SettingsRepository::get_instance();
			$service  = new ContentStorageService( $settings );
			$GLOBALS['test_update_meta_set'] = true;
			$data   = [ 'summary' => 'S', 'date' => '2024-01-01' ];
			$result = $service->storeResults( [ 1 => $data ], 'summary' );
			$this->assertTrue( $result[1] );
			$this->assertSame( $data, $GLOBALS['wp_meta'][1][ Summary_Service::META_KEY ] );
		}

		public function test_store_results_reports_error_on_failed_update(): void {
			$settings = SettingsRepository::get_instance();
			$service  = new ContentStorageService( $settings );
			$data   = [ 'summary' => 'S', 'date' => '2024-02-02' ];
			$result = $service->storeResults( [ 1 => $data ], 'summary' );
			$this->assertSame( 'Failed to update summary data for post 1', $result[1] );
		}
	}
}
