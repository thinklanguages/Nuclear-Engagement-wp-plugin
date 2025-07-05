<?php

use NuclearEngagement\Services\GenerationService;
use NuclearEngagement\Services\RemoteApiService;
use NuclearEngagement\Services\ContentStorageService;
use NuclearEngagement\Services\AutoGenerationService;
use NuclearEngagement\Services\PostDataFetcher;
use NuclearEngagement\Core\JobQueue;
use NuclearEngagement\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

class ContentGenerationWorkflowTest extends TestCase {

	private $generation_service;
	private $api_service;
	private $storage_service;
	private $auto_generation_service;
	private $post_fetcher;
	private $job_queue;
	private $event_dispatcher;

	public function setUp(): void {
		\WP_Mock::setUp();
		
		// Mock services
		$this->generation_service = \Mockery::mock( GenerationService::class );
		$this->api_service = \Mockery::mock( RemoteApiService::class );
		$this->storage_service = \Mockery::mock( ContentStorageService::class );
		$this->auto_generation_service = \Mockery::mock( AutoGenerationService::class );
		$this->post_fetcher = \Mockery::mock( PostDataFetcher::class );
		$this->job_queue = \Mockery::mock( JobQueue::class );
		$this->event_dispatcher = \Mockery::mock( EventDispatcher::class );
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
		\Mockery::close();
	}

	public function test_manual_quiz_generation_workflow() {
		$post_id = 123;
		$post_data = array(
			'id' => $post_id,
			'title' => 'Test Post',
			'content' => 'This is test content for quiz generation.',
			'excerpt' => 'Test excerpt',
		);

		$generation_request = array(
			'post_id' => $post_id,
			'type' => 'quiz',
			'settings' => array(
				'question_count' => 5,
				'difficulty' => 'medium',
			),
		);

		$api_response = array(
			'success' => true,
			'data' => array(
				'questions' => array(
					array(
						'question' => 'What is the main topic?',
						'answers' => array( 'A', 'B', 'C', 'D' ),
						'correct' => 0,
					),
				),
			),
		);

		$stored_content = array(
			'id' => 'quiz_' . $post_id,
			'type' => 'quiz',
			'data' => $api_response['data'],
			'created_at' => time(),
		);

		// 1. Fetch post data
		$this->post_fetcher->shouldReceive( 'get_post_data' )
			->with( $post_id )
			->once()
			->andReturn( $post_data );

		// 2. Dispatch generation started event
		$this->event_dispatcher->shouldReceive( 'dispatch' )
			->with( \Mockery::on( function( $event ) {
				return $event->get_name() === 'generation.started';
			} ) )
			->once();

		// 3. Send request to API
		$this->api_service->shouldReceive( 'generate_content' )
			->with( $post_data, $generation_request )
			->once()
			->andReturn( $api_response );

		// 4. Store generated content
		$this->storage_service->shouldReceive( 'store' )
			->with( $post_id, 'quiz', $api_response['data'] )
			->once()
			->andReturn( $stored_content );

		// 5. Dispatch generation completed event
		$this->event_dispatcher->shouldReceive( 'dispatch' )
			->with( \Mockery::on( function( $event ) {
				return $event->get_name() === 'generation.completed';
			} ) )
			->once();

		// Execute workflow
		$post_data_result = $this->post_fetcher->get_post_data( $post_id );
		
		$start_event = new \NuclearEngagement\Events\Event( 'generation.started', array(
			'post_id' => $post_id,
			'type' => 'quiz',
		) );
		$this->event_dispatcher->dispatch( $start_event );
		
		$api_result = $this->api_service->generate_content( $post_data_result, $generation_request );
		$storage_result = $this->storage_service->store( $post_id, 'quiz', $api_result['data'] );
		
		$complete_event = new \NuclearEngagement\Events\Event( 'generation.completed', array(
			'post_id' => $post_id,
			'content_id' => $storage_result['id'],
		) );
		$this->event_dispatcher->dispatch( $complete_event );

		// Verify results
		$this->assertEquals( $post_data, $post_data_result );
		$this->assertTrue( $api_result['success'] );
		$this->assertEquals( 'quiz_' . $post_id, $storage_result['id'] );
	}

