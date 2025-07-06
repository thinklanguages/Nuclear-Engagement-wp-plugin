<?php

// Mock LoggingService before any other includes
namespace NuclearEngagement\Services {
	if ( ! class_exists( 'NuclearEngagement\Services\LoggingService' ) ) {
		class LoggingService {
			public static function log( $message ) {
				// Mock implementation for tests
			}
		}
	}
}

namespace {

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Requests\GenerateRequest;

if ( ! defined( 'NUCLEN_SUMMARY_LENGTH_MIN' ) ) {
	define( 'NUCLEN_SUMMARY_LENGTH_MIN', 20 );
}
if ( ! defined( 'NUCLEN_SUMMARY_LENGTH_MAX' ) ) {
	define( 'NUCLEN_SUMMARY_LENGTH_MAX', 50 );
}
if ( ! defined( 'NUCLEN_SUMMARY_LENGTH_DEFAULT' ) ) {
	define( 'NUCLEN_SUMMARY_LENGTH_DEFAULT', 30 );
}
if ( ! defined( 'NUCLEN_SUMMARY_ITEMS_MIN' ) ) {
	define( 'NUCLEN_SUMMARY_ITEMS_MIN', 3 );
}
if ( ! defined( 'NUCLEN_SUMMARY_ITEMS_MAX' ) ) {
	define( 'NUCLEN_SUMMARY_ITEMS_MAX', 7 );
}
if ( ! defined( 'NUCLEN_SUMMARY_ITEMS_DEFAULT' ) ) {
	define( 'NUCLEN_SUMMARY_ITEMS_DEFAULT', 3 );
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $t ) { return $t; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) { return $v; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap, $id = null ) { 
		return true; 
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) { return json_encode( $data ); }
}

require_once dirname( __DIR__ ) . '/nuclear-engagement/inc/Requests/GenerateRequest.php';

class GenerateRequestTest extends TestCase {
	
	public function setUp(): void {
		parent::setUp();
		// Set up posts in global array for get_post to find
		$GLOBALS['wp_posts'][3] = (object) ['ID' => 3, 'post_status' => 'publish', 'post_type' => 'post'];
		$GLOBALS['wp_posts'][4] = (object) ['ID' => 4, 'post_status' => 'publish', 'post_type' => 'post'];
	}
	
	public function tearDown(): void {
		parent::tearDown();
		// Clear global posts
		$GLOBALS['wp_posts'] = [];
	}

	public function test_valid_payload_produces_expected_values(): void {
		$post = array(
			'payload' => json_encode( array(
				'nuclen_selected_post_ids' => json_encode( array( 3, 4 ) ),
				'nuclen_selected_post_status' => 'draft',
				'nuclen_selected_post_type' => 'page',
				'nuclen_selected_generate_workflow' => 'quiz',
				'nuclen_selected_summary_format' => 'bullet_list',
				'nuclen_selected_summary_length' => 40,
				'nuclen_selected_summary_number_of_items' => 5,
				'generation_id' => 'abc123',
			) )
		);
		$req = GenerateRequest::from_post( $post );
		$this->assertSame( array( 3, 4 ), $req->postIds );
		$this->assertSame( 'draft', $req->postStatus );
		$this->assertSame( 'page', $req->postType );
		$this->assertSame( 'quiz', $req->workflowType );
		$this->assertSame( 'bullet_list', $req->summaryFormat );
		$this->assertSame( 40, $req->summaryLength );
		$this->assertSame( 5, $req->summaryItems );
		$this->assertSame( 'abc123', $req->generationId );
	}

	public function test_invalid_json_triggers_exception(): void {
		$this->expectException( \InvalidArgumentException::class );
		GenerateRequest::from_post( array( 'payload' => '{bad' ) );
	}

	public function test_invalid_post_ids_throw_exception(): void {
		$post = array(
			'payload' => json_encode( array(
				'nuclen_selected_post_ids' => json_encode( array( 0, -2 ) ),
				'nuclen_selected_generate_workflow' => 'quiz',
			) )
		);
		$this->expectException( \InvalidArgumentException::class );
		GenerateRequest::from_post( $post );
	}

	public function test_invalid_workflow_throws_exception(): void {
		$post = array(
			'payload' => json_encode( array(
				'nuclen_selected_post_ids' => json_encode( array( 1 ) ),
				'nuclen_selected_generate_workflow' => 'foo',
			) )
		);
		$this->expectException( \InvalidArgumentException::class );
		GenerateRequest::from_post( $post );
	}
}

}