<?php
/**
 * Tests for BatchProcessingHandler::handle_poll - bounded polling loop, terminal
 * handling, soft-fail, exponential backoff, and cleanup.
 *
 * Covers the production-incident fix: server contract now returns a `status`
 * field (queued/running/completed/completed_with_failures/failed/cancelled/
 * not_found) and a `completed` boolean that is true iff terminal. `not_found`
 * is NOT terminal but bumps a streak counter that caps at 20; overall polls
 * cap at 240.
 *
 * We inject a BPH_TestHooks collaborator via BatchProcessingHandler::$test_hooks
 * so the poll loop uses the stub for scheduling, hook-clearing, transient I/O,
 * action dispatch, and spawn_cron. This avoids conflicts with other test files
 * that declare namespaced WordPress function shims in NuclearEngagement\Services.
 *
 * @package NuclearEngagement\Tests
 */

// TaskTransientManager checks wp_using_ext_object_cache() to decide whether
// to use direct-DB fallback; force it to return false from the Services
// namespace so it stays on the transient API path.
namespace NuclearEngagement\Services {
	if ( ! function_exists( __NAMESPACE__ . '\\wp_using_ext_object_cache' ) ) {
		function wp_using_ext_object_cache() {
			return false;
		}
	}
}

namespace {

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\BatchProcessingHandler;
use NuclearEngagement\Services\TaskTransientManager;

// ---------------------------------------------------------------------------
// Test double collaborators.
//
// BatchProcessingHandler's constructor is strictly typed against the real
// classes (RemoteApiService, ContentStorageService,
// BulkGenerationBatchProcessor) so we subclass each with a trivial no-arg
// constructor and override only the methods handle_poll exercises.
// ---------------------------------------------------------------------------

class BPH_StubRemoteApi extends \NuclearEngagement\Services\RemoteApiService {
	/** @var array<int, mixed> Queue of fetch_updates responses to return in order. */
	public array $responses = array();
	/** @var int */
	public int $calls = 0;
	/** @var \Throwable|null Throw this on next call instead of returning. */
	public ?\Throwable $throw_next = null;

	public function __construct() {
		// Skip parent ctor - we don't need any of its wiring.
	}

	public function fetch_updates( string $generation_id ): array {
		$this->calls++;
		if ( $this->throw_next !== null ) {
			$e                = $this->throw_next;
			$this->throw_next = null;
			throw $e;
		}
		if ( empty( $this->responses ) ) {
			return array();
		}
		$next = array_shift( $this->responses );
		return is_array( $next ) ? $next : array();
	}
}

class BPH_StubStorage extends \NuclearEngagement\Services\ContentStorageService {
	public array $stored = array();

	public function __construct() {}

	public function storeResults( array $results, string $workflow_type ): array {
		$this->stored[] = array( 'results' => $results, 'workflow' => $workflow_type );
		return array_fill_keys( array_keys( $results ), true );
	}
}

class BPH_StubBatchProcessor extends \NuclearEngagement\Services\BulkGenerationBatchProcessor {
	public array $status_updates = array();

	public function __construct() {
		// Skip parent ctor entirely.
	}

	public function update_batch_status( string $batch_id, string $status, array $results = array() ): bool {
		$this->status_updates[] = array(
			'batch_id' => $batch_id,
			'status'   => $status,
			'results'  => $results,
		);
		return true;
	}
}

/**
 * Test double wired into BatchProcessingHandler::$test_hooks so calls to
 * scheduling / transient / action helpers go here instead of WordPress
 * globals (which are polluted by other test files in this suite).
 */
class BPH_TestHooks {
	/** @var array<int, array{timestamp:int, hook:string, args:array}> */
	public array $scheduled = array();
	public array $cleared   = array();
	public array $transients = array();
	public array $actions    = array();

	public function schedule_single_event( int $timestamp, string $hook, array $args ): void {
		$this->scheduled[] = array(
			'timestamp' => $timestamp,
			'hook'      => $hook,
			'args'      => $args,
		);
	}

	public function clear_scheduled_hook( string $hook, array $args ): void {
		$this->cleared[] = $hook;
	}

	public function get_transient( string $key ) {
		return $this->transients[ $key ] ?? false;
	}

	public function set_transient( string $key, $value ): void {
		$this->transients[ $key ] = $value;
	}

	public function delete_transient( string $key ): void {
		unset( $this->transients[ $key ] );
	}