	public function test_auto_generation_workflow() {
		$post_ids = array( 123, 124, 125 );
		$job_id = 'auto_gen_' . time();

		// Mock job queue operations
		$this->job_queue->shouldReceive( 'add_job' )
			->with( 'auto_generation', \Mockery::type( 'array' ) )
			->once()
			->andReturn( $job_id );

		$this->job_queue->shouldReceive( 'get_job' )
			->with( $job_id )
			->once()
			->andReturn( array(
				'id' => $job_id,
				'type' => 'auto_generation',
				'status' => 'pending',
				'data' => array( 'post_ids' => $post_ids ),
			) );

		// Mock auto generation service
		$this->auto_generation_service->shouldReceive( 'get_eligible_posts' )
			->once()
			->andReturn( $post_ids );

		$this->auto_generation_service->shouldReceive( 'process_posts' )
			->with( $post_ids )
			->once()
			->andReturn( array(
				'processed' => 3,
				'successful' => 2,
				'failed' => 1,
			) );

		// Mock events
		$this->event_dispatcher->shouldReceive( 'dispatch' )
			->times( 4 ); // Started, progress updates, completed

		// Execute workflow
		$eligible_posts = $this->auto_generation_service->get_eligible_posts();
		$job_id_result = $this->job_queue->add_job( 'auto_generation', array(
			'post_ids' => $eligible_posts,
		) );

		$start_event = new \NuclearEngagement\Events\Event( 'auto_generation.started', array(
			'job_id' => $job_id_result,
			'post_count' => count( $eligible_posts ),
		) );
		$this->event_dispatcher->dispatch( $start_event );

		$processing_result = $this->auto_generation_service->process_posts( $eligible_posts );
		
		$complete_event = new \NuclearEngagement\Events\Event( 'auto_generation.completed', array(
			'job_id' => $job_id_result,
			'results' => $processing_result,
		) );
		$this->event_dispatcher->dispatch( $complete_event );

		// Verify results
		$this->assertEquals( $post_ids, $eligible_posts );
		$this->assertEquals( $job_id, $job_id_result );
		$this->assertEquals( 3, $processing_result['processed'] );
		$this->assertEquals( 2, $processing_result['successful'] );
	}

	public function test_api_failure_retry_workflow() {
		$post_id = 123;
		$post_data = array( 'id' => $post_id, 'title' => 'Test' );
		$generation_request = array( 'type' => 'quiz' );

		// First attempt fails
		$this->api_service->shouldReceive( 'generate_content' )
			->with( $post_data, $generation_request )
			->once()
			->andThrow( new \Exception( 'API timeout' ) );

		// Dispatch retry event
		$this->event_dispatcher->shouldReceive( 'dispatch' )
			->with( \Mockery::on( function( $event ) {
				return $event->get_name() === 'generation.retry';
			} ) )
			->once();

		// Second attempt succeeds
		$this->api_service->shouldReceive( 'generate_content' )
			->with( $post_data, $generation_request )
			->once()
			->andReturn( array(
				'success' => true,
				'data' => array( 'questions' => array() ),
			) );

		// Storage after successful retry
		$this->storage_service->shouldReceive( 'store' )
			->once()
			->andReturn( array( 'id' => 'retry_success' ) );

		// Execute workflow with retry
		try {
			$this->api_service->generate_content( $post_data, $generation_request );
			$this->fail( 'Expected exception was not thrown' );
		} catch ( \Exception $e ) {
			$retry_event = new \NuclearEngagement\Events\Event( 'generation.retry', array(
				'post_id' => $post_id,
				'attempt' => 2,
				'error' => $e->getMessage(),
			) );
			$this->event_dispatcher->dispatch( $retry_event );

			// Retry
			$retry_result = $this->api_service->generate_content( $post_data, $generation_request );
			$storage_result = $this->storage_service->store( $post_id, 'quiz', $retry_result['data'] );

			$this->assertTrue( $retry_result['success'] );
			$this->assertEquals( 'retry_success', $storage_result['id'] );
		}
	}

	public function test_content_update_workflow() {
		$post_id = 123;
		$existing_content_id = 'quiz_123_old';
		$new_content_id = 'quiz_123_new';

		// Check for existing content
		$this->storage_service->shouldReceive( 'get_by_post_id' )
			->with( $post_id, 'quiz' )
			->once()
			->andReturn( array(
				'id' => $existing_content_id,
				'type' => 'quiz',
				'data' => array( 'old' => 'content' ),
			) );

		// Generate new content
		$new_content = array(
			'questions' => array(
				array( 'question' => 'Updated question?' ),
			),
		);

		$this->api_service->shouldReceive( 'generate_content' )
			->once()
			->andReturn( array(
				'success' => true,
				'data' => $new_content,
			) );

		// Archive old content
		$this->storage_service->shouldReceive( 'archive' )
			->with( $existing_content_id )
			->once()
			->andReturn( true );

		// Store new content
		$this->storage_service->shouldReceive( 'store' )
			->with( $post_id, 'quiz', $new_content )
			->once()
			->andReturn( array(
				'id' => $new_content_id,
				'type' => 'quiz',
				'data' => $new_content,
			) );

		// Dispatch update event
		$this->event_dispatcher->shouldReceive( 'dispatch' )
			->with( \Mockery::on( function( $event ) use ( $existing_content_id, $new_content_id ) {
				return $event->get_name() === 'content.updated' &&
					   $event->get( 'old_content_id' ) === $existing_content_id &&
					   $event->get( 'new_content_id' ) === $new_content_id;
			} ) )
			->once();

		// Execute update workflow
		$existing = $this->storage_service->get_by_post_id( $post_id, 'quiz' );
		
		$api_result = $this->api_service->generate_content( array(), array() );
		$archived = $this->storage_service->archive( $existing['id'] );
		$new_stored = $this->storage_service->store( $post_id, 'quiz', $api_result['data'] );
		
		$update_event = new \NuclearEngagement\Events\Event( 'content.updated', array(
			'post_id' => $post_id,
			'old_content_id' => $existing['id'],
			'new_content_id' => $new_stored['id'],
		) );
		$this->event_dispatcher->dispatch( $update_event );

		// Verify results
		$this->assertEquals( $existing_content_id, $existing['id'] );
		$this->assertTrue( $archived );
		$this->assertEquals( $new_content_id, $new_stored['id'] );
	}

