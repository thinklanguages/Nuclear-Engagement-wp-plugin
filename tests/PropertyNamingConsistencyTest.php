<?php
declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Tests to catch property naming mismatches between class definitions and usage
 * 
 * This test ensures that:
 * 1. Request objects have consistent property naming
 * 2. Service classes access properties that actually exist
 * 3. No dynamic properties are created accidentally
 */
class PropertyNamingConsistencyTest extends TestCase {

	/**
	 * Test that PostsCountRequest properties are consistently named and accessible
	 */
	public function test_posts_count_request_property_consistency(): void {
		require_once dirname(__DIR__) . '/nuclear-engagement/inc/Requests/PostsCountRequest.php';
		
		$reflection = new ReflectionClass('NuclearEngagement\Requests\PostsCountRequest');
		$properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
		
		// Expected properties based on current usage
		$expectedProperties = [
			'postStatus',
			'categoryId', 
			'authorId',
			'postType',
			'workflow',
			'allowRegenerate',
			'regenerateProtected'
		];
		
		$actualProperties = [];
		foreach ($properties as $property) {
			$actualProperties[] = $property->getName();
		}
		
		// Ensure all expected properties exist
		foreach ($expectedProperties as $expected) {
			$this->assertContains(
				$expected, 
				$actualProperties,
				"PostsCountRequest is missing property: {$expected}"
			);
		}
		
		// Ensure no snake_case properties exist (common source of dynamic property errors)
		$forbiddenProperties = [
			'post_status',
			'category_id',
			'author_id', 
			'post_type',
			'allow_regenerate',
			'regenerate_protected'
		];
		
		foreach ($forbiddenProperties as $forbidden) {
			$this->assertNotContains(
				$forbidden,
				$actualProperties,
				"PostsCountRequest should not have snake_case property: {$forbidden}"
			);
		}
	}

	/**
	 * Test that GenerateRequest properties are consistently named and accessible
	 */
	public function test_generate_request_property_consistency(): void {
		require_once dirname(__DIR__) . '/nuclear-engagement/inc/Requests/GenerateRequest.php';
		
		$reflection = new ReflectionClass('NuclearEngagement\Requests\GenerateRequest');
		$properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
		
		// Expected properties based on current usage in GenerationService
		$expectedProperties = [
			'postIds',
			'workflowType',
			'summaryFormat',
			'summaryLength',
			'summaryItems',
			'generationId',
			'postStatus',
			'postType'
		];
		
		$actualProperties = [];
		foreach ($properties as $property) {
			$actualProperties[] = $property->getName();
		}
		
		// Ensure all expected properties exist
		foreach ($expectedProperties as $expected) {
			$this->assertContains(
				$expected,
				$actualProperties,
				"GenerateRequest is missing property: {$expected}"
			);
		}
		
		// Ensure no snake_case properties exist
		$forbiddenProperties = [
			'post_ids',
			'workflow_type',
			'summary_format',
			'summary_length', 
			'summary_items',
			'generation_id',
			'post_status',
			'post_type'
		];
		
		foreach ($forbiddenProperties as $forbidden) {
			$this->assertNotContains(
				$forbidden,
				$actualProperties,
				"GenerateRequest should not have snake_case property: {$forbidden}"
			);
		}
	}