	public function do_action( string $hook, ...$args ): void {
		$this->actions[ $hook ] = ( $this->actions[ $hook ] ?? 0 ) + 1;
	}

	public function spawn_cron(): void {
		// no-op
	}
}

class BatchProcessingHandlerTest extends TestCase {

	/** @var BPH_StubRemoteApi */
	private BPH_StubRemoteApi $api;
	/** @var BPH_StubStorage */
	private BPH_StubStorage $storage;
	/** @var BPH_StubBatchProcessor */
	private BPH_StubBatchProcessor $batchProcessor;
	/** @var BPH_TestHooks */
	private BPH_TestHooks $hooks;

	protected function setUp(): void {
		// Reset the global transient store that TaskTransientManager touches.
		global $wp_transients;
		$wp_transients = array();

		$this->api            = new BPH_StubRemoteApi();
		$this->storage        = new BPH_StubStorage();
		$this->batchProcessor = new BPH_StubBatchProcessor();
		$this->hooks          = new BPH_TestHooks();

		BatchProcessingHandler::$test_hooks = $this->hooks;
	}

	protected function tearDown(): void {
		BatchProcessingHandler::$test_hooks = null;
		parent::tearDown();
	}

	private function pollEvents(): array {
		return array_values(
			array_filter(
				$this->hooks->scheduled,
				fn( $e ) => $e['hook'] === 'nuclen_poll_batch'
			)
		);
	}

	private function makeHandler(): BatchProcessingHandler {
		return new BatchProcessingHandler( $this->api, $this->storage, $this->batchProcessor );
	}

	private function baseBatchData( string $batch_id = 'b1', array $overrides = array() ): array {
		return array_merge(
			array(
				'status'            => 'processing',
				'api_generation_id' => 'gen-' . $batch_id,
				'posts'             => array(
					array( 'post_id' => 1 ),
					array( 'post_id' => 2 ),
				),
				'workflow'          => array( 'type' => 'quiz' ),
				'parent_id'         => 'parent-1',
			),
			$overrides
		);
	}

	private function loadBatch( string $batch_id ): array {
		$data = TaskTransientManager::get_batch_transient( $batch_id );
		$this->assertIsArray( $data, "Batch transient for {$batch_id} should exist." );
		return $data;
	}

	// -----------------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------------

	public function test_completed_true_runs_success_path(): void {
		$batch_id = 'b-complete-ok';

		// Seed results transient via the injected hooks.
		$this->hooks->transients['nuclen_batch_results_' . $batch_id] = array(
			1 => array( 'foo' => 'bar' ),
			2 => array( 'foo' => 'baz' ),
		);

		$this->api->responses[] = array(
			'success'      => true,
			'status'       => 'completed',
			'completed'    => true,
			'completed_at' => '2026-04-21T00:00:00Z',
			'processed'    => 2,
			'total'        => 2,
		);

		$this->makeHandler()->handle_poll( $batch_id, $this->baseBatchData( $batch_id ) );

		$this->assertCount( 1, $this->storage->stored );
		$this->assertSame( 'quiz', $this->storage->stored[0]['workflow'] );

		$this->assertNotEmpty( $this->batchProcessor->status_updates );
		$last = end( $this->batchProcessor->status_updates );
		$this->assertSame( 'completed', $last['status'] );

		// Results transient cleared by cleanup_terminal.
		$this->assertArrayNotHasKey( 'nuclen_batch_results_' . $batch_id, $this->hooks->transients );

		$this->assertContains( 'nuclen_poll_batch', $this->hooks->cleared );
		$this->assertSame( 1, $this->hooks->actions['nuclen_task_completed'] ?? 0 );
		$this->assertEmpty( $this->pollEvents() );
	}

	public function test_completed_true_but_empty_results_fails_batch(): void {
		$batch_id = 'b-complete-empty';

		// No results transient set.
		$this->api->responses[] = array(
			'success'   => true,
			'status'    => 'completed',
			'completed' => true,
			'processed' => 2,
			'total'     => 2,
		);

		$this->makeHandler()->handle_poll( $batch_id, $this->baseBatchData( $batch_id ) );

		$this->assertNotEmpty( $this->batchProcessor->status_updates );
		$last = end( $this->batchProcessor->status_updates );
		$this->assertSame( 'failed', $last['status'] );
		$this->assertSame( 2, $last['results']['fail_count'] );
		$this->assertSame( 0, $last['results']['success_count'] );
		$this->assertStringContainsString( 'no results', strtolower( $last['results']['error'] ) );

		$this->assertContains( 'nuclen_poll_batch', $this->hooks->cleared );
		$this->assertSame( 1, $this->hooks->actions['nuclen_task_completed'] ?? 0 );
	}

