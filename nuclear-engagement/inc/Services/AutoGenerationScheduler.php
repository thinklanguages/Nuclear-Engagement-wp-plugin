<?php
declare(strict_types=1);
/**
 * File: includes/Services/AutoGenerationScheduler.php
 *
 * Delegates polling of auto generation results.
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoGenerationScheduler {
	private GenerationPoller $poller;

	public function __construct( GenerationPoller $poller ) {
		$this->poller = $poller;
	}

	public function register_hooks(): void {
		$this->poller->register_hooks();
	}

	/**
	 * Poll for generation updates.
	 *
	 * @param string $generation_id Generation ID
	 * @param string $workflow_type Type of workflow
	 * @param array  $post_ids      Post IDs
	 * @param int    $attempt       Current attempt number
	 */
	public function poll_generation( string $generation_id, string $workflow_type, array $post_ids, int $attempt ): void {
		$this->poller->poll_generation( $generation_id, $workflow_type, $post_ids, $attempt );
	}
}
