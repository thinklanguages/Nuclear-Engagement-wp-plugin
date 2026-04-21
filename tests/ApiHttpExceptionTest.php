<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Exceptions\ApiException;

class ApiHttpExceptionTest extends TestCase {
	public function test_http_error_prefers_remote_message_for_credit_failures(): void {
		$exception = ApiException::httpError(
			'https://app.nuclearengagement.com/api/process-posts',
			403,
			array(
				'message' => 'Not enough credits. Please upgrade or purchase more credits to continue.',
			)
		);

		$this->assertSame(
			'Not enough credits. Please upgrade or purchase more credits to continue.',
			$exception->getMessage()
		);
		$this->assertSame(
			'Not enough credits. Please top up your account or reduce the number of posts.',
			$exception->get_user_message()
		);
		$this->assertFalse( $exception->is_retryable() );
	}
}

