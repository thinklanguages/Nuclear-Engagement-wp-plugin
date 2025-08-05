<?php

namespace NuclearEngagement\Admin\Controllers;

use NuclearEngagement\Core\ServiceContainer;
use NuclearEngagement\Services\TaskTransientManager;
use NuclearEngagement\Services\Generation\GenerationResult;

/**
 * Controller for handling Server-Sent Events (SSE) streaming
 */
class StreamController {

	public function __construct( ServiceContainer $container ) {
		// TaskTransientManager uses static methods, no need to inject
	}

	/**
	 * Stream task progress and results via SSE
	 */
	public function stream_progress(): void {
		// Validate request
		if ( ! $this->validate_request() ) {
			wp_die( 'Invalid request', 403 );
		}

		$task_id = sanitize_text_field( $_GET['task_id'] ?? '' );
		if ( empty( $task_id ) ) {
			wp_die( 'Task ID required', 400 );
		}

		// Set up SSE headers
		$this->set_sse_headers();

		// Start streaming
		$this->stream_task_updates( $task_id );
	}

	/**
	 * Validate the streaming request
	 */
	private function validate_request(): bool {
		return check_ajax_referer( 'nuclen_stream_nonce', 'nonce', false ) &&
				current_user_can( 'edit_posts' );
	}

	/**
	 * Set Server-Sent Events headers
	 */
	private function set_sse_headers(): void {
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' ); // Disable Nginx buffering

		// Disable output buffering
		if ( ob_get_level() ) {
			ob_end_clean();
		}
	}

	/**
	 * Stream task updates to the client
	 */
	private function stream_task_updates( string $task_id ): void {
		$last_update = '';
		$retry_count = 0;
		$max_retries = 300; // 5 minutes max

		while ( $retry_count < $max_retries ) {
			$task_data = TaskTransientManager::get_task_transient( $task_id );

			if ( ! $task_data ) {
				$this->send_event( 'error', array( 'message' => 'Task not found' ) );
				break;
			}

			// Check if task data has changed
			$current_update = json_encode( $task_data );
			if ( $current_update !== $last_update ) {
				$this->send_task_event( $task_data );
				$last_update = $current_update;
			}

			// Check if task is complete
			if ( $task_data['status'] === 'completed' || $task_data['status'] === 'failed' ) {
				$this->send_event( 'complete', array( 'status' => $task_data['status'] ) );
				break;
			}

			// Flush output
			flush();

			// Wait before next check
			sleep( 1 );
			++$retry_count;
		}

		// Timeout reached
		if ( $retry_count >= $max_retries ) {
			$this->send_event( 'timeout', array( 'message' => 'Stream timeout reached' ) );
		}
	}

	/**
	 * Send task progress event
	 */
	private function send_task_event( array $task_data ): void {
		$event_data = array(
			'progress'  => $task_data['progress'] ?? 0,
			'processed' => $task_data['processed'] ?? 0,
			'total'     => $task_data['total'] ?? 0,
			'status'    => $task_data['status'] ?? 'processing',
		);

		// Include partial results if available
		if ( ! empty( $task_data['results'] ) ) {
			$event_data['results'] = $this->format_results( $task_data['results'] );
		}

		$this->send_event( 'progress', $event_data );
	}

	/**
	 * Format results for streaming
	 */
	private function format_results( array $results ): array {
		$formatted = array();

		foreach ( $results as $post_id => $result ) {
			if ( $result instanceof GenerationResult ) {
				$formatted[ $post_id ] = array(
					'success' => $result->is_success(),
					'title'   => $result->get_post_title(),
					'errors'  => $result->get_errors(),
				);
			}
		}

		return $formatted;
	}

	/**
	 * Send SSE event
	 */
	private function send_event( string $event, array $data ): void {
		echo "event: {$event}\n";
		echo 'data: ' . json_encode( $data ) . "\n\n";
		flush();
	}
}
