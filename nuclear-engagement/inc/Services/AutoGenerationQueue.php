<?php
declare(strict_types=1);
/**
 * File: includes/Services/AutoGenerationQueue.php
 *
 * Handles queue management for auto generation.
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoGenerationQueue {
	/** Option name storing the queued post IDs. */
	private const QUEUE_OPTION = 'nuclen_autogen_queue';

	/** Maximum number of posts to send in one batch. */
	private const BATCH_SIZE = 5;


	private RemoteApiService $remote_api;
	private ContentStorageService $content_storage;
	private PostDataFetcher $fetcher;

	public function __construct( RemoteApiService $remote_api, ContentStorageService $content_storage, ?PostDataFetcher $fetcher = null ) {
		$this->remote_api      = $remote_api;
		$this->content_storage = $content_storage;
		$this->fetcher         = $fetcher ?: new PostDataFetcher();
	}

	/**
	 * Add a post to the pending generation queue and schedule processing.
	 */
	public function queue_post( int $post_id, string $workflow_type ): void {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! isset( $queue[ $workflow_type ] ) ) {
			$queue[ $workflow_type ] = array();
		}
		if ( ! in_array( $post_id, $queue[ $workflow_type ], true ) ) {
			$queue[ $workflow_type ][] = $post_id;
			update_option( self::QUEUE_OPTION, $queue, 'no' );
		}
		if ( ! wp_next_scheduled( AutoGenerationService::QUEUE_HOOK ) ) {
			$scheduled = wp_schedule_single_event( time(), AutoGenerationService::QUEUE_HOOK, array() );
			if ( false === $scheduled ) {
				\NuclearEngagement\Services\LoggingService::log( 'Failed to schedule event ' . AutoGenerationService::QUEUE_HOOK );
				\NuclearEngagement\Services\LoggingService::notify_admin(
					sprintf( __( 'Failed to schedule event %s', 'nuclear-engagement' ), AutoGenerationService::QUEUE_HOOK )
				);
			}
		}
	}

/**
 * Process queued posts in batches.
 */
	public function process_queue(): void {
	$queue = $this->get_queued_ids();
	if ( empty( $queue ) ) {
	return;
	}
	
	$generations = get_option( 'nuclen_active_generations', array() );
	
	foreach ( $queue as $workflow_type => $ids ) {
	while ( ! empty( $ids ) ) {
	$batch     = array_splice( $ids, 0, self::BATCH_SIZE );
	$post_data = $this->prepare_post_data( $batch );
	
	if ( empty( $post_data ) ) {
	continue;
	}
	
	$generation_id = $this->dispatch_generation( $post_data, $workflow_type );
	if ( ! $generation_id ) {
	continue;
	}
	
	$this->schedule_follow_up_events( $generation_id, $workflow_type, $batch, $generations );
	}
	$queue[ $workflow_type ] = $ids;
	}
	
	update_option( 'nuclen_active_generations', $generations, 'no' );
	$this->maybe_reschedule_queue( $queue );
	}

	/** Fetch the queued post IDs grouped by workflow type. */
	private function get_queued_ids(): array {
		$queue   = get_option( self::QUEUE_OPTION, array() );
		$result  = array();
		foreach ( $queue as $type => $ids ) {
		$result[ $type ] = array_map( 'absint', (array) $ids );
		}
		return $result;
	}
	
	/** Prepare post payload data for the API. */
	private function prepare_post_data( array $ids ): array {
		$post_data = array();
		$posts     = $this->fetcher->fetch( $ids );
		foreach ( $posts as $post ) {
		$pid         = (int) $post->ID;
		$post_data[] = array(
		'id'      => $pid,
		'title'   => $post->post_title,
		'content' => wp_strip_all_tags( $post->post_content ),
		);
		}
	return $post_data;
	}
	
	/**
	 * Send the posts to the remote API to start a generation.
	 *
	 * @return string Generation ID on success, empty string on failure.
	 */
	private function dispatch_generation( array $post_data, string $workflow_type ): string {
		$workflow      = array(
		'type'                    => $workflow_type,
		'summary_format'          => 'paragraph',
		'summary_length'          => NUCLEN_SUMMARY_LENGTH_DEFAULT,
		'summary_number_of_items' => NUCLEN_SUMMARY_ITEMS_DEFAULT,
		);
		$generation_id = 'gen_' . uniqid( 'auto_', true );
		try {
		$this->remote_api->send_posts_to_generate(
		array(
		'posts'         => $post_data,
		'workflow'      => $workflow,
		'generation_id' => $generation_id,
		)
		);
		} catch ( \Throwable $e ) {
		\NuclearEngagement\Services\LoggingService::log( 'Failed to start generation: ' . $e->getMessage() );
		\NuclearEngagement\Services\LoggingService::notify_admin( 'Auto-generation failed: ' . $e->getMessage() );
		return '';
		}
		return $generation_id;
	}
	
	/** Store generation info and schedule the polling event. */
	private function schedule_follow_up_events( string $generation_id, string $workflow_type, array $batch, array &$generations ): void {
		$next_poll                     = time() + NUCLEN_INITIAL_POLL_DELAY + mt_rand( 1, 5 );
		$generations[ $generation_id ] = array(
		'started_at'    => current_time( 'mysql' ),
		'post_ids'      => $batch,
		'next_poll'     => $next_poll,
		'attempt'       => 1,
		'workflow_type' => $workflow_type,
		);
		$scheduled = wp_schedule_single_event( $next_poll, 'nuclen_poll_generation', array( $generation_id, $workflow_type, $batch, 1 ) );
		if ( false === $scheduled ) {
		\NuclearEngagement\Services\LoggingService::log( 'Failed to schedule event nuclen_poll_generation for generation ' . $generation_id );
		\NuclearEngagement\Services\LoggingService::notify_admin(
		sprintf( __( 'Failed to schedule event nuclen_poll_generation for generation %s', 'nuclear-engagement' ), $generation_id )
		);
		}
	}
	
	/** Update the queue option and schedule processing if needed. */
	private function maybe_reschedule_queue( array $queue ): void {
		foreach ( $queue as $type => $ids ) {
		if ( empty( $ids ) ) {
		unset( $queue[ $type ] );
		}
		}
		if ( empty( $queue ) ) {
		delete_option( self::QUEUE_OPTION );
		return;
		}
		update_option( self::QUEUE_OPTION, $queue, 'no' );
		$scheduled = wp_schedule_single_event( time() + 1, AutoGenerationService::QUEUE_HOOK, array() );
		if ( false === $scheduled ) {
		\NuclearEngagement\Services\LoggingService::log( 'Failed to schedule event ' . AutoGenerationService::QUEUE_HOOK );
		\NuclearEngagement\Services\LoggingService::notify_admin(
		sprintf( __( 'Failed to schedule event %s', 'nuclear-engagement' ), AutoGenerationService::QUEUE_HOOK )
		);
		}
	}
	}
