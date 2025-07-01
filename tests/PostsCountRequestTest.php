<?php
declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Requests\PostsCountRequest;

// WordPress function stubs
if (!function_exists('sanitize_text_field')) { 
	function sanitize_text_field($t) { 
		return trim(strip_tags($t)); 
	} 
}
if (!function_exists('wp_unslash')) { 
	function wp_unslash($d) { 
		return is_string($d) ? stripslashes($d) : 
			(is_array($d) ? array_map('stripslashes', $d) : $d); 
	} 
}
if (!function_exists('absint')) { 
	function absint($v) { 
		return abs(intval($v)); 
	} 
}

require_once dirname(__DIR__) . '/nuclear-engagement/inc/Requests/PostsCountRequest.php';

class PostsCountRequestTest extends TestCase {
	
	public function test_fromPost_with_all_fields(): void {
		$postData = [
			'nuclen_post_status' => 'publish',
			'nuclen_category' => '5',
			'nuclen_author' => '3',
			'nuclen_post_type' => 'page',
			'nuclen_generate_workflow' => 'quiz',
			'nuclen_allow_regenerate_data' => '1',
			'nuclen_regenerate_protected_data' => '1'
		];
		
		$request = PostsCountRequest::fromPost($postData);
		
		$this->assertSame('publish', $request->postStatus);
		$this->assertSame(5, $request->categoryId);
		$this->assertSame(3, $request->authorId);
		$this->assertSame('page', $request->postType);
		$this->assertSame('quiz', $request->workflow);
		$this->assertTrue($request->allowRegenerate);
		$this->assertTrue($request->regenerateProtected);
	}
	
	public function test_fromPost_with_defaults(): void {
		$postData = [];
		
		$request = PostsCountRequest::fromPost($postData);
		
		$this->assertSame('any', $request->postStatus);
		$this->assertSame(0, $request->categoryId);
		$this->assertSame(0, $request->authorId);
		$this->assertSame('post', $request->postType); // Should default to 'post'
		$this->assertSame('', $request->workflow);
		$this->assertFalse($request->allowRegenerate);
		$this->assertFalse($request->regenerateProtected);
	}
	
	public function test_fromPost_with_empty_post_type(): void {
		$postData = [
			'nuclen_post_type' => '',
			'nuclen_post_status' => 'publish'
		];
		
		$request = PostsCountRequest::fromPost($postData);
		
		// Empty post type should default to 'post'
		$this->assertSame('post', $request->postType);
		$this->assertSame('publish', $request->postStatus);
	}
	
	public function test_fromPost_sanitizes_input(): void {
		$postData = [
			'nuclen_post_status' => '<script>alert("xss")</script>publish',
			'nuclen_post_type' => '  post  ', // Extra spaces
			'nuclen_generate_workflow' => '<b>summary</b>',
			'nuclen_category' => 'not-a-number',
			'nuclen_author' => '-5.7'
		];
		
		$request = PostsCountRequest::fromPost($postData);
		
		$this->assertSame('alert("xss")publish', $request->postStatus); // Script tags stripped
		$this->assertSame('post', $request->postType); // Trimmed
		$this->assertSame('summary', $request->workflow); // HTML tags stripped
		$this->assertSame(0, $request->categoryId); // Invalid number becomes 0
		$this->assertSame(5, $request->authorId); // Absolute value of negative
	}
	
	public function test_fromPost_handles_string_booleans(): void {
		$postData = [
			'nuclen_allow_regenerate_data' => '0',
			'nuclen_regenerate_protected_data' => '1'
		];
		
		$request = PostsCountRequest::fromPost($postData);
		
		$this->assertFalse($request->allowRegenerate);
		$this->assertTrue($request->regenerateProtected);
	}
	
	public function test_fromPost_handles_slashed_data(): void {
		$postData = [
			'nuclen_post_type' => 'custom\\\'post\\\'type',
			'nuclen_generate_workflow' => 'quiz\\\\test'
		];
		
		$request = PostsCountRequest::fromPost($postData);
		
		// wp_unslash should remove slashes
		$this->assertSame('custom\'post\'type', $request->postType);
		$this->assertSame('quiz\\test', $request->workflow);
	}
	
	public function test_fromPost_with_null_values(): void {
		$postData = [
			'nuclen_post_status' => null,
			'nuclen_category' => null,
			'nuclen_author' => null,
			'nuclen_post_type' => null,
			'nuclen_generate_workflow' => null,
			'nuclen_allow_regenerate_data' => null,
			'nuclen_regenerate_protected_data' => null
		];
		
		$request = PostsCountRequest::fromPost($postData);
		
		// All should use defaults
		$this->assertSame('any', $request->postStatus);
		$this->assertSame(0, $request->categoryId);
		$this->assertSame(0, $request->authorId);
		$this->assertSame('post', $request->postType);
		$this->assertSame('', $request->workflow);
		$this->assertFalse($request->allowRegenerate);
		$this->assertFalse($request->regenerateProtected);
	}
}