	public function test_batch_generation_workflow() {
		$post_ids = array( 101, 102, 103 );
		$batch_id = 'batch_' . time();

		// Create batch job
		$this->job_queue->shouldReceive( 'add_batch_job' )
			->with( 'batch_generation', \Mockery::type( 'array' ) )
			->once()
			->andReturn( $batch_id );

		// Process each post in batch
		foreach ( $post_ids as $index => $post_id ) {
			$this->post_fetcher->shouldReceive( 'get_post_data' )
				->with( $post_id )
				->once()
				->andReturn( array( 'id' => $post_id, 'title' => "Post {$post_id}" ) );

			$this->api_service->shouldReceive( 'generate_content' )
				->once()
				->andReturn( array(
					'success' => true,
					'data' => array( 'generated' => "content_{$post_id}" ),
				) );

			$this->storage_service->shouldReceive( 'store' )
				->once()
				->andReturn( array( 'id' => "stored_{$post_id}" ) );

			// Progress event for each item
			$this->event_dispatcher->shouldReceive( 'dispatch' )
				->with( \Mockery::on( function( $event ) use ( $index ) {
					return $event->get_name() === 'batch.progress' &&
						   $event->get( 'completed' ) === $index + 1;
				} ) )
				->once();
		}

		// Batch completion event
		$this->event_dispatcher->shouldReceive( 'dispatch' )
			->with( \Mockery::on( function( $event ) use ( $batch_id ) {
				return $event->get_name() === 'batch.completed' &&
					   $event->get( 'batch_id' ) === $batch_id;
			} ) )
			->once();

		// Execute batch workflow
		$batch_job_id = $this->job_queue->add_batch_job( 'batch_generation', array(
			'post_ids' => $post_ids,
		) );

		$results = array();
		foreach ( $post_ids as $index => $post_id ) {
			$post_data = $this->post_fetcher->get_post_data( $post_id );
			$api_result = $this->api_service->generate_content( $post_data, array() );
			$storage_result = $this->storage_service->store( $post_id, 'quiz', $api_result['data'] );
			
			$results[] = $storage_result;
			
			$progress_event = new \NuclearEngagement\Events\Event( 'batch.progress', array(
				'batch_id' => $batch_job_id,
				'completed' => $index + 1,
				'total' => count( $post_ids ),
			) );
			$this->event_dispatcher->dispatch( $progress_event );
		}

		$complete_event = new \NuclearEngagement\Events\Event( 'batch.completed', array(
			'batch_id' => $batch_job_id,
			'results' => $results,
		) );
		$this->event_dispatcher->dispatch( $complete_event );

		// Verify results
		$this->assertEquals( $batch_id, $batch_job_id );
		$this->assertCount( 3, $results );
	}

	public function test_quota_exceeded_workflow() {
		$post_id = 123;
		$quota_error = new \Exception( 'API quota exceeded' );

		// API call fails with quota error
		$this->api_service->shouldReceive( 'generate_content' )
			->once()
			->andThrow( $quota_error );

		// Check if error is quota-related
		$this->api_service->shouldReceive( 'is_quota_error' )
			->with( $quota_error )
			->once()
			->andReturn( true );

		// Get next available time
		$this->api_service->shouldReceive( 'get_quota_reset_time' )
			->once()
			->andReturn( time() + 3600 ); // 1 hour from now

		// Schedule retry job
		$this->job_queue->shouldReceive( 'schedule_job' )
			->with( 'generation_retry', \Mockery::type( 'array' ), time() + 3600 )
			->once()
			->andReturn( 'retry_job_123' );

		// Dispatch quota exceeded event
		$this->event_dispatcher->shouldReceive( 'dispatch' )
			->with( \Mockery::on( function( $event ) {
				return $event->get_name() === 'api.quota_exceeded';
			} ) )
			->once();

		// Execute quota handling workflow
		try {
			$this->api_service->generate_content( array(), array() );
			$this->fail( 'Expected quota exception' );
		} catch ( \Exception $e ) {
			if ( $this->api_service->is_quota_error( $e ) ) {
				$reset_time = $this->api_service->get_quota_reset_time();
				$retry_job_id = $this->job_queue->schedule_job( 'generation_retry', array(
					'post_id' => $post_id,
					'original_error' => $e->getMessage(),
				), $reset_time );

				$quota_event = new \NuclearEngagement\Events\Event( 'api.quota_exceeded', array(
					'post_id' => $post_id,
					'retry_job_id' => $retry_job_id,
					'reset_time' => $reset_time,
				) );
				$this->event_dispatcher->dispatch( $quota_event );

				$this->assertEquals( 'retry_job_123', $retry_job_id );
				$this->assertGreaterThan( time(), $reset_time );
			}
		}
	}
}