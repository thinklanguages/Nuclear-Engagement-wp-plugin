<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Requests\ContentRequest;

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($text) {
		return is_string($text) ? trim($text) : '';
	}
}

require_once dirname(__DIR__) . '/nuclear-engagement/inc/Requests/ContentRequest.php';

class ContentRequestTest extends TestCase {
	public function test_from_json_success(): void {
		$data = [
			'workflow' => ' quiz ',
			'results'  => [1 => ['a' => 'b']],
		];
		$req = ContentRequest::fromJson($data);
		$this->assertInstanceOf(ContentRequest::class, $req);
		$this->assertSame('quiz', $req->workflow);
		$this->assertSame($data['results'], $req->results);
	}

	public function test_from_json_missing_workflow_throws(): void {
		$this->expectException(InvalidArgumentException::class);
		ContentRequest::fromJson(['results' => []]);
	}

	public function test_from_json_missing_results_throws(): void {
		$this->expectException(InvalidArgumentException::class);
		ContentRequest::fromJson(['workflow' => 'summary']);
	}

	public function test_from_json_nonarray_results_throws(): void {
		$this->expectException(InvalidArgumentException::class);
		ContentRequest::fromJson(['workflow' => 'summary', 'results' => 'fail']);
	}
}