	/**
	 * Test property access patterns in actual service usage
	 */
	public function test_service_property_access_patterns(): void {
		// This test reads the actual service files and checks for property access patterns
		$postsQueryServiceFile = dirname(__DIR__) . '/nuclear-engagement/inc/Services/PostsQueryService.php';
		$generationServiceFile = dirname(__DIR__) . '/nuclear-engagement/inc/Services/GenerationService.php';
		
		if (file_exists($postsQueryServiceFile)) {
			$content = file_get_contents($postsQueryServiceFile);
			
			// Check that it's using camelCase property access
			$this->assertStringContainsString('$request->postType', $content, 'PostsQueryService should use camelCase postType');
			$this->assertStringContainsString('$request->postStatus', $content, 'PostsQueryService should use camelCase postStatus');
			$this->assertStringContainsString('$request->categoryId', $content, 'PostsQueryService should use camelCase categoryId');
			$this->assertStringContainsString('$request->authorId', $content, 'PostsQueryService should use camelCase authorId');
			$this->assertStringContainsString('$request->allowRegenerate', $content, 'PostsQueryService should use camelCase allowRegenerate');
			$this->assertStringContainsString('$request->regenerateProtected', $content, 'PostsQueryService should use camelCase regenerateProtected');
			
			// Check that it's NOT using snake_case property access
			$this->assertStringNotContainsString('$request->post_type', $content, 'PostsQueryService should not use snake_case post_type');
			$this->assertStringNotContainsString('$request->post_status', $content, 'PostsQueryService should not use snake_case post_status');
		}
		
		if (file_exists($generationServiceFile)) {
			$content = file_get_contents($generationServiceFile);
			
			// Check that it's using camelCase property access
			$this->assertStringContainsString('$request->postIds', $content, 'GenerationService should use camelCase postIds');
			$this->assertStringContainsString('$request->workflowType', $content, 'GenerationService should use camelCase workflowType');
			$this->assertStringContainsString('$request->generationId', $content, 'GenerationService should use camelCase generationId');
			
			// Check that it's NOT using snake_case property access  
			$this->assertStringNotContainsString('$request->post_ids', $content, 'GenerationService should not use snake_case post_ids');
			$this->assertStringNotContainsString('$request->workflow_type', $content, 'GenerationService should not use snake_case workflow_type');
		}
	}

	/**
	 * Test that request objects can be instantiated and properties set without dynamic property warnings
	 */
	public function test_request_objects_instantiation(): void {
		require_once dirname(__DIR__) . '/nuclear-engagement/inc/Requests/PostsCountRequest.php';
		require_once dirname(__DIR__) . '/nuclear-engagement/inc/Requests/GenerateRequest.php';
		
		// Test PostsCountRequest
		$postsCountRequest = new \NuclearEngagement\Requests\PostsCountRequest();
		$postsCountRequest->postType = 'test';
		$postsCountRequest->postStatus = 'publish';
		$postsCountRequest->categoryId = 1;
		$postsCountRequest->authorId = 2;
		$postsCountRequest->allowRegenerate = true;
		$postsCountRequest->regenerateProtected = false;
		$postsCountRequest->workflow = 'quiz';
		
		$this->assertSame('test', $postsCountRequest->postType);
		$this->assertSame('publish', $postsCountRequest->postStatus);
		$this->assertSame(1, $postsCountRequest->categoryId);
		$this->assertSame(2, $postsCountRequest->authorId);
		$this->assertTrue($postsCountRequest->allowRegenerate);
		$this->assertFalse($postsCountRequest->regenerateProtected);
		$this->assertSame('quiz', $postsCountRequest->workflow);
		
		// Test GenerateRequest
		$generateRequest = new \NuclearEngagement\Requests\GenerateRequest();
		$generateRequest->postIds = [1, 2, 3];
		$generateRequest->workflowType = 'summary';
		$generateRequest->generationId = 'test123';
		$generateRequest->postType = 'page';
		$generateRequest->postStatus = 'draft';
		$generateRequest->summaryFormat = 'bullet_list';
		$generateRequest->summaryLength = 40;
		$generateRequest->summaryItems = 5;
		
		$this->assertSame([1, 2, 3], $generateRequest->postIds);
		$this->assertSame('summary', $generateRequest->workflowType);
		$this->assertSame('test123', $generateRequest->generationId);
		$this->assertSame('page', $generateRequest->postType);
		$this->assertSame('draft', $generateRequest->postStatus);
		$this->assertSame('bullet_list', $generateRequest->summaryFormat);
		$this->assertSame(40, $generateRequest->summaryLength);
		$this->assertSame(5, $generateRequest->summaryItems);
	}
}