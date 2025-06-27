<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Responses\UpdatesResponse;

require_once dirname(__DIR__) . '/nuclear-engagement/inc/Responses/UpdatesResponse.php';

class UpdatesResponseTest extends TestCase {
	public function test_to_array_contains_all_keys(): void {
		$r = new UpdatesResponse();
		$r->success = false;
		$r->processed = 3;
		$r->total = 5;
		$r->results = array( 'a' => true );
		$r->workflow = 'quiz';
		$r->remainingCredits = 7;
		$r->message = 'ok';
		$r->statusCode = 200;

		$data = $r->toArray();

		$this->assertArrayHasKey('success', $data);
		$this->assertArrayHasKey('processed', $data);
		$this->assertArrayHasKey('total', $data);
		$this->assertArrayHasKey('results', $data);
		$this->assertArrayHasKey('workflow', $data);
		$this->assertArrayHasKey('remaining_credits', $data);
		$this->assertArrayHasKey('message', $data);
		$this->assertArrayHasKey('status_code', $data);
	}

	public function test_to_array_omits_null_properties(): void {
		$r = new UpdatesResponse();
		$r->processed = 1;

		$data = $r->toArray();

		$this->assertArrayHasKey('success', $data);
		$this->assertArrayHasKey('processed', $data);
		$this->assertArrayNotHasKey('total', $data);
		$this->assertArrayNotHasKey('results', $data);
		$this->assertArrayNotHasKey('workflow', $data);
		$this->assertArrayNotHasKey('remaining_credits', $data);
		$this->assertArrayNotHasKey('message', $data);
		$this->assertArrayNotHasKey('status_code', $data);
	}
}