	public function test_cancelled_status_halts_without_processing(): void {
		$batch_id = 'b-cancel';

		// Pre-seed the results transient. handle_cancelled must NOT process them.
		$this->hooks->transients['nuclen_batch_results_' . $batch_id] = array(
			1 => array( 'foo' => 'bar' ),
		);

		$this->api->responses[] = array(
			'success'      => true,
			'status'       => 'cancelled',
			'completed'    => true,
			'completed_at' => '2026-04-21T00:00:00Z',
			'processed'    => 1,
			'total'        => 2,
		);

		$this->makeHandler()->handle_poll( $batch_id, $this->baseBatchData( $batch_id ) );

		// storeResults NOT called.
		$this->assertCount( 0, $this->storage->stored );

		$this->assertNotEmpty( $this->batchProcessor->status_updates );
		$last = end( $this->batchProcessor->status_updates );
		$this->assertSame( 'cancelled', $last['status'] );

		$this->assertArrayNotHasKey( 'nuclen_batch_results_' . $batch_id, $this->hooks->transients );
		$this->assertContains( 'nuclen_poll_batch', $this->hooks->cleared );
		$this->assertSame( 1, $this->hooks->actions['nuclen_task_completed'] ?? 0 );
		$this->assertEmpty( $this->pollEvents() );
	}

	public function test_not_found_streak_increments_and_fails_at_20(): void {
		$batch_id = 'b-nf-cap';

		$batch_data                     = $this->baseBatchData( $batch_id );
		$batch_data['poll_attempts']    = 5;
		$batch_data['not_found_streak'] = 19;

		$this->api->responses[] = array(
			'success'   => true,
			'status'    => 'not_found',
			'completed' => false,
			'processed' => 0,
			'total'     => 0,
		);

		$this->makeHandler()->handle_poll( $batch_id, $batch_data );

		$stored = $this->loadBatch( $batch_id );
		$this->assertSame( 20, $stored['not_found_streak'] );

		$this->assertNotEmpty( $this->batchProcessor->status_updates );
		$last = end( $this->batchProcessor->status_updates );
		$this->assertSame( 'failed', $last['status'] );
		$this->assertStringContainsString( '20', $last['results']['error'] );
		$this->assertStringContainsString( 'not_found', $last['results']['error'] );

		$this->assertContains( 'nuclen_poll_batch', $this->hooks->cleared );
		$this->assertSame( 1, $this->hooks->actions['nuclen_task_completed'] ?? 0 );
		$this->assertEmpty( $this->pollEvents() );
	}

	public function test_not_found_streak_resets_on_other_status(): void {
		$batch_id = 'b-nf-reset';

		$batch_data                     = $this->baseBatchData( $batch_id );
		$batch_data['poll_attempts']    = 5;
		$batch_data['not_found_streak'] = 12;

		$this->api->responses[] = array(
			'success'   => true,
			'status'    => 'running',
			'completed' => false,
			'processed' => 1,
			'total'     => 2,
		);

		$this->makeHandler()->handle_poll( $batch_id, $batch_data );

		$stored = $this->loadBatch( $batch_id );
		$this->assertSame( 0, $stored['not_found_streak'], 'not_found_streak should reset on non-not_found status.' );
		$this->assertSame( 6, $stored['poll_attempts'] );

		$this->assertEmpty(
			array_filter(
				$this->batchProcessor->status_updates,
				fn( $u ) => in_array( $u['status'], array( 'failed', 'completed', 'cancelled' ), true )
			)
		);

		$this->assertCount( 1, $this->pollEvents() );
	}

