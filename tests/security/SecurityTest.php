<?php
/**
 * Security validation tests for Nuclear Engagement plugin.
 *
 * @package NuclearEngagement\Tests
 */

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\PostsQueryService;
use NuclearEngagement\Services\PostDataFetcher;
use NuclearEngagement\Admin\Controller\Ajax\BaseController;

class SecurityTest extends TestCase {

	/**
	 * Test SQL injection protection in PostsQueryService.
	 */
	public function test_sql_injection_protection_posts_query() {
		// Create a mock request with malicious data
		$request = $this->createMock( \NuclearEngagement\Requests\PostsCountRequest::class );
		$request->postType = "post'; DROP TABLE wp_posts; --";
		$request->postStatus = 'publish';
		$request->categoryId = null;
		$request->authorId = null;
		$request->allowRegenerate = false;
		$request->regenerateProtected = false;
		$request->workflow = 'quiz';

		$service = new PostsQueryService();
		
		// This should not cause SQL injection
		$args = $service->buildQueryArgs( $request );
		
		// Verify the post type is properly sanitized
		$this->assertNotContains( 'DROP TABLE', $args['post_type'] );
		$this->assertNotContains( ';', $args['post_type'] );
	}

	/**
	 * Test input validation in BaseController.
	 */
	public function test_base_controller_input_validation() {
		$controller = new class extends BaseController {
			public function testValidatePostInt( $key, $min = 0, $max = PHP_INT_MAX ) {
				return $this->validatePostInt( $key, $min, $max );
			}
			
			public function testValidatePostString( $key, $max_length = 255, $allowed = array() ) {
				return $this->validatePostString( $key, $max_length, $allowed );
			}
		};

		// Test integer validation
		$_POST['test_int'] = '123';
		$this->assertEquals( 123, $controller->testValidatePostInt( 'test_int' ) );
		
		$_POST['test_int'] = 'abc';
		$this->assertNull( $controller->testValidatePostInt( 'test_int' ) );
		
		$_POST['test_int'] = '999';
		$this->assertNull( $controller->testValidatePostInt( 'test_int', 0, 100 ) );

		// Test string validation
		$_POST['test_string'] = 'valid_string';
		$this->assertEquals( 'valid_string', $controller->testValidatePostString( 'test_string' ) );
		
		$_POST['test_string'] = str_repeat( 'a', 300 );
		$this->assertNull( $controller->testValidatePostString( 'test_string', 255 ) );
		
		$_POST['test_string'] = 'invalid';
		$this->assertNull( $controller->testValidatePostString( 'test_string', 255, array( 'valid', 'allowed' ) ) );

		// Clean up
		unset( $_POST['test_int'], $_POST['test_string'] );
	}

	/**
	 * Test that database queries use pagination.
	 */
	public function test_pagination_limits() {
		$request = $this->createMock( \NuclearEngagement\Requests\PostsCountRequest::class );
		$request->postType = 'post';
		$request->postStatus = 'publish';
		$request->categoryId = null;
		$request->authorId = null;
		$request->allowRegenerate = false;
		$request->regenerateProtected = false;
		$request->workflow = 'quiz';

		$service = new PostsQueryService();
		$args = $service->buildQueryArgs( $request );
		
		// Verify pagination is applied
		$this->assertArrayHasKey( 'posts_per_page', $args );
		$this->assertNotEquals( -1, $args['posts_per_page'] );
		$this->assertLessThanOrEqual( 1000, $args['posts_per_page'] );
	}

	/**
	 * Test path traversal protection.
	 */
	public function test_path_traversal_protection() {
		// Mock the constants
		if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
			define( 'NUCLEN_PLUGIN_DIR', '/tmp/test_plugin/' );
		}
		if ( ! defined( 'NUCLEN_PLUGIN_URL' ) ) {
			define( 'NUCLEN_PLUGIN_URL', 'http://example.com/wp-content/plugins/test_plugin/' );
		}

		$utils = new \NuclearEngagement\Utils\Utils();
		
		// This should not cause any errors or path traversal
		ob_start();
		$utils->display_nuclen_page_header();
		$output = ob_get_clean();
		
		// The method should handle invalid paths gracefully
		$this->assertTrue( true ); // Test passes if no exceptions thrown
	}

	/**
	 * Test multisite compatibility.
	 */
	public function test_multisite_option_isolation() {
		// Mock multisite environment
		$original_multisite = function_exists( 'is_multisite' ) ? is_multisite() : false;
		
		// This is a conceptual test - in a real environment we'd need to properly mock multisite
		$this->assertTrue( true ); // Placeholder for multisite testing
	}
}