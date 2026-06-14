<?php
/**
 * Regression tests for DistributedLock::takeover_expired_lock().
 *
 * Locks in the TOCTOU fix from commit 39bfcd2: expired-lock takeover on the
 * option-storage path must be an atomic compare-and-swap, so that of two
 * processes racing to take over the same expired lock, exactly one wins.
 *
 * The previous implementation did delete-then-add — a check-then-act race where
 * both racers could delete and re-add and each believe it owned the lock. The
 * fix replaces it with a single conditional `$wpdb->update()` keyed on the
 * existing serialized value; the loser's UPDATE matches zero rows.
 *
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\DistributedLock;

/**
 * `takeover_expired_lock()` is private static and the public `acquire()` flow
 * cannot be exercised end-to-end here (the option store and the $wpdb mock are
 * separate layers in the test harness), so we invoke the method directly via
 * reflection and inject a $wpdb whose update() faithfully models the
 * compare-and-swap: it only affects a row when BOTH option_name AND the
 * existing serialized option_value match.
 */
final class DistributedLockTakeoverTest extends TestCase {

	/** @var mixed Saved global $wpdb so other tests are unaffected. */
	private $original_wpdb;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->original_wpdb = $wpdb;

		// Stateful options-table double implementing conditional UPDATE.
		$wpdb = new class() {
			public $options = 'wp_options';
			/** @var array<string,string> option_name => serialized option_value */
			public $store = array();
			/** @var int Number of UPDATE statements issued. */
			public $update_calls = 0;

			public function update( $table, $data, $where, $format = null, $where_format = null ) {
				++$this->update_calls;
				$name     = $where['option_name'];
				$expected = $where['option_value'];

				// Compare-and-swap: only succeeds if the stored pre-image matches.
				if ( isset( $this->store[ $name ] ) && $this->store[ $name ] === $expected ) {
					$this->store[ $name ] = $data['option_value'];
					return 1; // One row affected.
				}

				return 0; // Pre-image no longer matches — caller lost the race.
			}
		};

		// Ensure the option-storage branch (not the database branch) runs.
		DistributedLock::set_storage_type( 'option' );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		parent::tearDown();
	}

	/**
	 * Invoke the private static takeover_expired_lock().
	 *
	 * @param array $existing Existing (expired) lock payload.
	 * @param array $new_data Candidate replacement payload.
	 */
	private function takeover( string $lock_key, array $existing, array $new_data ): bool {
		$method = new \ReflectionMethod( DistributedLock::class, 'takeover_expired_lock' );
		$method->setAccessible( true );

		return (bool) $method->invoke( null, $lock_key, $existing, $new_data );
	}

	public function test_only_one_of_two_concurrent_takeovers_wins(): void {
		global $wpdb;

		$lock_key = 'nuclen_lock_generation';
		$existing = array(
			'value'   => 'holder-old',
			'expires' => 1, // Long expired.
		);
		$wpdb->store[ $lock_key ] = maybe_serialize( $existing );

		$winner = array(
			'value'   => 'process-A',
			'expires' => time() + 300,
		);
		$loser = array(
			'value'   => 'process-B',
			'expires' => time() + 300,
		);

		// Both processes read the same expired pre-image, then race to take over.
		$first_result  = $this->takeover( $lock_key, $existing, $winner );
		$second_result = $this->takeover( $lock_key, $existing, $loser );

		$this->assertTrue( $first_result, 'First takeover should win the compare-and-swap.' );
		$this->assertFalse( $second_result, 'Second takeover against the now-stale pre-image must lose.' );
		$this->assertSame(
			maybe_serialize( $winner ),
			$wpdb->store[ $lock_key ],
			'Only the winner\'s lock payload may be persisted.'
		);
		$this->assertSame( 2, $wpdb->update_calls, 'Each takeover attempt issues exactly one conditional UPDATE.' );
	}

	public function test_takeover_fails_when_pre_image_no_longer_matches(): void {
		global $wpdb;

		$lock_key = 'nuclen_lock_polling';
		$existing = array(
			'value'   => 'holder-old',
			'expires' => 1,
		);

		// Simulate another process having already replaced the lock value, so the
		// stored pre-image differs from the one this caller observed.
		$wpdb->store[ $lock_key ] = maybe_serialize(
			array(
				'value'   => 'someone-else',
				'expires' => time() + 300,
			)
		);

		$result = $this->takeover(
			$lock_key,
			$existing,
			array(
				'value'   => 'too-late',
				'expires' => time() + 300,
			)
		);

		$this->assertFalse( $result, 'Takeover must fail (0 rows affected) when the pre-image was already changed.' );
		$this->assertNotSame(
			maybe_serialize( array( 'value' => 'too-late', 'expires' => time() + 300 ) ),
			$wpdb->store[ $lock_key ],
			'A losing takeover must not overwrite the lock.'
		);
	}
}