	public function test_poll_attempts_hard_cap_at_240(): void {
		$batch_id = 'b-attempts-cap';

		$batch_data                     = $this->baseBatchData( $batch_id );
		$batch_data['poll_attempts']    = 239;
		$batch_data['not_found_streak'] = 0;

		$this->api->responses[] = array(
			'success'   => true,
			'status'    => 'running',
			'completed' => false,
			'processed' => 1,
			'total'     => 2,
		);

		$this->makeHandler()->handle_poll( $batch_id, $batch_data );

		$stored = $this->loadBatch( $batch_id );
		$this->assertSame( 240, $stored['poll_attempts'] );

		$this->assertNotEmpty( $this->batchProcessor->status_updates );
		$last = end( $this->batchProcessor->status_updates );
		$this->assertSame( 'failed', $last['status'] );
		$this->assertStringContainsString( '240', $last['results']['error'] );

		$this->assertContains( 'nuclen_poll_batch', $this->hooks->cleared );
		$this->assertSame( 1, $this->hooks->actions['nuclen_task_completed'] ?? 0 );
		$this->assertEmpty( $this->pollEvents() );
	}

	public function test_exponential_backoff_thresholds(): void {
		// poll_attempts is incremented BEFORE branching, so the post-increment
		// value determines the bucket:
		//   1-10 -> 30s, 11-30 -> 60s, 31+ -> 120s
		$cases = array(
			array( 'initial' => 0, 'expected' => 30 ),
			array( 'initial' => 9, 'expected' => 30 ),
			array( 'initial' => 10, 'expected' => 60 ),
			array( 'initial' => 29, 'expected' => 60 ),
			array( 'initial' => 30, 'expected' => 120 ),
			array( 'initial' => 100, 'expected' => 120 ),
		);

		foreach ( $cases as $i => $case ) {
			$this->hooks->scheduled = array();

			$batch_id                       = 'b-backoff-' . $i;
			$batch_data                     = $this->baseBatchData( $batch_id );
			$batch_data['poll_attempts']    = $case['initial'];
			$batch_data['not_found_streak'] = 0;

			$this->api->responses[] = array(
				'success'   => true,
				'status'    => 'running',
				'completed' => false,
				'processed' => 1,
				'total'     => 2,
			);

			$before = time();
			$this->makeHandler()->handle_poll( $batch_id, $batch_data );
			$after = time();

			$poll_events = $this->pollEvents();
			$this->assertCount( 1, $poll_events, "Case {$i}: expected exactly one scheduled poll." );

			$ts    = (int) $poll_events[0]['timestamp'];
			$delay = $ts - $before;
			$this->assertGreaterThanOrEqual(
				$case['expected'] - 1,
				$delay,
				sprintf( 'Case %d (initial=%d): expected ~%ds delay, got %d', $i, $case['initial'], $case['expected'], $delay )
			);
			$this->assertLessThanOrEqual(
				$case['expected'] + ( $after - $before ) + 1,
				$delay,
				sprintf( 'Case %d (initial=%d): expected ~%ds delay, got %d', $i, $case['initial'], $case['expected'], $delay )
			);
		}
	}

	public function test_soft_fail_updates_dont_increment_not_found_streak(): void {
		// Case 1: fetch_updates returns empty array.
		$batch_id                       = 'b-soft-fail';
		$batch_data                     = $this->baseBatchData( $batch_id );
		$batch_data['poll_attempts']    = 3;
		$batch_data['not_found_streak'] = 4;

		$this->api->responses[] = array();

		$this->makeHandler()->handle_poll( $batch_id, $batch_data );

		$stored = $this->loadBatch( $batch_id );
		$this->assertSame( 4, $stored['not_found_streak'], 'soft-fail must NOT touch not_found_streak.' );
		$this->assertSame( 4, $stored['poll_attempts'], 'poll_attempts still bumps on soft-fail.' );
		$this->assertSame( 'soft_fail', $stored['last_status'] );

		$this->assertCount( 1, $this->pollEvents() );

		// Case 2: fetch_updates throws.
		$this->hooks->scheduled = array();

		$batch_id2               = 'b-soft-fail-throw';
		$bd2                     = $this->baseBatchData( $batch_id2 );
		$bd2['poll_attempts']    = 1;
		$bd2['not_found_streak'] = 7;

		$this->api->throw_next = new \RuntimeException( 'transient network' );
		$this->makeHandler()->handle_poll( $batch_id2, $bd2 );

		$stored2 = $this->loadBatch( $batch_id2 );
		$this->assertSame( 7, $stored2['not_found_streak'] );
		$this->assertSame( 2, $stored2['poll_attempts'] );
		$this->assertSame( 'soft_fail', $stored2['last_status'] );

		$this->assertCount( 1, $this->pollEvents() );
	}
}

} // end namespace {}
