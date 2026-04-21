<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Exceptions\ApiException;
use NuclearEngagement\Services\CircuitBreaker;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\Remote\ApiResponseHandler;
use NuclearEngagement\Services\Remote\RemoteRequest;

class RemoteApiServiceCircuitBreakerTest extends TestCase {
	protected function setUp(): void {
		SettingsRepository::reset_for_tests();
		$settings = SettingsRepository::get_instance();
		$settings->set_string( 'api_key', 'test-key' )->save();
	}

	public function test_send_posts_does_not_open_circuit_breaker_for_non_retryable_http_error(): void {
		$request = $this->getMockBuilder( RemoteRequest::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'post' ) )
			->getMock();

		$request->expects( $this->once() )
			->method( 'post' )
			->willThrowException(
				ApiException::httpError(
					'https://app.nuclearengagement.com/api/process-posts',
					403,
					array(
						'message' => 'Not enough credits. Please upgrade or purchase more credits to continue.',
					)
				)
			);

		$breaker = $this->getMockBuilder( CircuitBreaker::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_request_allowed', 'record_failure', 'record_success' ) )
			->getMock();

		$breaker->expects( $this->once() )
			->method( 'is_request_allowed' )
			->willReturn( true );
		$breaker->expects( $this->never() )->method( 'record_failure' );
		$breaker->expects( $this->never() )->method( 'record_success' );

		$service = new RemoteApiService(
			SettingsRepository::get_instance(),
			$request,
			new ApiResponseHandler(),
			$breaker
		);

		try {
			$service->send_posts_to_generate(
				array(
					'generation_id' => 'gen_manual_test',
					'posts'         => array(
						array(
							'id'      => 1,
							'title'   => 'Post title',
							'content' => 'Post content',
						),
					),
					'workflow'      => array(
						'type' => 'quiz',
					),
				)
			);
			$this->fail( 'Expected ApiException was not thrown.' );
		} catch ( ApiException $e ) {
			$this->assertStringContainsString( 'Not enough credits', $e->getMessage() );
		}
	}
}
